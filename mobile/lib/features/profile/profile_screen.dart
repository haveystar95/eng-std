import 'package:flutter/cupertino.dart' show CupertinoPicker, CupertinoPickerDefaultSelectionOverlay;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/languages.dart' show kLanguages, kCefrLevels, languageByCode;
import '../../data/app_settings.dart';
import '../../data/locale_controller.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// Профиль (кадры 11a / 13a). Sections: обучение · приложение · подписка · аккаунт. Reads local
/// where it can (settings, stats); the learning rows edit the server profile. Paper/ink.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final user = ref.watch(authControllerProvider).value;
    final settings = ref.watch(appSettingsProvider).value ?? AppSettings.defaults;
    final uiLang = ref.watch(localeControllerProvider).value ?? UiLanguageOption.system;

    if (user == null) return const SizedBox.shrink();
    final profile = user.profile;

    final bottomInset = AppTabBarMetrics.height +
        AppTabBarMetrics.bottomInset +
        MediaQuery.viewPaddingOf(context).bottom +
        AppSpacing.s8;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: SafeArea(
        bottom: false,
        child: ListView(
          padding: EdgeInsets.fromLTRB(AppSpacing.screenH, AppSpacing.s8, AppSpacing.screenH, bottomInset),
          children: [
            Text(l.profileTitle, style: AppText.screenTitle),
            const SizedBox(height: 18),
            _Header(user: user),

            if (profile != null) ...[
              _SectionLabel(l.profileSectionLearning),
              _NavRow(label: l.profileRowLevel, value: profile.cefrLevel, onTap: () => _editLevel(context, ref, profile.cefrLevel)),
              _NavRow(
                  label: l.profileRowGoal,
                  value: l.profileGoalValue(profile.dailyGoal),
                  onTap: () => _editGoal(context, ref, profile.dailyGoal)),
              _NavRow(
                  label: l.profileRowTargetLang,
                  value: languageByCode(profile.targetLanguage).name,
                  onTap: () => _editTargetLang(context, ref, profile.targetLanguage),
                  last: true),
            ],

            _SectionLabel(l.profileSectionApp),
            _NavRow(
                label: l.profileRowUiLang,
                value: _uiLangName(l, uiLang),
                onTap: () => _editUiLang(context, ref, uiLang)),
            _SwitchRow(
              label: l.profileRowReminders,
              hint: l.profileRemindersHint,
              value: settings.remindersEnabled,
              onChanged: (v) => ref.read(appSettingsProvider.notifier).setRemindersEnabled(v),
            ),
            // «Время» appears only while reminders are on (design 13a — the row slides in/out).
            AnimatedSize(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOut,
              child: settings.remindersEnabled
                  ? _NavRow(
                      label: l.profileRowReminderTime,
                      value: settings.reminderTime,
                      onTap: () => _editReminderTime(context, ref, settings.reminderTime))
                  : const SizedBox(width: double.infinity),
            ),
            _SwitchRow(
              label: l.profileRowAutoPronounce,
              hint: l.profileAutoPronounceHint,
              value: settings.autoPronounce,
              onChanged: (v) => ref.read(appSettingsProvider.notifier).setAutoPronounce(v),
              last: true,
            ),

            _SectionLabel(l.profileSectionSubscription),
            _InfoRow(title: l.profileFreeTier, hint: l.profileFreeTierHint, trailing: l.profileSoon, last: true),

            _SectionLabel(l.profileSectionAccount),
            _LinkRow(label: l.profileSignOut, onTap: () => ref.read(authControllerProvider.notifier).signOut()),
            _LinkRow(
              label: l.profileDeleteAccount,
              destructive: true,
              last: true,
              onTap: () => _confirmDelete(context, ref),
            ),
          ],
        ),
      ),
    );
  }

  String _uiLangName(AppLocalizations l, UiLanguageOption o) => switch (o) {
        UiLanguageOption.system => l.uiLangSystem,
        UiLanguageOption.russian => l.uiLangRussian,
        UiLanguageOption.english => l.uiLangEnglish,
      };

  Future<void> _saveProfile(BuildContext context, WidgetRef ref, Map<String, dynamic> changes) async {
    try {
      await ref.read(authControllerProvider.notifier).updateProfile(changes);
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _editLevel(BuildContext context, WidgetRef ref, String current) async {
    final l = AppLocalizations.of(context);
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _GridSheet(title: l.profileLevelSheet, options: kCefrLevels, current: current),
    );
    if (chosen != null && chosen != current && context.mounted) {
      await _saveProfile(context, ref, {'cefr_level': chosen});
    }
  }

  Future<void> _editGoal(BuildContext context, WidgetRef ref, int current) async {
    final l = AppLocalizations.of(context);
    final chosen = await showAppBottomSheet<int>(
      context: context,
      builder: (_) => _GoalSheet(title: l.profileGoalSheet, current: current),
    );
    if (chosen != null && chosen != current && context.mounted) {
      await _saveProfile(context, ref, {'daily_goal': chosen});
    }
  }

  Future<void> _editTargetLang(BuildContext context, WidgetRef ref, String current) async {
    final l = AppLocalizations.of(context);
    final native = ref.read(authControllerProvider).value?.profile?.nativeLanguage ?? 'ru';
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _LanguageSheet(title: l.profileRowTargetLang, current: current, exclude: native),
    );
    if (chosen != null && chosen != current && context.mounted) {
      await _saveProfile(context, ref, {'target_language': chosen});
    }
  }

  Future<void> _editUiLang(BuildContext context, WidgetRef ref, UiLanguageOption current) async {
    final chosen = await showAppBottomSheet<UiLanguageOption>(
      context: context,
      builder: (_) => _UiLangSheet(current: current),
    );
    if (chosen != null && chosen != current) {
      await ref.read(localeControllerProvider.notifier).setOption(chosen);
    }
  }

  Future<void> _editReminderTime(BuildContext context, WidgetRef ref, String current) async {
    final chosen = await showAppBottomSheet<String>(
      context: context,
      builder: (_) => _TimeSheet(current: current),
    );
    if (chosen != null) {
      await ref.read(appSettingsProvider.notifier).setReminderTime(chosen);
    }
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref) async {
    final l = AppLocalizations.of(context);
    final stats = ref.read(statsProvider).value;
    final words = l.deleteAccountWords(stats?.totalWords ?? 0);
    final streak = l.deleteAccountStreak(stats?.streakDays ?? 0);
    final ok = await showCenterAlert(
      context: context,
      title: l.deleteAccountTitle,
      message: l.deleteAccountBody(words, streak),
      confirmLabel: l.deleteAccountConfirm,
      cancelLabel: l.commonCancel,
    );
    if (ok == true) {
      try {
        await ref.read(authControllerProvider.notifier).deleteAccount();
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
        }
      }
    }
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.user});
  final AppUser user;

  @override
  Widget build(BuildContext context) {
    final initials = user.name.trim().isEmpty
        ? '?'
        : user.name.trim().split(RegExp(r'\s+')).take(2).map((w) => w.characters.first.toUpperCase()).join();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            alignment: Alignment.center,
            decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
            child: user.avatar != null
                ? ClipOval(child: Image.network(user.avatar!, width: 52, height: 52, fit: BoxFit.cover))
                : Text(initials,
                    style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.paper)),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(user.name,
                    style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.ink)),
                if (user.email != null) ...[
                  const SizedBox(height: 3),
                  Text(user.email!, style: AppText.transcription.copyWith(fontSize: 12.5)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 20, bottom: 4),
        child: Text(text.toUpperCase(), style: AppText.sectionLabel.copyWith(color: AppColors.tertiary)),
      );
}

