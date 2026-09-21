import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/admin/data/admin_repository.dart';

class _FakeTokenStorage extends TokenStorage {
  _FakeTokenStorage() : super();

  @override
  Future<String?> read() async => null;

  @override
  Future<void> write(String token) async {}

  @override
  Future<void> delete() async {}
}

class _FakeAdapter implements HttpClientAdapter {
  _FakeAdapter(this.body);

  final String body;

  @override
  void close({bool force = false}) {}

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    return ResponseBody.fromString(
      body,
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }
}

void main() {
  test('members() parses the paginated envelope (data + meta)', () async {
    final dio = Dio(BaseOptions(baseUrl: 'https://example.test/api/v1'));
    dio.httpClientAdapter = _FakeAdapter(jsonEncode({
      'data': [
        {
          'id': 1,
          'name': 'Ahmad Firdaus',
          'member_no': 'M001',
          'status': 'active',
        },
        {
          'id': 2,
          'name': 'Siti Aminah',
          'member_no': 'M002',
          'status': 'pending',
        },
      ],
      'meta': {
        'current_page': 1,
        'last_page': 3,
        'per_page': 25,
        'total': 60,
      },
      'links': {'first': null, 'last': null, 'prev': null, 'next': null},
    }));

    final repository = AdminRepository(ApiClient(_FakeTokenStorage(), dio: dio));

    final result = await repository.members(page: 1);

    expect(result.items.length, 2);
    expect(result.items.first.name, 'Ahmad Firdaus');
    expect(result.currentPage, 1);
    expect(result.lastPage, 3);
    expect(result.total, 60);
  });
}
