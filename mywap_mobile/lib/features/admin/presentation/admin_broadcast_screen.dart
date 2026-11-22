import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/l10n/l10n.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../auth/application/auth_controller.dart';
import '../application/admin_providers.dart';

/// Skrin Komposisi & Penghantaran Mesej Siaran Pentadbir (`/admin/broadcast`).
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
  String _audience = 'all';
  int? _selectedOrgId;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    final user = ref.read(currentUserProvider);
    if (user != null && !user.isSuperadmin) {
      _audience = 'members';
      _selectedOrgId = user.currentOrganizationId;
    }
  }

  @override
  void dispose() {
    _titleController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    try {
      final user = ref.read(currentUserProvider);
      final orgId = _audience == 'org'
          ? _selectedOrgId
          : (user?.currentOrganizationId);

      final result = await ref.read(adminRepositoryProvider).broadcast(
            title: _titleController.text.trim(),
            message: _messageController.text.trim(),
            audience: _audience,
            organizationId: orgId,
          );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.message.isEmpty
                ? context.l10n.adminBroadcastSuccess
                : result.message,
          ),
          backgroundColor: AppColors.movementDarkGreen,
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.error,
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(currentUserProvider);
    final isSuperadmin = user?.isSuperadmin ?? false;
    final isBranchAdmin = user?.isBranchAdmin ?? false;

    // Bina pilihan audiens berdasarkan peranan
    final audienceItems = <DropdownMenuItem<String>>[];

    if (isSuperadmin) {
      audienceItems.addAll([
        DropdownMenuItem(
          value: 'all',
          child: Text(context.l10n.adminAudienceAllNgo),
        ),
        DropdownMenuItem(
          value: 'org',
          child: Text(context.l10n.adminAudienceSpecificOrg),
        ),
        DropdownMenuItem(
          value: 'usrah',
          child: Text(context.l10n.adminAudienceAllUsrah),
        ),
      ]);
    } else if (isBranchAdmin) {
      audienceItems.addAll([
        DropdownMenuItem(
          value: 'members',
          child: Text(context.l10n.adminAudienceMyBranchMembers),
        ),
        DropdownMenuItem(
          value: 'usrah',
          child: Text(context.l10n.adminAudienceMyBranchUsrah),
        ),
      ]);
    } else {
      // Admin Organisasi
      audienceItems.addAll([
        DropdownMenuItem(
          value: 'members',
          child: Text(
            user?.organizationName != null
                ? context.l10n.adminAudienceOrgMembers(user!.organizationName!)
                : context.l10n.adminAudienceOrgMembersFallback,
          ),
        ),
        DropdownMenuItem(
          value: 'usrah',
          child: Text(context.l10n.adminAudienceOrgUsrah),
        ),
      ]);
    }

    return Scaffold(
      backgroundColor: AppColors.pageBackground,
      appBar: AppBar(
        title: Text(context.l10n.adminBroadcastTitle),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(Spacing.lg),
          children: [
            // Kad Maklumat Skop Pengirim
            Card(
              margin: EdgeInsets.zero,
              color: AppColors.white,
              shape: RoundedRectangleBorder(
                borderRadius: AppRadius.card,
                side: const BorderSide(color: AppColors.divider),
              ),
              child: Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Row(
                  children: [
                    const CircleAvatar(
                      radius: 18,
                      backgroundColor: AppColors.movementSoftGreen,
                      child: Icon(
                        Icons.campaign_rounded,
                        color: AppColors.movementNavy,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: Spacing.md),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.adminRoleTitle ?? context.l10n.adminSystemAdministrator,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                          Text(
                            context.l10n.adminBroadcastHint,
                            style: TextStyle(
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

            const SizedBox(height: Spacing.lg),

            // Tajuk Siaran
            TextFormField(
              controller: _titleController,
              textInputAction: TextInputAction.next,
              maxLength: 60,
              decoration: InputDecoration(
                labelText: context.l10n.adminBroadcastTitleLabel,
                hintText: context.l10n.adminBroadcastTitleHint,
                prefixIcon: const Icon(
                  Icons.title,
                  color: AppColors.textSecondary,
                ),
                filled: true,
                fillColor: AppColors.white,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? context.l10n.adminBroadcastTitleRequired
                  : null,
            ),

            const SizedBox(height: Spacing.lg),

            // Mesej / Kandungan
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
                filled: true,
                fillColor: AppColors.white,
              ),
              validator: (value) => (value == null || value.trim().isEmpty)
                  ? context.l10n.adminBroadcastMessageRequired
                  : null,
            ),

            const SizedBox(height: Spacing.lg),

            // Pilihan Sasaran Audiens
            DropdownButtonFormField<String>(
              value: audienceItems.any((item) => item.value == _audience)
                  ? _audience
                  : audienceItems.firstOrNull?.value,
              decoration: InputDecoration(
                labelText: context.l10n.adminBroadcastAudienceLabel,
                prefixIcon: const Icon(
                  Icons.people_outline,
                  color: AppColors.textSecondary,
                ),
                filled: true,
                fillColor: AppColors.white,
              ),
              items: audienceItems,
              onChanged: (value) {
                if (value != null) setState(() => _audience = value);
              },
            ),

            // Jika Superadmin memilih 'org', tunjukkan Dropdown pilihan Organisasi
            if (isSuperadmin && _audience == 'org') ...[
              const SizedBox(height: Spacing.lg),
              DropdownButtonFormField<int>(
                value: _selectedOrgId,
                decoration: InputDecoration(
                  labelText: context.l10n.adminSelectTargetOrg,
                  prefixIcon: const Icon(
                    Icons.apartment,
                    color: AppColors.textSecondary,
                  ),
                  filled: true,
                  fillColor: AppColors.white,
                ),
                items: const [
                  DropdownMenuItem(value: 1, child: Text('PKPIM')),
                  DropdownMenuItem(value: 2, child: Text('ABIM')),
                  DropdownMenuItem(value: 3, child: Text('WADAH')),
                ],
                validator: (val) =>
                    val == null ? context.l10n.adminSelectOrgError : null,
                onChanged: (value) => setState(() => _selectedOrgId = value),
              ),
            ],

            const SizedBox(height: Spacing.xl),

            // Butang Hantar
            FilledButton.icon(
              onPressed: _submitting ? null : _submit,
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(AppSizes.buttonHeight),
                backgroundColor: AppColors.movementDarkGreen,
              ),
              icon: _submitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.white,
                      ),
                    )
                  : const Icon(Icons.send_rounded),
              label: Text(
                _submitting
                    ? context.l10n.adminSending
                    : context.l10n.adminSendBroadcast,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
