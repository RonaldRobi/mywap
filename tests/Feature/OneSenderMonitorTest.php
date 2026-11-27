<?php

namespace Tests\Feature;

use App\Console\Commands\MonitorOneSender;
use App\Mail\OneSenderStatusAlert;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class OneSenderMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
        config(['services.onesender.alert_email' => 'cyberokettechnology@gmail.com']);
        AppSetting::singleton()->update([
            'onesender_url' => 'https://onesender.test/api/v1/messages',
            'onesender_api_key' => 'monitor-key',
        ]);
    }

    public function test_online_device_is_checked_without_sending_email(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'data' => ['is_online' => true]])]);

        $this->artisan('onesender:monitor')->assertSuccessful();

        Mail::assertNothingSent();
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request->url() === 'https://onesender.test/api/v1/device'
            && $request->hasHeader('Authorization', 'Bearer monitor-key'));
        $this->assertSame('online', Cache::get(MonitorOneSender::STATE_KEY)['state']);
        $this->assertNotNull(Cache::get(MonitorOneSender::STATE_KEY)['checked_at']);
    }

    public function test_offline_device_sends_only_one_email_until_recovery(): void
    {
        Http::fakeSequence()
            ->push(['code' => 200, 'data' => ['is_online' => false]])
            ->push(['code' => 200, 'data' => ['is_online' => false]])
            ->push(['code' => 200, 'data' => ['is_online' => true]])
            ->push(['code' => 200, 'data' => ['is_online' => false]]);

        $this->artisan('onesender:monitor')->assertSuccessful();
        $this->travel(5)->minutes();
        $this->artisan('onesender:monitor')->assertSuccessful();

        Mail::assertSent(OneSenderStatusAlert::class, 1);
        Mail::assertSent(OneSenderStatusAlert::class, fn ($mail) => $mail->hasTo('cyberokettechnology@gmail.com')
            && $mail->status['state'] === 'offline' && ! $mail->isTest);

        $this->artisan('onesender:monitor')->assertSuccessful();
        $this->assertNull(Cache::get(MonitorOneSender::STATE_KEY)['alerted_at']);

        $this->artisan('onesender:monitor')->assertSuccessful();
        Mail::assertSent(OneSenderStatusAlert::class, 2);
    }

    public function test_rejected_key_is_reported_as_unknown_instead_of_online(): void
    {
        Http::fake(['*' => Http::response(['code' => 401], 401)]);

        $this->artisan('onesender:monitor')->assertSuccessful();

        Mail::assertSent(OneSenderStatusAlert::class, fn ($mail) => $mail->status['state'] === 'unknown');
    }

    public function test_missing_or_non_boolean_status_is_not_treated_as_online(): void
    {
        $sequence = Http::fakeSequence();
        foreach ([null, 'false', 'true', 1] as $value) {
            $sequence->push(['code' => 200, 'data' => ['is_online' => $value]]);
        }
        foreach ([null, 'false', 'true', 1] as $value) {
            Cache::forget(MonitorOneSender::STATE_KEY);
            $this->artisan('onesender:monitor')->assertSuccessful();
            $this->assertSame('unknown', Cache::get(MonitorOneSender::STATE_KEY)['state']);
        }
    }

    public function test_api_connection_failure_sends_an_alert(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->artisan('onesender:monitor')->assertSuccessful();

        Mail::assertSent(OneSenderStatusAlert::class, fn ($mail) => $mail->status['state'] === 'unknown');
    }

    public function test_failed_email_is_retried_on_next_check(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'data' => ['is_online' => false]])]);
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->artisan('onesender:monitor')->assertFailed();
        $this->assertNull(Cache::get(MonitorOneSender::STATE_KEY));

        Mail::swap($mailFake);
        $this->artisan('onesender:monitor')->assertSuccessful();
        Mail::assertSent(OneSenderStatusAlert::class, 1);
    }

    public function test_test_email_does_not_suppress_real_offline_alert(): void
    {
        Http::fake(['*' => Http::response(['code' => 200, 'data' => ['is_online' => false]])]);

        $this->artisan('onesender:monitor --test-email')->assertSuccessful();
        $this->assertNull(Cache::get(MonitorOneSender::STATE_KEY));
        Mail::assertSent(OneSenderStatusAlert::class, fn ($mail) => $mail->isTest);

        $this->artisan('onesender:monitor')->assertSuccessful();
        Mail::assertSent(OneSenderStatusAlert::class, 2);
    }

    public function test_monitor_does_not_run_twice_while_lock_is_held(): void
    {
        $lock = Cache::lock('onesender:monitor:lock', 120);
        $lock->get();
        try {
            $this->artisan('onesender:monitor')->assertSuccessful();
            Http::assertNothingSent();
            Mail::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_alert_body_contains_no_keys_or_recipient_message_history(): void
    {
        $mail = new OneSenderStatusAlert(['state' => 'offline', 'reason' => 'Peranti tidak tersambung.']);
        $rendered = $mail->render();
        $this->assertStringContainsString('Peranti tidak tersambung.', $rendered);
        $this->assertStringContainsString('(Malaysia)', $rendered);
        $this->assertStringNotContainsString('monitor-key', $rendered);
    }

    public function test_monitor_is_scheduled_every_five_minutes(): void
    {
        $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'onesender:monitor'));
        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