/// A row with a value + chevron that opens an editor.
class _NavRow extends StatelessWidget {
  const _NavRow({required this.label, required this.value, required this.onTap, this.last = false});
  final String label, value;
  final VoidCallback onTap;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      last: last,
      onTap: onTap,
      child: Row(
        children: [
          Expanded(child: Text(label, style: _labelStyle)),
          Text(value, style: AppText.translation.copyWith(fontSize: 15, color: AppColors.secondary)),
          const SizedBox(width: 9),
          const Icon(Icons.chevron_right, size: 18, color: AppColors.tertiary),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, this.hint, required this.value, required this.onChanged, this.last = false});
  final String label;
  final String? hint;
  final bool value;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      last: last,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: _labelStyle),
                if (hint != null) ...[
                  const SizedBox(height: 3),
                  Text(hint!, style: AppText.transcription.copyWith(fontSize: 12, color: AppColors.tertiary)),
                ],
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: (v) {
              AppHaptics.light();
              onChanged(v);
            },
            activeTrackColor: AppColors.ink,
            activeThumbColor: AppColors.paper,
          ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.title, this.hint, this.trailing, this.last = false});
  final String title;
  final String? hint;
  final String? trailing;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      last: last,
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: _labelStyle),
                if (hint != null) ...[
                  const SizedBox(height: 3),
                  Text(hint!, style: AppText.transcription.copyWith(fontSize: 12, color: AppColors.tertiary)),
                ],
              ],
            ),
          ),
          if (trailing != null)
            Text(trailing!, style: AppText.translation.copyWith(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.tertiary)),
        ],
      ),
    );
  }
}

