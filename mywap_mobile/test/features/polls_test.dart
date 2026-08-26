import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

import 'package:mywap_mobile/core/network/api_client.dart';
import 'package:mywap_mobile/core/network/api_exception.dart';
import 'package:mywap_mobile/core/storage/token_storage.dart';
import 'package:mywap_mobile/features/auth/application/auth_controller.dart';
import 'package:mywap_mobile/features/auth/data/models/user.dart';
import 'package:mywap_mobile/features/polls/application/poll_providers.dart';
import 'package:mywap_mobile/features/polls/data/models/poll.dart';
import 'package:mywap_mobile/features/polls/data/poll_repository.dart';
import 'package:mywap_mobile/features/polls/presentation/poll_detail_screen.dart';
import 'package:mywap_mobile/features/polls/presentation/poll_results_screen.dart';
import 'package:mywap_mobile/features/polls/presentation/polls_screen.dart';

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

class _FakePollRepository implements PollRepository {
  _FakePollRepository({
    this.listData,
    this.poll,
    this.resultsData,
    this.respondError,
  });

  final PollListData? listData;
  final Poll? poll;
  final PollResults? resultsData;
  final ApiException? respondError;

  int respondCalls = 0;
  Map<int, List<int>>? lastAnswers;

  @override
  Future<PollListData> list() async => listData ?? const PollListData();

  @override
  Future<Poll> detail(int id) async => poll ?? const Poll();

  @override
  Future<PollResults> results(int id) async =>
      resultsData ?? const PollResults();

  @override
  Future<int> respond(int id, Map<int, List<int>> answers) async {
    final error = respondError;
    if (error != null) throw error;
    respondCalls++;
    lastAnswers = answers;
    return 1;
  }
}

const _availablePoll = PollSummary(
  id: 1,
  title: 'Cadangan Program Raya',
  type: 'poll',
  endsAtFormatted: '1 Sep 2026, 5:00 PM',
  responseCount: 12,
);

const _answeredPoll = PollSummary(
  id: 2,
  title: 'Pilih Aktiviti Mingguan',
  type: 'poll',
  endsAtFormatted: '15 Sep 2026, 9:00 AM',
  responseCount: 30,
  hasResponded: true,
);

const _listData = PollListData(
  availablePolls: [_availablePoll],
  answeredPolls: [_answeredPoll],
);

const _poll = Poll(
  id: 1,
  title: 'Cadangan Program Raya',
  description: 'Pilih cadangan yang paling sesuai.',
  questions: [
    PollQuestion(
      id: 1,
      questionText: 'Cadangan anda?',
      type: 'single_choice',
      options: [
        PollOption(id: 1, optionText: 'Program Malam'),
        PollOption(id: 2, optionText: 'Program Pagi'),
      ],
    ),
    PollQuestion(
      id: 2,
      questionText: 'Tema yang diminati?',
      type: 'multiple_choice',
      options: [
        PollOption(id: 3, optionText: 'Keluarga'),
        PollOption(id: 4, optionText: 'Remaja'),
      ],
    ),
  ],
);

const _results = PollResults(
  poll: _poll,
  questions: [
    PollResultQuestion(
      id: 1,
      questionText: 'Cadangan anda?',
      type: 'single_choice',
      options: [
        PollResultOption(id: 1, optionText: 'Program Malam', count: 8, percentage: 66.7, widthPct: 100),
        PollResultOption(id: 2, optionText: 'Program Pagi', count: 4, percentage: 33.3, widthPct: 50),
      ],
      totalAnswers: 12,
    ),
  ],
  totalResponses: 12,
  myAnswers: [1],
);

