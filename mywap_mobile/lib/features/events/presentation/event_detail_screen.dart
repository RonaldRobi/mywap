import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/share_item.dart';
import '../../member/application/member_providers.dart';
import '../../auth/application/auth_controller.dart';
import '../../public/presentation/guest_prompt.dart';
import '../application/event_providers.dart';
import '../data/models/event.dart';

class EventDetailScreen extends ConsumerStatefulWidget {
  const EventDetailScreen({super.key, required this.eventId});

  final int eventId;

  @override
  ConsumerState<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends ConsumerState<EventDetailScreen> {
  bool _rsvpLoading = false;
  String? _rsvpError;

  Future<void> _toggleRsvp(String? currentStatus) async {
    if (ref.read(currentUserProvider) == null) {
      await showLoginPrompt(
        context,
        message: context.l10n.eventRsvpLoginPrompt,
      );
      return;
    }
    setState(() {
      _rsvpLoading = true;
      _rsvpError = null;
    });
    try {
      await ref
          .read(eventRepositoryProvider)
          .rsvp(
            widget.eventId,
            status: currentStatus == 'going' ? 'declined' : 'going',
          );
      if (!mounted) return;
      ref.invalidate(eventDetailProvider(widget.eventId));
      ref.invalidate(eventsProvider);
      ref.invalidate(memberDashboardProvider);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.eventRsvpUpdated)));
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _rsvpError = e.message);
    } finally {
      if (mounted) setState(() => _rsvpLoading = false);
    }
  }

  void _openRegistration(EventDetail detail) {
    if (ref.read(currentUserProvider) == null) {
      showLoginPrompt(context, message: context.l10n.eventRegisterLoginPrompt);
      return;
    }
    final forms = detail.registration_forms ?? const <RegistrationForm>[];
    if (forms.isEmpty) return;

    void pushForm(RegistrationForm form) {
      context.push('/events/registration/${widget.eventId}/${form.id}');
    }

    if (forms.length == 1) {
      pushForm(forms.first);
      return;
    }

    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        final theme = Theme.of(sheetContext);
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(Spacing.lg),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.eventRegisterProgram,
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: Spacing.sm),
                for (final form in forms)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(
                      Icons.assignment_outlined,
                      color: AppColors.movementGreen,
                    ),
                    title: Text(form.title ?? context.l10n.eventFormFallback),
                    subtitle:
                        form.paymentRequired && (form.price ?? 0) > 0
                            ? Text(
                              context.l10n.eventFee(
                                Formatters.currency(form.price),
                              ),
                            )
                            : null,
                    onTap: () {
                      Navigator.of(sheetContext).pop();
                      pushForm(form);
                    },
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final detailAsync = ref.watch(eventDetailProvider(widget.eventId));
    final shareEvent = detailAsync.valueOrNull?.event;

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(fallback: '/events'),
        title: Text(context.l10n.eventDetailsTitle),
        actions: [
          if (shareEvent != null)
            ShareItemIconButton(
              title: shareEvent.title ?? '',
              url: shareEvent.share_url,
              text: shareEvent.description,
            ),
        ],
      ),
      body: detailAsync.when(
        data:
            (detail) => _DetailContent(
              event: detail.event,
              registrationForms: detail.registration_forms ?? const [],
              myRegistration: detail.my_registration,
              rsvpLoading: _rsvpLoading,
              rsvpError: _rsvpError,
              onRsvp: _toggleRsvp,
              onRegister: () => _openRegistration(detail),
              onRefresh:
                  () async =>
                      ref.invalidate(eventDetailProvider(widget.eventId)),
            ),
        loading: () => const _DetailSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry:
                  () => ref.invalidate(eventDetailProvider(widget.eventId)),
            ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.event,
    required this.registrationForms,
    required this.myRegistration,
    required this.rsvpLoading,
    required this.rsvpError,
    required this.onRsvp,
    required this.onRegister,
    required this.onRefresh,
  });

  final Event? event;
  final List<RegistrationForm> registrationForms;
  final EventRegistrationSummary? myRegistration;
  final bool rsvpLoading;
  final String? rsvpError;
  final ValueChanged<String?> onRsvp;
  final VoidCallback onRegister;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final title = event?.title ?? context.l10n.eventTitleFallback;
    final description = event?.description;
    final myRsvp = event?.my_rsvp;
    final isGoing = myRsvp == 'going';
    final registrationOpen = event?.registration_open ?? false;
    final attendanceOpen = event?.attendance_open ?? false;
    final registrationClosesAt =
        DateTime.tryParse(event?.registration_closes_at ?? '')?.toLocal();
    final attendanceOpensAt =
        DateTime.tryParse(event?.attendance_opens_at ?? '')?.toLocal();
    final attendanceClosesAt =
        DateTime.tryParse(event?.attendance_closes_at ?? '')?.toLocal();

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          _EventPosterCarousel(event: event, title: title),
          Padding(
            padding: const EdgeInsets.all(Spacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: theme.textTheme.headlineSmall),
                const SizedBox(height: Spacing.md),
                ShareItemButton(
                  title: title,
                  url: event?.share_url,
                  text: description,
                ),
                const SizedBox(height: Spacing.md),
                if (event?.organization?.name != null) ...[
                  Row(
                    children: [
                      Icon(
                        Icons.apartment,
                        size: 18,
                        color: AppColors.movementGreen,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        event!.organization!.name!,
                        style: theme.textTheme.titleSmall?.copyWith(
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.sm),
                ],
                _InfoRow(
                  icon: Icons.calendar_today_outlined,
                  text: event?.start_formatted ?? '',
                ),
                _InfoRow(
                  icon: Icons.location_on_outlined,
                  text: event?.location_or_link ?? '',
                ),
                if (event?.rsvp_count != null) ...[
                  const SizedBox(height: Spacing.sm),
                  _InfoRow(
                    icon: Icons.people_outline,
                    text: context.l10n.eventParticipants(
                      event!.rsvp_count ?? 0,
                    ),
                  ),
                ],
                if (description != null && description.isNotEmpty) ...[
                  const SizedBox(height: Spacing.xl),
                  Text(
                    context.l10n.eventDescription,
                    style: theme.textTheme.titleLarge,
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(description, style: theme.textTheme.bodyLarge),
                ],
                if (rsvpError != null) ...[
                  const SizedBox(height: Spacing.lg),
                  Text(
                    rsvpError!,
                    style: const TextStyle(color: AppColors.error),
                  ),
                ],
                const SizedBox(height: Spacing.xl),
                if (registrationForms.isNotEmpty) ...[
                  if (myRegistration == null) ...[
                    if (registrationOpen) ...[
                      FilledButton.icon(
                        onPressed: onRegister,
                        icon: const Icon(Icons.assignment_outlined),
                        label: Text(context.l10n.eventRegisterAsParticipant),
                      ),
                      const SizedBox(height: Spacing.sm),
                      Text(
                        context.l10n.eventRegisterHint,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ] else ...[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(Spacing.md),
                        decoration: BoxDecoration(
                          color: AppColors.warning.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.l10n.eventRegistrationClosed,
                              style: theme.textTheme.titleSmall?.copyWith(
                                color: AppColors.warning,
                              ),
                            ),
                            if (registrationClosesAt != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                context.l10n.eventRegistrationClosedAt(
                                  Formatters.datetime(registrationClosesAt),
                                ),
                                style: theme.textTheme.bodySmall,
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ] else ...[
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(Spacing.md),
                      decoration: BoxDecoration(
                        color: AppColors.movementGreen.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.l10n.eventAlreadyRegistered,
                            style: theme.textTheme.titleSmall?.copyWith(
                              color: AppColors.movementGreen,
                            ),
                          ),
                          if (myRegistration!.registrationNo != null) ...[
                            const SizedBox(height: 4),
                            Text(
                              context.l10n.eventRegistrationNo(
                                myRegistration!.registrationNo ?? '-',
                              ),
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                          if (myRegistration!.statusLabel != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              context.l10n.eventStatus(
                                myRegistration!.statusLabel ?? '-',
                              ),
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    OutlinedButton.icon(
                      onPressed: () => context.go('/events/my-registrations'),
                      icon: const Icon(Icons.event_available_outlined),
                      label: Text(context.l10n.eventViewMyRegistrations),
                    ),
                  ],
                ] else ...[
                  FilledButton.icon(
                    onPressed: rsvpLoading ? null : () => onRsvp(myRsvp),
                    icon:
                        rsvpLoading
                            ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.white,
                              ),
                            )
                            : Icon(
                              isGoing
                                  ? Icons.event_busy
                                  : Icons.event_available,
                            ),
                    label: Text(
                      isGoing
                          ? context.l10n.eventCancelAttendance
                          : context.l10n.eventWillAttend,
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(
                    context.l10n.eventRsvpHint,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
                if (attendanceOpensAt != null || attendanceClosesAt != null) ...[
                  const SizedBox(height: Spacing.lg),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(Spacing.md),
                    decoration: BoxDecoration(
                      color:
                          (attendanceOpen
                                  ? AppColors.success
                                  : AppColors.warning)
                              .withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.qr_code_2_outlined,
                          size: 18,
                          color:
                              attendanceOpen
                                  ? AppColors.success
                                  : AppColors.warning,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            attendanceOpen
                                ? context.l10n.eventAttendanceWindowOpen(
                                  Formatters.datetime(attendanceClosesAt),
                                )
                                : context.l10n.eventAttendanceWindowClosed(
                                  Formatters.datetime(attendanceOpensAt),
                                  Formatters.datetime(attendanceClosesAt),
                                ),
                            style: theme.textTheme.bodySmall?.copyWith(
                              color:
                                  attendanceOpen
                                      ? AppColors.success
                                      : AppColors.warning,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EventPosterCarousel extends StatefulWidget {
  const _EventPosterCarousel({required this.event, required this.title});

  final Event? event;
  final String title;

  @override
  State<_EventPosterCarousel> createState() => _EventPosterCarouselState();
}

class _EventPosterCarouselState extends State<_EventPosterCarousel> {
  late final PageController _controller;
  Timer? _timer;
  late final List<String> _posters;
  int _activeIndex = 0;
  bool _paused = false;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
    _posters = [
      ...(widget.event?.poster_urls ?? const <String>[]),
    ].where((url) => url.isNotEmpty).toList();
    if (_posters.isEmpty && widget.event?.featured_image_url != null) {
      _posters.add(widget.event!.featured_image_url!);
    }
    if (_posters.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (!_paused && mounted &&
            !(MediaQuery.maybeOf(context)?.disableAnimations ?? false)) {
          final next = (_activeIndex + 1) % _posters.length;
          _controller.animateToPage(
            next,
            duration: const Duration(milliseconds: 350),
            curve: Curves.easeOut,
          );
        }
      });
    }
  }

  void _pause() => setState(() => _paused = true);

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_posters.isEmpty) {
      return const AspectRatio(aspectRatio: 4 / 5, child: SizedBox.shrink());
    }

    return Column(
      children: [
        AspectRatio(
          aspectRatio: 4 / 5,
          child: GestureDetector(
            onTap: _pause,
            onPanDown: (_) => _pause(),
            child: PageView.builder(
              controller: _controller,
              itemCount: _posters.length,
              onPageChanged: (index) => setState(() => _activeIndex = index),
              itemBuilder: (context, index) => Semantics(
                label: '${widget.title}, poster ${index + 1} daripada ${_posters.length}',
                image: true,
                child: AppImage(
                  _posters[index],
                  width: double.infinity,
                  borderRadius: BorderRadius.zero,
                ),
              ),
            ),
          ),
        ),
        if (_posters.length > 1)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                for (var index = 0; index < _posters.length; index++)
                  Semantics(
                    label: 'Poster ${index + 1}',
                    selected: index == _activeIndex,
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      height: 8,
                      width: index == _activeIndex ? 20 : 8,
                      decoration: BoxDecoration(
                        color: index == _activeIndex
                            ? AppColors.movementGreen
                            : AppColors.textSecondary.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: Spacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(color: AppColors.textSecondary),
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: const [
        AspectRatio(aspectRatio: 4 / 5, child: SkeletonBox(radius: 0)),
        Padding(
          padding: EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 28, width: 260),
              SizedBox(height: 16),
              SkeletonBox(height: 18),
              SizedBox(height: 8),
              SkeletonBox(height: 18),
              SizedBox(height: 24),
              SkeletonBox(height: 120),
            ],
          ),
        ),
      ],
    );
  }
}
