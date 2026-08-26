/// A single chat bubble: either the user's input or the bot's reply.
class ChatMessage {
  const ChatMessage.user(this.text) : isUser = true;

  const ChatMessage.bot(this.text) : isUser = false;

  final String text;
  final bool isUser;
}
