import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/admin_providers.dart';

/// Compose and send an announcement (`/admin/broadcast`).
class AdminBroadcastScreen extends ConsumerStatefulWidget {
  const AdminBroadcastScreen({super.key});

  @override
  ConsumerState<AdminBroadcastScreen> createState() =>
      _AdminBroadcastScreenState();
}

class _AdminBroadcastScreenState extends ConsumerState<AdminBroadcastScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _messageController = TextEditingController();
  final _orgController = TextEditingController();
  String _audience = 'all';
  bool _submitting = false;

  @override
  void dispose() {
    _titleController.dispose();
    _messageController.dispose();
    _orgController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final result = await ref
          .read(adminRepositoryProvider)
          .broadcast(
            title: _titleController.text.trim(),
            message: _messageController.text.trim(),
            audience: _audience,
            organizationId:
                _audience == 'org'
                    ? int.tryParse(_orgController.text.trim())
                    : null,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.message.isEmpty
                ? context.l10n.adminBroadcastSuccess
                : result.message,
          ),
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.adminBroadcastTitle)),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(Spacing.lg),
          children: [
            TextFormField(
              controller: _titleController,
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: context.l10n.adminBroadcastTitleLabel,
                hintText: context.l10n.adminBroadcastTitleHint,
                prefixIcon: const Icon(
                  Icons.title,
                  color: AppColors.textSecondary,
                ),
              ),
              validator:
                  (value) =>
                      (value == null || value.trim().isEmpty)
                          ? context.l10n.adminBroadcastTitleRequired
                          : null,
            ),
            const SizedBox(height: Spacing.lg),
            TextFormField(
              controller: _messageController,
              minLines: 4,
              maxLines: 6,
              decoration: InputDecoration(
                labelText: context.l10n.adminBroadcastMessageLabel,
                hintText: context.l10n.adminBroadcastMessageHint,
                alignLabelWithHint: true,
                prefixIcon: const Padding(
                  padding: EdgeInsets.only(bottom: 80),
                  child: Icon(Icons.notes, color: AppColors.textSecondary),
                ),
              ),
              validator:
                  (value) =>
                      (value == null || value.trim().isEmpty)
                          ? context.l10n.adminBroadcastMessageRequired
                          : null,
            ),
            const SizedBox(height: Spacing.lg),
            DropdownButtonFormField<String>(
              value: _audience,
              decoration: InputDecoration(
                labelText: context.l10n.adminBroadcastAudienceLabel,
                prefixIcon: const Icon(
                  Icons.people_outline,
                  color: AppColors.textSecondary,
                ),
              ),
              items: [
                DropdownMenuItem(
                  value: 'all',
                  child: Text(context.l10n.adminAudienceAll),
                ),
                DropdownMenuItem(
                  value: 'members',
                  child: Text(context.l10n.adminAudienceMembers),
                ),
                DropdownMenuItem(
                  value: 'usrah',
                  child: Text(context.l10n.adminAudienceUsrah),
                ),
                DropdownMenuItem(
                  value: 'org',
                  child: Text(context.l10n.adminAudienceOrganization),
                ),
              ],
              onChanged: (value) {
                if (value != null) setState(() => _audience = value);
              },
            ),
            if (_audience == 'org') ...[
              const SizedBox(height: Spacing.lg),
              TextFormField(
                controller: _orgController,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: context.l10n.adminOrganizationIdOptional,
                  prefixIcon: const Icon(
                    Icons.apartment,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
            const SizedBox(height: Spacing.xl),
            FilledButton.icon(
              onPressed: _submitting ? null : _submit,
              icon:
                  _submitting
                      ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: AppColors.white,
                        ),
                      )
                      : const Icon(Icons.send),
              label: Text(
                _submitting
                    ? context.l10n.adminSending
                    : context.l10n.adminSendBroadcast,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
