import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../shared/theme/app_colors.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/app_back_button.dart';
import '../application/profile_providers.dart';

/// Tukar kata laluan akaun (`POST /profile/password`).
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() =>
      _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _obscure = true;
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref.read(profileRepositoryProvider).changePassword(
        currentPassword: _currentController.text.trim(),
        newPassword: _newController.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Kata laluan anda telah dikemas kini.')),
      );
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'Ralat tidak dijangka. Sila cuba lagi.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: const Text('Tukar Kata Laluan'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(Spacing.lg),
        children: [
          Text(
            'Kemas kini kata laluan akaun myWAP anda.',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: Spacing.xl),
          Form(
            key: _formKey,
            child: Column(
              children: [
                TextFormField(
                  controller: _currentController,
                  obscureText: _obscure,
                  decoration: const InputDecoration(
                    labelText: 'Kata Laluan Semasa *',
                    prefixIcon: Icon(Icons.lock_outline),
                  ),
                  validator: (value) =>
                      (value == null || value.trim().isEmpty)
                          ? 'Sila masukkan kata laluan semasa.'
                          : null,
                ),
                const SizedBox(height: Spacing.md),
                TextFormField(
                  controller: _newController,
                  obscureText: _obscure,
                  decoration: const InputDecoration(
                    labelText: 'Kata Laluan Baharu *',
                    prefixIcon: Icon(Icons.password_outlined),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Sila masukkan kata laluan baharu.';
                    }
                    if (value.length < 8) {
                      return 'Minimum 8 aksara.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: Spacing.md),
                TextFormField(
                  controller: _confirmController,
                  obscureText: _obscure,
                  decoration: const InputDecoration(
                    labelText: 'Sahkan Kata Laluan Baharu *',
                    prefixIcon: Icon(Icons.verified_user_outlined),
                  ),
                  validator: (value) =>
                      value != _newController.text
                          ? 'Kata laluan tidak sepadan.'
                          : null,
                ),
                const SizedBox(height: Spacing.sm),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton.icon(
                    onPressed: () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure ? Icons.visibility_off : Icons.visibility,
                      size: 18,
                    ),
                    label: Text(_obscure ? 'Tunjuk' : 'Sembunyi'),
                  ),
                ),
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: Spacing.sm),
            Text(
              _error!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.error),
            ),
          ],
          const SizedBox(height: Spacing.xl),
          FilledButton.icon(
            onPressed: _saving ? null : _submit,
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(48),
            ),
            icon:
                _saving
                    ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.white,
                      ),
                    )
                    : const Icon(Icons.check),
            label: Text(_saving ? 'Menyimpan...' : 'Simpan Kata Laluan'),
          ),
        ],
      ),
    );
  }
}
