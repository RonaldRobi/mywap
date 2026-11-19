import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/share_item.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../../member/application/member_providers.dart';
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
      await ref.read(eventRepositoryProvider).rsvp(
            widget.eventId,
            status: currentStatus == 'going' ? 'declined' : 'going',
          );
      if (!mounted) return;
      ref.invalidate(eventDetailProvider(widget.eventId));
      ref.invalidate(eventsProvider);
      ref.invalidate(memberDashboardProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.eventRsvpUpdated)),
      );
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
                    subtitle: form.paymentRequired && (form.price ?? 0) > 0
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

    return detailAsync.when(
      data: (detail) {
        final event = detail.event;
        final registrationForms = detail.registration_forms ?? const [];
        final myRegistration = detail.my_registration;
        final feeText = _formatFee(registrationForms);

        return Scaffold(
          backgroundColor: AppColors.pageBackground,
          body: _DetailContent(
            event: event,
            feeText: feeText,
            registrationForms: registrationForms,
            myRegistration: myRegistration,
            onRefresh: () async =>
                ref.invalidate(eventDetailProvider(widget.eventId)),
          ),
          bottomNavigationBar: _BottomActionBar(
            feeText: feeText,
            registrationForms: registrationForms,
            myRegistration: myRegistration,
            registrationOpen: event?.registration_open ?? false,
            isGoing: event?.my_rsvp == 'going',
            rsvpLoading: _rsvpLoading,
            rsvpError: _rsvpError,
            myRsvp: event?.my_rsvp,
            onRegister: () => _openRegistration(detail),
            onRsvp: _toggleRsvp,
          ),
        );
      },
      loading: () => const Scaffold(
        backgroundColor: AppColors.pageBackground,
        body: _DetailSkeleton(),
      ),
      error: (error, _) => Scaffold(
        backgroundColor: AppColors.pageBackground,
        appBar: AppBar(
          leading: const AppBackButton(fallback: '/events'),
          title: Text(context.l10n.eventDetailsTitle),
        ),
        body: ErrorRetry(
          message: error is ApiException
              ? error.message
              : context.l10n.unexpectedError,
          onRetry: () => ref.invalidate(eventDetailProvider(widget.eventId)),
        ),
      ),
    );
  }
}

String _formatFee(List<RegistrationForm> forms) {
  if (forms.isEmpty) return 'Percuma';
  final paidForms =
      forms.where((f) => f.paymentRequired && (f.price ?? 0) > 0).toList();
  if (paidForms.isEmpty) return 'Percuma';
  final prices = paidForms.map((f) => f.price!).toList()..sort();
  if (prices.first == prices.last) {
    return Formatters.currency(prices.first);
  }
  return '${Formatters.currency(prices.first)} - ${Formatters.currency(prices.last)}';
}

