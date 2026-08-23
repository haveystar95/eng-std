import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/languages.dart' show kLanguages, kCefrLevels;
import '../../data/providers.dart';

/// First-run setup (кадры 10b–10d): three steps, each already carrying a default so «Далее» is
/// always active. Step 1 target language · step 2 level · step 3 daily goal. On finish it persists
/// the profile (`PUT /profile`) and marks onboarding complete locally. Paper/ink.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  static const _steps = 3;
  int _step = 0;
  bool _saving = false;

  // Native stays the UI/source language (ru); onboarding only asks the target (per the design).
  late final String _native =
      ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
  late String _target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
  late String _level = ref.read(authControllerProvider).value?.profile?.cefrLevel ?? 'B1';
  late int _goal = ref.read(authControllerProvider).value?.profile?.dailyGoal ?? 20;

  void _next() {
    AppHaptics.light();
    if (_step < _steps - 1) {
      setState(() => _step++);
    } else {
      _finish();
    }
  }

  Future<void> _finish() async {
    setState(() => _saving = true);
    try {
      // `onboarded: true` stamps profile.onboarded_at server-side (F1); the refreshed user then
      // carries it, so the gate routes on to the home. No device-local flag anymore.
      await ref.read(authControllerProvider.notifier).updateProfile({
        'native_language': _native,
        'target_language': _target,
        'cefr_level': _level,
        'daily_goal': _goal,
        'onboarded': true,
      });
      AppHaptics.success();
      ref.invalidate(onboardedProvider); // routes on to the home screen
    } catch (e) {
      AppHaptics.warning();
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.screenHWide,
                  14,
                  AppSpacing.screenHWide,
                  0,
                ),
                child: _StepBars(count: _steps, index: _step),
              ),
              Expanded(
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 240),
                  child: KeyedSubtree(
                    key: ValueKey(_step),
                    child: switch (_step) {
                      0 => _LangStep(
                        target: _target,
                        native: _native,
                        onPick: (c) => setState(() => _target = c),
                      ),
                      1 => _LevelStep(level: _level, onPick: (v) => setState(() => _level = v)),
                      _ => _GoalStep(goal: _goal, onPick: (g) => setState(() => _goal = g)),
                    },
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(
                  AppSpacing.screenHWide,
                  8,
                  AppSpacing.screenHWide,
                  MediaQuery.viewPaddingOf(context).bottom + 20,
                ),
                child: PrimaryButton(
                  label: _step < _steps - 1 ? l.onbNext : l.onbStart,
                  enabled: !_saving,
                  onPressed: _next,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StepBars extends StatelessWidget {
  const _StepBars({required this.count, required this.index});
  final int count, index;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < count; i++) ...[
          if (i > 0) const SizedBox(width: 7),
          Container(
            width: 26,
            height: 4,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(2),
              color: i <= index ? AppColors.ink : AppColors.track,
            ),
          ),
        ],
      ],
    );
  }
}

/// Shared step layout: title + subtitle + scrollable content.
class _StepShell extends StatelessWidget {
  const _StepShell({
    required this.title,
    required this.subtitle,
    required this.children,
    this.footer,
  });
  final String title, subtitle;
  final List<Widget> children;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenHWide, 26, AppSpacing.screenHWide, 20),
      children: [
        Text(title, style: AppText.stepTitle),
        const SizedBox(height: 8),
        Text(
          subtitle,
          style: AppText.translation.copyWith(
            fontSize: 13.5,
            height: 1.4,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: 24),
        ...children,
        if (footer != null) ...[const SizedBox(height: 22), footer!],
      ],
    );
  }
}

class _LangStep extends StatelessWidget {
  const _LangStep({required this.target, required this.native, required this.onPick});
  final String target, native;
  final ValueChanged<String> onPick;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final langs = kLanguages.where((lang) => lang.code != native).toList();
    return _StepShell(
      title: l.onbLangTitle,
      subtitle: l.onbLangSubtitle,
      children: [
        for (final lang in langs)
          _SelectRow(
            selected: lang.code == target,
            onTap: () => onPick(lang.code),
            leading: MiniFlag(languageCode: lang.code),
            title: lang.endonym,
          ),
      ],
    );
  }
}

