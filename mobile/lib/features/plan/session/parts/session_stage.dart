import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../session_texts.dart' show StageLine;
import 'session_bits.dart';
import 'session_chrome.dart';

/// A stage's status in the list of five (30-1).
enum StageRowStatus { done, current, ahead }

/// A row of the stage list, in the SERVER's state (приёмка CLIENT-CONV-1c 22.09: «состояние ряда — из state сервера, не
/// из счёта карточек» — the entry says what the day window says). [replay] — the stage is being walked a second time
/// («Once more» from the day summary): the server has it done, and the row says «ещё раз» (FIX-1 §5).
typedef StageRow = ({PlanStage stage, StageRowStatus status, bool replay});

/// STAGE ENTRY (canvas 30-1): back arrow, scene strip, the stage name in Literata, description, «≈ N min», five
/// stage dots (the current one in brass), the list of five stages with statuses, «No hints», «Start». In the footer,
/// small — the build version.
class SessionStageEntry extends StatelessWidget {
  const SessionStageEntry({
    super.key,
    required this.stage,
    required this.stageName,
    required this.description,
    required this.minutes,
    required this.rows,
    required this.scene,
    required this.noHints,
    required this.onNoHints,
    required this.onStart,
    required this.onBack,
    this.buildLabel,
  });

  final PlanStage stage;
  final String Function(PlanStage) stageName;
  final String description;

  /// «≈ N min» — only for the day window's current stage; null — not drawn.
  final int? minutes;
  final List<StageRow> rows;
  final PlanScene? scene;
  final bool noHints;
  final ValueChanged<bool> onNoHints;

  /// Null — the stage has nothing left to start.
  final VoidCallback? onStart;
  final VoidCallback onBack;

  /// «build 1.0.0 (2)».
  final String? buildLabel;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: SessionCloseButton(onTap: onBack, label: l.planWindowBack, back: true),
                  ),
                ),
                SessionSceneStrip(scene: scene),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 24, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(stageName(stage), style: AppTextSession.stageTitle),
                      const SizedBox(height: 24),
                      Text(description, style: AppTextSession.body),
                      if (minutes != null) ...[
                        const SizedBox(height: 24),
                        Text(l.planSessionApproxMinutes(minutes!), style: AppTextSession.meta),
                      ],
                      const SizedBox(height: 24),
                      SessionStageDots(rows: rows),
                      const SizedBox(height: 24),
                      for (var i = 0; i < rows.length; i++) ...[
                        if (i > 0) const Divider(height: 1, thickness: 1, color: AppColors.markerOutline),
                        _StageListRow(row: rows[i], name: stageName(rows[i].stage)),
                      ],
                      const SizedBox(height: 24),
                      _NoHintsCard(value: noHints, onChanged: onNoHints),
                      if (buildLabel != null) ...[
                        const SizedBox(height: 24),
                        Text(buildLabel!, textAlign: TextAlign.center, style: AppTextSession.buildStamp),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(child: SessionDockButton(label: l.planSessionStart, enabled: onStart != null, onTap: onStart)),
      ],
    );
  }
}

/// FIVE STAGE DOTS (30-1, 30-6): done ones 8 in sage, the current one 10 in brass with a 3 ring, ahead — outlined.
class SessionStageDots extends StatelessWidget {
  const SessionStageDots({super.key, required this.rows});

  final List<StageRow> rows;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 16,
    child: Row(
      children: [
        for (var i = 0; i < rows.length; i++) ...[
          if (i > 0) const SizedBox(width: 12),
          switch (rows[i].status) {
            StageRowStatus.done => const _Dot(size: 8, color: AppColors.verdictKnown),
            StageRowStatus.current => Container(
              width: 10,
              height: 10,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.brassInk,
                boxShadow: [BoxShadow(color: AppColors.sessionBrassRing, spreadRadius: 3)],
              ),
            ),
            StageRowStatus.ahead => Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.markerOutline, width: 1.5)),
            ),
          },
        ],
      ],
    ),
  );
}

class _Dot extends StatelessWidget {
  const _Dot({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(shape: BoxShape.circle, color: color),
  );
}

class _StageListRow extends StatelessWidget {
  const _StageListRow({required this.row, required this.name});

