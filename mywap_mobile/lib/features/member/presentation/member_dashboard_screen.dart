import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../../auth/application/auth_controller.dart';
import '../../events/data/models/event.dart';
import '../application/member_providers.dart';
import '../data/models/dashboard_data.dart';
import 'main_shell.dart';

class MemberDashboardScreen extends ConsumerWidget {
  const MemberDashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(currentUserProvider);
    final dashboardAsync = ref.watch(memberDashboardProvider);

    return Scaffold(
      body: dashboardAsync.when(
        data: (data) => _DashboardContent(userName: user?.name, data: data),
        loading: () => const _DashboardSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : 'Ralat tidak dijangka.',
              onRetry: () => ref.invalidate(memberDashboardProvider),
            ),
      ),
    );
  }
}

class _DashboardContent extends ConsumerWidget {
  const _DashboardContent({required this.userName, required this.data});

  final String? userName;
  final DashboardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final member = data.member;
    final memberName = member?.name ?? userName ?? 'Ahli';
    final banners = data.banners ?? const [];
    final events = data.upcoming_events ?? const [];

    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(memberDashboardProvider),
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            pinned: true,
            backgroundColor: AppColors.white.withValues(alpha: 0.94),
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            titleSpacing: Spacing.lg,
            title: _Greeting(name: memberName),
            actions: const [LogoutIconButton(), SizedBox(width: Spacing.sm)],
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.lg,
              Spacing.lg,
              Spacing.lg,
              Spacing.xxl,
            ),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                _Banner(banner: banners.isEmpty ? null : banners.first),
                const SizedBox(height: Spacing.xl),
                const _SectionLabel(title: 'Pintasan'),
                const SizedBox(height: Spacing.md),
                const _Shortcuts(),
                const SizedBox(height: Spacing.xl),
                _MembershipCard(member: member, feeStatus: data.fee_status),
                const SizedBox(height: Spacing.xl),
                _SectionLabel(
                  title: 'Program',
                  subtitle: 'Acara dan aktiviti akan datang',
                  action: () => context.go('/events'),
                ),
                const SizedBox(height: Spacing.md),
                if (events.isEmpty)
                  const _EmptyPrograms()
                else
                  SizedBox(
                    height: 230,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: events.length,
                      separatorBuilder:
                          (_, __) => const SizedBox(width: Spacing.md),
                      itemBuilder:
                          (_, index) => _EventCard(event: events[index]),
                    ),
                  ),
                if (data.next_event != null) ...[
                  const SizedBox(height: Spacing.xl),
                  _NextEvent(event: data.next_event!),
                ],
              ]),
            ),
          ),
        ],
      ),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name});
  final String name;

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greeting =
        hour < 12
            ? 'Selamat Pagi'
            : hour < 18
            ? 'Selamat Petang'
            : 'Selamat Malam';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Assalamualaikum, $name',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(
            context,
          ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
        ),
        Text(
          greeting,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: AppColors.textSecondary,
            fontSize: 10,
          ),
        ),
      ],
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({this.banner});
  final DashboardBanner? banner;

  @override
  Widget build(BuildContext context) {
    return AspectRatio(
      aspectRatio: 21 / 9,
      child: ClipRRect(
        borderRadius: AppRadius.hero,
        child:
            banner?.image_path?.isNotEmpty == true
                ? AppImage(
                  banner!.image_path,
                  width: double.infinity,
                  fit: BoxFit.cover,
                )
                : Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        AppColors.movementSoftGreen,
                        AppColors.movementGreen,
                      ],
                    ),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    banner?.title ?? 'Selamat datang ke myWAP',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: AppColors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel({required this.title, this.subtitle, this.action});
  final String title;
  final String? subtitle;
  final VoidCallback? action;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            if (subtitle != null)
              Text(
                subtitle!,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textSecondary,
                  fontSize: 11,
                ),
              ),
          ],
        ),
      ),
      if (action != null)
        TextButton(onPressed: action, child: const Text('Lihat Semua')),
    ],
  );
}

class _Shortcuts extends StatelessWidget {
  const _Shortcuts();
  @override
  Widget build(BuildContext context) => Row(
    children: [
      _Shortcut(
        icon: Icons.credit_card_outlined,
        label: 'Yuran Saya',
        colors: const [Color(0xFF34D399), Color(0xFF059669)],
        onTap: () => context.push('/member/fee-status'),
      ),
      _Shortcut(
        icon: Icons.calendar_month_outlined,
        label: 'Tempah',
        colors: const [Color(0xFFFBBF24), Color(0xFFD97706)],
        onTap: () => context.push('/facilities'),
      ),
      _Shortcut(
        icon: Icons.newspaper_outlined,
        label: 'Info',
        colors: const [Color(0xFF818CF8), Color(0xFF4F46E5)],
        onTap: () => context.push('/news'),
      ),
      _Shortcut(
        icon: Icons.volunteer_activism_outlined,
        label: 'Infaq',
        colors: const [Color(0xFFFB7185), Color(0xFFE11D48)],
        onTap: () => context.go('/infaq'),
      ),
    ],
  );
}

