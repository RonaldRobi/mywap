import '../../../core/constants/api_paths.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/json_parsing.dart';

class MediaStatement {
  const MediaStatement({
    required this.id,
    this.title,
    this.featuredImage,
    this.organizationName,
    this.publishedDate,
    this.detailUrl,
    this.shareUrl,
    this.pdfUrl,
  });

  final int id;
  final String? title;
  final String? featuredImage;
  final String? organizationName;
  final String? publishedDate;
  final String? detailUrl;
  final String? shareUrl;
  final String? pdfUrl;

  factory MediaStatement.fromJson(Map<String, dynamic> json) => MediaStatement(
    id: parseIntValue(json['id']) ?? 0,
    title: json['title'] as String?,
    featuredImage: json['featured_image'] as String?,
    organizationName: json['organization_name'] as String?,
    publishedDate: json['published_date'] as String?,
    detailUrl: json['detail_url'] as String?,
    shareUrl: json['share_url'] as String?,
    pdfUrl: json['pdf_url'] as String?,
  );
}

class MediaStatementDetail {
  const MediaStatementDetail({
    required this.statement,
    this.content,
    this.attachments = const [],
  });

  final MediaStatement statement;
  final String? content;
  final List<MediaAttachment> attachments;

  factory MediaStatementDetail.fromJson(Map<String, dynamic> json) =>
      MediaStatementDetail(
        statement: MediaStatement.fromJson(json),
        content: json['content'] as String?,
        attachments: (json['attachments'] as List? ?? const [])
            .whereType<Map>()
            .map(
              (item) => MediaAttachment.fromJson(item.cast<String, dynamic>()),
            )
            .toList(growable: false),
      );
}

class MediaAttachment {
  const MediaAttachment({this.type, this.url});

  final String? type;
  final String? url;

  factory MediaAttachment.fromJson(Map<String, dynamic> json) =>
      MediaAttachment(
        type: json['type'] as String?,
        url: json['url'] as String?,
      );
}

class MediaStatementRepository {
  MediaStatementRepository(this._api);

  final ApiClient _api;

  Future<List<MediaStatement>> list({int page = 1}) async {
    final data = await _api.get(
      ApiPaths.mediaStatements,
      query: {'page': page, 'per_page': 20},
    );
    if (data is List) {
      return data
          .whereType<Map>()
          .map((item) => MediaStatement.fromJson(item.cast<String, dynamic>()))
          .toList(growable: false);
    }
    if (data is Map && data['data'] is List) {
      return (data['data'] as List)
          .whereType<Map>()
          .map((item) => MediaStatement.fromJson(item.cast<String, dynamic>()))
          .toList(growable: false);
    }
    return const [];
  }

  Future<MediaStatementDetail> detail(int id) async {
    final data = await _api.get(ApiPaths.mediaStatementDetail(id));
    final map = data is Map<String, dynamic> ? data : <String, dynamic>{};
    return MediaStatementDetail.fromJson(
      (map['data'] as Map<String, dynamic>?) ?? map,
    );
  }
}
