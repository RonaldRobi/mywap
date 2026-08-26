import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../data/models/app_notification.dart';
import '../data/notification_repository.dart';

final notificationRepositoryProvider = Provider<NotificationRepository>(
  (ref) => NotificationRepository(ref.watch(apiClientProvider)),
);

final notificationsControllerProvider =
    NotifierProvider<NotificationsController, NotificationsState>(
  NotificationsController.new,
);

class NotificationsState {
  const NotificationsState({
    this.notifications = const [],
    this.isLoading = true,
    this.isMarking = false,
    this.error,
  });

  final List<AppNotification> notifications;
  final bool isLoading;
  final bool isMarking;
  final String? error;

  NotificationsState copyWith({
    List<AppNotification>? notifications,
    bool? isLoading,
    bool? isMarking,
    Object? error = _unset,
  }) {
    return NotificationsState(
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      isMarking: isMarking ?? this.isMarking,
      error: identical(error, _unset) ? this.error : error as String?,
    );
  }
}

const _unset = Object();

class NotificationsController extends Notifier<NotificationsState> {
  @override
  NotificationsState build() {
    _load();
    return const NotificationsState();
  }

  Future<void> _load() async {
    try {
      final items = await ref.read(notificationRepositoryProvider).list();
      state = NotificationsState(
        notifications: items,
        isLoading: false,
        isMarking: state.isMarking,
        error: null,
      );
    } on ApiException catch (e) {
      state = NotificationsState(
        isLoading: false,
        isMarking: state.isMarking,
        error: e.message,
      );
    }
  }

  Future<void> retry() async {
    state = state.copyWith(isLoading: true, error: null);
    await _load();
  }

  Future<void> markAllRead() async {
    if (state.isMarking || state.notifications.isEmpty) return;
    state = state.copyWith(isMarking: true, error: null);
    try {
      await ref.read(notificationRepositoryProvider).readAll();
      state = state.copyWith(
        notifications: state.notifications
            .map((n) => n.copyWith(readAt: DateTime.now()))
            .toList(),
        isMarking: false,
      );
    } on ApiException catch (e) {
      state = state.copyWith(isMarking: false, error: e.message);
    }
  }
}