class _Shortcut extends StatelessWidget {
  const _Shortcut({
    required this.icon,
    required this.label,
    required this.colors,
    required this.onTap,
  });
  final IconData icon;
  final String label;
  final List<Color> colors;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 3),
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(
              vertical: Spacing.md,
              horizontal: Spacing.xs,
            ),
            child: Column(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: colors),
                    borderRadius: AppRadius.lg,
                  ),
                  child: Icon(icon, color: AppColors.white, size: 25),
                ),
                const SizedBox(height: Spacing.sm),
                Text(
                  label,
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

class _MembershipCard extends StatelessWidget {
  const _MembershipCard({required this.member, required this.feeStatus});
  final DashboardMember? member;
  final Map<String, dynamic>? feeStatus;
  @override
  Widget build(BuildContext context) {
    final active = feeStatus?['status']?.toString() == 'active';
    return Container(
      padding: const EdgeInsets.all(Spacing.xl),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.movementDarkGreen,
            AppColors.movementGreen,
            AppColors.movementDarkGreen,
          ],
        ),
        borderRadius: AppRadius.hero,
        boxShadow: const [
          BoxShadow(
            color: Color(0x55071F25),
            blurRadius: 22,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.workspace_premium_outlined,
                color: AppColors.white,
                size: 20,
              ),
              const SizedBox(width: Spacing.sm),
              Text(
                'KAD AHLI · myWAP',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textOnDark,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                ),
              ),
              const Spacer(),
              Icon(
                Icons.circle,
                size: 12,
                color: active ? AppColors.movementSoftGreen : AppColors.warning,
              ),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: Spacing.md),
            child: Divider(color: Color(0x33FFFFFF)),
          ),
          Row(
            children: [
              ClipRRect(
                borderRadius: AppRadius.md,
                child: AppImage(
                  member?.photo_url,
                  width: 72,
                  height: 72,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(width: Spacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member?.name ?? 'Ahli myWAP',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      'Ahli sejak ${member?.member_since ?? '-'}',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.textOnDark,
                        fontSize: 11,
                      ),
                    ),
                    const SizedBox(height: Spacing.xs),
                    Text(
                      member?.member_no ?? 'No. ahli belum tersedia',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: AppColors.movementSoftGreen,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: Spacing.lg),
          Row(
            children: [
              Expanded(
                child: _CardStat(
                  label: 'CAWANGAN AHLI',
                  value: member?.branch_name ?? '-',
                ),
              ),
              const SizedBox(width: Spacing.sm),
              Expanded(
                child: _CardStat(
                  label: 'STATUS',
                  value: active ? 'AKTIF' : 'SEMAK YURAN',
                ),
              ),
            ],
          ),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(
              onPressed: () => context.push('/card'),
              child: const Text(
                'Lihat Kad Penuh',
                style: TextStyle(color: AppColors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CardStat extends StatelessWidget {
  const _CardStat({required this.label, required this.value});
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.sm),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: .1),
      borderRadius: AppRadius.md,
      border: Border.all(color: Colors.white.withValues(alpha: .12)),
    ),
    child: Column(
      children: [
        Text(
          label,
          style: const TextStyle(
            color: Color(0x99FFFFFF),
            fontSize: 9,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.white,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final Event event;
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 175,
      child: Material(
        color: AppColors.white,
        borderRadius: AppRadius.card,
        child: InkWell(
          borderRadius: AppRadius.card,
          onTap: () => context.push('/events/${event.id}'),
          child: ClipRRect(
            borderRadius: AppRadius.card,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      if (event.featured_image_url?.isNotEmpty == true)
                        AppImage(event.featured_image_url, fit: BoxFit.cover)
                      else
                        Container(color: AppColors.paleGreen),
                      const DecoratedBox(
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.bottomCenter,
                            end: Alignment.topCenter,
                            colors: [Color(0x66000000), Colors.transparent],
                          ),
                        ),
                      ),
                      Positioned(
                        left: Spacing.sm,
                        top: Spacing.sm,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.white,
                            borderRadius: BorderRadius.circular(99),
                          ),
                          child: Text(
                            event.type == 'physical' ? 'FIZIKAL' : 'ONLINE',
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        event.title ?? '-',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        event.start_formatted ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(
                          context,
                        ).textTheme.labelMedium?.copyWith(
                          fontSize: 11,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NextEvent extends StatelessWidget {
  const _NextEvent({required this.event});
  final Map<String, dynamic> event;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.lg),
    decoration: BoxDecoration(
      color: AppColors.softGreenSurface,
      borderRadius: AppRadius.card,
      border: Border.all(color: AppColors.movementSoftGreen),
    ),
    child: Row(
      children: [
        const Icon(Icons.star_outline, color: AppColors.movementGreen),
        const SizedBox(width: Spacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'ACARA SETERUSNYA',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.movementGreen,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                ),
              ),
              Text(
                event['title']?.toString() ?? '-',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              Text(
                event['start_formatted']?.toString() ?? '',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _EmptyPrograms extends StatelessWidget {
  const _EmptyPrograms();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(Spacing.xxl),
    decoration: BoxDecoration(
      color: AppColors.white,
      borderRadius: AppRadius.card,
      border: Border.all(color: AppColors.divider, style: BorderStyle.solid),
    ),
    child: const Column(
      children: [
        Icon(
          Icons.event_busy_outlined,
          color: AppColors.textSecondary,
          size: 32,
        ),
        SizedBox(height: Spacing.sm),
        Text('Tiada acara akan datang buat masa ini.'),
      ],
    ),
  );
}

class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(Spacing.lg),
    children: const [
      SkeletonBox(height: 42, radius: 0),
      SizedBox(height: Spacing.lg),
      SkeletonBox(height: 160, radius: 28),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 112, radius: 20),
      SizedBox(height: Spacing.xl),
      SkeletonBox(height: 270, radius: 28),
    ],
  );
}
