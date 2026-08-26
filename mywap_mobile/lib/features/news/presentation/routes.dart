import 'package:go_router/go_router.dart';

import 'article_detail_screen.dart';
import 'articles_list_screen.dart';
import 'news_detail_screen.dart';
import 'news_list_screen.dart';
import 'videos_screen.dart';

/// Routes owned by the news feature.
final List<RouteBase> newsRoutes = [
  GoRoute(
    path: '/news',
    builder: (_, __) => const NewsListScreen(),
  ),
  GoRoute(
    path: '/news/:id',
    builder: (_, state) =>
        NewsDetailScreen(newsId: int.parse(state.pathParameters['id']!)),
  ),
  GoRoute(
    path: '/articles',
    builder: (_, __) => const ArticlesListScreen(),
  ),
  GoRoute(
    path: '/articles/:id',
    builder: (_, state) =>
        ArticleDetailScreen(articleId: int.parse(state.pathParameters['id']!)),
  ),
  GoRoute(
    path: '/videos',
    builder: (_, __) => const VideosScreen(),
  ),
];
