import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';
import 'package:url_launcher/url_launcher.dart';

import '../theme/app_colors.dart';

/// Renders rich HTML content (from the admin Quill editor) as formatted text.
///
/// Content without any HTML markup is rendered with a plain [Text] so that
/// newlines are preserved. This avoids showing raw tags such as `<p>` in the
/// mobile app.
class HtmlContent extends StatelessWidget {
  const HtmlContent(this.html, {super.key, this.style});

  final String? html;
  final TextStyle? style;

  static final _tagPattern = RegExp(r'<[a-zA-Z][^>]*>');

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final raw = html?.trim() ?? '';
    if (raw.isEmpty) return const SizedBox.shrink();

    final base = style ?? theme.textTheme.bodyLarge;

    if (!_tagPattern.hasMatch(raw)) {
      return Text(raw, style: base);
    }

    return Html(
      data: raw,
      onLinkTap: (url, _, __) {
        if (url == null) return;
        final uri = Uri.tryParse(url);
        if (uri != null) {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      },
      style: {
        'body': Style(
          margin: Margins.zero,
          padding: HtmlPaddings.zero,
          fontSize: FontSize(base?.fontSize ?? 15),
          color: base?.color ?? AppColors.textPrimary,
          lineHeight: LineHeight.number(1.5),
          fontFamily: base?.fontFamily,
        ),
        'p': Style(margin: Margins.only(bottom: 12)),
        'a': Style(color: AppColors.movementGreen),
      },
    );
  }
}
