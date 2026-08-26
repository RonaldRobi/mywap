import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/loading_overlay.dart';
import '../../auth/application/auth_controller.dart';
import '../application/infaq_providers.dart';
import '../data/models/infaq.dart';

/// Opens the donate bottom sheet. Resolves with the outcome of the submit, or
/// `null` if the user cancelled.
Future<InfaqDonateResult?> showInfaqDonateSheet(
  BuildContext context, {
  required InfaqInfo infaq,
}) {
  return showModalBottomSheet<InfaqDonateResult>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.background,
    useSafeArea: true,
    builder: (_) => _InfaqDonateSheet(infaq: infaq),
  );
}

class _InfaqDonateSheet extends ConsumerStatefulWidget {
  const _InfaqDonateSheet({required this.infaq});

  final InfaqInfo infaq;

  @override
  ConsumerState<_InfaqDonateSheet> createState() => _InfaqDonateSheetState();
}

class _InfaqDonateSheetState extends ConsumerState<_InfaqDonateSheet> {
  static const _presets = <int>[10, 25, 50, 100];

  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amount;
  late final TextEditingController _name;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _message;
  bool _anonymous = false;
  bool _recurring = false;
  String _frequency = 'monthly';
  bool _submitting = false;
  String? _submitError;

  bool get _canRecur => widget.infaq.allowRecurring;

  @override
  void initState() {
    super.initState();
    final user = ref.read(currentUserProvider);
    _amount = TextEditingController();
    _name = TextEditingController(text: user?.name ?? '');
    _phone = TextEditingController(text: user?.phone ?? '');
    _email = TextEditingController(text: user?.email ?? '');
    _message = TextEditingController();
  }

  @override
  void dispose() {
    _amount.dispose();
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _message.dispose();
    super.dispose();
  }

