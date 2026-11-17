#!/usr/bin/env ruby
# frozen_string_literal: true

require "json"
require "net/http"
require "openssl"
require "base64"
require "uri"
require "time"

ROOT = File.expand_path("..", __dir__)
PUBSPEC = File.join(ROOT, "pubspec.yaml")
ASC_KEY_PATH = File.expand_path(ENV.fetch("ASC_KEY_JSON", File.join(ROOT, "fastlane", "asc_key.json")))
PLAY_KEY_PATH = File.expand_path(ENV.fetch("PLAY_JSON_KEY", File.join(ROOT, "fastlane", "play_service_account.json")))
BUNDLE_ID = ENV.fetch("IOS_BUNDLE_ID", "com.mywap.mywapMobile")
PACKAGE_NAME = ENV.fetch("ANDROID_PACKAGE", "com.mywap.mywap_mobile")
PLAY_TRACKS = %w[internal alpha beta production].freeze
BLOCKED_STATES = %w[
  WAITING_FOR_REVIEW IN_REVIEW PROCESSING_FOR_APP_STORE
  PENDING_DEVELOPER_RELEASE PENDING_APPLE_RELEASE
].freeze

NetworkError = Class.new(StandardError)

def note(msg)
  warn(msg)
end

def usage
  puts <<~TEXT
    Penggunaan: ruby tool/version.rb <command> [pilihan]

    Command:
      status                    Semak versi tempatan + App Store Connect + Play
      bump-build [--write]      Naikkan build number (max semua sumber + 1)
      next-release [--write]    Tentukan nombor depan yang sah untuk release
      verify --build N          Tunggu build N muncul di TestFlight/Play

    Pilihan:
      --write         Tulis ke pubspec.yaml
      --dry-run       Sama seperti tanpa --write
      --offline       Jangan hubungi API (bump-build sahaja)
      --env           Cetak KEY=VALUE untuk skrip
      --json          Cetak JSON
      --ios-only      verify: semak iOS sahaja
      --android-only  verify: semak Android sahaja
      --minutes N     verify: had masa (default 20)
  TEXT
end

def read_pubspec
  text = File.read(PUBSPEC)
  match = text.match(/^version:\s*([0-9]+(?:\.[0-9]+){0,2})(?:\+([0-9]+))?\s*$/)
  raise "Baris 'version:' tidak dijumpai dalam #{PUBSPEC}" unless match
  { marketing: match[1], build: (match[2] || "0").to_i }
end

def write_pubspec(marketing, build)
  text = File.read(PUBSPEC)
  desired = "version: #{marketing_str(marketing)}+#{build}"
  updated = text.sub(/^version:[^\n]*$/, desired)
  return if updated == text && text.include?(desired)
  raise "Gagal kemas kini #{PUBSPEC}" if updated == text
  File.write(PUBSPEC, updated)
end

def marketing_str(value)
  parts = value.to_s.split(".").map(&:to_i)
  parts = [0] if parts.empty?
  parts << 0 while parts.size < 3
  parts.join(".")
end

def vnum(value)
  parts = value.to_s.split(".").map(&:to_i)
  (parts + [0, 0, 0])[0, 3]
end

def vcmp(a, b)
  vnum(a) <=> vnum(b)
end

def bump_minor_str(value)
  parts = value.to_s.split(".").map(&:to_i)
  parts = [parts[0] || 0, parts[1] || 0] if parts.size < 2
  parts[1] += 1
  parts[2] = 0 if parts.size > 2
  parts.join(".")
end

def b64url(str)
  Base64.urlsafe_encode64(str, padding: false)
end

