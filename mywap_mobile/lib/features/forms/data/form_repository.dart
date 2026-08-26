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
    return form is Map<String, dynamic> ? FormModel.fromJson(form) : const FormModel();
  }

  Future<FormSubmitResult> submit(
    String token,
    Map<String, dynamic> answers,
  ) async {
    final data = await _api.post(
      ApiPaths.formSubmit(token),
      body: {'answers': answers},
    );
    return FormSubmitResult.fromJson(data is Map<String, dynamic> ? data : const {});
  }
}
