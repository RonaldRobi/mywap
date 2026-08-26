import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/network/api_exception.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/facilities/application/facility_providers.dart';
import 'package:mywap_mobile/features/facilities/data/facility_repository.dart';
import 'package:mywap_mobile/features/facilities/data/models/facility.dart';
import 'package:mywap_mobile/features/facilities/presentation/facilities_screen.dart';
import 'package:mywap_mobile/features/facilities/presentation/facility_detail_screen.dart';

class _FakeTokenStorage extends TokenStorage {
  _FakeTokenStorage() : super();

  @override
  Future<String?> read() async => null;
}

class _MockAdapter implements HttpClientAdapter {
  _MockAdapter(this.onRequest);

  final ResponseBody Function(RequestOptions options) onRequest;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    return onRequest(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody _json(int status, Map<String, dynamic> body) {
  return ResponseBody.fromString(
    jsonEncode(body),
    status,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );
}

ApiClient _api(ResponseBody Function(RequestOptions options) handler) {
  final dio = Dio(BaseOptions(baseUrl: 'http://test/api/v1'));
  dio.httpClientAdapter = _MockAdapter(handler);
  return ApiClient(_FakeTokenStorage(), dio: dio);
}

class _FakeAuthController extends AuthController {
  _FakeAuthController(this._initial);

  final AuthState _initial;

  @override
  AuthState build() => _initial;
}

const _user = User(
  name: 'Ali',
  email: 'ali@test.com',
  phone: '0123456789',
);

class _FakeFacilityRepository implements FacilityRepository {
  _FakeFacilityRepository({
    this.listData,
    this.detailData,
    this.error,
  });

  final FacilityListData? listData;
  final FacilityDetailData? detailData;
  final ApiException? error;

  int bookCalls = 0;
  DateTime? lastStart;
  DateTime? lastEnd;

  @override
  Future<FacilityListData> list() async {
    final error = this.error;
    if (error != null) throw error;
    return listData ?? const FacilityListData();
  }

  @override
  Future<FacilityDetailData> detail(int id) async {
    final error = this.error;
    if (error != null) throw error;
    return detailData ?? const FacilityDetailData();
  }

  @override
  Future<BookingResult> book(
    int id, {
    required DateTime start,
    required DateTime end,
    String? contactName,
    String? contactPhone,
  }) async {
    final error = this.error;
    if (error != null) throw error;
    bookCalls++;
    lastStart = start;
    lastEnd = end;
    return const BookingResult();
  }
}

const _facility = Facility(
  id: 1,
  organizationName: 'Masjid Al-Hidayah',
  name: 'Dewan Serbaguna',
  location: 'Batu Caves, Selangor',
  type: 'hourly',
  pricePerUnit: 50,
  capacity: 100,
  description: 'Dewan berhawa dingin untuk pelbagai acara.',
);

const _listData = FacilityListData(
  facilities: [_facility],
  myBookings: [
    FacilityBooking(
      id: 1,
      facilityId: 1,
      facilityName: 'Dewan Serbaguna',
      organizationName: 'Masjid Al-Hidayah',
      startDatetime: '2026-09-01 09:00:00',
      endDatetime: '2026-09-01 12:00:00',
      totalPrice: 150,
      bookingStatus: 'pending',
    ),
  ],
  isMember: true,
);

const _detailData = FacilityDetailData(
  facility: _facility,
  myBookings: [
    FacilityBooking(
      id: 1,
      facilityId: 1,
      startDatetime: '2026-09-01 09:00:00',
      endDatetime: '2026-09-01 12:00:00',
      totalPrice: 150,
      bookingStatus: 'pending',
    ),
  ],
  isMember: true,
);

Widget _wrap(Widget child, {required FacilityRepository repo}) {
  return ProviderScope(
    overrides: [
      authControllerProvider.overrideWith(
        () => _FakeAuthController(const AuthAuthenticated(_user)),
      ),
      facilityRepositoryProvider.overrideWithValue(repo),
    ],
    child: MaterialApp(home: child),
  );
}

void main() {
  Future<void> setViewSize(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
  }

  group('FacilityRepository', () {
    test('list returns facilities, bookings and member flag', () async {
      final repo = FacilityRepository(_api((options) {
        expect(options.path, '/facilities');
        return _json(200, {
          'data': {
            'facilities': [
              {
                'id': 1,
                'name': 'Dewan Serbaguna',
                'organization_name': 'Masjid Al-Hidayah',
                'location': 'Batu Caves',
                'price_per_unit': 50.0,
                'capacity': 100,
                'media': [],
              },
            ],
            'myBookings': [
              {
                'id': 1,
                'facility_id': 1,
                'facility_name': 'Dewan Serbaguna',
                'start_datetime': '2026-09-01 09:00:00',
                'end_datetime': '2026-09-01 12:00:00',
                'total_price': 150.0,
                'booking_status': 'pending',
              },
            ],
            'isMember': true,
          },
        });
      }));

      final data = await repo.list();
      expect(data.facilities, hasLength(1));
      expect(data.facilities.first.name, 'Dewan Serbaguna');
      expect(data.myBookings.first.bookingStatus, 'pending');
      expect(data.myBookings.first.totalPrice, 150.0);
      expect(data.isMember, isTrue);
    });

    test('detail returns facility with bookings', () async {
      final repo = FacilityRepository(_api((options) {
        expect(options.path, '/facilities/1');
        return _json(200, {
          'data': {
            'facility': {
              'id': 1,
              'name': 'Dewan Serbaguna',
              'price_per_unit': 50.0,
              'media': [
                {'id': 1, 'path': 'facilities/dewan.jpg', 'caption': 'Dewan'},
              ],
            },
            'bookings': [
              {
                'id': 9,
                'start_datetime': '2026-09-01 09:00:00',
                'end_datetime': '2026-09-01 12:00:00',
                'booking_status': 'approved',
              },
            ],
            'myBookings': <Map<String, dynamic>>[],
            'isMember': false,
          },
        });
      }));

      final data = await repo.detail(1);
      expect(data.facility?.name, 'Dewan Serbaguna');
      expect(data.facility?.media, hasLength(1));
      expect(data.bookings, hasLength(1));
      expect(data.isMember, isFalse);
    });

    test('book posts datetime payload and parses result', () async {
      late RequestOptions captured;
      final repo = FacilityRepository(_api((options) {
        captured = options;
        return _json(201, {
          'data': {
            'booking': {
              'id': 7,
              'start_datetime': '2026-09-01 09:00:00',
              'end_datetime': '2026-09-01 12:00:00',
              'total_price': 150.0,
              'booking_status': 'pending',
              'payment_status': 'unpaid',
            },
            'total_price': 150.0,
            'booking_status': 'pending',
          },
        });
      }));

      final result = await repo.book(
        1,
        start: DateTime(2026, 9, 1, 9),
        end: DateTime(2026, 9, 1, 12),
        contactName: 'Ali',
        contactPhone: '0123456789',
      );

      expect(captured.path, '/facilities/1/book');
      final body = captured.data as Map<String, dynamic>;
      expect(body['start_datetime'], '2026-09-01 09:00:00');
      expect(body['end_datetime'], '2026-09-01 12:00:00');
      expect(body['contact_name'], 'Ali');
      expect(body['contact_phone'], '0123456789');
      expect(result.totalPrice, 150.0);
      expect(result.bookingStatus, 'pending');
      expect(result.booking?.id, 7);
    });

    test('book conflict maps 422 to ApiException with errors', () async {
      final repo = FacilityRepository(_api((options) {
        return ResponseBody.fromString(
          jsonEncode({
            'message': 'The given data was invalid.',
            'errors': {
              'start_datetime': [
                'Slot tempahan bertindih dengan tempahan sedia ada (pending/approved). Sila pilih masa lain.',
              ],
            },
          }),
          422,
          headers: {
            Headers.contentTypeHeader: [Headers.jsonContentType],
          },
        );
      }));

      await expectLater(
        repo.book(1, start: DateTime(2026, 9, 1, 9), end: DateTime(2026, 9, 1, 10)),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'statusCode', 422)
              .having(
                (e) => e.errors,
                'errors',
                contains('start_datetime'),
              ),
        ),
      );
    });
  });

