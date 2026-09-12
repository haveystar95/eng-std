import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/plan/plan_models.dart';
import '../plan_route.dart';
import 'entry_state.dart';

/// ПРЕВЬЮ ПЛАНА (кадры 22-4a … 22-4d).
///
/// Шапка и кнопка стоят на ОДНИХ И ТЕХ ЖЕ местах во всех четырёх состояниях — меняется только
/// середина. Поэтому экран не «дёргается», когда план становится готов: заголовок и подпись уже
/// там, где были, а маршрут просто занимает место трёх растущих узлов.
///
/// Сводок и правок здесь нет: канва убрала и ленту ответов, и свайп «убрать день» — маршрут можно
/// менять уже в табе, а лента на экране результата отвлекала от самого результата.
class EntryPreviewStep extends StatelessWidget {
  const EntryPreviewStep({
    super.key,
    required this.state,
    required this.summary,
    required this.onRetry,
    required this.onEditGoal,
  });

  final EntryState state;

  /// «7 дней · английский · средний · приём 17 сентября» — одна строка под заголовком.
  final String summary;

  final VoidCallback onRetry;
  final VoidCallback onEditGoal;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final plan = state.plan;

    final (String title, Widget body) = switch (state.phase) {
      EntryBuildPhase.idle || EntryBuildPhase.building => (
        l.planEntryPreviewLoadingTitle,
        _Building(),
      ),
      EntryBuildPhase.ready || EntryBuildPhase.starting => (
        l.planEntryPreviewTitle,
        plan == null ? _Building() : _Ready(plan: plan),
      ),
      EntryBuildPhase.unclear => (
        l.planEntryPreviewUnclearTitle,
        _Notice(
          icon: PlanIcon.noticeUnclear,
          // Заголовок цитирует то, что человек написал: «"Английский" — это про что?»
          title: l.planEntryPreviewUnclearQuote(state.goal.trim()),
          sub: l.planEntryPreviewUnclearSub,
          action: l.planEntryPreviewUnclearCta,
          onAction: onEditGoal,
        ),
      ),
      EntryBuildPhase.failed => (
        l.planEntryPreviewErrorTitle,
        _Notice(
          icon: PlanIcon.noticeFailed,
          title: state.offline ? l.planEntryOffline : l.planEntryPreviewErrorWhat,
          sub: l.planEntryPreviewErrorSub,
          action: l.planEntryPreviewErrorRetry,
          onAction: onRetry,
        ),
      ),
    };

    return ListView(
      padding: EdgeInsets.fromLTRB(20, 8, 20, 110 + MediaQuery.viewPaddingOf(context).bottom),
      children: [
        Text(
          title,
          style: const TextStyle(
            fontFamily: AppFonts.literata,
            fontSize: 30,
            fontWeight: FontWeight.w500,
            letterSpacing: -0.6,
            height: 1.15,
            color: AppColors.ink,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          summary,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            height: 1.45,
            color: AppColors.secondary,
          ),
        ),
        const SizedBox(height: 32),
        body,
      ],
    );
  }
}

/// МАРШРУТ ГОТОВОГО ПЛАНА (22-4b) — тот же узел, что в табе: картинка 56 на линии, цели дня
/// словами, мета «≈ 20 минут · слова, фразы, разговор».
class _Ready extends StatelessWidget {
  const _Ready({required this.plan});

  final Plan plan;

  @override
  Widget build(BuildContext context) => PlanRoute(plan: plan, preview: true);
}

/// СБОРКА (22-4a) — три узла 34 на линии, появляющиеся по очереди, и срок словами.
///
/// «Около 10 секунд» вместо процента: процент у запроса к модели врёт, а человеческий срок — нет.
class _Building extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 14),
      child: Column(
        children: [
          const _GrowingNodes(),
          const SizedBox(height: 16),
          Text(
            l.planEntryPreviewAbout,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFonts.literata,
              fontSize: 22,
              fontWeight: FontWeight.w500,
              height: 1.2,
              color: AppColors.ink,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            l.planEntryPreviewLoadingSub,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 15,
              height: 1.45,
              color: AppColors.secondary,
            ),
          ),
        ],
      ),
    );
  }
}

/// Три узла 34: пройденный латунью с галкой, текущий латунным контуром, будущий пунктиром.
/// Шаг появления 900 мс — столько же, сколько человек смотрит на каждый.
class _GrowingNodes extends StatefulWidget {
  const _GrowingNodes();

  @override
  State<_GrowingNodes> createState() => _GrowingNodesState();
}

class _GrowingNodesState extends State<_GrowingNodes> {
  int _grown = 1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Под «уменьшением движения» и в снимке стоят все три — кадр не должен зависеть от секунды.
    if (MediaQuery.of(context).disableAnimations) _grown = 2;
  }

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      for (var i = 0; i < 3; i++) ...[
        if (i > 0)
          Container(
            width: 34,
            height: 1.5,
            color: i <= _grown ? AppColors.brassInk : AppColors.ink.withValues(alpha: .22),
          ),
        Container(
          width: 34,
          height: 34,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: i < _grown ? AppColors.brassInk : AppColors.ground,
            border: i == _grown ? Border.all(color: AppColors.brassInk, width: 2) : null,
          ),
          child: i < _grown
              ? const Icon(LucideIcons.check, size: 16, color: AppColors.paper)
              : (i > _grown
                    ? DecoratedBox(
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: AppColors.ink.withValues(alpha: .28),
                            width: 1.5,
                          ),
                        ),
                        child: const SizedBox(width: 34, height: 34),
                      )
                    : null),
        ),
      ],
    ],
  );
}

/// ИЗВЕЩЕНИЕ ПРЕВЬЮ (22-4c, 22-4d) — значок 40, что случилось, что уцелело, одно действие.
class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.title,
    required this.sub,
    required this.action,
    required this.onAction,
  });

  final PlanIcon icon;
  final String title, sub, action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      PlanIconMark(
        icon: icon,
        size: 40,
        color: icon == PlanIcon.noticeFailed
            ? AppColors.destructiveText
            : AppColors.brassInk,
      ),
      const SizedBox(height: 16),
      Text(
        title,
        style: const TextStyle(
          fontFamily: AppFonts.literata,
          fontSize: 22,
          fontWeight: FontWeight.w500,
          height: 1.2,
          color: AppColors.ink,
        ),
      ),
      const SizedBox(height: 8),
      Text(
        sub,
        style: const TextStyle(
          fontFamily: AppFonts.inter,
          fontSize: 15,
          height: 1.45,
          color: AppColors.secondary,
        ),
      ),
      const SizedBox(height: 20),
      Semantics(
        button: true,
        label: action,
        child: Material(
          color: AppColors.ink,
          borderRadius: BorderRadius.circular(14),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () {
              AppHaptics.light();
              onAction();
            },
            child: Container(
              height: 44,
              padding: const EdgeInsets.symmetric(horizontal: 20),
              alignment: Alignment.center,
              child: Text(
                action,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: AppColors.paper,
                ),
              ),
            ),
          ),
        ),
      ),
    ],
  );
}
