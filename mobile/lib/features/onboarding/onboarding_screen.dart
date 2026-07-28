import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/providers.dart';

/// First-run setup: pick languages, level and a daily goal. Reuses the glass
/// pickers from Settings, laid out as a swipeable 3-step flow. On finish it
/// persists the profile (`PUT /profile`) and marks onboarding complete locally.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _pager = PageController();
  int _page = 0;
  bool _saving = false;

  late String _native = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
  late String _target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
  late String _level = ref.read(authControllerProvider).value?.profile?.cefrLevel ?? 'B1';
  late int _goal = ref.read(authControllerProvider).value?.profile?.dailyGoal ?? 20;

  static const _steps = 3;

  @override
  void dispose() {
    _pager.dispose();
    super.dispose();
  }

  void _next() {
    AppFeedback.select();
    if (_page < _steps - 1) {
      _pager.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
    } else {
      _finish();
    }
  }

  Future<void> _finish() async {
    setState(() => _saving = true);
    try {
      await ref.read(authControllerProvider.notifier).updateProfile({
        'native_language': _native,
        'target_language': _target,
        'cefr_level': _level,
        'daily_goal': _goal,
      });
      await ref.read(tokenStoreProvider).setOnboarded();
      AppFeedback.success();
      ref.invalidate(onboardedProvider); // routes on to the home screen
    } catch (e) {
      AppFeedback.warn();
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ошибка: $e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AmbientBackground(
        child: SafeArea(
          child: Column(
            children: [
              const SizedBox(height: AppSpacing.md),
              _Dots(count: _steps, index: _page),
              Expanded(
                child: PageView(
                  controller: _pager,
                  onPageChanged: (i) => setState(() => _page = i),
                  children: [
                    _LanguagesStep(
                      native: _native,
                      target: _target,
                      onNative: (c) => setState(() => _native = c),
                      onTarget: (c) => setState(() => _target = c),
                    ),
                    _LevelStep(level: _level, onPick: (l) => setState(() => _level = l)),
                    _GoalStep(goal: _goal, onChange: (g) => setState(() => _goal = g)),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.md),
                child: GlassButton(
                  label: _page < _steps - 1 ? 'Далее' : 'Начать 🚀',
                  icon: _page < _steps - 1 ? Icons.arrow_forward_rounded : null,
                  busy: _saving,
                  onTap: _next,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Dots extends StatelessWidget {
  const _Dots({required this.count, required this.index});
  final int count;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        for (var i = 0; i < count; i++)
          AnimatedContainer(
            duration: const Duration(milliseconds: 260),
            curve: Curves.easeOut,
            margin: const EdgeInsets.symmetric(horizontal: 4),
            width: i == index ? 26 : 8,
            height: 8,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadii.pill),
              gradient: i == index ? AppGradients.brand : null,
              color: i == index ? null : Colors.white.withValues(alpha: 0.16),
            ),
          ),
      ],
    );
  }
}

/// Shared step scaffold: big emoji, title, subtitle, then content.
class _StepShell extends StatelessWidget {
  const _StepShell({required this.emoji, required this.title, required this.subtitle, required this.child});
  final String emoji;
  final String title;
  final String subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.lg),
      children: [
        Text(emoji, style: const TextStyle(fontSize: 52)).animate().scale(duration: 400.ms, curve: Curves.easeOutBack),
        const SizedBox(height: AppSpacing.md),
        Text(title, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800))
            .animate()
            .fadeIn(delay: 80.ms)
            .slideY(begin: 0.1, end: 0),
        const SizedBox(height: 6),
        Text(subtitle, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary, height: 1.4))
            .animate()
            .fadeIn(delay: 140.ms),
        const SizedBox(height: AppSpacing.lg),
        child.animate().fadeIn(delay: 200.ms).slideY(begin: 0.08, end: 0),
      ],
    );
  }
}

class _LanguagesStep extends StatelessWidget {
  const _LanguagesStep({required this.native, required this.target, required this.onNative, required this.onTarget});
  final String native, target;
  final ValueChanged<String> onNative, onTarget;

  @override
  Widget build(BuildContext context) {
    return _StepShell(
      emoji: '👋',
      title: 'Добро пожаловать!',
      subtitle: 'Выбери свой родной язык и язык, который изучаешь',
      child: Column(
        children: [
          _card(context, 'Родной язык', native, onNative),
          const SizedBox(height: AppSpacing.md),
          _card(context, 'Изучаю', target, onTarget),
        ],
      ),
    );
  }

  Widget _card(BuildContext context, String title, String selected, ValueChanged<String> onPick) {
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: kLanguages
                .map((l) => GlassChip(label: l.name, leading: l.flag, selected: l.code == selected, onTap: () => onPick(l.code)))
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _LevelStep extends StatelessWidget {
  const _LevelStep({required this.level, required this.onPick});
  final String level;
  final ValueChanged<String> onPick;

  static const _hints = {
    'A1': 'Начинающий — базовые слова и фразы',
    'A2': 'Элементарный — простое бытовое общение',
    'B1': 'Средний — уверенно на знакомые темы',
    'B2': 'Выше среднего — свободнее и точнее',
    'C1': 'Продвинутый — тонкости и нюансы',
    'C2': 'В совершенстве — как носитель',
  };

  @override
  Widget build(BuildContext context) {
    return _StepShell(
      emoji: '📈',
      title: 'Твой уровень',
      subtitle: 'Это поможет подбирать слова по силам',
      child: GlassCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: kCefrLevels.map((l) => GlassChip(label: l, selected: l == level, onTap: () => onPick(l))).toList(),
            ),
            const SizedBox(height: AppSpacing.md),
            Text(_hints[level] ?? '',
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 14, height: 1.4)),
          ],
        ),
      ),
    );
  }
}

class _GoalStep extends StatelessWidget {
  const _GoalStep({required this.goal, required this.onChange});
  final int goal;
  final ValueChanged<int> onChange;

  @override
  Widget build(BuildContext context) {
    return _StepShell(
      emoji: '🎯',
      title: 'Цель на день',
      subtitle: 'Сколько слов повторять каждый день',
      child: GlassCard(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          children: [
            ShaderMask(
              shaderCallback: (r) => AppGradients.brand.createShader(r),
              child: Text('$goal',
                  style: const TextStyle(color: Colors.white, fontSize: 56, fontWeight: FontWeight.w900, height: 1)),
            ),
            const Text('слов в день', style: TextStyle(color: AppColors.textSecondary, fontSize: 14)),
            const SizedBox(height: AppSpacing.lg),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _stepBtn(Icons.remove_rounded, () => onChange((goal - 5).clamp(5, 100))),
                const SizedBox(width: AppSpacing.lg),
                _stepBtn(Icons.add_rounded, () => onChange((goal + 5).clamp(5, 100))),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _stepBtn(IconData icon, VoidCallback onTap) => SpringTap(
        onTap: onTap,
        child: Container(
          width: 52,
          height: 52,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white.withValues(alpha: 0.08),
            border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
          ),
          child: Icon(icon, color: AppColors.textPrimary, size: 24),
        ),
      );
}