class _LevelStep extends StatelessWidget {
  const _LevelStep({required this.level, required this.onPick});
  final String level;
  final ValueChanged<String> onPick;

  static String _hint(AppLocalizations l, String lvl) => switch (lvl) {
    'A1' => l.cefrHintA1,
    'A2' => l.cefrHintA2,
    'B1' => l.cefrHintB1,
    'B2' => l.cefrHintB2,
    'C1' => l.cefrHintC1,
    _ => l.cefrHintC2,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return _StepShell(
      title: l.onbLevelTitle,
      subtitle: l.onbLevelSubtitle,
      footer: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 4),
          const SizedBox(height: 1, child: ColoredBox(color: AppColors.hairline)),
          const SizedBox(height: 16),
          Text(
            l.onbLevelExample(level),
            style: AppText.translation.copyWith(
              fontSize: 13,
              height: 1.5,
              color: AppColors.secondary,
            ),
          ),
        ],
      ),
      children: [
        GridView.count(
          crossAxisCount: 3,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 1.55,
          children: [
            for (final lvl in kCefrLevels)
              _LevelCell(
                label: lvl,
                hint: _hint(l, lvl),
                selected: lvl == level,
                onTap: () => onPick(lvl),
              ),
          ],
        ),
      ],
    );
  }
}

class _GoalStep extends StatelessWidget {
  const _GoalStep({required this.goal, required this.onPick});
  final int goal;
  final ValueChanged<int> onPick;

  static const _options = [10, 20, 30];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    String subtitle(int g) {
      final mins = g ~/ 2; // 10→5, 20→10, 30→15
      final base = l.onbGoalMinutes(mins);
      return g == 20 ? '$base · ${l.onbGoalRecommended}' : base;
    }

    return _StepShell(
      title: l.onbGoalTitle,
      subtitle: l.onbGoalSubtitle,
      footer: Text(
        l.onbFooterNote,
        style: AppText.translation.copyWith(fontSize: 13, height: 1.5, color: AppColors.secondary),
      ),
      children: [
        for (final g in _options) ...[
          if (g != _options.first) const SizedBox(height: 10),
          _SelectRow(
            selected: g == goal,
            onTap: () => onPick(g),
            title: l.profileGoalValue(g),
            subtitle: subtitle(g),
          ),
        ],
      ],
    );
  }
}

/// A bordered radio row (selected → ink border + ink check), used for language and goal options.
class _SelectRow extends StatelessWidget {
  const _SelectRow({
    required this.selected,
    required this.onTap,
    required this.title,
    this.subtitle,
    this.leading,
  });
  final bool selected;
  final VoidCallback onTap;
  final String title;
  final String? subtitle;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 0),
      child: Material(
        color: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.field),
          side: BorderSide(
            color: selected ? AppColors.ink : AppColors.hairline,
            width: selected ? 1.5 : 1,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                if (leading != null) ...[leading!, const SizedBox(width: 12)],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ink,
                        ),
                      ),
                      if (subtitle != null) ...[
                        const SizedBox(height: 3),
                        Text(
                          subtitle!,
                          style: AppText.transcription.copyWith(
                            fontSize: 12.5,
                            color: AppColors.tertiary,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                _Radio(selected: selected),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Radio extends StatelessWidget {
  const _Radio({required this.selected});
  final bool selected;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 20,
      height: 20,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: selected ? AppColors.ink : null,
        border: selected ? null : Border.all(color: AppColors.dashed, width: 1.5),
      ),
      child: selected ? const Icon(Icons.check, size: 13, color: AppColors.paper) : null,
    );
  }
}

class _LevelCell extends StatelessWidget {
  const _LevelCell({
    required this.label,
    required this.hint,
    required this.selected,
    required this.onTap,
  });
  final String label, hint;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? AppColors.ink : Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.field),
        side: selected ? BorderSide.none : const BorderSide(color: AppColors.hairline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              label,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 17,
                fontWeight: FontWeight.w700,
                color: selected ? AppColors.paper : AppColors.ink,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              hint,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 11,
                color: selected ? AppColors.paper.withValues(alpha: 0.7) : AppColors.tertiary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
