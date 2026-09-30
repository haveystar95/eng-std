import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/app_settings.dart';
import '../../data/languages.dart' show languageByCode;
import '../../data/locale_controller.dart';
import '../../data/plan/notify_permission.dart';
import '../../data/plan/plan_languages.dart';
import '../../data/providers.dart';
import '../plan/plan_providers.dart';
import 'account_providers.dart';
import 'profile_screen.dart' show setRemindersFromProfile;

// ── 42-2 · the name ─────────────────────────────────────────────────────────────────────────────────────────────

/// ИМЯ (frame 42-2): one field under the sheet's title — no label of its own, «Как тебя зовут» in it while empty — and
/// «Готово»; no «Отмена» — the sheet goes down by a drag. Over the keyboard whole.
Future<void> showNameSheet(BuildContext context, WidgetRef ref, {required String current}) async {
  final name = await showPaperSheet<String>(context: context, builder: (_) => _NameSheet(current: current));
  if (name != null && name.trim().isNotEmpty && name.trim() != current) {
    await ref.read(accountNameProvider.notifier).rename(name);
  }
}

class _NameSheet extends StatefulWidget {
  const _NameSheet({required this.current});

  final String current;

  @override
  State<_NameSheet> createState() => _NameSheetState();
}

class _NameSheetState extends State<_NameSheet> {
  late final _field = TextEditingController(text: widget.current);

  @override
  void dispose() {
    _field.dispose();
    super.dispose();
  }

  void _done() => Navigator.of(context).pop(_field.text);

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      key: const ValueKey('name-sheet'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.accountNameTitle, style: AppTextSession.sheetTitle),
        const SizedBox(height: 14),
        Container(
          height: 56,
          padding: const EdgeInsets.symmetric(horizontal: 16),
          alignment: Alignment.centerLeft,
          decoration: BoxDecoration(
            color: AppColors.paper,
            borderRadius: BorderRadius.circular(16),
            boxShadow: const [BoxShadow(color: AppColors.windowSourceShadow, blurRadius: 8, offset: Offset(0, 2))],
          ),
          child: TextField(
            key: const ValueKey('name-field'),
            controller: _field,
            autofocus: true,
            textCapitalization: TextCapitalization.words,
            textInputAction: TextInputAction.done,
            maxLines: 1,
            cursorColor: AppColors.ink,
            style: AppTextStart.field,
            decoration: InputDecoration.collapsed(hintText: l.accountNameHint, hintStyle: AppTextStart.fieldHint),
            onSubmitted: (_) => _done(),
          ),
        ),
        const SizedBox(height: 32),
        DockButton(key: const ValueKey('name-done'), label: l.accountDone, onTap: _done),
      ],
    );
  }
}

// ── 42-3 · delete the account ─────────────────────────────────────────────────────────────────────────────────

/// УДАЛИТЬ АККАУНТ (frame 42-3): what disappears (the plan by its name), where to cancel a subscription, «Отмена» in
/// brass and «Удалить аккаунт» as a terracotta outline. Pressed — «Удаляем…»: both ways out go quiet and the sheet
/// cannot be dragged away; the server answers (`DELETE /auth/me`) — the gate brings the sign-in (41-4a).
Future<void> showDeleteAccountSheet(BuildContext context, WidgetRef ref) async {
  final plan = heldPlan(ref)?.displayTitle;
  await showPaperSheet<void>(context: context, builder: (_) => _DeleteSheet(planTitle: plan));
}

class _DeleteSheet extends ConsumerStatefulWidget {
  const _DeleteSheet({required this.planTitle});

  final String? planTitle;

  @override
  ConsumerState<_DeleteSheet> createState() => _DeleteSheetState();
}

class _DeleteSheetState extends ConsumerState<_DeleteSheet> {
  bool _deleting = false;
  bool _failed = false;