def http(method, url, headers = {}, body = nil, timeout = 60)
  uri = URI(url)
  klass = { get: Net::HTTP::Get, post: Net::HTTP::Post, delete: Net::HTTP::Delete }.fetch(method)
  req = klass.new(uri)
  headers.each { |key, value| req[key] = value }
  unless body.nil?
    req.body = body.is_a?(String) ? body : JSON.generate(body)
    req["Content-Type"] ||= "application/json"
  end
  res = Net::HTTP.start(uri.host, uri.port, use_ssl: uri.scheme == "https",
                        open_timeout: 15, read_timeout: timeout) do |conn|
    conn.request(req)
  end
  parsed = begin
    res.body.to_s.empty? ? nil : JSON.parse(res.body)
  rescue JSON::ParserError
    res.body.to_s
  end
  [res.code.to_i, parsed]
rescue StandardError => e
  raise NetworkError, "#{method.upcase} #{uri}: #{e.class}: #{e.message}"
end

def asc_token
  data = JSON.parse(File.read(ASC_KEY_PATH))
  key = OpenSSL::PKey.read(data.fetch("key"))
  now = Time.now.to_i
  header = b64url(JSON.generate({ alg: "ES256", kid: data.fetch("key_id"), typ: "JWT" }))
  claims = b64url(JSON.generate({
    iss: data.fetch("issuer_id"), iat: now, exp: now + 600, aud: "appstoreconnect-v1"
  }))
  der = key.sign(OpenSSL::Digest.new("SHA256"), "#{header}.#{claims}")
  raw = OpenSSL::ASN1.decode(der).value.map { |item| item.value.to_s(2).rjust(32, "\0") }.join
  "#{header}.#{claims}.#{b64url(raw)}"
end

def asc_get(url, token)
  full = url.start_with?("http") ? url : "https://api.appstoreconnect.apple.com#{url}"
  code, body = http(:get, full, { "Authorization" => "Bearer #{token}" })
  unless code == 200
    detail = body.is_a?(Hash) ? body.dig("errors", 0, "detail") : body
    raise NetworkError, "App Store Connect HTTP #{code}: #{detail}"
  end
  body
end

def asc_state
  token = asc_token
  app_body = asc_get("/v1/apps?filter[bundleId]=#{BUNDLE_ID}&fields[apps]=name,bundleId", token)
  app = (app_body["data"] || []).first
  raise NetworkError, "App #{BUNDLE_ID} tidak dijumpai di App Store Connect" unless app

  versions_body = asc_get(
    "/v1/apps/#{app['id']}/appStoreVersions?filter[platform]=IOS&limit=20" \
    "&fields[appStoreVersions]=versionString,appStoreState,createdDate,platform", token
  )
  versions = (versions_body["data"] || []).map do |item|
    attrs = item["attributes"] || {}
    { version: attrs["versionString"].to_s, state: attrs["appStoreState"].to_s,
      created: attrs["createdDate"].to_s }
  end.sort_by { |v| v[:created] }.reverse

  builds = []
  url = "/v1/builds?filter[app]=#{app['id']}&limit=200&sort=-uploadedDate" \
        "&fields[builds]=version,processingState,uploadedDate"
  loop do
    body = asc_get(url, token)
    (body["data"] || []).each do |item|
      attrs = item["attributes"] || {}
      builds << { build: attrs["version"].to_s.to_i, state: attrs["processingState"].to_s,
                  uploaded: attrs["uploadedDate"].to_s }
    end
    url = body.dig("links", "next")
    break if url.nil? || url.empty? || builds.size > 2000
  end

  { versions: versions, builds: builds, latest_build: builds.map { |b| b[:build] }.max }
end

