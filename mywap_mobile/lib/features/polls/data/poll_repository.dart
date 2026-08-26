import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import 'models/poll.dart';

class PollRepository {
  PollRepository(this._api);

  final ApiClient _api;

  Future<PollListData> list() async {
    final data = await _api.get(ApiPaths.polls);
    return PollListData.fromJson(_asMap(data));
  }

  Future<Poll> detail(int id) async {
    final data = await _api.get(ApiPaths.pollDetail(id));
    final poll = _asMap(data)['poll'];
    return poll is Map<String, dynamic> ? Poll.fromJson(poll) : const Poll();
  }

  Future<int> respond(int id, Map<int, List<int>> answers) async {
    final data = await _api.post(
      ApiPaths.pollRespond(id),
      body: {
        'answers': [
          for (final entry in answers.entries)
            {'question_id': entry.key, 'option_ids': entry.value},
        ],
      },
    );
    return (_asMap(data)['response_id'] as num?)?.toInt() ?? 0;
  }

  Future<PollResults> results(int id) async {
    final data = await _api.get(ApiPaths.pollResults(id));
    return PollResults.fromJson(_asMap(data));
  }

  Map<String, dynamic> _asMap(dynamic data) =>
      data is Map<String, dynamic> ? data : const {};
}
