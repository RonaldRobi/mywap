/// Reply from `POST /chat`. The backend may return a 503 with a reply inside
/// `data` when the AI is not configured — surfaced here as a normal reply.
class ChatReply {
  const ChatReply({this.reply});

  final String? reply;

  factory ChatReply.fromJson(Map<String, dynamic> json) =>
      ChatReply(reply: json['reply'] as String?);
}