GoRouter _router(PollRepository repo) {
  return GoRouter(
    initialLocation: '/polls/1',
    routes: [
      GoRoute(
        path: '/polls',
        builder: (_, __) => const PollsScreen(),
      ),
      GoRoute(
        path: '/polls/:id',
        builder: (_, state) =>
            PollDetailScreen(pollId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/polls/:id/results',
        builder: (_, state) =>
            PollResultsScreen(pollId: int.parse(state.pathParameters['id']!)),
      ),
    ],
  );
}

Widget _wrap(Widget child, {required PollRepository repo}) {
  return ProviderScope(
    overrides: [
      authControllerProvider.overrideWith(
        () => _FakeAuthController(const AuthAuthenticated(_user)),
      ),
      pollRepositoryProvider.overrideWithValue(repo),
    ],
    child: MaterialApp(home: child),
  );
}

Widget _wrapRouter({required PollRepository repo}) {
  return ProviderScope(
    overrides: [
      authControllerProvider.overrideWith(
        () => _FakeAuthController(const AuthAuthenticated(_user)),
      ),
      pollRepositoryProvider.overrideWithValue(repo),
    ],
    child: MaterialApp.router(routerConfig: _router(repo)),
  );
}

void main() {
  Future<void> setViewSize(WidgetTester tester) async {
    tester.view.physicalSize = const Size(800, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);
  }

  group('PollRepository', () {
    test('list returns available and answered polls', () async {
      final repo = PollRepository(_api((options) {
        expect(options.path, '/polls');
        return _json(200, {
          'data': {
            'availablePolls': [
              {
                'id': 1,
                'title': 'Cadangan Program Raya',
                'type': 'single',
                'response_count': 12,
                'has_responded': false,
                'ends_at_formatted': '1 Sep 2026, 5:00 PM',
              },
            ],
            'answeredPolls': [
              {
                'id': 2,
                'title': 'Pilih Aktiviti Mingguan',
                'type': 'multiple',
                'response_count': 30,
                'has_responded': true,
                'my_response_id': 9,
              },
            ],
          },
        });
      }));

      final data = await repo.list();
      expect(data.availablePolls, hasLength(1));
      expect(data.availablePolls.first.title, 'Cadangan Program Raya');
      expect(data.answeredPolls.first.responseCount, 30);
      expect(data.answeredPolls.first.myResponseId, 9);
    });

    test('detail returns poll with questions and options', () async {
      final repo = PollRepository(_api((options) {
        expect(options.path, '/polls/1');
        return _json(200, {
          'data': {
            'poll': {
              'id': 1,
              'title': 'Cadangan Program Raya',
              'type': 'single',
              'questions': [
                {
                  'id': 1,
                  'question_text': 'Cadangan anda?',
                  'type': 'single',
                  'options': [
                    {'id': 1, 'option_text': 'Program Malam'},
                    {'id': 2, 'option_text': 'Program Pagi'},
                  ],
                },
              ],
            },
          },
        });
      }));

      final poll = await repo.detail(1);
      expect(poll.title, 'Cadangan Program Raya');
      expect(poll.questions, hasLength(1));
      expect(poll.questions.first.options, hasLength(2));
      expect(poll.questions.first.isMultiple, isFalse);
    });

    test('respond posts answers array and returns response id', () async {
      late RequestOptions captured;
      final repo = PollRepository(_api((options) {
        captured = options;
        return _json(200, {
          'data': {'response_id': 42},
        });
      }));

      final id = await repo.respond(1, {
        1: [2],
        2: [3, 4],
      });

      expect(captured.path, '/polls/1/respond');
      final body = captured.data as Map<String, dynamic>;
      final answers = body['answers'] as List<dynamic>;
      expect(answers, hasLength(2));
      expect(answers[0], {'question_id': 1, 'option_ids': [2]});
      expect(answers[1], {'question_id': 2, 'option_ids': [3, 4]});
      expect(id, 42);
    });

    test('respond duplicate maps 409 to ApiException', () async {
      final repo = PollRepository(_api((options) {
        return _json(409, {
          'message': 'Anda sudah menjawab undian ini.',
        });
      }));

      await expectLater(
        repo.respond(1, {
          1: [1],
        }),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'statusCode', 409)
              .having((e) => e.message, 'message', 'Anda sudah menjawab undian ini.'),
        ),
      );
    });

    test('results parses question bars and totals', () async {
      final repo = PollRepository(_api((options) {
        expect(options.path, '/polls/1/results');
        return _json(200, {
          'data': {
            'poll': {'id': 1, 'title': 'Cadangan Program Raya'},
            'questions': [
              {
                'id': 1,
                'question_text': 'Cadangan anda?',
                'type': 'single',
                'total_answers': 12,
                'options': [
                  {'id': 1, 'option_text': 'Program Malam', 'count': 8, 'percentage': 66.7, 'width_pct': 100.0},
                ],
              },
            ],
            'total_responses': 12,
            'my_answers': [1],
          },
        });
      }));

      final results = await repo.results(1);
      expect(results.totalResponses, 12);
      expect(results.questions, hasLength(1));
      expect(results.questions.first.options.first.percentage, 66.7);
      expect(results.myAnswers, [1]);
    });
  });

  group('PollsScreen', () {
    testWidgets('renders available and answered sections', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(const PollsScreen(), repo: _FakePollRepository(listData: _listData)),
      );
      await tester.pumpAndSettle();

      expect(find.text('Undian Terkini'), findsOneWidget);
      expect(find.text('Sudah Dijawab'), findsOneWidget);
      expect(find.text('Cadangan Program Raya'), findsOneWidget);
      expect(find.text('Pilih Aktiviti Mingguan'), findsOneWidget);
      expect(find.textContaining('12 respons'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('shows empty state', (tester) async {
      await setViewSize(tester);
      await tester.pumpWidget(
        _wrap(
          const PollsScreen(),
          repo: _FakePollRepository(listData: const PollListData()),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tiada undian buat masa ini.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('PollDetailScreen', () {
    testWidgets('renders questions and submits to results', (tester) async {
      await setViewSize(tester);
      final repo = _FakePollRepository(
        poll: _poll,
        resultsData: _results,
      );

      await tester.pumpWidget(_wrapRouter(repo: repo));
      await tester.pumpAndSettle();

      expect(find.text('Cadangan Program Raya'), findsOneWidget);
      expect(find.text('1. Cadangan anda?'), findsOneWidget);
      expect(find.text('2. Tema yang diminati?'), findsOneWidget);

      await tester.tap(find.text('Program Malam'));
      await tester.pump();
      await tester.tap(find.text('Keluarga'));
      await tester.pump();

      await tester.ensureVisible(find.text('Hantar Jawapan'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Hantar Jawapan'));
      await tester.pumpAndSettle();

      expect(repo.respondCalls, 1);
      expect(repo.lastAnswers, {
        1: [1],
        2: [3],
      });

      expect(find.text('Keputusan Undian'), findsOneWidget);
      expect(find.text('12 respons'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('409 duplicate shows message and opens results', (tester) async {
      await setViewSize(tester);
      final repo = _FakePollRepository(
        poll: _poll,
        resultsData: _results,
        respondError: const ApiException(
          'Anda sudah menjawab undian ini.',
          statusCode: 409,
        ),
      );

      await tester.pumpWidget(_wrapRouter(repo: repo));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Program Malam'));
      await tester.pump();
      await tester.tap(find.text('Keluarga'));
      await tester.pump();

      await tester.ensureVisible(find.text('Hantar Jawapan'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Hantar Jawapan'));
      await tester.pumpAndSettle();

      expect(find.text('Keputusan Undian'), findsOneWidget);
      expect(find.text('12 respons'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
