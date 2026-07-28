import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/languages.dart';
import '../../data/providers.dart';

/// Learning preferences — native + target language, level, daily goal.
/// Glass surfaces, spring taps, haptics + click sound.
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  late String _native;
  late String _target;
  late String _level;
  late int _goal;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final p = ref.read(authControllerProvider).value?.profile;
    _native = p?.nativeLanguage ?? 'ru';
    _target = p?.targetLanguage ?? 'en';
    _level = p?.cefrLevel ?? 'B1';
    _goal = p?.dailyGoal ?? 20;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await ref.read(authControllerProvider.notifier).updateProfile({
        'native_language': _native,
        'target_language': _target,
        'cefr_level': _level,
        'daily_goal': _goal,
      });
      AppFeedback.success();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Настройки сохранены ✨')));
        Navigator.of(context).maybePop();
      }
    } catch (e) {
      AppFeedback.warn();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Ошибка: $e')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(title: const Text('Настройки')),
      body: AmbientBackground(
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.md, AppSpacing.md, 40),
            children: [
              _langCard('Родной язык', _native, (c) => setState(() => _native = c)).animate().fadeIn().slideY(begin: 0.08, end: 0),
              const SizedBox(height: AppSpacing.md),
              _langCard('Изучаю', _target, (c) => setState(() => _target = c)).animate().fadeIn(delay: 60.ms).slideY(begin: 0.08, end: 0),
              const SizedBox(height: AppSpacing.md),
              _levelCard().animate().fadeIn(delay: 120.ms).slideY(begin: 0.08, end: 0),
              const SizedBox(height: AppSpacing.md),
              _goalCard().animate().fadeIn(delay: 180.ms).slideY(begin: 0.08, end: 0),
              const SizedBox(height: AppSpacing.lg),
              GlassButton(label: 'Сохранить', icon: Icons.check_rounded, busy: _saving, onTap: _save)
                  .animate().fadeIn(delay: 240.ms),
            ],
          ),
        ),
      ),
    );
  }

  Widget _sectionTitle(String t) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Text(t, style: const TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700)),
      );

  Widget _langCard(String title, String selected, ValueChanged<String> onPick) {
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle(title),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: kLanguages
                .map((l) => GlassChip(
                      label: l.name,
                      leading: l.flag,
                      selected: l.code == selected,
                      onTap: () => onPick(l.code),
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }

  Widget _levelCard() {
    return GlassCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionTitle('Мой уровень'),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: kCefrLevels
                .map((lvl) => GlassChip(
                      label: lvl,
                      selected: lvl == _level,
                      onTap: () => setState(() => _level = lvl),
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }

  Widget _goalCard() {
    return GlassCard(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Цель в день', style: TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text('$_goal слов в день', style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
              ],
            ),
          ),
          _stepBtn(Icons.remove_rounded, () => setState(() => _goal = (_goal - 5).clamp(5, 100))),
          const SizedBox(width: 14),
          SizedBox(width: 34, child: Text('$_goal', textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textPrimary, fontSize: 20, fontWeight: FontWeight.w800))),
          const SizedBox(width: 14),
          _stepBtn(Icons.add_rounded, () => setState(() => _goal = (_goal + 5).clamp(5, 100))),
        ],
      ),
    );
  }

  Widget _stepBtn(IconData icon, VoidCallback onTap) => SpringTap(
        onTap: onTap,
        child: Container(
          width: 40,
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white.withValues(alpha: 0.08),
            border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
          ),
          child: Icon(icon, color: AppColors.textPrimary, size: 20),
        ),
      );
}
