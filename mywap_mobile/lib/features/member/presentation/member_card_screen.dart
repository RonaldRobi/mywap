import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../../../shared/widgets/app_image.dart';
import '../../../shared/widgets/error_retry.dart';
import '../../../shared/widgets/skeleton_box.dart';
import '../application/member_core_providers.dart';
import '../data/models/member_card_data.dart';
import 'widgets/member_card_layout.dart';

class MemberCardScreen extends ConsumerWidget {
  const MemberCardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cardAsync = ref.watch(memberCardProvider);
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.menuCard),
      ),
      body: cardAsync.when(
        data: (data) => _CardContent(data: data),
        loading: () => const _CardSkeleton(),
        error:
            (error, _) => ErrorRetry(
              message:
                  error is ApiException
                      ? error.message
                      : context.l10n.unexpectedError,
              onRetry: () => ref.invalidate(memberCardProvider),
            ),
      ),
    );
  }
}

class _CardContent extends ConsumerWidget {
  const _CardContent({required this.data});
  final MemberCardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(memberCardProvider),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(Spacing.lg),
        children: [
          Center(
            // Hadkan lebar supaya kad kekal berkad pada tablet, bukan
            // meregang sehingga memenuhi skrin.
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: _MemberCard(card: data.card, qrValue: data.card?.qrValue),
            ),
          ),
          const SizedBox(height: Spacing.md),
          Text(
            context.l10n.orgCardTapHint,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

/// Kad keahlian — muka depan/belakang dengan animasi flip 3D.
///
/// Susun atur dport daripada design web (`resources/js/Components/
/// MemberCard/`): kanvas 638 × 1004 diskalakan kepada lebar sebenar.
class _MemberCard extends StatefulWidget {
  const _MemberCard({required this.card, required this.qrValue});

  final MemberCardInfo? card;
  final String? qrValue;

  @override
  State<_MemberCard> createState() => _MemberCardState();
}

class _MemberCardState extends State<_MemberCard>
    with SingleTickerProviderStateMixin {
  /// Sama dengan `cubic-bezier(0.4, 0.2, 0.2, 1)` pada design web.
  static const _flipCurve = Cubic(0.4, 0.2, 0.2, 1.0);

  late final AnimationController _flipController;
  bool _showBack = false;

  @override
  void initState() {
    super.initState();
    _flipController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    );
  }

  @override
  void dispose() {
    _flipController.dispose();
    super.dispose();
  }

  void _toggle() {
    final reduceMotion = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    setState(() => _showBack = !_showBack);
    if (reduceMotion) {
      _flipController.value = _showBack ? 1 : 0;
    } else if (_showBack) {
      _flipController.forward();
    } else {
      _flipController.reverse();
    }
  }

  @override
  Widget build(BuildContext context) {
    // Bina kedua-dua muka SEKALI sahaja. Jika dibina di dalam builder
    // animasi, `_CardFront` (dengan ukuran `TextPainter` dan QR) akan dibina
    // semula pada setiap frame semasa flip 700ms.
    final front = _CardFront(
      card: widget.card,
      qrValue: widget.qrValue,
      onQrTap: _showQrDialog,
    );
    final back = _CardBack(path: widget.card?.card_back_path);

    return Semantics(
      button: true,
      label:
          _showBack
              ? context.l10n.orgCardViewFront
              : context.l10n.orgCardViewBack,
      child: GestureDetector(
        onTap: _toggle,
        behavior: HitTestBehavior.opaque,
        child: AspectRatio(
          aspectRatio: CardLayout.cardW / CardLayout.cardH,
          child: AnimatedBuilder(
            animation: _flipController,
            builder: (context, _) {
              // Guna nilai yang sama untuk putaran dan titik pertukaran muka
              // supaya pertukaran berlaku tepat pada 90°.
              final t = _flipCurve.transform(_flipController.value);
              final isBack = t > 0.5;
              return Transform(
                alignment: Alignment.center,
                transform:
                    Matrix4.identity()
                      ..setEntry(3, 2, 0.0015) // perspektif
                      ..rotateY(t * math.pi),
                child:
                    isBack
                        ? Transform(
                          alignment: Alignment.center,
                          // Balikkan semula supaya muka belakang tidak
                          // kelihatan cermin.
                          transform: Matrix4.identity()..rotateY(math.pi),
                          child: back,
                        )
                        : front,
              );
            },
          ),
        ),
      ),
    );
  }

  Future<void> _showQrDialog() async {
    final value = widget.qrValue;
    if (value == null || value.isEmpty) return;
    await showDialog<void>(
      context: context,
      builder:
          (dialogContext) => Dialog(
            backgroundColor: AppColors.white,
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  QrImageView(
                    data: value,
                    version: QrVersions.auto,
                    size: 260,
                    padding: const EdgeInsets.all(Spacing.sm),
                    backgroundColor: AppColors.white,
                    gapless: true,
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(
                    context.l10n.orgCardQrHint,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: Spacing.sm),
                  TextButton(
                    onPressed: () => Navigator.of(dialogContext).pop(),
                    child: Text(context.l10n.close),
                  ),
                ],
              ),
            ),
          ),
    );
  }
}

