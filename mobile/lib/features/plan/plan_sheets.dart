import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/plan/plan_models.dart';
import 'plan_format.dart';

/// THE THREE SHEETS AND THE ALERT OF THE TAB — кадры 21-8, 21-10, 21-11, 21-12.
///
/// All three sheets are the app's own bottom sheet (5b): #FCFAF5, radius 28 on top, a handle, and
/// the title in Inter 22/800 — the записка's open question («Спорное: заголовки листов оставлены
/// Inter 22/800») is left as the frames draw it.

/// «Как устроен план» (кадр 21-8) — once, after the first plan is created.
Future<void> showPlanHowSheet(BuildContext context) {
  final l = AppLocalizations.of(context);

  return showAppBottomSheet<void>(
    context: context,
    builder: (sheet) => _Sheet(
      title: l.planSheetTitle,
      children: [
        _HowRow(
          icon: _BrassCircle(child: Text('1', style: _brassNumber)),
          text: l.planSheetRowDays,
        ),
        const SizedBox(height: 18),
        _HowRow(icon: const _StagesGlyph(), text: l.planSheetRowStages),
        const SizedBox(height: 18),
        _HowRow(
          icon: const _BrassCircle(child: Icon(LucideIcons.undo2, size: 12, color: AppColors.brassInk)),
          text: l.planSheetRowReturn,
        ),
        const SizedBox(height: AppSpacing.s26),
        _InkButton(label: l.planSheetCta, onTap: () => Navigator.of(sheet).pop()),
      ],
    ),
  );
}

/// What «Когда приём?» (кадр 21-10) answers with.
sealed class PlanDateChoice {
  const PlanDateChoice();
}

class PlanDateKeep extends PlanDateChoice {
  const PlanDateKeep();
}

class PlanDateClear extends PlanDateChoice {
  const PlanDateClear();
}

class PlanDateSet extends PlanDateChoice {
  const PlanDateSet(this.date);

  final DateTime date;
}

/// «Изменить дату» (кадр 21-10): the current date «как сейчас», the date just picked, «Другая
/// дата» into the calendar; «Применить» / «Отменить». Returns null when cancelled.
///
/// The «Маршрут · было 7 дней → станет 5» block of the frame is NOT drawn: the contract has no
/// dry run of `PATCH /schedule`, and the client does not lay the calendar out itself
/// (`docs/plan-api.md`, «Что клиенту НЕ надо считать») — reported to the architect.
Future<PlanDateChoice?> showPlanDateSheet(BuildContext context, Plan plan) {
  return showAppBottomSheet<PlanDateChoice>(
    context: context,
    builder: (sheet) => _DateSheetBody(plan: plan),
  );
}

class _DateSheetBody extends StatefulWidget {
  const _DateSheetBody({required this.plan});

  final Plan plan;

  @override
  State<_DateSheetBody> createState() => _DateSheetBodyState();
}

class _DateSheetBodyState extends State<_DateSheetBody> {
  DateTime? _current;
  DateTime? _picked;
  bool _clear = false;

  @override
  void initState() {
    super.initState();
    _current = PlanFormat.parseWireDate(widget.plan.eventDate);
  }

  Future<void> _pick() async {
    final today = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _picked ?? _current ?? today,
      firstDate: DateTime(today.year, today.month, today.day),
      lastDate: today.add(const Duration(days: 365)),
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: AppColors.ink,
            onPrimary: AppColors.paper,
            surface: AppColors.surfaceRaised,
            onSurface: AppColors.ink,
          ),
        ),
        child: child!,
      ),
    );
    if (picked == null || !mounted) return;
    setState(() {
      _picked = DateTime(picked.year, picked.month, picked.day);
      _clear = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final event = (widget.plan.eventNative ?? '').trim();
    final keepSelected = _picked == null && !_clear;
    final changed = _picked != null || _clear;

    return _Sheet(
      title: event.isEmpty ? l.planDateTitleNoEvent : l.planDateTitle(event),
      children: [
        if (_current != null) ...[
          ChoiceCard(
            title: PlanFormat.date(_current!, locale),
            subtitle: l.planDateOptionCurrent(PlanFormat.weekday(_current!, locale)),
            selected: keepSelected,
            onTap: () => setState(() {
              _picked = null;
              _clear = false;
            }),
          ),
          const SizedBox(height: 10),
        ],
        if (_picked != null) ...[
          ChoiceCard(
            title: PlanFormat.date(_picked!, locale),
            subtitle: PlanFormat.weekday(_picked!, locale),
            selected: true,
            onTap: _pick,
          ),
          const SizedBox(height: 10),
        ],
        ChoiceCard(
          title: l.planDateOptionOther,
          subtitle: l.planDateOptionOtherSub,
          selected: false,
          onTap: _pick,
        ),
        if (_current != null) ...[
          const SizedBox(height: 10),
          ChoiceCard(
            title: l.planDateRemove,
            selected: _clear,
            onTap: () => setState(() {
              _clear = true;
              _picked = null;
            }),
          ),
        ],
        const SizedBox(height: AppSpacing.s26),
        _InkButton(
          label: l.planDateCta,
          enabled: changed,
          onTap: () => Navigator.of(context).pop(
            _clear ? const PlanDateClear() : PlanDateSet(_picked!),
          ),
        ),
        _TextButton(label: l.planDateCancel, onTap: () => Navigator.of(context).pop()),
      ],
    );
  }
}

