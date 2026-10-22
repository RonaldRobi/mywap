import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../l10n/l10n.dart';

import '../theme/app_theme.dart';

/// Full-screen WebView for hosted payment pages.
///
/// Pops with `true` when the user taps "Saya Sudah Bayar" (caller polls the
/// payment status) or `false` when closed via "Tutup"/"Batal".
class PaymentWebviewScreen extends StatefulWidget {
  const PaymentWebviewScreen({super.key, required this.paymentUrl, this.title});

  final String paymentUrl;
  final String? title;

  @override
  State<PaymentWebviewScreen> createState() => _PaymentWebviewScreenState();
}

class _PaymentWebviewScreenState extends State<PaymentWebviewScreen> {
  late final WebViewController _controller;

  @override
  void initState() {
    super.initState();
    _controller =
        WebViewController()
          ..setJavaScriptMode(JavaScriptMode.unrestricted)
          ..setNavigationDelegate(
            NavigationDelegate(
              onNavigationRequest: (request) {
                if (request.url.startsWith('mywap://')) {
                  Navigator.of(context).pop(true);
                  return NavigationDecision.prevent;
                }
                return NavigationDecision.navigate;
              },
            ),
          )
          ..loadRequest(Uri.parse(widget.paymentUrl));
  }

  void _pop(bool paid) => Navigator.of(context).pop(paid);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title ?? context.l10n.paymentTitle),
        actions: [
          TextButton(
            onPressed: () => _pop(false),
            child: Text(context.l10n.close),
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(child: WebViewWidget(controller: _controller)),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.all(Spacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  FilledButton.icon(
                    onPressed: () => _pop(true),
                    icon: const Icon(Icons.check_circle),
                    label: Text(context.l10n.paymentAlreadyPaid),
                  ),
                  const SizedBox(height: Spacing.sm),
                  OutlinedButton(
                    onPressed: () => _pop(false),
                    child: Text(context.l10n.cancel),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
