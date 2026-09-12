import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'entry_scaffold.dart';
import 'entry_state.dart';
import 'entry_summary.dart';

/// ШАГ ДЛИНЫ ПЛАНА (кадр 22-3a) — четыре длины, и под каждой СЛОВАМИ, из чего она состоит.
///
/// Канва вычла ползунок и слово «интенсивность»: человек выбирает не силу, а длину, и видит, что
/// повторение и репетиция уже ВНУТРИ — считать их самому не надо. Слева у каждой длины мини-маршрут
/// точками: ситуации ink, повторения латунью, репетиция контуром.
///
/// Состав каждой длины — таблица канвы, а не формула: сервер отдаёт `route_summary` только у
/// готового плана, а плана на этом шаге ещё нет. Четыре длины — четыре известных состава, и
/// придумывать правило «сколько повторений на N дней» клиенту нечем.
class EntryDaysStep extends StatelessWidget {
  const EntryDaysStep({
    super.key,
    required this.goal,
    required this.languageValue,
    required this.days,
    required this.onDays,
    required this.onEditGoal,
    required this.onEditLanguage,
  });

  final String goal;

  /// «Английский · средний» — сводка предыдущего шага.
  final String languageValue;

  final int days;
  final ValueChanged<int> onDays;
  final VoidCallback onEditGoal;
  final VoidCallback onEditLanguage;

  /// Состав плана по длине: сколько ситуаций и сколько дней повторения (репетиция всегда одна).
  ///
  /// У плана на три дня повторения НЕТ — повторять нечего, пока ситуаций всего две.
  static const Map<int, ({int scenes, int reviews})> composition = {
    3: (scenes: 2, reviews: 0),
    5: (scenes: 3, reviews: 1),
    7: (scenes: 4, reviews: 2),
    10: (scenes: 6, reviews: 3),
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return EntryContent(
      children: [
        EntryQuestion(l.planEntryDaysTitle),
        const SizedBox(height: 14),
        EntrySummaryRow(
          icon: PlanIcon.goal,
          label: l.planEntryTapeGoal,
          value: goal,
          onTap: onEditGoal,
          twoLines: true,
        ),
        EntrySummaryRow(
          icon: PlanIcon.globe,
          label: l.planEntryTapeLanguage,
          value: languageValue,
          onTap: onEditLanguage,
          last: true,
        ),
        const SizedBox(height: 32),
        for (final n in EntryState.dayChoices) ...[
          if (n != EntryState.dayChoices.first) const SizedBox(height: 8),
          ChoiceCard(
            title: l.planDaysCount(n),
            subtitle: _composition(l, n),
            selected: n == days,
            onTap: () => onDays(n),
            trailingCheck: false,
            leading: _MiniRoute(days: n, selected: n == days),
          ),
        ],
      ],
    );
  }

  /// «3 ситуации, 1 повторение, репетиция» — репетиция названа всегда, повторения только если есть.
  String _composition(AppLocalizations l, int n) {
    final c = composition[n];
    if (c == null) return '';
    final scenes = l.planEntryDaysScenes(c.scenes);
    final rehearsal = l.planEntryDaysRehearsal;

    return c.reviews == 0
        ? '$scenes, $rehearsal'
        : '$scenes, ${l.planEntryDaysReviews(c.reviews)}, $rehearsal';
  }
}

/// МИНИ-МАРШРУТ длины — точки 6 в колонке 88: ситуации ink, повторения латунью, репетиция контуром.
///
/// Порядок тот же, что построит сервер: две ситуации, затем день повторения, и так до репетиции —
/// поэтому по точкам видно не только СКОЛЬКО дней, но и как они лягут.
class _MiniRoute extends StatelessWidget {
  const _MiniRoute({required this.days, required this.selected});

  final int days;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final c = EntryDaysStep.composition[days];
    if (c == null) return const SizedBox(width: 88);

    // Ситуации парами, после каждой пары — повторение; репетиция последней.
    final kinds = <_Dot>[];
    var reviews = c.reviews;
    for (var i = 0; i < c.scenes; i++) {
      kinds.add(_Dot.scene);
      if (i.isOdd && reviews > 0) {
        kinds.add(_Dot.review);
        reviews--;
      }
    }
    while (reviews > 0) {
      kinds.add(_Dot.review);
      reviews--;
    }
    kinds.add(_Dot.rehearsal);

    final ink = selected ? AppColors.paper : AppColors.ink;
    final brass = selected ? AppColors.brass : AppColors.brassInk;
    final link = ink.withValues(alpha: .28);

    return SizedBox(
      width: 88,
      child: Row(
        children: [
          for (var i = 0; i < kinds.length; i++) ...[
            if (i > 0) Container(width: 4, height: 1.5, color: link),
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: switch (kinds[i]) {
                  _Dot.scene => ink,
                  _Dot.review => brass,
                  _Dot.rehearsal => Colors.transparent,
                },
                border: kinds[i] == _Dot.rehearsal
                    ? Border.all(color: ink, width: 1.5)
                    : null,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

enum _Dot { scene, review, rehearsal }
