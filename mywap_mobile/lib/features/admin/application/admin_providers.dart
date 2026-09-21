import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../events/data/models/event.dart';
import '../data/admin_repository.dart';
import '../data/models/admin_models.dart';

final adminRepositoryProvider = Provider<AdminRepository>(
  (ref) => AdminRepository(ref.watch(apiClientProvider)),
);

final adminDashboardProvider = FutureProvider<AdminDashboard>((ref) {
  return ref.watch(adminRepositoryProvider).dashboard();
});

final adminUpcomingEventsProvider = FutureProvider<List<Event>>((ref) {
  return ref.watch(adminRepositoryProvider).upcomingEvents();
});

/// Fees list keyed by status filter ('' = semua, 'paid', 'pending').
final adminFeesProvider = FutureProvider.family<FeesData, String>((ref, status) {
  return ref.watch(adminRepositoryProvider).fees(status: status);
});

final adminAttendanceRegistrationsProvider =
    FutureProvider.family<AttendanceData, int>((ref, eventId) {
  return ref.watch(adminRepositoryProvider).attendanceRegistrations(eventId);
});

/// Pagination + search + filter state for the members screen.
class AdminMembersState {
  const AdminMembersState({
    this.items = const [],
    this.loading = false,
    this.error,
    this.page = 0,
    this.hasMore = false,
    this.total = 0,
  });

  final List<AdminMember> items;
  final bool loading;
  final String? error;
  final int page;
  final bool hasMore;
  final int total;

  AdminMembersState copyWith({
    List<AdminMember>? items,
    bool? loading,
    String? error,
    bool clearError = false,
    int? page,
    bool? hasMore,
    int? total,
  }) {
    return AdminMembersState(
      items: items ?? this.items,
      loading: loading ?? this.loading,
      error: clearError ? null : error ?? this.error,
      page: page ?? this.page,
      hasMore: hasMore ?? this.hasMore,
      total: total ?? this.total,
    );
  }
}

class AdminMembersController extends Notifier<AdminMembersState> {
  String _search = '';
  String _status = '';
  int _requestId = 0;

  @override
  AdminMembersState build() {
    Future.microtask(() => _load(refresh: true));
    return const AdminMembersState();
  }

  void search(String value) {
    _search = value.trim();
    _load(refresh: true);
  }

  void setStatus(String status) {
    _status = status;
    _load(refresh: true);
  }

  void retry() => _load(refresh: true);

  /// Nyahaktifkan / aktifkan semula ahli, kemudian muat semula senarai.
  Future<void> toggleActive(int userId) async {
    await ref.read(adminRepositoryProvider).toggleMemberActive(userId);
    await _load(refresh: true);
  }

  /// Padam ahli (soft delete di backend), kemudian muat semula senarai.
  Future<void> deleteMember(int userId) async {
    await ref.read(adminRepositoryProvider).deleteMember(userId);
    await _load(refresh: true);
  }

  /// Muat halaman seterusnya. Diabaikan jika tiada lagi atau sedang memuat.
  void loadMore() {
    if (state.loading || !state.hasMore) return;
    _load();
  }

  Future<void> _load({bool refresh = false}) async {
    // Setiap permintaan mendapat id unik supaya carian/filter baharu tidak
    // digugurkan (dulu `if (state.loading) return` menyebabkan carian
    // kadang-kadang tidak dijalankan) dan respons lama tidak menimpa yang baharu.
    final requestId = ++_requestId;
    final page = refresh ? 1 : state.page + 1;
    state = state.copyWith(loading: true, clearError: true);
    try {
      final result = await ref.read(adminRepositoryProvider).members(
            search: _search,
            status: _status,
            page: page,
          );
      if (requestId != _requestId) return;
      final items = page == 1 ? result.items : [...state.items, ...result.items];
      state = AdminMembersState(
        items: items,
        loading: false,
        page: result.currentPage,
        hasMore: result.currentPage < result.lastPage,
        total: result.total,
      );
    } on ApiException catch (e) {
      if (requestId != _requestId) return;
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      if (requestId != _requestId) return;
      state = state.copyWith(loading: false, error: 'Ralat tidak dijangka.');
    }
  }
}

final adminMembersControllerProvider =
    NotifierProvider<AdminMembersController, AdminMembersState>(
  AdminMembersController.new,
);
