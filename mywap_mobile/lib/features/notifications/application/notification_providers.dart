import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/providers.dart';
import '../../../core/push/app_badge_service.dart';
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
    this.unreadCount = 0,
    this.error,
  });

  final List<AppNotification> notifications;
  final bool isLoading;
  final bool isMarking;

  /// Jumlah sebenar belum dibaca (dari backend), sumber untuk badge ikon app.
  final int unreadCount;
  final String? error;

  NotificationsState copyWith({
    List<AppNotification>? notifications,
    bool? isLoading,
    bool? isMarking,
    int? unreadCount,
    Object? error = _unset,
  }) {
    return NotificationsState(
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      isMarking: isMarking ?? this.isMarking,
      unreadCount: unreadCount ?? this.unreadCount,
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
      final page = await ref.read(notificationRepositoryProvider).list();
      state = NotificationsState(
        notifications: page.notifications,
        isLoading: false,
        isMarking: state.isMarking,
        unreadCount: page.unreadCount,
        error: null,
      );
      unawaited(AppBadgeService.setUnread(page.unreadCount));
    } on ApiException catch (e) {
      state = NotificationsState(
        notifications: state.notifications,
        isLoading: false,
        isMarking: state.isMarking,
        unreadCount: state.unreadCount,
        error: e.message,
      );
    }
  }

  Future<void> retry() async {
    state = state.copyWith(isLoading: true, error: null);
    await _load();
  }

  /// Muat semula senarai tanpa mengosongkan data sedia ada. Dipanggil bila
  /// skrin notifikasi dibuka atau app kembali dari latar supaya siaran baharu
  /// muncul tanpa perlu tarik-untuk-refresh.
  Future<void> refresh() async {
    if (state.isLoading) return;
    await _load();
  }

  Future<void> markRead(AppNotification notification) async {
    final id = notification.id;
    if (id == null || notification.isRead) return;

    final previous = state;
    final nextUnread = state.unreadCount > 0 ? state.unreadCount - 1 : 0;
    state = state.copyWith(
      notifications:
          state.notifications
              .map(
                (n) => n.id == id ? n.copyWith(readAt: DateTime.now()) : n,
              )
              .toList(growable: false),
      unreadCount: nextUnread,
    );
    unawaited(AppBadgeService.setUnread(nextUnread));

    try {
      await ref.read(notificationRepositoryProvider).read(id);
    } on ApiException catch (e) {
      state = previous.copyWith(error: e.message);
      unawaited(AppBadgeService.setUnread(previous.unreadCount));
    }
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
        unreadCount: 0,
      );
      unawaited(AppBadgeService.setUnread(0));
    } on ApiException catch (e) {
      state = state.copyWith(isMarking: false, error: e.message);
    }
  }
}