class _LinkRow extends StatelessWidget {
  const _LinkRow({required this.label, required this.onTap, this.destructive = false, this.last = false});
  final String label;
  final VoidCallback onTap;
  final bool destructive, last;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      last: last,
      onTap: onTap,
      child: Text(label,
          style: TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 15.5,
              fontWeight: FontWeight.w600,
              color: destructive ? AppColors.destructiveText : AppColors.ink)),
    );
  }
}

const _labelStyle = TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, color: AppColors.ink);

/// A settings row: 12px vertical padding + a hairline underline unless it's the section's last.
class _RowShell extends StatelessWidget {
  const _RowShell({required this.child, this.onTap, this.last = false});
  final Widget child;
  final VoidCallback? onTap;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final row = Container(
      decoration: last
          ? null
          : const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.dividerFaint))),
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: child,
    );
    if (onTap == null) return row;
    return InkWell(
      onTap: () {
        AppHaptics.light();
        onTap!();
      },
      child: row,
    );
  }
}

// ── Editor sheets ──────────────────────────────────────────────────────────

/// A wrap-grid chooser (level).
class _GridSheet extends StatelessWidget {
  const _GridSheet({required this.title, required this.options, required this.current});
  final String title;
  final List<String> options;
  final String current;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final o in options)
              SizedBox(
                width: 88,
                child: _PillOption(label: o, selected: o == current, onTap: () => Navigator.of(context).pop(o)),
              ),
          ],
        ),
      ],
    );
  }
}

class _GoalSheet extends StatelessWidget {
  const _GoalSheet({required this.title, required this.current});
  final String title;
  final int current;

  static const _options = [10, 20, 30];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 12),
        for (final g in _options) ...[
          if (g != _options.first) const SizedBox(height: 10),
          _PillOption(label: l.profileGoalValue(g), selected: g == current, onTap: () => Navigator.of(context).pop(g)),
        ],
      ],
    );
  }
}

class _LanguageSheet extends StatelessWidget {
  const _LanguageSheet({required this.title, required this.current, required this.exclude});
  final String title, current, exclude;

