import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../core/network/api_interceptor.dart';
import '../../../core/storage/token_storage.dart';

/// Downloads a manual PDF once and caches it on-device under
/// `<documents>/manuals/<id>.pdf` so re-opening is instant and works offline.
///
/// The API returns an absolute public URL (`file_url`), so no Authorization
/// header is strictly required, but the token is sent when available.
class ManualFileService {
  ManualFileService({TokenStorage? tokenStorage, Directory? baseDirectory})
    : _tokenStorage = tokenStorage ?? TokenStorage(),
      _baseDirectory = baseDirectory;

  final TokenStorage _tokenStorage;

  /// Test seam — when null the real app documents dir is used.
  final Directory? _baseDirectory;

  Future<File> fileFor(
    int id,
    String? url, {
    void Function(double progress)? onProgress,
  }) async {
    if (url == null || url.isEmpty) {
      throw ApiException(currentAppLocalizations.noFileToOpen, statusCode: 404);
    }

    final root = _baseDirectory ?? await getApplicationDocumentsDirectory();
    final cacheDir = Directory('${root.path}/manuals');
    if (!await cacheDir.exists()) {
      await cacheDir.create(recursive: true);
    }

    final target = File('${cacheDir.path}/$id.pdf');
    if (await target.exists() && await target.length() > 0) {
      return target;
    }

    await _download(url, target, onProgress);

    if (!await target.exists() || await target.length() == 0) {
      throw const ApiException(
        'Fail tidak sah. Sila cuba lagi.',
        statusCode: 0,
      );
    }
    return target;
  }

  Future<void> _download(
    String url,
    File target,
    void Function(double progress)? onProgress,
  ) async {
    final tmp = File('${target.path}.part');
    try {
      final token = await _tokenStorage.read();
      final headers = <String, dynamic>{
        if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
      };

      await Dio().download(
        url,
        tmp.path,
        onReceiveProgress: (received, total) {
          if (onProgress != null && total > 0) {
            onProgress(received / total);
          }
        },
        options: Options(
          headers: headers,
          receiveTimeout: const Duration(seconds: 60),
        ),
      );

      if (await tmp.exists() && await tmp.length() > 0) {
        await tmp.rename(target.path);
      }
    } on DioException catch (e) {
      throw mapApiError(e);
    } finally {
      if (await tmp.exists()) {
        await tmp.delete();
      }
    }
  }
}