  Future<void> _delete() async {
    setState(() {
      _deleting = true;
      _failed = false;
    });
    try {
      await ref.read(authControllerProvider.notifier).deleteAccount();
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      debugPrint('[profile] delete: $e');
      if (mounted) {
        setState(() {
          _deleting = false;
          _failed = true;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plan = widget.planTitle;
    return PopScope(
      canPop: !_deleting,
      child: PaperSheetBody(
        key: const ValueKey('delete-sheet'),
        title: l.accountDeleteTitle,
        body: plan == null || plan.trim().isEmpty ? l.accountDeleteBody : l.accountDeleteBodyPlan(plan.trim()),
        note: _failed ? l.accountDeleteFailed : l.accountDeleteNote,
        stayLabel: l.commonCancel,
        onStay: () => Navigator.of(context).pop(),
        actionLabel: l.accountDelete,
        onAction: _delete,
        busy: _deleting,
        busyLabel: l.accountDeleting,
        destructive: true,
      ),
    );
  }
}

// ── 42-4 · reminders ──────────────────────────────────────────────────────────────────────────────────────────

/// НАПОМИНАНИЯ (frame 42-4): the switch, the wheel of hours and minutes (off — the wheel goes grey and does not
/// turn; iOS refused notifications — a line about the Settings instead of the wheel), one sentence, «Готово».
Future<void> showRemindersSheet(BuildContext context, WidgetRef ref, {required bool on, required ({int hour, int minute}) time}) async {
  final permission = await ref.read(notifyPermissionProbeProvider).status();
  if (!context.mounted) return;
  final result = await showPaperSheet<({bool on, int hour, int minute})>(
    context: context,
    builder: (_) => _RemindersSheet(on: on, time: time, denied: permission == NotifyPermission.denied),
  );
  if (result == null) return;
  final hhmm = '${result.hour.toString().padLeft(2, '0')}:${result.minute.toString().padLeft(2, '0')}';
  if (result.hour != time.hour || result.minute != time.minute) {
    await ref.read(appSettingsProvider.notifier).setReminderTime(hhmm);
  }
  if (result.on != on) await setRemindersFromProfile(ref, on: result.on);
}

class _RemindersSheet extends StatefulWidget {
  const _RemindersSheet({required this.on, required this.time, required this.denied});

  final bool on;
  final ({int hour, int minute}) time;
  final bool denied;

  @override
  State<_RemindersSheet> createState() => _RemindersSheetState();
}

class _RemindersSheetState extends State<_RemindersSheet> {
  late bool _on = widget.on && !widget.denied;
  late int _hour = widget.time.hour;
  late int _minute = widget.time.minute;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      key: const ValueKey('reminders-sheet'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.accountRemindersTitle, style: AppTextSession.sheetTitle),
        const SizedBox(height: 14),
        SettingsRow(
          key: const ValueKey('reminders-switch'),
          first: true,
          label: l.accountRemind,
          end: SettingsRowEnd.toggle,
          on: _on,
          onTap: widget.denied ? null : () => setState(() => _on = !_on),
        ),
        const SizedBox(height: 14),
        if (widget.denied)
          GestureDetector(
            key: const ValueKey('reminders-denied'),
            behavior: HitTestBehavior.opaque,
            onTap: () => launchUrl(Uri.parse('app-settings:')),
            child: Text(l.accountRemindersDenied, style: AppTextSession.body.copyWith(color: AppColors.ink)),
          )
        else
          _TimeWheel(
            enabled: _on,
            hour: _hour,
            minute: _minute,
            onHour: (h) => _hour = h,
            onMinute: (m) => _minute = m,
          ),
        const SizedBox(height: 14),
        Text(l.accountRemindersNote, style: AppTextSession.body),
        const SizedBox(height: 32),
        DockButton(
          key: const ValueKey('reminders-done'),
          label: l.accountDone,
          onTap: () => Navigator.of(context).pop((on: _on, hour: _hour, minute: _minute)),
        ),
      ],
    );
  }
}

/// Two wheels, hours and minutes, their digits 40 apart (two 48 columns 20 apart), over a paper band 36 tall at the
/// middle; the chosen value 22/500 ink, the others 17 grey, fading out at the edges. Off — grey and still.
class _TimeWheel extends StatelessWidget {
  const _TimeWheel({
    required this.enabled,
    required this.hour,
    required this.minute,
    required this.onHour,
    required this.onMinute,
  });

  final bool enabled;
  final int hour;
  final int minute;
  final ValueChanged<int> onHour;
  final ValueChanged<int> onMinute;