  group('FacilitiesScreen', () {
    testWidgets('renders facility list and my bookings', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(const FacilitiesScreen(), repo: _FakeFacilityRepository(listData: _listData)),
      );
      await tester.pumpAndSettle();

      expect(find.text('Dewan Serbaguna'), findsNWidgets(2));
      expect(find.text('Masjid Al-Hidayah'), findsNWidgets(2));
      expect(find.text('Tempahan Saya'), findsOneWidget);
      expect(find.text('Menunggu'), findsOneWidget);
      expect(find.textContaining('RM50'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('shows empty state when no facilities', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(
          const FacilitiesScreen(),
          repo: _FakeFacilityRepository(listData: const FacilityListData()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tiada kemudahan buat masa ini.'), findsOneWidget);
      expect(find.text('Tiada tempahan buat masa ini.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('shows error with retry', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(
          const FacilitiesScreen(),
          repo: _FakeFacilityRepository(
            error: const ApiException('Ralat memuatkan kemudahan.'),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ralat memuatkan kemudahan.'), findsOneWidget);
      expect(find.text('Cuba Semula'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('FacilityDetailScreen', () {
    testWidgets('renders facility info and booking button', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(
          const FacilityDetailScreen(facilityId: 1),
          repo: _FakeFacilityRepository(detailData: _detailData),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Dewan Serbaguna'), findsOneWidget);
      expect(find.text('Penerangan'), findsOneWidget);
      expect(find.text('Tempah'), findsOneWidget);

      await tester.tap(find.text('Tempah'));
      await tester.pumpAndSettle();

      expect(find.text('Hantar Tempahan'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('renders error state', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(
          const FacilityDetailScreen(facilityId: 1),
          repo: _FakeFacilityRepository(
            error: const ApiException('Tidak dijumpai.', statusCode: 404),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tidak dijumpai.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