/// «Начать другой план?» (кадр 21-11). True — «Собрать новый».
Future<bool> showPlanNewSheet(BuildContext context, Plan plan) async {
  final l = AppLocalizations.of(context);
  final day = plan.currentDay?.number ?? plan.daysTotal;
  final answer = await showAppBottomSheet<bool>(
    context: context,
    builder: (sheet) => _Sheet(
      title: l.planNewTitle,
      children: [
        Text(
          l.planNewBody(day, plan.daysTotal, plan.displayTitle),
          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.5, color: AppColors.inkBody),
        ),
        const SizedBox(height: AppSpacing.s26),
        _InkButton(label: l.planNewCta, onTap: () => Navigator.of(sheet).pop(true)),
        _TextButton(label: l.planNewKeep, onTap: () => Navigator.of(sheet).pop(false)),
      ],
    ),
  );

  return answer ?? false;
}

/// «Удалить план?» (кадр 21-12) — the centred alert 5f. True — deleted.
Future<bool> showPlanDeleteAlert(BuildContext context, Plan plan) async {
  final l = AppLocalizations.of(context);
  final ok = await showCenterAlert(
    context: context,
    title: l.planDeleteTitle,
    message: plan.collectionId == null
        ? l.planDeleteBodyNoCollection
        : l.planDeleteBody(plan.displayTitle),
    confirmLabel: l.planDeleteConfirm,
    cancelLabel: l.planDateCancel,
  );

  return ok == true;
}

// ── the sheet's own materials ───────────────────────────────────────────────────────────────

class _Sheet extends StatelessWidget {
  const _Sheet({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(6, 4, 6, 10),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.44,
            color: AppColors.ink,
          ),
        ),
        const SizedBox(height: 18),
        ...children,
      ],
    ),
  );
}

class _HowRow extends StatelessWidget {
  const _HowRow({required this.icon, required this.text});

  final Widget icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(width: 40, child: Align(alignment: Alignment.topLeft, child: icon)),
      Expanded(
        child: Text(
          text,
          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.45, color: AppColors.ink),
        ),
      ),
    ],
  );
}

const _brassNumber = TextStyle(
  fontFamily: AppFonts.inter,
  fontSize: 11,
  fontWeight: FontWeight.w700,
  color: AppColors.brassInk,
);

class _BrassCircle extends StatelessWidget {
  const _BrassCircle({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    width: 22,
    height: 22,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: AppColors.brassInk, width: 1.5),
    ),
    child: child,
  );
}

/// The three-dot «stages in order» glyph of the sheet — dots and dashes, brass, fading.
class _StagesGlyph extends StatelessWidget {
  const _StagesGlyph();

  @override
  Widget build(BuildContext context) {
    Widget dot(bool filled, double alpha) => Container(
      width: 8,
      height: 8,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: filled ? AppColors.brassInk : null,
        border: filled ? null : Border.all(color: AppColors.brassInk.withValues(alpha: alpha), width: 1.5),
      ),
    );
    Widget dash(double alpha) => Expanded(
      child: Container(height: 2, color: AppColors.brassInk.withValues(alpha: alpha)),
    );

    return SizedBox(
      width: 36,
      child: Column(
        children: [
          Row(children: [dot(true, 1), const SizedBox(width: 4), dash(.7)]),
          const SizedBox(height: 4),
          Row(children: [dot(false, 1), const SizedBox(width: 4), dash(.7)]),
          const SizedBox(height: 4),
          Row(children: [dot(false, .35), const SizedBox(width: 4), dash(.35)]),
        ],
      ),
    );
  }
}

/// The sheet's ink button — 52 / radius 16 / 17 / 700, like every ink button of the plan.
class _InkButton extends StatelessWidget {
  const _InkButton({required this.label, this.onTap, this.enabled = true});

  final String label;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) => PrimaryButton(
    label: label,
    minHeight: 52,
    enabled: enabled,
    onPressed: onTap,
  );
}

/// The quiet second action under the ink button — 48, 15/600 ink-body, no frame.
class _TextButton extends StatelessWidget {
  const _TextButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Container(
        height: 48,
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: AppColors.inkBody,
          ),
        ),
      ),
    ),
  );
}
