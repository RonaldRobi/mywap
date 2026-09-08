import 'dart:io';

import 'package:dio/dio.dart';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/form_model.dart';

class FormSubmitResult {
  const FormSubmitResult({this.success = false, this.responseId});

  final bool success;
  final int? responseId;

  factory FormSubmitResult.fromJson(Map<String, dynamic> json) =>
      FormSubmitResult(
        success: json['success'] as bool? ?? false,
        responseId: (json['response_id'] as num?)?.toInt(),
      );
}

class FormRepository {
  FormRepository(this._api);

  final ApiClient _api;

  Future<FormModel> detail(String token) async {
    final data = await _api.get(ApiPaths.formDetail(token));
    final form = data is Map<String, dynamic> ? data['form'] : null;
    return form is Map<String, dynamic>
        ? FormModel.fromJson(form)
        : const FormModel();
  }

  Future<FormSubmitResult> submit(
    String token,
    Map<String, dynamic> answers, {
    Map<int, File> files = const {},
  }) async {
    final data =
        files.isEmpty
            ? await _api.post(
              ApiPaths.formSubmit(token),
              body: {'answers': answers},
            )
            : await _submitMultipart(token, answers, files);
    return FormSubmitResult.fromJson(
      data is Map<String, dynamic> ? data : const {},
    );
  }

  Future<dynamic> _submitMultipart(
    String token,
    Map<String, dynamic> answers,
    Map<int, File> files,
  ) async {
    final fields = <String, dynamic>{
      for (final entry in answers.entries)
        'answers[${entry.key}]': _stringify(entry.value),
      for (final entry in files.entries)
        'answers[${entry.key}]': MultipartFile.fromFileSync(
          entry.value.path,
          filename: _fileName(entry.value.path),
        ),
    };
    return _api.post(
      ApiPaths.formSubmit(token),
      body: FormData.fromMap(fields),
    );
  }

  String _stringify(dynamic value) {
    if (value is List) return value.map((e) => e.toString()).join(', ');
    return value?.toString() ?? '';
  }

  String _fileName(String path) {
    final normalized = path.replaceAll('\\', '/');
    final index = normalized.lastIndexOf('/');
    return index == -1 ? normalized : normalized.substring(index + 1);
  }
}
