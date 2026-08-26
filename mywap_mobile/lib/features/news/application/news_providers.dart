import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../data/models/news.dart';
import '../data/news_repository.dart';

final newsRepositoryProvider = Provider<NewsRepository>(
  (ref) => NewsRepository(ref.watch(apiClientProvider)),
);

/// Paginated news list (Info Terkini) with lazy page loading.
final newsListProvider = AsyncNotifierProvider<NewsListNotifier, List<NewsPost>>(
  NewsListNotifier.new,
);

class NewsListNotifier extends AsyncNotifier<List<NewsPost>> {
  int _page = 1;

  static const _perPage = 25;

  @override
  Future<List<NewsPost>> build() async {
    _page = 1;
    return ref.watch(newsRepositoryProvider).newsList(page: _page);
  }

  bool get hasMore => (state.value?.length ?? 0) >= _perPage;

  Future<void> loadMore() async {
    final next = await ref
        .read(newsRepositoryProvider)
        .newsList(page: _page + 1);
    if (next.isEmpty) return;
    _page++;
    state = AsyncData([...state.value ?? const [], ...next]);
  }
}

final newsDetailProvider = FutureProvider.family<NewsDetail, int>(
  (ref, id) => ref.watch(newsRepositoryProvider).newsDetail(id),
);

final articleListProvider =
    AsyncNotifierProvider<ArticleListNotifier, List<Article>>(
  ArticleListNotifier.new,
);

class ArticleListNotifier extends AsyncNotifier<List<Article>> {
  int _page = 1;

  static const _perPage = 25;

  @override
  Future<List<Article>> build() async {
    _page = 1;
    return ref.watch(newsRepositoryProvider).articleList(page: _page);
  }

  bool get hasMore => (state.value?.length ?? 0) >= _perPage;

  Future<void> loadMore() async {
    final next = await ref
        .read(newsRepositoryProvider)
        .articleList(page: _page + 1);
    if (next.isEmpty) return;
    _page++;
    state = AsyncData([...state.value ?? const [], ...next]);
  }
}

final articleDetailProvider = FutureProvider.family<ArticleDetail, int>(
  (ref, id) => ref.watch(newsRepositoryProvider).articleDetail(id),
);

final videosProvider = FutureProvider<List<Video>>(
  (ref) => ref.watch(newsRepositoryProvider).videos(),
);
