import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:font_awesome_flutter/font_awesome_flutter.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../l10n/l10n.dart';
import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Opens the share sheet with compact direct-share options.
///
/// The link points at the web share-preview route for the item, so WhatsApp
/// and social apps read the server-rendered OG tags (including the item's
/// featured image) when they build the preview card. Items without a public
/// link still share their title and summary instead of blocking the action.
Future<void> shareItem(
  BuildContext context, {
  required String title,
  required String? url,
  String? text,
}) async {
  final link = (url ?? '').trim();
  final heading = title.trim();
  final summary = text?.trim() ?? '';
  final hasLink = link.isNotEmpty;
  final message = _shareMessage(
    heading: heading,
    summary: summary,
    link: link,
  );

  final box = context.findRenderObject() as RenderBox?;
  final origin = box == null ? null : box.localToGlobal(Offset.zero) & box.size;

  await showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(borderRadius: AppRadius.sheet),
    builder:
        (sheetContext) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(
              Spacing.xl,
              Spacing.sm,
              Spacing.xl,
              Spacing.xl,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  sheetContext.l10n.shareSheetTitle,
                  style: Theme.of(
                    sheetContext,
                  ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: Spacing.md),
                Wrap(
                  spacing: Spacing.sm,
                  runSpacing: Spacing.md,
                  children: [
                    _ShareAction(
                      icon: FontAwesomeIcons.whatsapp,
                      color: const Color(0xFF25D366),
                      label: sheetContext.l10n.shareWhatsApp,
                      onTap: () {
                        Navigator.of(sheetContext).pop();
                        _open(
                          context,
                          Uri.parse(
                            'https://wa.me/?text=${Uri.encodeComponent(message)}',
                          ),
                        );
                      },
                    ),
                    if (hasLink)
                      _ShareAction(
                        icon: FontAwesomeIcons.facebookF,
                        color: const Color(0xFF1877F2),
                        label: sheetContext.l10n.shareFacebook,
                        onTap: () {
                          Navigator.of(sheetContext).pop();
                          _open(
                            context,
                            Uri.parse(
                              'https://www.facebook.com/sharer/sharer.php'
                              '?u=${Uri.encodeComponent(link)}',
                            ),
                          );
                        },
                      ),
                    _ShareAction(
                      icon: FontAwesomeIcons.telegram,
                      color: const Color(0xFF229ED9),
                      label: sheetContext.l10n.shareTelegram,
                      onTap: () {
                        Navigator.of(sheetContext).pop();
                        _open(
                          context,
                          Uri.parse('https://t.me/share/url').replace(
                            queryParameters: {
                              if (hasLink) 'url': link,
                              'text': [
                                if (heading.isNotEmpty) heading,
                                if (summary.isNotEmpty) summary,
                              ].join('\n'),
                            },
                          ),
                        );
                      },
                    ),
                    if (hasLink)
                      _ShareAction(
                        icon: FontAwesomeIcons.xTwitter,
                        color: const Color(0xFF111111),
                        label: sheetContext.l10n.shareX,
                        onTap: () {
                          Navigator.of(sheetContext).pop();
                          _open(
                            context,
                            Uri.parse('https://twitter.com/intent/tweet')
                                .replace(
                                  queryParameters: {
                                    'text': [
                                      if (heading.isNotEmpty) heading,
                                      if (summary.isNotEmpty) summary,
                                    ].join('\n'),
                                    if (hasLink) 'url': link,
                                  },
                                ),
                          );
                        },
                      ),
                    _ShareAction(
                      icon: Icons.link_rounded,
                      label:
                          hasLink
                              ? sheetContext.l10n.shareCopyLink
                              : sheetContext.l10n.shareCopyText,
                      onTap: () {
                        Navigator.of(sheetContext).pop();
                        _copy(context, hasLink ? link : message, hasLink);
                      },
                    ),
                    _ShareAction(
                      icon: Icons.more_horiz_rounded,
                      label: sheetContext.l10n.shareMoreOptions,
                      onTap: () {
                        Navigator.of(sheetContext).pop();
                        _shareNatively(context, message, heading, origin);
                      },
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
  );
}

Future<void> _open(BuildContext context, Uri uri) async {
  try {
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened) throw StateError('Could not open $uri');
  } catch (_) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.shareFailed)));
  }
}

String _shareMessage({
  required String heading,
  required String summary,
  required String link,
}) {
  final lines = <String>[
    if (heading.isNotEmpty) heading,
    if (link.isNotEmpty) link,
    if (summary.isNotEmpty) summary,
  ];
  return lines.join('\n');
}

Future<void> _copy(BuildContext context, String value, bool isLink) async {
  await Clipboard.setData(ClipboardData(text: value));
  if (!context.mounted) return;
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(
        isLink ? context.l10n.shareLinkCopied : context.l10n.shareTextCopied,
      ),
    ),
  );
}

Future<void> _shareNatively(
  BuildContext context,
  String message,
  String title,
  Rect? origin,
) async {
  try {
    await SharePlus.instance.share(
      ShareParams(
        text: message,
        title: title,
        subject: title,
        sharePositionOrigin: origin,
      ),
    );
  } catch (_) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.shareFailed)));
  }
}

class _ShareAction extends StatelessWidget {
  const _ShareAction({
    required this.icon,
    required this.label,
    required this.onTap,
    this.color,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 72,
      child: InkWell(
        onTap: onTap,
        borderRadius: AppRadius.md,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: Spacing.sm),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: AppSizes.tapTarget,
                height: AppSizes.tapTarget,
                decoration: BoxDecoration(
                  color:
                      color?.withValues(alpha: .12) ?? AppColors.surfaceMuted,
                  borderRadius: AppRadius.md,
                ),
                child: Icon(
                  icon,
                  color: color ?? AppColors.movementDarkGreen,
                  size: 22,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Compact share action for an AppBar.
class ShareItemIconButton extends StatelessWidget {
  const ShareItemIconButton({
    super.key,
    required this.title,
    required this.url,
    this.text,
  });

  final String title;
  final String? url;
  final String? text;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: context.l10n.shareItem,
      onPressed: () => shareItem(context, title: title, url: url, text: text),
      icon: const Icon(Icons.share_outlined),
    );
  }
}

/// Prominent share action for detail bodies.
class ShareItemButton extends StatelessWidget {
  const ShareItemButton({
    super.key,
    required this.title,
    required this.url,
    this.text,
  });

  final String title;
  final String? url;
  final String? text;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: () => shareItem(context, title: title, url: url, text: text),
      icon: const Icon(Icons.share_outlined, size: 20),
      label: Text(context.l10n.share),
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.movementGreen,
        side: const BorderSide(color: AppColors.divider),
        shape: const RoundedRectangleBorder(borderRadius: AppRadius.md),
        minimumSize: const Size(0, AppSizes.tapTarget),
      ),
    );
  }
}