def google_token
  data = JSON.parse(File.read(PLAY_KEY_PATH))
  key = OpenSSL::PKey::RSA.new(data.fetch("private_key"))
  now = Time.now.to_i
  header = b64url(JSON.generate({ alg: "RS256", typ: "JWT", kid: data["private_key_id"] }))
  claims = b64url(JSON.generate({
    iss: data.fetch("client_email"),
    scope: "https://www.googleapis.com/auth/androidpublisher",
    aud: data.fetch("token_uri"),
    iat: now,
    exp: now + 3600
  }))
  signature = b64url(key.sign(OpenSSL::Digest.new("SHA256"), "#{header}.#{claims}"))
  code, body = http(
    :post, data.fetch("token_uri"),
    { "Content-Type" => "application/x-www-form-urlencoded" },
    URI.encode_www_form(
      grant_type: "urn:ietf:params:oauth:grant-type:jwt-bearer",
      assertion: "#{header}.#{claims}.#{signature}"
    )
  )
  unless code == 200 && body.is_a?(Hash) && body["access_token"]
    detail = body.is_a?(Hash) ? (body["error_description"] || body["error"]) : body
    raise NetworkError, "Google OAuth gagal (HTTP #{code}): #{detail}"
  end
  body["access_token"]
end

def play_tracks(token)
  auth = { "Authorization" => "Bearer #{token}" }
  base = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/#{PACKAGE_NAME}"
  code, edit = http(:post, "#{base}/edits", auth, {})
  unless code == 200 && edit.is_a?(Hash) && edit["id"]
    detail = edit.is_a?(Hash) ? edit.dig("error", "message") : edit
    raise NetworkError, "Play edit gagal (HTTP #{code}): #{detail}"
  end
  edit_id = edit["id"]
  begin
    code, body = http(:get, "#{base}/edits/#{edit_id}/tracks", auth)
    unless code == 200
      detail = body.is_a?(Hash) ? body.dig("error", "message") : body
      raise NetworkError, "Play tracks gagal (HTTP #{code}): #{detail}"
    end
    result = {}
    (body["tracks"] || []).each do |track|
      codes = (track["releases"] || []).flat_map { |r| Array(r["versionCodes"]) }.map(&:to_i)
      result[track["track"]] = codes.max || 0
    end
    result
  ensure
    http(:delete, "#{base}/edits/#{edit_id}", auth) rescue nil
  end
end

def remote_ios
  state = asc_state
  state.merge(max_build: state[:latest_build].to_i)
end

def remote_android
  tracks = play_tracks(google_token)
  max_code = tracks.values.max.to_i
  { tracks: tracks, max_code: max_code }
end

def status_data(offline: false, ios: true, android: true)
  local = read_pubspec
  data = {
    marketing: local[:marketing],
    local_build: local[:build],
    ios: nil,
    android: nil,
    next_build: nil,
    suggested_release: nil,
    release_blocker: nil,
    errors: {}
  }
  unless offline
    if ios
      begin
        data[:ios] = remote_ios
      rescue NetworkError => e
        data[:errors][:ios] = e.message
        note("! iOS: #{e.message}")
      end
    end
    if android
      begin
        data[:android] = remote_android
      rescue NetworkError => e
        data[:errors][:android] = e.message
        note("! Android: #{e.message}")
      end
    end
  end
  candidates = [local[:build], data.dig(:ios, :max_build), data.dig(:android, :max_code)].compact
  data[:next_build] = candidates.max + 1

  if data[:ios] && !data[:ios][:versions].empty?
    latest = data[:ios][:versions].max_by { |v| vnum(v[:version]) }
    data[:ios][:latest_version] = latest
    if BLOCKED_STATES.include?(latest[:state])
      data[:release_blocker] = latest
    elsif latest[:state] == "READY_FOR_SALE"
      data[:suggested_release] = marketing_str(bump_minor_str(latest[:version]))
    else
      data[:suggested_release] = marketing_str(latest[:version])
    end
  end
  data
end

def print_env(data)
  puts "MARKETING=#{data[:marketing]}"
  puts "BUILD=#{data[:local_build]}"
  puts "NEXT_BUILD=#{data[:next_build]}"
  puts "IOS_LATEST_BUILD=#{data.dig(:ios, :max_build)}"
  puts "ANDROID_LATEST_CODE=#{data.dig(:android, :max_code)}"
  puts "IOS_STORE_VERSION=#{data.dig(:ios, :latest_version, :version)}"
  puts "IOS_STATE=#{data.dig(:ios, :latest_version, :state)}"
  puts "SUGGESTED_RELEASE=#{data[:suggested_release]}"
