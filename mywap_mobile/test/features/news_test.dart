import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:mywap_mobile/features/news/application/news_providers.dart';
import 'package:mywap_mobile/features/news/data/models/news.dart';
import 'package:mywap_mobile/features/news/data/news_repository.dart';
import 'package:mywap_mobile/features/news/presentation/articles_list_screen.dart';
import 'package:mywap_mobile/features/news/presentation/news_detail_screen.dart';
import 'package:mywap_mobile/features/news/presentation/news_list_screen.dart';
import 'package:mywap_mobile/features/news/presentation/videos_screen.dart';
import 'package:mywap_mobile/shared/theme/app_theme.dart';

class _FakeNewsRepository implements NewsRepository {
  _FakeNewsRepository({this.failLists = false});

  final bool failLists;
  final List<NewsPost> posts = [
    NewsPost(
      id: 1,
      title: 'Muktamar Tahunan 2026',
      excerpt: 'Ringkasan program muktamar.',
      categoryName: 'Umum',
      publishedAt: '2026-08-01 10:00:00',
      likesCount: 3,
      commentsCount: 2,
    ),
  ];
  final List<Article> articles = [
    Article(
      id: 1,
      title: 'Artikel Khas',
      excerpt: 'Petikan artikel.',
      authorName: 'Admin',
      publishedDate: '1 Ogos 2026',
    ),
  ];
  final List<Video> videoItems = [
    Video(id: 1, title: 'Video WADAH', youtubeId: 'abc123'),
  ];

  @override
  Future<List<NewsPost>> newsList({int page = 1, int? categoryId}) async {
    if (failLists) throw Exception('network');
    return posts;
  }

  @override
  Future<NewsDetail> newsDetail(int id) async => NewsDetail(
        post: NewsDetailPost(
          id: id,
          title: 'Muktamar Tahunan 2026',
          content: 'Kandungan penuh muktamar.',
          category: const NewsCategory(id: 1, name: 'Umum'),
          likesCount: 3,
        ),
        comments: const [
          Comment(id: 1, content: 'Bagus!', userName: 'Ahmad'),
        ],
      );

  @override
  Future<Map<String, dynamic>> reactNews(int id, String reaction) async => {
        'post_id': id,
        'reaction': reaction,
        'likes_count': 4,
        'dislikes_count': 0,
      };

  @override
  Future<Comment> commentNews(int id, String content) async =>
      Comment(id: 2, content: content, userName: 'Saya');

  @override
  Future<List<Article>> articleList({int page = 1}) async {
    if (failLists) throw Exception('network');
    return articles;
  }

  @override
  Future<ArticleDetail> articleDetail(int id) async => ArticleDetail(
        article: ArticleDetailPost(
          id: id,
          title: 'Artikel Khas',
          content: 'Kandungan artikel.',
          likesCount: 1,
        ),
      );

  @override
  Future<Map<String, dynamic>> reactArticle(int id, String reaction) async => {
        'article_id': id,
        'reaction': reaction,
      };

  @override
  Future<Comment> commentArticle(int id, String content) async =>
      Comment(id: 2, content: content, userName: 'Saya');

  @override
  Future<List<Video>> videos({int page = 1}) async {
    if (failLists) throw Exception('network');
    return videoItems;
  }

  @override
  Future<String> watchVideo(Video video) async => video.watchUrl;
}

Widget _wrap(Widget child, List<Override> overrides) {
  return ProviderScope(
    overrides: overrides,
    child: MaterialApp(theme: AppTheme.light, home: child),
  );
}

void main() {
  group('NewsListScreen', () {
    testWidgets('renders news posts', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const NewsListScreen(),
          [
            newsRepositoryProvider.overrideWithValue(_FakeNewsRepository()),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Info Terkini'), findsOneWidget);
      expect(find.text('Muktamar Tahunan 2026'), findsOneWidget);
      expect(find.text('Umum'), findsOneWidget);
    });

    testWidgets('shows error then retries', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const NewsListScreen(),
          [
            newsRepositoryProvider.overrideWithValue(
              _FakeNewsRepository(failLists: true),
            ),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Ralat tidak dijangka.'), findsOneWidget);
      expect(find.text('Cuba Semula'), findsOneWidget);
    });
  });

  group('NewsDetailScreen', () {
    testWidgets('renders content, reactions and comments', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const NewsDetailScreen(newsId: 1),
          [
            newsRepositoryProvider.overrideWithValue(_FakeNewsRepository()),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Muktamar Tahunan 2026'), findsOneWidget);
      expect(find.text('Kandungan penuh muktamar.'), findsOneWidget);
      expect(find.text('Bagus!'), findsOneWidget);
      expect(find.text('Komen (1)'), findsOneWidget);
    });
  });

  group('ArticlesListScreen', () {
    testWidgets('renders articles', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const ArticlesListScreen(),
          [
            newsRepositoryProvider.overrideWithValue(_FakeNewsRepository()),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Artikel Khas'), findsOneWidget);
      expect(find.text('Admin'), findsOneWidget);
    });
  });

  group('VideosScreen', () {
    testWidgets('renders video cards', (tester) async {
      await tester.pumpWidget(
        _wrap(
          const VideosScreen(),
          [
            newsRepositoryProvider.overrideWithValue(_FakeNewsRepository()),
          ],
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Video WADAH'), findsOneWidget);
      expect(find.byIcon(Icons.play_arrow), findsOneWidget);
    });
  });
}
