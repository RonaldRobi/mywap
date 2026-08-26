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

  @override
  AdminMembersState build() {
    Future.microtask(_load);
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

  void loadMore() => _load();

  Future<void> _load({bool refresh = false}) async {
    if (state.loading) return;
    final page = refresh ? 1 : state.page + 1;
    state = state.copyWith(loading: true, clearError: true);
    try {
      final result = await ref.read(adminRepositoryProvider).members(
            search: _search,
            status: _status,
            page: page,
          );
      final items = page == 1 ? result.items : [...state.items, ...result.items];
      state = AdminMembersState(
        items: items,
        loading: false,
        page: result.currentPage,
        hasMore: result.currentPage < result.lastPage,
        total: result.total,
      );
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      state = state.copyWith(loading: false, error: 'Ralat tidak dijangka.');
    }
  }
}

final adminMembersControllerProvider =
    NotifierProvider<AdminMembersController, AdminMembersState>(
  AdminMembersController.new,
);
