import 'dart:convert';

import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_exception.dart';
import 'models/chat_reply.dart';

class ChatRepository {
  ChatRepository(this._api);

  final ApiClient _api;

  /// Sends a message and returns the bot reply.
  ///
  /// The backend may answer 503 with a reply inside `data` when the AI is not
  /// configured — that is surfaced as a normal reply instead of an error.
  Future<ChatReply> send(String message) async {
    try {
      final data = await _api.post(ApiPaths.chat, body: {'message': message});
      if (data is String) {
        return ChatReply.fromJson(jsonDecode(data) as Map<String, dynamic>);
      }
      return ChatReply.fromJson((data as Map<String, dynamic>?) ?? const {});
    } on ApiException catch (e) {
      if (e.statusCode == 503) {
        return const ChatReply(
          reply: 'AI chatbot belum dikonfigurasi. Sila hubungi admin.',
        );
      }
      rethrow;
    }
  }
}
