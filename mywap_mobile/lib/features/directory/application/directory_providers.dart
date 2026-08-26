import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../data/directory_repository.dart';
import '../data/models/directory_user.dart';

final directoryRepositoryProvider = Provider<DirectoryRepository>(
  (ref) => DirectoryRepository(ref.watch(apiClientProvider)),
);

final directoryControllerProvider =
    NotifierProvider<DirectoryController, DirectoryState>(
  DirectoryController.new,
);

class DirectoryState {
  const DirectoryState({
    this.users = const [],
    this.industries = const [],
    this.industry = '',
    this.isLoading = true,
    this.isLoadingMore = false,
    this.error,
    this.hasMore = true,
    this.page = 1,
  });

  final List<DirectoryUser> users;
  final List<String> industries;
  final String industry;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final bool hasMore;
  final int page;

  DirectoryState copyWith({
    List<DirectoryUser>? users,
    List<String>? industries,
    String? industry,
    bool? isLoading,
    bool? isLoadingMore,
    bool? hasMore,
    int? page,
    Object? error = _unset,
  }) {
    return DirectoryState(
      users: users ?? this.users,
      industries: industries ?? this.industries,
      industry: industry ?? this.industry,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      hasMore: hasMore ?? this.hasMore,
      page: page ?? this.page,
      error: identical(error, _unset) ? this.error : error as String?,
    );
  }
}

const _unset = Object();

class DirectoryController extends Notifier<DirectoryState> {
  Timer? _debounce;
  String _search = '';
  String _industry = '';

  @override
  DirectoryState build() {
    _load(append: false);
    return const DirectoryState();
  }

  /// Debounced so the backend only receives a request once typing settles.
  void setSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      _search = value.trim();
      _reload();
    });
  }

  void setIndustry(String? value) {
    _debounce?.cancel();
    _industry = value ?? '';
    state = state.copyWith(industry: _industry);
    _reload();
  }

  void retry() {
    state = state.copyWith(isLoading: true, error: null);
    _load(append: false);
  }

  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoading || state.isLoadingMore) return;
    state = state.copyWith(isLoadingMore: true);
    await _load(append: true);
  }

  void _reload() {
    state = state.copyWith(isLoading: true, error: null);
    _load(append: false);
  }

  Future<void> _load({required bool append}) async {
    final page = append ? state.page + 1 : 1;
    try {
      final data = await ref.read(directoryRepositoryProvider).directory(
            page: page,
            search: _search,
            industry: _industry,
          );
      state = state.copyWith(
        users: append ? [...state.users, ...data.users] : data.users,
        industries: data.industries,
        page: page,
        hasMore: data.hasMore,
        isLoading: false,
        isLoadingMore: false,
        error: null,
      );
    } on ApiException catch (e) {
      state = state.copyWith(isLoading: false, isLoadingMore: false, error: e.message);
    }
  }
}