/// Muka depan kad.
class _CardFront extends StatelessWidget {
  const _CardFront({
    required this.card,
    required this.qrValue,
    required this.onQrTap,
  });

  final MemberCardInfo? card;
  final String? qrValue;
  final VoidCallback onQrTap;

  @override
  Widget build(BuildContext context) {
    final frontPath = card?.card_front_path;
    final hasFront = frontPath != null && frontPath.isNotEmpty;

    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.biggest;
        final scale = size.width / CardLayout.cardW;

        final name = CardLayout.layoutName(card?.name);
        final org = CardLayout.layoutOrganization(
          card?.organization?.name,
          name.bottom,
        );
        final memberNo = CardLayout.layoutMemberNo(card?.member_no, org.boxTop);

        final photoUrl = card?.photo_url;
        final hasPhoto = photoUrl != null && photoUrl.isNotEmpty;
        final hasQr = qrValue != null && qrValue!.isNotEmpty;

        final radius = BorderRadius.all(
          Radius.elliptical(size.width * 0.046, size.height * 0.029),
        );

        // Bayang mesti berada DI LUAR `ClipRRect` — jika tidak ia dipotong.
        return DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: radius,
            boxShadow: const [
              BoxShadow(
                color: AppColors.cardShadow,
                blurRadius: 40,
                offset: Offset(0, 18),
                spreadRadius: -12,
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: radius,
            child: Stack(
              fit: StackFit.expand,
              children: [
                // Latar: imej design organisasi atau gradien fallback.
                if (hasFront)
                  AppImage(
                    frontPath,
                    width: double.infinity,
                    height: double.infinity,
                    fit: BoxFit.cover,
                    borderRadius: BorderRadius.zero,
                  )
                else
                  const DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: AppColors.cardFallbackGradient,
                      ),
                    ),
                  ),

                // Foto ahli (bulatan).
                Positioned(
                  left: (CardLayout.photoCx - CardLayout.photoR) * scale,
                  top: (CardLayout.photoCy - CardLayout.photoR) * scale,
                  width: CardLayout.photoR * 2 * scale,
                  height: CardLayout.photoR * 2 * scale,
                  child: ClipOval(
                    child:
                        hasPhoto
                            ? AppImage(
                              photoUrl,
                              width: CardLayout.photoR * 2 * scale,
                              height: CardLayout.photoR * 2 * scale,
                              fit: BoxFit.cover,
                              borderRadius: BorderRadius.zero,
                            )
                            : Container(
                              color: AppColors.cardPhotoPlaceholder,
                              alignment: Alignment.center,
                              child: Text(
                                CardLayout.initials(card?.name),
                                style: TextStyle(
                                  fontSize: 72 * scale,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.cardOrgPill,
                                ),
                              ),
                            ),
                  ),
                ),

                // Nama (1–2 baris, auto-shrink).
                for (final line in name.lines)
                  Positioned(
                    left: 0,
                    right: 0,
                    top: line.top * scale,
                    child: Text(
                      line.text,
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      softWrap: false,
                      overflow: TextOverflow.visible,
                      style: TextStyle(
                        fontSize: line.fontSize * scale,
                        fontWeight: CardLayout.nameFontWeight,
                        color: AppColors.cardNameInk,
                      ),
                    ),
                  ),

                // Pil organisasi.
                Positioned(
                  left: CardLayout.orgBoxX * scale,
                  top: org.boxTop * scale,
                  width: CardLayout.orgBoxW * scale,
                  height: CardLayout.orgBoxH * scale,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: AppColors.cardOrgPill,
                      borderRadius: BorderRadius.circular(
                        CardLayout.orgBoxRadius * scale,
                      ),
                    ),
                    child: Stack(
                      children: [
                        Positioned(
                          left: 0,
                          right: 0,
                          top: (org.textTop - org.boxTop) * scale,
                          child: Text(
                            org.text,
                            textAlign: TextAlign.center,
                            maxLines: 1,
                            softWrap: false,
                            overflow: TextOverflow.visible,
                            style: TextStyle(
                              fontSize: org.fontSize * scale,
                              fontWeight: CardLayout.orgFontWeight,
                              letterSpacing:
                                  CardLayout.orgLetterSpacing * scale,
                              color: AppColors.white,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // Nombor ahli.
                Positioned(
                  left: 0,
                  right: 0,
                  top: memberNo.top * scale,
                  child: Text(
                    memberNo.text,
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    softWrap: false,
                    overflow: TextOverflow.visible,
                    style: TextStyle(
                      fontSize: memberNo.fontSize * scale,
                      fontWeight: CardLayout.memberNoFontWeight,
                      letterSpacing: CardLayout.memberNoLetterSpacing * scale,
                      color: AppColors.cardMemberNoInk,
                    ),
                  ),
                ),

                // Kod QR (sudut kanan-bawah) — ketik untuk besarkan.
                Positioned(
                  left: CardLayout.qrX * scale,
                  top: CardLayout.qrY * scale,
                  width: CardLayout.qrSize * scale,
                  height: CardLayout.qrSize * scale,
                  child: GestureDetector(
                    onTap: onQrTap,
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppColors.white,
                        borderRadius: BorderRadius.circular(
                          CardLayout.qrRadius * scale,
                        ),
                      ),
                      padding: EdgeInsets.all(CardLayout.qrPadding * scale),
                      child:
                          hasQr
                              ? QrImageView(
                                data: qrValue!,
                                version: QrVersions.auto,
                                size:
                                    (CardLayout.qrSize -
                                        CardLayout.qrPadding * 2) *
                                    scale,
                                padding: EdgeInsets.zero,
                                backgroundColor: AppColors.white,
                                gapless: true,
                              )
                              : Icon(
                                Icons.qr_code_2,
                                size: CardLayout.qrSize * 0.7 * scale,
                                color: AppColors.cardMemberNoInk,
                              ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Muka belakang kad.
class _CardBack extends StatelessWidget {
  const _CardBack({required this.path});

  final String? path;

  @override
  Widget build(BuildContext context) {
    final hasBack = path != null && path!.isNotEmpty;

    return LayoutBuilder(
      builder: (context, constraints) {
        final size = constraints.biggest;
        final radius = BorderRadius.all(
          Radius.elliptical(size.width * 0.046, size.height * 0.029),
        );
        return DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: radius,
            gradient: const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: AppColors.cardBackGradient,
            ),
            boxShadow: const [
              BoxShadow(
                color: AppColors.cardShadow,
                blurRadius: 40,
                offset: Offset(0, 18),
                spreadRadius: -12,
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: radius,
            child:
                hasBack
                    ? AppImage(
                      path,
                      width: double.infinity,
                      height: double.infinity,
                      fit: BoxFit.cover,
                      borderRadius: BorderRadius.zero,
                    )
                    : Center(
                      child: Padding(
                        padding: const EdgeInsets.all(Spacing.xl),
                        child: Text(
                          context.l10n.orgCardBackMissing,
                          textAlign: TextAlign.center,
                          style: Theme.of(
                            context,
                          ).textTheme.bodyMedium?.copyWith(
                            color: AppColors.white.withValues(alpha: .8),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
          ),
        );
      },
    );
  }
}

class _CardSkeleton extends StatelessWidget {
  const _CardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(Spacing.lg),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: const AspectRatio(
              aspectRatio: CardLayout.cardW / CardLayout.cardH,
              child: SkeletonBox(radius: 20),
            ),
          ),
        ),
      ],
    );
  }
}
