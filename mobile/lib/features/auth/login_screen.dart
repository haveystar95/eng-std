import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/providers.dart';

class LoginScreen extends ConsumerWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final isLoading = auth.isLoading;

    ref.listen(authControllerProvider, (_, next) {
      if (next.hasError) {
        AppFeedback.warn();
        ScaffoldMessenger.of(context)
          ..clearSnackBars()
          ..showSnackBar(SnackBar(content: Text('${next.error}')));
      }
    });

    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AmbientBackground(
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Column(
              children: [
                const Spacer(),
                Container(
                  width: 104,
                  height: 104,
                  decoration: BoxDecoration(
                    gradient: AppGradients.brand,
                    borderRadius: BorderRadius.circular(AppRadii.xl),
                    boxShadow: AppShadows.glow(AppColors.primary),
                  ),
                  child: const Icon(Icons.auto_stories_rounded, color: Colors.white, size: 52),
                )
                    .animate(onPlay: (c) => c.repeat(reverse: true))
                    .scale(begin: const Offset(1, 1), end: const Offset(1.06, 1.06), duration: 1800.ms, curve: Curves.easeInOut)
                    .animate()
                    .fadeIn(duration: 500.ms)
                    .scale(begin: const Offset(0.8, 0.8), end: const Offset(1, 1), curve: Curves.easeOutBack),
                const SizedBox(height: AppSpacing.lg),
                Text('Eng Std',
                        style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800))
                    .animate()
                    .fadeIn(delay: 150.ms),
                const SizedBox(height: AppSpacing.sm),
                Text('Твой личный тренажёр английских слов\nс интервальным повторением и ИИ',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary, height: 1.5))
                    .animate()
                    .fadeIn(delay: 250.ms),
                const SizedBox(height: AppSpacing.xl),
                const _FeatureRow().animate().fadeIn(delay: 320.ms).slideY(begin: 0.15, end: 0),
                const Spacer(),
                _GoogleButton(
                  loading: isLoading,
                  onTap: () => ref.read(authControllerProvider.notifier).signIn(),
                ).animate().fadeIn(delay: 400.ms).slideY(begin: 0.2, end: 0),
                const SizedBox(height: AppSpacing.md),
                Text('Входя, ты создаёшь личный профиль обучения',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
                const SizedBox(height: AppSpacing.md),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FeatureRow extends StatelessWidget {
  const _FeatureRow();

  @override
  Widget build(BuildContext context) {
    return const Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _Feature(icon: Icons.style_rounded, label: 'Коллекции'),
        SizedBox(width: 10),
        _Feature(icon: Icons.bolt_rounded, label: 'ИИ-подбор'),
        SizedBox(width: 10),
        _Feature(icon: Icons.repeat_rounded, label: 'Повторение'),
      ],
    );
  }
}

class _Feature extends StatelessWidget {
  const _Feature({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      radius: 18,
      child: Column(
        children: [
          Icon(icon, color: AppColors.primary, size: 22),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

class _GoogleButton extends StatelessWidget {
  const _GoogleButton({required this.loading, required this.onTap});
  final bool loading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: loading ? () {} : onTap,
      child: GlassCard(
        padding: const EdgeInsets.symmetric(vertical: 17),
        child: loading
            ? const Center(
                child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white)),
              )
            : const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _GoogleGlyph(),
                  SizedBox(width: 12),
                  Text('Войти через Google',
                      style: TextStyle(color: AppColors.textPrimary, fontWeight: FontWeight.w700, fontSize: 16)),
                ],
              ),
      ),
    );
  }
}

class _GoogleGlyph extends StatelessWidget {
  const _GoogleGlyph();

  @override
  Widget build(BuildContext context) {
    // Simple multi-color "G" mark without bundling an asset.
    return const Text('G', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColors.primary));
  }
}