  final StageRow row;
  final String name;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final current = row.status == StageRowStatus.current;
    final status = switch (row.status) {
      StageRowStatus.done => l.planSessionStateDone,
      StageRowStatus.current when row.replay => l.planSessionStateReplay,
      StageRowStatus.current => l.planWindowStateInProgress,
      StageRowStatus.ahead => l.planSessionStateAhead,
    };
    return SizedBox(
      height: 44,
      child: Row(
        children: [
          sessionStageGlyph(row.stage, current ? AppColors.ink : AppColors.tertiary),
          const SizedBox(width: 12),
          Expanded(child: Text(name, style: current ? AppTextSession.stageRowCurrent : AppTextSession.stageRow)),
          Text(status, style: AppTextSession.meta),
        ],
      ),
    );
  }
}

class _NoHintsCard extends StatelessWidget {
  const _NoHintsCard({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Semantics(
      toggled: value,
      label: l.planSessionNoHints,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () {
          AppHaptics.light();
          onChanged(!value);
        },
        child: SessionSheet(
          padding: const EdgeInsets.all(16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.planSessionNoHints, style: AppTextSession.text15),
                    const SizedBox(height: 4),
                    Text(l.planSessionNoHintsSub, style: AppTextSession.meta),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              AnimatedContainer(
                duration: AppMotion.sessionChipSelect,
                width: 44,
                height: 26,
                padding: const EdgeInsets.all(3),
                alignment: value ? Alignment.centerRight : Alignment.centerLeft,
                decoration: BoxDecoration(
                  color: value ? AppColors.verdictKnown : AppColors.sessionToggleTrack,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Container(
                  width: 20,
                  height: 20,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    color: AppColors.paper,
                    boxShadow: [BoxShadow(color: AppColors.sessionToggleKnobShadow, blurRadius: 3, offset: Offset(0, 1))],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// What comes after a stage (30-6 «Дальше»): the next stage — its glyph, its name and «≈ N мин» when the server sent
/// its minutes; or, after the day's last stage with cards, the day's total — no glyph, the day's minutes.
typedef StageNext = ({PlanStage? stage, String name, String? value});

/// THE STAGE SUMMARY (кадр 30-6, SESSION-DES-4; решение архитектора 22.09) — ONE COMPONENT FOR EVERY STAGE BUT THE TALK
/// (its own is 37-12): Слова, Фразы, Диалог, Слушаю и отвечаю, Говорю сам, Вспомнить, Повторение. The cross, the scene
/// strip; a sage ring 32 with its check; the title with the stage's minutes; the day's stage dots; three lines in words
/// (the third grey); «Дальше» and the plate of what comes next; one button. No percentages, no points, no «8 из 8», no
/// «Остальные закрыты», and no list of what comes back — the day summary (30-7) says that.
class SessionStageSummary extends StatelessWidget {
  const SessionStageSummary({
    super.key,
    required this.title,
    required this.rows,
    required this.lines,
    required this.next,
    required this.scene,
    required this.onClose,
    required this.onNext,
    this.busy = false,
  });

  final String title;
  final List<StageRow> rows;

  /// Two or three — a line with nothing to say is not given.
  final List<StageLine> lines;
  final StageNext next;
  final PlanScene? scene;
  final VoidCallback onClose;
  final VoidCallback? onNext;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final nextStage = next.stage;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
                  child: Align(
                    alignment: Alignment.centerLeft,
                    child: SessionCloseButton(onTap: onClose, label: l.planSessionClose),
                  ),
                ),
                SessionSceneStrip(scene: scene),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 14, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Align(alignment: Alignment.centerLeft, child: _PassedRing()),
                      const SizedBox(height: 14),
                      Text(title, key: const ValueKey('stage-summary-title'), style: AppTextSession.stageTitle),
                      const SizedBox(height: 14),
                      SessionStageDots(rows: rows),
                    ],
                  ),
                ),
                if (lines.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(kSessionGutter, 32, kSessionGutter, 0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (final (i, line) in lines.indexed) ...[
                          if (i > 0) const SizedBox(height: 8),
                          Text(
                            line.text,
                            key: ValueKey('stage-summary-line-$i'),
                            style: line.soft ? AppTextSession.body : AppTextSession.text15,
                          ),
                        ],
                      ],
                    ),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 32, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SessionEyebrow(l.planSessionNext),
                      const SizedBox(height: 14),
                      SessionSheet(
                        key: const ValueKey('stage-summary-next'),
                        // The row is 20 high as the frame draws it; a longer name wraps and the plate grows — nothing
                        // in a session is cut.
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(minHeight: 20),
                          child: Row(
                            children: [
                              if (nextStage != null) ...[sessionStageGlyph(nextStage, AppColors.tertiary), const SizedBox(width: 12)],
                              Expanded(child: Text(next.name, style: AppTextSession.text15)),
                              if (next.value case final value?) ...[
                                const SizedBox(width: 12),
                                Text(value, style: AppTextSession.meta),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(child: SessionDockButton(label: l.planSessionNext, busy: busy, onTap: onNext)),
      ],
    );
  }
}

/// THE STAGE IS CLOSED (30-6): a sage ring 32 — a line 1.5 — with a sage check 16 inside.
class _PassedRing extends StatelessWidget {
  const _PassedRing();

  @override
  Widget build(BuildContext context) => Container(
    key: const ValueKey('stage-summary-ring'),
    width: 32,
    height: 32,
    alignment: Alignment.center,
    decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.verdictKnown, width: 1.5)),
    child: const Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown),
  );
}

/// THE DAY SUMMARY (canvas 30-7): cross, scene strip, «Day done · 19 minutes»; the dark plate of the day's stages, each
/// with a sage check (it fades in, 200 ms after 80); «Coming back tomorrow» — one sentence of what returns; the next
/// day of the plan with a pulsing brass dot; «Close the day».
class SessionDaySummary extends StatelessWidget {
  const SessionDaySummary({
    super.key,
    required this.title,
    required this.stages,
    required this.stageName,
    required this.returnsLine,
    this.highlights = const [],
    required this.nextDay,
    required this.scene,
    required this.onClose,
    required this.onCloseDay,
    this.closing = false,
    this.closeFailed = false,
  });

  final String title;

  /// The day's stages, in walking order — every one of them is done.
  final List<PlanStage> stages;
  final String Function(PlanStage stage) stageName;

  /// «ЧТО БЫЛО ХОРОШО» (кадр 30-7, наряд CONV-1) — two or three READY lines of the server, printed
  /// in order and not inflected here. Empty — the block is not drawn at all: a block that says «0
  /// фраз» about a talk that did not happen is not praise.
  final List<String> highlights;

  /// «5 cards: 2 words, 2 phrases and 1 line.»; null — nothing comes back, the block is not drawn.
  final String? returnsLine;

  /// «Day 3 — building»; null — this is the plan's last day.
  final String? nextDay;
  final PlanScene? scene;
  final VoidCallback onClose;
  final VoidCallback? onCloseDay;
  final bool closing;
  final bool closeFailed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    final plate = _DayPlate(stages: stages, stageName: stageName);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 4, kSessionGutter, 0),
                  child: Align(alignment: Alignment.centerLeft, child: SessionCloseButton(onTap: onClose, label: l.planSessionClose)),
                ),
                SessionSceneStrip(scene: scene),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 14, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(title, key: const ValueKey('day-summary-title'), style: AppTextSession.stageTitle),
                      const SizedBox(height: 32),
                      if (reduce)
                        plate
                      else
                        TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: 1),
                          duration: AppMotion.sessionDayPlateDelay + AppMotion.sessionDayPlate,
                          curve: const Interval(80 / 280, 1, curve: Curves.easeOut),
                          builder: (_, t, child) => Opacity(opacity: t, child: child),
                          child: plate,
                        ),
                      if (highlights.isNotEmpty) ...[
                        const SizedBox(height: 40),
                        SessionEyebrow(l.planTalkHighlights),
                        const SizedBox(height: 14),
                        for (final (i, line) in highlights.indexed) ...[
                          if (i > 0) const SizedBox(height: 8),
                          Text(line, key: ValueKey('day-summary-highlight-$i'), style: AppTextSession.body),
                        ],
                      ],
                      if (returnsLine != null || nextDay != null) SizedBox(height: highlights.isEmpty ? 100 : 40),
                      if (returnsLine != null) ...[
                        SessionEyebrow(l.planSessionReturnsTomorrow),
                        const SizedBox(height: 14),
                        Text(returnsLine!, key: const ValueKey('day-summary-returns'), style: AppTextSession.body),
                      ],
                      if (nextDay != null) ...[
                        if (returnsLine != null) const SizedBox(height: 32),
                        Row(
                          children: [
                            const SizedBox(width: 20, height: 20, child: Center(child: _PulseDot())),
                            const SizedBox(width: 12),
                            Expanded(child: Text(nextDay!, key: const ValueKey('day-summary-next'), style: AppTextSession.text15)),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (closeFailed) ...[
                Text(l.planSessionCloseFailed, key: const ValueKey('day-summary-failed'), textAlign: TextAlign.center, style: AppTextSession.meta),
                const SizedBox(height: 14),
              ],
              SessionDockButton(key: const ValueKey('day-summary-close'), label: l.planSessionCloseDay, busy: closing, onTap: onCloseDay),
            ],
          ),
        ),
      ],
    );
  }
}