  @override
  Widget build(BuildContext context) {
    final langs = kLanguages.where((lang) => lang.code != exclude).toList();
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 8),
        Flexible(
          child: ListView.builder(
            shrinkWrap: true,
            itemCount: langs.length,
            itemBuilder: (context, i) {
              final lang = langs[i];
              return InkWell(
                onTap: () => Navigator.of(context).pop(lang.code),
                borderRadius: BorderRadius.circular(AppRadii.field),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
                  child: Row(
                    children: [
                      MiniFlag(languageCode: lang.code),
                      const SizedBox(width: 12),
                      Expanded(child: Text(lang.name, style: _labelStyle)),
                      if (lang.code == current) const Icon(Icons.check, size: 18, color: AppColors.ink),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _UiLangSheet extends StatelessWidget {
  const _UiLangSheet({required this.current});
  final UiLanguageOption current;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final options = <(UiLanguageOption, String)>[
      (UiLanguageOption.system, l.uiLangSystem),
      (UiLanguageOption.russian, l.uiLangRussian),
      (UiLanguageOption.english, l.uiLangEnglish),
    ];
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.profileUiLangSheet, style: AppText.sheetButton.copyWith(fontSize: 17)),
        const SizedBox(height: 8),
        for (final (opt, name) in options)
          InkWell(
            onTap: () => Navigator.of(context).pop(opt),
            borderRadius: BorderRadius.circular(AppRadii.field),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 13),
              child: Row(
                children: [
                  Expanded(child: Text(name, style: _labelStyle)),
                  if (opt == current) const Icon(Icons.check, size: 18, color: AppColors.ink),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// Reminder time — two wheels (24h hour, 15-min minute), Сохранить (кадр 13b).
class _TimeSheet extends StatefulWidget {
  const _TimeSheet({required this.current});
  final String current;

  @override
  State<_TimeSheet> createState() => _TimeSheetState();
}

class _TimeSheetState extends State<_TimeSheet> {
  late int _hour;
  late int _minuteIndex; // 0,15,30,45 → 0..3
  static const _minutes = [0, 15, 30, 45];

  @override
  void initState() {
    super.initState();
    final parts = widget.current.split(':');
    _hour = int.tryParse(parts.first)?.clamp(0, 23) ?? 20;
    final m = parts.length > 1 ? (int.tryParse(parts[1]) ?? 0) : 0;
    _minuteIndex = _minutes.indexOf((m ~/ 15) * 15).clamp(0, 3);
  }

  String get _value => '${_hour.toString().padLeft(2, '0')}:${_minutes[_minuteIndex].toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.reminderSheetTitle,
            style: const TextStyle(fontFamily: AppFonts.literata, fontSize: 23, fontWeight: FontWeight.w500, color: AppColors.ink)),
        const SizedBox(height: 7),
        Text(l.reminderSheetSubtitle, style: AppText.translation.copyWith(fontSize: 13, height: 1.45, color: AppColors.secondary)),
        const SizedBox(height: 12),
        SizedBox(
          height: 160,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _Wheel(
                count: 24,
                initial: _hour,
                label: (i) => i.toString().padLeft(2, '0'),
                onChanged: (i) => setState(() => _hour = i),
              ),
              const Text(':', style: TextStyle(fontFamily: AppFonts.inter, fontSize: 25, fontWeight: FontWeight.w700, color: AppColors.ink)),
              _Wheel(
                count: _minutes.length,
                initial: _minuteIndex,
                label: (i) => _minutes[i].toString().padLeft(2, '0'),
                onChanged: (i) => setState(() => _minuteIndex = i),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        PrimaryButton(label: l.commonSave, minHeight: 52, onPressed: () => Navigator.of(context).pop(_value)),
      ],
    );
  }
}

class _Wheel extends StatelessWidget {
  const _Wheel({required this.count, required this.initial, required this.label, required this.onChanged});
  final int count, initial;
  final String Function(int) label;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 74,
      child: CupertinoPicker(
        scrollController: FixedExtentScrollController(initialItem: initial),
        itemExtent: 40,
        squeeze: 1.1,
        diameterRatio: 1.3,
        selectionOverlay: const CupertinoPickerDefaultSelectionOverlay(background: AppColors.faintInk),
        onSelectedItemChanged: onChanged,
        children: [
          for (var i = 0; i < count; i++)
            Center(
              child: Text(label(i),
                  style: const TextStyle(
                      fontFamily: AppFonts.inter, fontSize: 24, fontWeight: FontWeight.w700, color: AppColors.ink)),
            ),
        ],
      ),
    );
  }
}

class _PillOption extends StatelessWidget {
  const _PillOption({required this.label, required this.selected, required this.onTap});
  final String label;
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
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 13),
          child: Center(
            child: Text(label,
                style: TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: selected ? AppColors.paper : AppColors.ink)),
          ),
        ),
      ),
    );
  }
}
