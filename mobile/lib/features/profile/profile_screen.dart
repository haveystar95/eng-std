import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import 'settings_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: const Text('Профиль'),
        actions: [
          IconButton(
            icon: const Icon(Icons.tune_rounded),
            onPressed: () {
              AppFeedback.select();
              Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SettingsScreen()));
            },
          ),
        ],
      ),
      body: AmbientBackground(
        child: SafeArea(
          child: user == null
              ? const SizedBox.shrink()
              : ListView(
                  padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, 120),
                  children: [
                    _Header(user: user).animate().fadeIn().slideY(begin: 0.08, end: 0),
                    const SizedBox(height: AppSpacing.md),
                    if (user.profile != null)
                      _PrefsCard(profile: user.profile!).animate().fadeIn(delay: 60.ms).slideY(begin: 0.08, end: 0),
                    const SizedBox(height: AppSpacing.md),
                    GlassButton(
                      label: 'Настройки обучения',
                      icon: Icons.tune_rounded,
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const SettingsScreen()),
                      ),
                    ).animate().fadeIn(delay: 120.ms),
                    const SizedBox(height: AppSpacing.md),
                    SpringTap(
                      onTap: () => ref.read(authControllerProvider.notifier).signOut(),
                      child: Container(
                        height: 54,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: AppColors.danger.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(AppRadii.md),
                          border: Border.all(color: AppColors.danger.withValues(alpha: 0.35)),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.logout_rounded, color: AppColors.danger, size: 20),
                            SizedBox(width: 10),
                            Text('Выйти', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.w700, fontSize: 15)),
                          ],
                        ),
                      ),
                    ).animate().fadeIn(delay: 180.ms),
                  ],
                ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.user});
  final AppUser user;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Row(
        children: [
          Container(
            width: 66,
            height: 66,
            decoration: const BoxDecoration(shape: BoxShape.circle, gradient: AppGradients.brand),
            child: user.avatar != null
                ? ClipOval(child: Image.network(user.avatar!, fit: BoxFit.cover))
                : Center(
                    child: Text(user.name.characters.first.toUpperCase(),
                        style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white)),
                  ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user.name,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                if (user.email != null)
                  Text(user.email!, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PrefsCard extends StatelessWidget {
  const _PrefsCard({required this.profile});
  final Profile profile;

  @override
  Widget build(BuildContext context) {
    final native = languageByCode(profile.nativeLanguage);
    final target = languageByCode(profile.targetLanguage);
    return GlassCard(
      child: Column(
        children: [
          _row('Родной язык', '${native.flag}  ${native.name}'),
          _divider(),
          _row('Изучаю', '${target.flag}  ${target.name}'),
          _divider(),
          _row('Уровень', profile.cefrLevel),
          _divider(),
          _row('Цель в день', '${profile.dailyGoal} слов'),
        ],
      ),
    );
  }

  Widget _divider() => Divider(height: 24, color: Colors.white.withValues(alpha: 0.10));

  Widget _row(String label, String value) => Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 14)),
          Text(value, style: const TextStyle(color: AppColors.textPrimary, fontSize: 15, fontWeight: FontWeight.w700)),
        ],
      );
}
