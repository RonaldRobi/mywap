import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../data/chat_repository.dart';
import '../data/models/chat_message.dart';

final chatRepositoryProvider = Provider<ChatRepository>(
  (ref) => ChatRepository(ref.watch(apiClientProvider)),
);

final chatControllerProvider =
    NotifierProvider<ChatController, ChatState>(ChatController.new);

class ChatState {
  const ChatState({
    this.messages = const [],
    this.sending = false,
    this.error,
  });

  final List<ChatMessage> messages;
  final bool sending;
  final String? error;

  ChatState copyWith({
    List<ChatMessage>? messages,
    bool? sending,
    Object? error = _unset,
  }) {
    return ChatState(
      messages: messages ?? this.messages,
      sending: sending ?? this.sending,
      error: identical(error, _unset) ? this.error : error as String?,
    );
  }
}

const _unset = Object();

class ChatController extends Notifier<ChatState> {
  bool _sending = false;

  @override
  ChatState build() => const ChatState();

  Future<void> send(String text) async {
    final message = text.trim();
    if (message.isEmpty || _sending) return;
    _sending = true;
    state = state.copyWith(
      messages: [...state.messages, ChatMessage.user(message)],
      sending: true,
      error: null,
    );
    try {
      final reply = await ref.read(chatRepositoryProvider).send(message);
      state = state.copyWith(
        messages: [...state.messages, ChatMessage.bot(reply.reply ?? 'Tiada jawapan.')],
        sending: false,
      );
    } on ApiException catch (e) {
      state = state.copyWith(sending: false, error: e.message);
    } finally {
      _sending = false;
    }
  }
}