  @override
  Widget build(BuildContext context) => SizedBox(
    key: const ValueKey('reminders-wheel'),
    height: 180,
    child: Stack(
      children: [
        Positioned(
          left: 0,
          right: 0,
          top: 72,
          height: 36,
          child: DecoratedBox(decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(12))),
        ),
        IgnorePointer(
          ignoring: !enabled,
          child: Opacity(
            opacity: enabled ? 1 : .5,
            // The edge rows fade out (42-4: the second value off the middle at about half), the nearer ones stay whole.
            child: ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (rect) => const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [AppColors.groundClear, AppColors.ground, AppColors.ground, AppColors.groundClear],
                stops: [0, .2, .8, 1],
              ).createShader(rect),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  _Wheel(count: 24, initial: hour, onChanged: onHour, enabled: enabled),
                  const SizedBox(width: 20),
                  _Wheel(count: 60, initial: minute, onChanged: onMinute, enabled: enabled),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}

class _Wheel extends StatefulWidget {
  const _Wheel({required this.count, required this.initial, required this.onChanged, required this.enabled});

  final int count;
  final int initial;
  final ValueChanged<int> onChanged;
  final bool enabled;

  @override
  State<_Wheel> createState() => _WheelState();
}

class _WheelState extends State<_Wheel> {
  late final _controller = FixedExtentScrollController(initialItem: widget.initial);
  late int _selected = widget.initial;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  // A bare wheel, not CupertinoPicker: the picker dims every row off the middle to 45 %, and 42-4 keeps the rows next
  // to the middle whole grey — only the edges fade (the mask in [_TimeWheel]).
  @override
  Widget build(BuildContext context) => SizedBox(
    width: 48,
    child: ListWheelScrollView.useDelegate(
      controller: _controller,
      itemExtent: 36,
      // Flat, as the canvas draws it: the rows 36 apart, no drum.
      diameterRatio: 20,
      physics: const FixedExtentScrollPhysics(),
      onSelectedItemChanged: (i) {
        final value = i % widget.count;
        AppHaptics.light();
        setState(() => _selected = value);
        widget.onChanged(value);
      },
      childDelegate: ListWheelChildLoopingListDelegate(
        children: [
          for (var i = 0; i < widget.count; i++)
            Center(
              child: Text(
                i.toString().padLeft(2, '0'),
                style: i == _selected && widget.enabled ? AppTextStart.wheelChosen : AppTextStart.wheelOther,
              ),
            ),
        ],
      ),
    ),
  );
}

// ── the two language choosers ─────────────────────────────────────────────────────────────────────────────────

/// «Язык интерфейса» → a sheet of two, «русский / English» (42-1: «шит выбора»).
Future<void> showUiLanguageSheet(BuildContext context, WidgetRef ref, {required UiLanguageOption current, required Locale locale}) async {
  final l = AppLocalizations.of(context);
  final effective = switch (current) {
    UiLanguageOption.system => locale.languageCode == 'en' ? UiLanguageOption.english : UiLanguageOption.russian,
    _ => current,
  };
  final chosen = await showPaperSheet<UiLanguageOption>(
    context: context,
    builder: (context) => Column(
      key: const ValueKey('ui-language-sheet'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.accountUiLanguage, style: AppTextSession.sheetTitle),
        const SizedBox(height: 14),
        for (final (i, (option, name)) in [
          (UiLanguageOption.russian, l.accountUiRussian),
          (UiLanguageOption.english, l.accountUiEnglish),
        ].indexed)
          SettingsRow(
            first: i == 0,
            label: name,
            end: SettingsRowEnd.check,
            on: option == effective,
            onTap: () => Navigator.of(context).pop(option),
          ),
      ],
    ),
  );
  if (chosen != null && chosen != current) await ref.read(localeControllerProvider.notifier).setOption(chosen);
}

/// A language's own name — «Русский», «Polski» — from the client's reference.
String nativeLanguageName(String code) => languageByCode(code).endonym;

/// «Родной язык» — the server's natives minus the account's target (LANG-1 §§10–11), confirmed before it is saved:
/// new collections and plans follow the new language, existing collections keep theirs.
Future<void> showNativeLanguageSheet(BuildContext context, WidgetRef ref, {required String current}) async {
  final l = AppLocalizations.of(context);
  final target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
  final lists = await _nativeLists(ref);
  if (!context.mounted) return;
  final options = lists.nativesFor(target: target);
  final chosen = await showPaperSheet<String>(
    context: context,
    builder: (context) => ConstrainedBox(
      constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .7),
      child: Column(
        key: const ValueKey('native-sheet'),
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.accountNativeLanguage, style: AppTextSession.sheetTitle),
          const SizedBox(height: 14),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              children: [
                for (final (i, lang) in options.indexed)
                  SettingsRow(
                    first: i == 0,
                    leading: MiniFlag(languageCode: lang.code),
                    label: lang.endonym,
                    end: SettingsRowEnd.check,
                    on: lang.code == current,
                    onTap: () => Navigator.of(context).pop(lang.code),
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
  if (chosen == null || chosen == current || !context.mounted) return;
  final ok = await showCenterAlert(
    context: context,
    title: l.profileNativeLangConfirmTitle(nativeLanguageName(chosen)),
    message: l.profileNativeLangConfirmBody,
    confirmLabel: l.commonSave,
    cancelLabel: l.commonCancel,
  );
  if (ok != true) return;
  try {
    await ref.read(authControllerProvider.notifier).updateProfile({'native_language': chosen});
  } catch (e) {
    debugPrint('[profile] native: $e');
  }
}

/// The run's language lists: the cached server answer when there is one; otherwise one more ask, waited on for 1.5 s
/// at most — a sheet that opens seconds after the tap reads as a dead row.
Future<PlanLanguages> _nativeLists(WidgetRef ref) async {
  final cached = ref.read(planLanguagesProvider).value;
  if (cached != null && !cached.fromBundle) return cached;
  if (cached != null) ref.invalidate(planLanguagesProvider);
  return ref.read(planLanguagesProvider.future).timeout(const Duration(milliseconds: 1500), onTimeout: () => PlanLanguages.bundled);
}