(String date, String? time) _parseEventDateTime(Event? event) {
  if (event == null) return ('-', null);
  final formatted = event.start_formatted?.trim();
  if (formatted != null && formatted.isNotEmpty) {
    if (formatted.contains('•')) {
      final parts = formatted.split('•');
      return (parts.first.trim(), parts.last.trim());
    }
    return (formatted, null);
  }
  if (event.start_time != null) {
    final dt = DateTime.tryParse(event.start_time!);
    if (dt != null) {
      return (Formatters.longDate(dt.toLocal()), null);
    }
  }
  return ('-', null);
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({
    required this.event,
    required this.feeText,
    required this.registrationForms,
    required this.myRegistration,
    required this.onRefresh,
  });

  final Event? event;
  final String feeText;
  final List<RegistrationForm> registrationForms;
  final EventRegistrationSummary? myRegistration;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final title = event?.title ?? context.l10n.eventTitleFallback;
    final description = event?.description;
    final isOnline = event?.type == 'online';
    final (dateStr, timeStr) = _parseEventDateTime(event);

    final rawLocation = event?.location_or_link?.trim();
    final bool hasLocationUrl = rawLocation != null &&
        (rawLocation.startsWith('http://') ||
            rawLocation.startsWith('https://'));

    final String locationTitle;
    final String? locationSubtitle;
    final String? locationActionUrl;

    if (isOnline) {
      locationTitle = context.l10n.eventOnline;
      locationSubtitle = hasLocationUrl ? rawLocation : null;
      locationActionUrl = hasLocationUrl ? rawLocation : null;
    } else {
      locationTitle = rawLocation?.isNotEmpty == true
          ? rawLocation!
          : context.l10n.eventPhysical;
      locationSubtitle = hasLocationUrl ? rawLocation : null;
      locationActionUrl = hasLocationUrl
          ? rawLocation
          : (rawLocation?.isNotEmpty == true
              ? 'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(rawLocation!)}'
              : null);
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.zero,
        children: [
          // Hero Gallery Poster (Swipeable) dengan Floating Back & Share
          _EventPosterGallery(event: event, title: title),

          // Kandungan Halaman
          Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.xxl,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Bar Lencana Status, Kategori & Yuran
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: isOnline
                            ? const Color(0xFFE6F4EA)
                            : const Color(0xFFFEF3C7),
                        borderRadius: BorderRadius.circular(99),
                        border: Border.all(
                          color: isOnline
                              ? const Color(0xFF34D399).withValues(alpha: 0.5)
                              : const Color(0xFFFBBF24).withValues(alpha: 0.5),
                          width: 0.8,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 7,
                            height: 7,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: isOnline
                                  ? const Color(0xFF10B981)
                                  : const Color(0xFFD97706),
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            isOnline
                                ? context.l10n.eventOnline.toUpperCase()
                                : context.l10n.eventPhysical.toUpperCase(),
                            style: TextStyle(
                              color: isOnline
                                  ? const Color(0xFF065F46)
                                  : const Color(0xFF92400E),
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.6,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (event?.category_label?.isNotEmpty == true ||
                        event?.category?.isNotEmpty == true) ...[
                      const SizedBox(width: Spacing.sm),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.white,
                          borderRadius: BorderRadius.circular(99),
                          border: Border.all(
                            color: AppColors.divider.withValues(alpha: 0.6),
                            width: 0.8,
                          ),
                        ),
                        child: Text(
                          (event?.category_label ?? event?.category ?? '')
                              .toUpperCase(),
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 10.5,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ],
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.paleGreen,
                        borderRadius: BorderRadius.circular(99),
                      ),
                      child: Text(
                        feeText.toUpperCase(),
                        style: const TextStyle(
                          color: AppColors.movementDarkGreen,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: Spacing.md),

                // Tajuk Acara
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppColors.movementNavy,
                    height: 1.25,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: Spacing.lg),

                // Kad Jadual & Lokasi
                Container(
                  decoration: BoxDecoration(
                    color: AppColors.white,
                    borderRadius: AppRadius.card,
                    border: Border.all(
                      color: AppColors.divider.withValues(alpha: 0.6),
                      width: 0.8,
                    ),
                    boxShadow: AppShadows.subtle,
                  ),
                  child: Column(
                    children: [
                      // Tarikh & Masa
                      Padding(
                        padding: const EdgeInsets.all(Spacing.md),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: AppColors.greenTint,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(
                                Icons.calendar_today_rounded,
                                color: AppColors.movementGreen,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: Spacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    dateStr,
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.movementNavy,
                                    ),
                                  ),
                                  if (timeStr != null) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      timeStr,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: AppColors.textSecondary,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      Divider(
                        height: 1,
                        color: AppColors.divider.withValues(alpha: 0.5),
                      ),

                      // Lokasi / Pautan
                      Padding(
                        padding: const EdgeInsets.all(Spacing.md),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: AppColors.greenTint,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Icon(
                                isOnline
                                    ? Icons.link_rounded
                                    : Icons.location_on_outlined,
                                color: AppColors.movementGreen,
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: Spacing.md),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    locationTitle,
                                    style: const TextStyle(
                                      fontSize: 14,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.movementNavy,
                                    ),
                                  ),
                                  if (locationSubtitle != null) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      locationSubtitle,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: AppColors.textSecondary,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            if (locationActionUrl != null)
                              IconButton(
                                icon: const Icon(
                                  Icons.open_in_new_rounded,
                                  size: 18,
                                  color: AppColors.movementGreen,
                                ),
                                tooltip:
                                    isOnline ? 'Buka Pautan' : 'Buka Peta',
                                onPressed: () => launchUrl(
                                  Uri.parse(locationActionUrl!),
                                  mode: LaunchMode.externalApplication,
                                ),
                              ),
                          ],
                        ),
                      ),

                      // Jumlah Peserta (jika ada)
                      if (event?.rsvp_count != null &&
                          (event!.rsvp_count ?? 0) > 0) ...[
                        Divider(
                          height: 1,
                          color: AppColors.divider.withValues(alpha: 0.5),
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: Spacing.md,
                            vertical: Spacing.sm + 2,
                          ),
                          child: Row(
                            children: [
                              const Icon(
                                Icons.people_outline_rounded,
                                size: 16,
                                color: AppColors.textSecondary,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                context.l10n.eventParticipants(
                                  event!.rsvp_count ?? 0,
                                ),
                                style: const TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),

                // Penganjur
                if (event?.organization != null) ...[
                  const SizedBox(height: Spacing.md),
                  Container(
                    padding: const EdgeInsets.all(Spacing.md),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: AppRadius.card,
                      border: Border.all(
                        color: AppColors.divider.withValues(alpha: 0.6),
                        width: 0.8,
                      ),
                      boxShadow: AppShadows.subtle,
                    ),
                    child: Row(
                      children: [
                        if (event!.organization!.logo_path?.isNotEmpty == true)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: AppImage(
                              event!.organization!.logo_path,
                              width: 36,
                              height: 36,
                              fit: BoxFit.contain,
                            ),
                          )
                        else
                          Container(
                            width: 36,
                            height: 36,
                            decoration: BoxDecoration(
                              color: AppColors.greenTint,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(
                              Icons.apartment_rounded,
                              color: AppColors.movementGreen,
                              size: 20,
                            ),
                          ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'PENGANJUR RASMI',
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.textTertiary,
                                  letterSpacing: 0.8,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                event!.organization!.name ?? '-',
                                style: const TextStyle(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.movementNavy,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                // Penerangan Program (Kad Putih Eksklusif)
                if (description != null && description.isNotEmpty) ...[
                  const SizedBox(height: Spacing.md),
                  _EventDescriptionCard(description: description),
                ],

                // Kad Status Pendaftaran Anda (Jika Sudah Daftar)
                if (myRegistration != null) ...[
                  const SizedBox(height: Spacing.md),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(Spacing.lg),
                    decoration: BoxDecoration(
                      color: AppColors.white,
                      borderRadius: AppRadius.card,
                      border: Border.all(
                        color: AppColors.movementGreen.withValues(alpha: 0.35),
                        width: 1,
                      ),
                      boxShadow: AppShadows.subtle,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Icon(
                              Icons.check_circle_rounded,
                              color: AppColors.movementGreen,
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              context.l10n.eventAlreadyRegistered,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: AppColors.movementGreen,
                              ),
                            ),
                          ],
                        ),
                        if (myRegistration!.registrationNo != null) ...[
                          const SizedBox(height: Spacing.sm),
                          Text(
                            context.l10n.eventRegistrationNo(
                              myRegistration!.registrationNo ?? '-',
                            ),
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppColors.textSecondary,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                        if (myRegistration!.statusLabel != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            context.l10n.eventStatus(
                              myRegistration!.statusLabel ?? '-',
                            ),
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],

                // Ruang bawah untuk mengelakkan kandungan terhalang oleh Sticky Bottom Bar
                const SizedBox(height: 120),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Kad Penerangan Program dengan pembungkus kad putih, tipografi kemas,
/// dan sokongan 'Baca Penuh / Ringkaskan' untuk teks yang panjang.
class _EventDescriptionCard extends StatefulWidget {
  const _EventDescriptionCard({required this.description});

  final String description;

  @override
  State<_EventDescriptionCard> createState() => _EventDescriptionCardState();
}

class _EventDescriptionCardState extends State<_EventDescriptionCard> {
  bool _isExpanded = false;

  @override
  Widget build(BuildContext context) {
    final isLongText = widget.description.length > 240;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        border: Border.all(
          color: AppColors.divider.withValues(alpha: 0.6),
          width: 0.8,
        ),
        boxShadow: AppShadows.subtle,
      ),
      padding: const EdgeInsets.all(Spacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  color: AppColors.greenTint,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.notes_rounded,
                  color: AppColors.movementGreen,
                  size: 18,
                ),
              ),
              const SizedBox(width: Spacing.sm + 2),
              Expanded(
                child: Text(
                  context.l10n.eventDescription,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: AppColors.movementNavy,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.md),
          Divider(
            height: 1,
            color: AppColors.divider.withValues(alpha: 0.5),
          ),
          const SizedBox(height: Spacing.md),
          Text(
            widget.description,
            style: const TextStyle(
              fontSize: 14.5,
              height: 1.65,
              color: AppColors.textPrimary,
              fontWeight: FontWeight.w400,
            ),
            maxLines: (_isExpanded || !isLongText) ? null : 6,
            overflow: (_isExpanded || !isLongText)
                ? TextOverflow.visible
                : TextOverflow.ellipsis,
          ),
          if (isLongText) ...[
            const SizedBox(height: Spacing.sm),
            Align(
              alignment: Alignment.centerLeft,
              child: InkWell(
                onTap: () => setState(() => _isExpanded = !_isExpanded),
                borderRadius: BorderRadius.circular(6),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    vertical: 4,
                    horizontal: 2,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _isExpanded ? 'Ringkaskan' : 'Baca Penuh',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.movementGreen,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(
                        _isExpanded
                            ? Icons.keyboard_arrow_up_rounded
                            : Icons.keyboard_arrow_down_rounded,
                        size: 18,
                        color: AppColors.movementGreen,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Galeri poster acara atas (Swipeable 4:5) dengan butang Back di kiri & Share di kanan.
class _EventPosterGallery extends StatefulWidget {
  const _EventPosterGallery({required this.event, required this.title});

  final Event? event;
  final String title;

  @override
  State<_EventPosterGallery> createState() => _EventPosterGalleryState();
}

class _EventPosterGalleryState extends State<_EventPosterGallery> {
  late final PageController _controller;
  late final List<String> _posters;
  int _activeIndex = 0;

  @override
  void initState() {
    super.initState();
    _controller = PageController();
    _posters = <String>[];
    if (widget.event?.featured_image_url?.isNotEmpty == true) {
      _posters.add(widget.event!.featured_image_url!);
    }
    for (final url in widget.event?.poster_urls ?? const <String>[]) {
      if (url.isNotEmpty && !_posters.contains(url)) {
        _posters.add(url);
      }
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.of(context).padding.top;

    if (_posters.isEmpty) {
      return Container(
        height: 320,
        decoration: const BoxDecoration(
          color: AppColors.movementNavy,
          borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
        ),
        child: Stack(
          children: [
            Center(
              child: Icon(
                Icons.event_outlined,
                size: 60,
                color: AppColors.movementSoftGreen.withValues(alpha: 0.35),
              ),
            ),
            Positioned(
              top: topPadding + Spacing.sm,
              left: Spacing.lg,
              child: _CircleNavButton(
                icon: Icons.arrow_back_ios_new_rounded,
                tooltip: context.l10n.back,
                onPressed: () {
                  if (context.canPop()) {
                    context.pop();
                  } else {
                    context.go('/events');
                  }
                },
              ),
            ),
            Positioned(
              top: topPadding + Spacing.sm,
              right: Spacing.lg,
              child: _CircleNavButton(
                icon: Icons.share_outlined,
                tooltip: context.l10n.share,
                onPressed: () => shareItem(
                  context,
                  title: widget.title,
                  url: widget.event?.share_url,
                  text: widget.event?.description,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return AspectRatio(
      aspectRatio: 4 / 5,
      child: ClipRRect(
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
        child: Stack(
          fit: StackFit.expand,
          children: [
            PageView.builder(
              controller: _controller,
              itemCount: _posters.length,
              onPageChanged: (i) => setState(() => _activeIndex = i),
              itemBuilder: (context, index) => Semantics(
                label:
                    '${widget.title}, poster ${index + 1} daripada ${_posters.length}',
                image: true,
                child: AppImage(
                  _posters[index],
                  width: double.infinity,
                  fit: BoxFit.cover,
                  borderRadius: BorderRadius.zero,
                ),
              ),
            ),

            // Bayang gradien atas untuk memastikan butang nav sentiasa jelas
            const Positioned(
              top: 0,
              left: 0,
              right: 0,
              height: 110,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0x8A000000), Colors.transparent],
                  ),
                ),
              ),
            ),

            // Butang Kembali (Kiri Atas)
            Positioned(
              top: topPadding + Spacing.sm,
              left: Spacing.lg,
              child: _CircleNavButton(
                icon: Icons.arrow_back_ios_new_rounded,
                tooltip: context.l10n.back,
                onPressed: () {
                  if (context.canPop()) {
                    context.pop();
                  } else {
                    context.go('/events');
                  }
                },
              ),
            ),

            // Butang Kongsi (Kanan Atas)
            Positioned(
              top: topPadding + Spacing.sm,
              right: Spacing.lg,
              child: _CircleNavButton(
                icon: Icons.share_outlined,
                tooltip: context.l10n.share,
                onPressed: () => shareItem(
                  context,
                  title: widget.title,
                  url: widget.event?.share_url,
                  text: widget.event?.description,
                ),
              ),
            ),

            // Kapsul Nombor Halaman Galeri (Kanan Bawah)
            if (_posters.length > 1)
              Positioned(
                right: Spacing.lg,
                bottom: Spacing.lg,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: AppColors.movementNavy.withValues(alpha: 0.72),
                    borderRadius: BorderRadius.circular(99),
                    border: Border.all(
                      color: AppColors.white.withValues(alpha: 0.25),
                      width: 0.75,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.photo_library_outlined,
                        size: 13,
                        color: AppColors.white,
                      ),
                      const SizedBox(width: 5),
                      Text(
                        '${_activeIndex + 1} / ${_posters.length}',
                        style: const TextStyle(
                          color: AppColors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Butang navigasi bulat terapung di atas imej (Kaca Gelap Lutsinar)
class _CircleNavButton extends StatelessWidget {
  const _CircleNavButton({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: AppColors.movementNavy.withValues(alpha: 0.65),
        border: Border.all(
          color: AppColors.white.withValues(alpha: 0.25),
          width: 0.8,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 8,
            offset: Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: IconButton(
          tooltip: tooltip,
          iconSize: 18,
          padding: EdgeInsets.zero,
          color: AppColors.white,
          icon: Icon(icon),
          onPressed: onPressed,
        ),
      ),
    );
  }
}

/// Bar Tindakan Bawah Kekal (Sticky Bottom Action Bar)
class _BottomActionBar extends StatelessWidget {
  const _BottomActionBar({
    required this.feeText,
    required this.registrationForms,
    required this.myRegistration,
    required this.registrationOpen,
    required this.isGoing,
    required this.rsvpLoading,
    required this.rsvpError,
    required this.onRegister,
    required this.onRsvp,
    required this.myRsvp,
  });

  final String feeText;
  final List<RegistrationForm> registrationForms;
  final EventRegistrationSummary? myRegistration;
  final bool registrationOpen;
  final bool isGoing;
  final bool rsvpLoading;
  final String? rsvpError;
  final VoidCallback onRegister;
  final ValueChanged<String?> onRsvp;
  final String? myRsvp;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;

    return Container(
      decoration: BoxDecoration(
        color: AppColors.white,
        border: Border(
          top: BorderSide(
            color: AppColors.divider.withValues(alpha: 0.6),
            width: 0.8,
          ),
        ),
        boxShadow: AppShadows.floating,
      ),
      padding: EdgeInsets.fromLTRB(
        Spacing.lg,
        Spacing.md,
        Spacing.lg,
        bottomInset > 0 ? bottomInset : Spacing.md,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (rsvpError != null) ...[
            Padding(
              padding: const EdgeInsets.only(bottom: Spacing.xs),
              child: Text(
                rsvpError!,
                style: const TextStyle(
                  color: AppColors.error,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
          if (myRegistration != null) ...[
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'STATUS PENDAFTARAN',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textTertiary,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        myRegistration!.statusLabel ?? 'Disahkan',
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton.icon(
                  onPressed: () => context.go('/events/my-registrations'),
                  icon: const Icon(Icons.confirmation_number_outlined, size: 18),
                  label: Text(context.l10n.eventViewMyRegistrations),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    minimumSize: const Size(0, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ],
            ),
          ] else if (registrationForms.isNotEmpty) ...[
            if (registrationOpen) ...[
              Row(
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'YURAN',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textTertiary,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        feeText,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.movementGreen,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(width: Spacing.lg),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: onRegister,
                      icon: const Icon(Icons.assignment_outlined, size: 18),
                      label: Text(context.l10n.eventRegisterAsParticipant),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.movementGreen,
                        foregroundColor: AppColors.white,
                        minimumSize: const Size(0, 48),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ] else ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: AppColors.warning.withValues(alpha: 0.3),
                  ),
                ),
                child: Text(
                  context.l10n.eventRegistrationClosed,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: AppColors.warning,
                  ),
                ),
              ),
            ],
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text(
                        'STATUS KEHADIRAN',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textTertiary,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isGoing ? 'Akan Hadir' : 'Belum Respons',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: isGoing
                              ? AppColors.movementGreen
                              : AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton.icon(
                  onPressed: rsvpLoading ? null : () => onRsvp(myRsvp),
                  icon: rsvpLoading
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.white,
                          ),
                        )
                      : Icon(
                          isGoing
                              ? Icons.event_busy_rounded
                              : Icons.event_available_rounded,
                          size: 18,
                        ),
                  label: Text(
                    isGoing
                        ? context.l10n.eventCancelAttendance
                        : context.l10n.eventWillAttend,
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: isGoing
                        ? AppColors.error
                        : AppColors.movementGreen,
                    foregroundColor: AppColors.white,
                    minimumSize: const Size(0, 48),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Rangka pemuatan (Skeleton)
class _DetailSkeleton extends StatelessWidget {
  const _DetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: EdgeInsets.zero,
      children: const [
        AspectRatio(aspectRatio: 4 / 5, child: SkeletonBox(radius: 0)),
        Padding(
          padding: EdgeInsets.all(Spacing.lg),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SkeletonBox(height: 26, width: 140, radius: 99),
              SizedBox(height: Spacing.md),
              SkeletonBox(height: 32, width: 280),
              SizedBox(height: Spacing.lg),
              SkeletonBox(height: 110, radius: 14),
              SizedBox(height: Spacing.md),
              SkeletonBox(height: 60, radius: 14),
              SizedBox(height: Spacing.xl),
              SkeletonBox(height: 140, radius: 14),
            ],
          ),
        ),
      ],
    );
  }
}
