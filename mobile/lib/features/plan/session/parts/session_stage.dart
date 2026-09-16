import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_models.dart';
import 'session_bits.dart';
import 'session_chrome.dart';

/// Статус этапа в списке пяти (30-1).
enum StageRowStatus { done, current, ahead }

/// Строка списка этапов.
typedef StageRow = ({PlanStage stage, StageRowStatus status, bool started});

/// ВХОД В ЭТАП (кадр 30-1): стрелка назад, полоса сцены, имя этапа Literata, описание, «≈ N мин», пять точек
/// этапов (текущая латунью), список пяти этапов со статусами, «Без подсказок», «Начать». У этапа без экранов в
/// этой сборке — вместо «Начать» подпись «в следующей сборке». В подвале мелко — версия сборки.
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

  /// «≈ N мин» — только у текущего этапа окна; null — не рисуется.
  final int? minutes;
  final List<StageRow> rows;
  final PlanScene? scene;
  final bool noHints;
  final ValueChanged<bool> onNoHints;

  /// Null — этап заблокирован в этой сборке.
  final VoidCallback? onStart;
  final VoidCallback onBack;

  /// «сборка 1.0.0 (2)».
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
        SessionDock(
          child: onStart == null
              ? Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(l.planSessionNextBuild, textAlign: TextAlign.center, style: AppTextSession.meta),
                    const SizedBox(height: 14),
                    SessionDockButton(label: l.planSessionStart, enabled: false, onTap: null),
                  ],
                )
              : SessionDockButton(label: l.planSessionStart, onTap: onStart),
        ),
      ],
    );
  }
}

/// ПЯТЬ ТОЧЕК ЭТАПОВ (30-1, 30-6): пройденные 8 шалфеем, текущая 10 латунью с кольцом 3, впереди контуром.
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
      StageRowStatus.current => row.started ? l.planWindowStateInProgress : l.planWindowStateNotStarted,
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

/// Единица, которая вернётся завтра, — парой строк словами (и фото у слова).
typedef ReturningUnit = ({String target, String native, CardImage? image});

/// ИТОГ ЭТАПА (кадр 30-6): крестик, полоса сцены, «Слова пройдены · 6 минут», точки этапов; «Вернётся
/// завтра» — единицы с возвратом; «Остальные N слов закрыты.»; «Дальше» — следующий этап и его минуты.
class SessionStageSummary extends StatelessWidget {
  const SessionStageSummary({
    super.key,
    required this.title,
    required this.rows,
    required this.returning,
    required this.closedLine,
    required this.nextStage,
    required this.nextName,
    required this.nextMinutes,
    required this.scene,
    required this.onClose,
    required this.onNext,
    this.busy = false,
  });

  final String title;
  final List<StageRow> rows;
  final List<ReturningUnit> returning;
  final String closedLine;
  final PlanStage? nextStage;
  final String? nextName;
  final int? nextMinutes;
  final PlanScene? scene;
  final VoidCallback onClose;
  final VoidCallback? onNext;
  final bool busy;

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
                    child: SessionCloseButton(onTap: onClose, label: l.planSessionClose),
                  ),
                ),
                SessionSceneStrip(scene: scene),
                Padding(
                  padding: const EdgeInsets.fromLTRB(kSessionGutter, 14, kSessionGutter, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(title, style: AppTextSession.stageTitle),
                      const SizedBox(height: 14),
                      SessionStageDots(rows: rows),
                      if (returning.isNotEmpty) ...[
                        const SizedBox(height: 70),
                        SessionEyebrow(l.planSessionReturnsTomorrow),
                        const SizedBox(height: 14),
                        for (final u in returning) ...[
                          if (u != returning.first) const SizedBox(height: 12),
                          _ReturningRow(unit: u),
                        ],
                        const SizedBox(height: 32),
                      ] else
                        const SizedBox(height: 70),
                      Text(closedLine, style: AppTextSession.body),
                      if (nextStage != null && nextName != null) ...[
                        const SizedBox(height: 20),
                        SessionEyebrow(l.planSessionNext),
                        const SizedBox(height: 14),
                        SessionSheet(
                          child: SizedBox(
                            height: 20,
                            child: Row(
                              children: [
                                sessionStageGlyph(nextStage!, AppColors.tertiary),
                                const SizedBox(width: 12),
                                Expanded(child: Text(nextName!, style: AppTextSession.text15)),
                                if (nextMinutes != null) Text(l.planSessionApproxMinutes(nextMinutes!), style: AppTextSession.meta),
                              ],
                            ),
                          ),
                        ),
                      ],
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

class _ReturningRow extends StatelessWidget {
  const _ReturningRow({required this.unit});

  final ReturningUnit unit;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: 56),
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
    decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(16), boxShadow: kSessionSheetShadow),
    child: Row(
      children: [
        if (unit.image != null) ...[
          SizedBox(width: 40, height: 40, child: SessionPhoto(image: unit.image, height: 40)),
          const SizedBox(width: 12),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(unit.target, style: AppTextSession.target22),
              const SizedBox(height: 2),
              Text(unit.native, style: AppTextSession.body),
            ],
          ),
        ),
        const SizedBox(width: 12),
        const SessionReturnDot(),
      ],
    ),
  );
}

/// ВЫХОД ИЗ ЭТАПА (кадр 30-8): шит с одним предложением и двумя кнопками — «Продолжить» текстом латунью и
/// «Выйти». Выход ничего не теряет: ответы уже на сервере. True — выйти.
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