  void _setPreset(int value) {
    setState(() => _amount.text = '$value');
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _submitError = null;
    });
    try {
      final result = await ref.read(infaqRepositoryProvider).donate(
            widget.infaq.slug ?? '',
            amount: double.parse(_amount.text.trim()),
            donorName: _name.text.trim(),
            donorPhone: _phone.text.trim(),
            donorEmail: _email.text.trim(),
            prayerMessage: _message.text.trim().isEmpty
                ? null
                : _message.text.trim(),
            isAnonymous: _anonymous,
            isRecurring: _recurring && _canRecur,
            frequency: _recurring && _canRecur ? _frequency : null,
          );
      if (!mounted) return;
      Navigator.of(context).pop(result);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submitError = e.message;
        _submitting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _submitError = 'Ralat tidak dijangka. Sila cuba lagi.';
        _submitting = false;
      });
    }
  }

  String? _validateAmount(String? value) {
    final v = value?.trim() ?? '';
    if (v.isEmpty) return 'Sila masukkan jumlah sumbangan.';
    final amount = double.tryParse(v);
    if (amount == null || amount < 1) {
      return 'Amaun minimum ialah RM1.';
    }
    if (amount > 99999) return 'Amaun maksimum ialah RM99,999.';
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(
        left: Spacing.lg,
        right: Spacing.lg,
        top: Spacing.lg,
        bottom: MediaQuery.viewInsetsOf(context).bottom + Spacing.xl,
      ),
      child: SingleChildScrollView(
        child: Stack(
          children: [
            Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: AppColors.divider,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: Spacing.lg),
                  Text(
                    widget.infaq.title ?? 'Sumbangan Infaq',
                    style: theme.textTheme.titleLarge,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (widget.infaq.targetAmount != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'Sasaran ${Formatters.currency(widget.infaq.targetAmount)}',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  const SizedBox(height: Spacing.xl),
                  _Labeled('Jumlah Sumbangan *'),
                  const SizedBox(height: Spacing.sm),
                  Wrap(
                    spacing: Spacing.sm,
                    runSpacing: Spacing.sm,
                    children: [
                      for (final preset in _presets)
                        ChoiceChip(
                          label: Text('RM$preset'),
                          selected: _amount.text == '$preset',
                          onSelected: (_) => _setPreset(preset),
                        ),
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _amount,
                    autovalidateMode: AutovalidateMode.onUserInteraction,
                    validator: _validateAmount,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'^\d*\.?\d{0,2}')),
                    ],
                    decoration: const InputDecoration(
                      labelText: 'Amaun (RM)',
                      prefixIcon: Icon(Icons.payments_outlined),
                    ),
                  ),
                  const SizedBox(height: Spacing.lg),
                  _Labeled('Maklumat Penyumbang'),
                  const SizedBox(height: Spacing.sm),
                  TextFormField(
                    controller: _name,
                    textInputAction: TextInputAction.next,
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'Sila masukkan nama anda.' : null,
                    decoration: const InputDecoration(
                      labelText: 'Nama',
                      prefixIcon: Icon(Icons.person_outline),
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _phone,
                    textInputAction: TextInputAction.next,
                    keyboardType: TextInputType.phone,
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'Sila masukkan nombor telefon.' : null,
                    decoration: const InputDecoration(
                      labelText: 'No. Telefon',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextFormField(
                    controller: _email,
                    textInputAction: TextInputAction.next,
                    keyboardType: TextInputType.emailAddress,
                    validator: (v) {
                      final value = v?.trim() ?? '';
                      if (value.isEmpty) return 'Sila masukkan e-mel anda.';
                      if (!value.contains('@')) return 'E-mel tidak sah.';
                      return null;
                    },
                    decoration: const InputDecoration(
                      labelText: 'E-mel',
                      prefixIcon: Icon(Icons.mail_outline),
                    ),
                  ),
                  const SizedBox(height: Spacing.sm),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Sumbang secara tanpa nama'),
                    subtitle: const Text('Nama anda akan dipaparkan sebagai "Hamba Allah".'),
                    value: _anonymous,
                    onChanged: (v) => setState(() => _anonymous = v),
                  ),
                  const SizedBox(height: Spacing.sm),
                  _Labeled('Doa & Pesanan (pilihan)'),
                  const SizedBox(height: Spacing.sm),
                  TextFormField(
                    controller: _message,
                    minLines: 2,
                    maxLines: 3,
                    maxLength: 400,
                    decoration: const InputDecoration(
                      hintText: 'Tulis pesanan ringkas...',
                      alignLabelWithHint: true,
                    ),
                  ),
                  if (_canRecur) ...[
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Sumbangan berkala'),
                      subtitle: const Text('Sumbang secara bulanan atau mingguan.'),
                      value: _recurring,
                      onChanged: (v) => setState(() => _recurring = v),
                    ),
                    if (_recurring) ...[
                      const SizedBox(height: Spacing.sm),
                      Row(
                        children: [
                          ChoiceChip(
                            label: const Text('Bulanan'),
                            selected: _frequency == 'monthly',
                            onSelected: (_) => setState(() => _frequency = 'monthly'),
                          ),
                          const SizedBox(width: Spacing.sm),
                          ChoiceChip(
                            label: const Text('Mingguan'),
                            selected: _frequency == 'weekly',
                            onSelected: (_) => setState(() => _frequency = 'weekly'),
                          ),
                        ],
                      ),
                      const SizedBox(height: Spacing.sm),
                    ],
                  ],
                  if (_submitError != null) ...[
                    const SizedBox(height: Spacing.md),
                    Text(
                      _submitError!,
                      style: const TextStyle(color: AppColors.error),
                    ),
                  ],
                  const SizedBox(height: Spacing.lg),
                  FilledButton.icon(
                    onPressed: _submitting ? null : _submit,
                    icon: const Icon(Icons.volunteer_activism_outlined),
                    label: const Text('Sahkan Sumbangan'),
                  ),
                ],
              ),
            ),
            if (_submitting)
              Positioned.fill(
                child: LoadingOverlay(
                  message: 'Memproses sumbangan...',
                  transparent: true,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _Labeled extends StatelessWidget {
  const _Labeled(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(text, style: Theme.of(context).textTheme.titleSmall);
  }
}

/// Full-screen success state shown after a donation is submitted.
///
/// - [donation] is set for direct-success responses (shows reference).
/// - Without [donation] the payment is still processing (redirect flow).
class InfaqDonationSuccessScreen extends StatelessWidget {
  const InfaqDonationSuccessScreen({super.key, this.donation});

  final InfaqDonation? donation;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final donation = this.donation;
    final pending = donation == null;

    return Scaffold(
      appBar: AppBar(title: const Text('Status Sumbangan')),
      body: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Icon(Icons.check_circle, size: 72, color: AppColors.movementGreen),
            const SizedBox(height: Spacing.lg),
            Text(
              'Terima kasih!',
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall,
            ),
            const SizedBox(height: Spacing.sm),
            Text(
              pending
                  ? 'Sumbangan anda sedang diproses. Kami akan maklumkan sebaik sahaja pembayaran disahkan.'
                  : 'Sumbangan anda telah diterima. Semoga Allah membalas kebaikan anda.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
            if (donation != null) ...[
              const SizedBox(height: Spacing.xl),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.lg),
                  child: Column(
                    children: [
                      _SuccessRow(label: 'Rujukan', value: donation.reference ?? '-'),
                      const Divider(height: Spacing.xl),
                      _SuccessRow(label: 'Amaun', value: Formatters.currency(donation.amount)),
                      const Divider(height: Spacing.xl),
                      _SuccessRow(label: 'Status', value: _statusLabel(donation.status)),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: Spacing.xl),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Selesai'),
            ),
          ],
        ),
      ),
    );
  }

  static String _statusLabel(String? status) {
    return switch (status) {
      'confirmed' => 'Disahkan',
      'pending' => 'Menunggu pembayaran',
      _ => status ?? '-',
    };
  }
}

class _SuccessRow extends StatelessWidget {
  const _SuccessRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.textSecondary,
                ),
          ),
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
        ),
      ],
    );
  }
}
