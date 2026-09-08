import 'dart:io';

import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';

import '../../../core/constants/app_env.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/network/api_interceptor.dart';
import '../../../core/storage/token_storage.dart';

/// Downloads the Pustaka PDF once and caches it on-device under
/// `<documents>/pustaka/<id>.pdf`. Re-opens read straight from cache so
/// subsequent loads are instant and work offline.
class LibraryFileService {
  LibraryFileService({TokenStorage? tokenStorage, Directory? baseDirectory})
      : _tokenStorage = tokenStorage ?? TokenStorage(),
        _baseDirectory = baseDirectory;

  final TokenStorage _tokenStorage;

  /// Test seam — when null the real app documents dir is used.
  final Directory? _baseDirectory;

  /// Returns a local [File] for the item, downloading it (reporting progress
  /// via [onProgress], 0..1) only when it isn't cached yet.
  Future<File> fileFor(
    int id,
    String? filePath, {
    void Function(double progress)? onProgress,
  }) async {
    if (filePath == null || filePath.isEmpty) {
      throw const ApiException('Tiada fail untuk dibuka.', statusCode: 404);
    }

    final root = _baseDirectory ?? await getApplicationDocumentsDirectory();
    final cacheDir = Directory('${root.path}/pustaka');
    if (!await cacheDir.exists()) {
      await cacheDir.create(recursive: true);
    }

    final target = File('${cacheDir.path}/$id.pdf');
    if (await target.exists() && await target.length() > 0) {
      return target;
    }

    await _download(AppEnv.resolveUrl(filePath), target, onProgress);

    if (!await target.exists() || await target.length() == 0) {
      throw const ApiException('Fail tidak sah. Sila cuba lagi.', statusCode: 0);
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