/// The dark plate of 30-7: radius 22, rows 44 — the stage glyph in `#BDB6AC`, the name in paper, a sage check 20.
class _DayPlate extends StatelessWidget {
  const _DayPlate({required this.stages, required this.stageName});

  final List<PlanStage> stages;
  final String Function(PlanStage stage) stageName;

  @override
  Widget build(BuildContext context) => Container(
    key: const ValueKey('day-summary-plate'),
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
    decoration: BoxDecoration(color: AppColors.windowInk, borderRadius: BorderRadius.circular(22)),
    child: Column(
      children: [
        for (final (i, stage) in stages.indexed)
          Container(
            height: 44,
            decoration: BoxDecoration(
              border: i == 0 ? null : const Border(top: BorderSide(color: AppColors.sessionPlateDivider)),
            ),
            child: Row(
              children: [
                sessionStageGlyph(stage, AppColors.windowAhead),
                const SizedBox(width: 12),
                Expanded(child: Text(stageName(stage), style: AppTextSession.text15.copyWith(color: AppColors.paper))),
                Container(
                  width: 20,
                  height: 20,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
                  child: const Icon(LucideIcons.check, size: 13, color: AppColors.paper),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}

/// The brass dot 10 of the next day — a ring pulse (1.6 s); under «Reduce Motion» it stands still.
class _PulseDot extends StatefulWidget {
  const _PulseDot();

  @override
  State<_PulseDot> createState() => _PulseDotState();
}

class _PulseDotState extends State<_PulseDot> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: AppMotion.sessionRolePulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) {
      _c.stop();
    } else if (!_c.isAnimating) {
      unawaited(_c.repeat());
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _c,
    builder: (_, _) {
      final t = Curves.easeOut.transform(_c.value);
      return Container(
        width: 10,
        height: 10,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.brassInk,
          boxShadow: [if (_c.isAnimating) BoxShadow(color: AppColors.sessionBrassRing.withValues(alpha: .30 * (1 - t)), spreadRadius: 6 * t)],
        ),
      );
    },
  );
}

/// STAGE EXIT (canvas 30-8): a sheet with one sentence and two buttons — «Continue» as brass text and
/// «Leave». Leaving loses nothing: the answers are already on the server. True — leave.
Future<bool> showSessionExitSheet(BuildContext context, {required String stageName}) async {
  final l = AppLocalizations.of(context);
  final leave = await showModalBottomSheet<bool>(
    context: context,
    backgroundColor: AppColors.ground,
    barrierColor: AppColors.windowSheetScrim,
    elevation: 0,
    isScrollControlled: true,
    sheetAnimationStyle: const AnimationStyle(duration: AppMotion.sessionExitSheet, curve: AppMotion.windowEaseOutCubic),
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (context) => Padding(
      padding: EdgeInsets.fromLTRB(24, 24, 24, 24 + MediaQuery.paddingOf(context).bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(color: AppColors.markerOutline, borderRadius: BorderRadius.circular(2)),
            ),
          ),
          const SizedBox(height: 24),
          Text(l.planSessionExitTitle, style: AppTextSession.sheetTitle),
          const SizedBox(height: 14),
          Text(l.planSessionExitBody(stageName), style: AppTextSession.body),
          const SizedBox(height: 32),
          Center(
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => Navigator.of(context).pop(false),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                child: Text(l.planSessionExitStay, style: AppTextSession.sheetStay),
              ),
            ),
          ),
          SessionDockButton(label: l.planSessionExitLeave, onTap: () => Navigator.of(context).pop(true)),
        ],
      ),
    ),
  );
  return leave == true;
}