end

def print_status(data, offline: false)
  puts "pubspec            #{data[:marketing]}+#{data[:local_build]}"
  if data[:ios]
    builds = data[:ios][:builds].first(3).map { |b| "#{b[:build]} #{b[:state]}" }.join(", ")
    puts "iOS builds         tertinggi #{data[:ios][:max_build]} (#{builds})"
    latest = data[:ios][:latest_version]
    if latest
      puts "iOS version        #{latest[:version]} #{latest[:state]} (#{latest[:created]})"
    else
      puts "iOS version        belum ada version record"
    end
  elsif offline
    puts "iOS                dilangkau (--offline)"
  else
    puts "iOS                tidak dapat dibaca"
  end
  if data[:android]
    tracks = data[:android][:tracks].map { |k, v| "#{k}=#{v}" }.join(", ")
    puts "Android versionCode tertinggi #{data[:android][:max_code]} (#{tracks})"
  elsif offline
    puts "Android            dilangkau (--offline)"
  else
    puts "Android            tidak dapat dibaca"
  end
  puts "Build seterusnya   #{data[:next_build]}"
  if data[:release_blocker]
    puts "Release            DITANGGUH: #{data[:release_blocker][:version]} masih #{data[:release_blocker][:state]}"
  elsif data[:suggested_release]
    puts "Nombor depan       #{data[:suggested_release]} (bila submit version baru)"
  end
end

def cmd_status(options)
  data = status_data(offline: options[:offline],
                     ios: !options[:android_only],
                     android: !options[:ios_only])
  if options[:json]
    puts JSON.pretty_generate(data)
  elsif options[:env]
    print_env(data)
  else
    print_status(data, offline: options[:offline])
  end
  0
end

def cmd_bump(options)
  local = read_pubspec
  data = status_data(offline: options[:offline])

  unless options[:offline] || data[:errors].empty?
    note("✗ Tidak dapat membaca semua sumber versi:")
    data[:errors].each { |source, message| note("  #{source}: #{message}") }
    note("  Build number tidak diubah. Betulkan rangkaian/kunci, atau guna --offline jika benar-benar perlu.")
    return 2
  end

  next_build = data[:next_build]

  if next_build <= local[:build]
    next_build = local[:build] + 1
  end

  if data.dig(:ios, :max_build) && next_build <= data[:ios][:max_build]
    abort "✗ Selamat berhenti: build #{next_build} tidak lebih tinggi daripada TestFlight (#{data[:ios][:max_build]})."
  end
  if data.dig(:android, :max_code) && next_build <= data[:android][:max_code]
    abort "✗ Selamat berhenti: build #{next_build} tidak lebih tinggi daripada Play (#{data[:android][:max_code]})."
  end

  result = { marketing: local[:marketing], build: next_build, next_build: next_build }
  write_pubspec(local[:marketing], next_build) if options[:write]

  if options[:env]
    puts "MARKETING=#{result[:marketing]}"
    puts "BUILD=#{result[:build]}"
    puts "NEXT_BUILD=#{result[:next_build]}"
  elsif options[:json]
    puts JSON.generate(result)
  else
    prefix = options[:write] ? "✓ pubspec dikemas kini" : "(dry-run)"
    puts "#{prefix}: version: #{result[:marketing]}+#{result[:build]}"
  end
  0
end

