import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../application/admin_providers.dart';

/// Compose and send an announcement (`/admin/broadcast`).
class AdminBroadcastScreen extends ConsumerStatefulWidget {
  const AdminBroadcastScreen({super.key});

  @override
  ConsumerState<AdminBroadcastScreen> createState() => _AdminBroadcastScreenState();
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
      final result = await ref.read(adminRepositoryProvider).broadcast(
            title: _titleController.text.trim(),
            message: _messageController.text.trim(),
            audience: _audience,
            organizationId: _audience == 'org'
                ? int.tryParse(_orgController.text.trim())
                : null,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result.message.isEmpty ? 'Siaran berjaya dihantar.' : result.message,
          ),
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Siaran')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(Spacing.lg),
          children: [
            TextFormField(
              controller: _titleController,
              textInputAction: TextInputAction.next,
              decoration: const InputDecoration(
                labelText: 'Tajuk',
                hintText: 'Contoh: Mesyuarat Agung Tahunan',
                prefixIcon: Icon(Icons.title, color: AppColors.textSecondary),
              ),
              validator: (value) =>
                  (value == null || value.trim().isEmpty) ? 'Sila isi tajuk.' : null,
            ),
            const SizedBox(height: Spacing.lg),
            TextFormField(
              controller: _messageController,
              minLines: 4,
              maxLines: 6,
              decoration: const InputDecoration(
                labelText: 'Mesej',
                hintText: 'Tulis mesej siaran di sini...',
                alignLabelWithHint: true,
                prefixIcon: Padding(
                  padding: EdgeInsets.only(bottom: 80),
                  child: Icon(Icons.notes, color: AppColors.textSecondary),
                ),
              ),
              validator: (value) =>
                  (value == null || value.trim().isEmpty) ? 'Sila isi mesej.' : null,
            ),
            const SizedBox(height: Spacing.lg),
            DropdownButtonFormField<String>(
              value: _audience,
              decoration: const InputDecoration(
                labelText: 'Khalayak',
                prefixIcon: Icon(Icons.people_outline, color: AppColors.textSecondary),
              ),
              items: const [
                DropdownMenuItem(value: 'all', child: Text('Semua')),
                DropdownMenuItem(value: 'members', child: Text('Ahli')),
                DropdownMenuItem(value: 'usrah', child: Text('Usrah')),
                DropdownMenuItem(value: 'org', child: Text('Organisasi')),
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
                decoration: const InputDecoration(
                  labelText: 'ID Organisasi (pilihan)',
                  prefixIcon: Icon(Icons.apartment, color: AppColors.textSecondary),
                ),
              ),
            ],
            const SizedBox(height: Spacing.xl),
            FilledButton.icon(
              onPressed: _submitting ? null : _submit,
              icon: _submitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.white),
                    )
                  : const Icon(Icons.send),
              label: Text(_submitting ? 'Menghantar...' : 'Hantar Siaran'),
            ),
          ],
        ),
      ),
    );
  }
}