def cmd_next_release(options)
  local = read_pubspec
  state = asc_state
  versions = state[:versions]

  if versions.empty?
    target = marketing_str(local[:marketing])
    note("! Tiada version record di App Store Connect; kekalkan #{target}")
  else
    latest = versions.max_by { |v| vnum(v[:version]) }
    if BLOCKED_STATES.include?(latest[:state])
      note("✗ Versi #{latest[:version]} masih #{latest[:state]}.")
      note("  Apple tak benarkan build baru dimasukkan ke version itu.")
      note("  Pilihan: tunggu keputusan review, atau tarik balik submission.")
      return 3
    end

    target = if latest[:state] == "READY_FOR_SALE"
               marketing_str(bump_minor_str(latest[:version]))
             else
               marketing_str(latest[:version])
             end
  end

  write_pubspec(target, local[:build]) if options[:write]

  if options[:env]
    puts "MARKETING=#{target}"
    puts "BUILD=#{local[:build]}"
  elsif options[:json]
    puts JSON.generate({ marketing: target, build: local[:build] })
  else
    prefix = options[:write] ? "✓ pubspec dikemas kini" : "(dry-run)"
    puts "#{prefix}: version: #{target}+#{local[:build]}"
  end
  0
end

def cmd_verify(options)
  build = options[:build]
  abort "✗ Gunakan --build N" unless build && build.positive?
  minutes = options[:minutes] || 20
  deadline = Time.now + (minutes * 60)
  want_ios = !options[:android_only]
  want_android = !options[:ios_only]
  seen_ios = false
  seen_android = false

  loop do
    if want_ios && !seen_ios
      begin
        state = asc_state
        hit = state[:builds].find { |b| b[:build] == build }
        if hit
          if %w[INVALID FAILED].include?(hit[:state])
            note("✗ Build #{build} di TestFlight berstatus #{hit[:state]}")
            return 1
          end
          seen_ios = hit[:state] == "VALID"
          note("· iOS build #{build}: #{hit[:state]}") unless seen_ios
        else
          note("· iOS build #{build} belum muncul")
        end
      rescue NetworkError => e
        note("! iOS: #{e.message}")
      end
    end

    if want_android && !seen_android
      begin
        max_code = play_tracks(google_token).values.max.to_i
        seen_android = max_code >= build
        note("· Android versionCode tertinggi: #{max_code}") unless seen_android
      rescue NetworkError => e
        note("! Android: #{e.message}")
      end
    end

    done_ios = !want_ios || seen_ios
    done_android = !want_android || seen_android
    if done_ios && done_android
      puts "✓ Build #{build} disahkan"
      return 0
    end

    if Time.now > deadline
      note("! Tamat masa #{minutes} minit; build mungkin masih diproses di sisi Apple/Google.")
      return 1
    end
    sleep 30
  end
end

command = ARGV.shift
options = { write: false, offline: false, env: false, json: false, minutes: 20 }
while (arg = ARGV.shift)
  case arg
  when "--write" then options[:write] = true
  when "--dry-run" then options[:write] = false
  when "--offline" then options[:offline] = true
  when "--env" then options[:env] = true
  when "--json" then options[:json] = true
  when "--ios-only" then options[:ios_only] = true
  when "--android-only" then options[:android_only] = true
  when "--build" then options[:build] = ARGV.shift.to_i
  when "--minutes" then options[:minutes] = ARGV.shift.to_i
  when "-h", "--help" then usage
                        exit 0
  else
    abort "✗ Argumen tidak dikenali: #{arg}\n\n#{usage}"
  end
end

unless command
  usage
  exit 1
end

code = begin
  case command
  when "status" then cmd_status(options)
  when "bump-build" then cmd_bump(options)
  when "next-release" then cmd_next_release(options)
  when "verify" then cmd_verify(options)
  else
    abort "✗ Command tidak dikenali: #{command}\n\n#{usage}"
  end
rescue NetworkError => e
  note("✗ #{e.message}")
  note("  Jika ini isu rangkaian, guna --offline untuk bump-build (tidak selamat untuk upload).")
  2
rescue StandardError => e
  note("✗ #{e.class}: #{e.message}")
  1
end
exit code
