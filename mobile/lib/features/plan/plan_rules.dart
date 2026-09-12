import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// ТРИ ПРАВИЛА ПЛАНА — один текст и одни значки на витрине (21-1) и в листе «Как устроен план»
/// (21-8).
///
/// Отдельным файлом именно потому, что канва требует их СОВПАДЕНИЯ: «тот же текст и те же иконки,
/// что в листе 21-8». Два списка в двух файлах разошлись бы на первой же правке формулировки, и
/// человек прочёл бы на витрине одно обещание, а в листе другое.
class PlanRules extends StatelessWidget {
  const PlanRules({super.key, this.stageRow = false});

  /// Лист 21-8 ставит под правилом про этапы ОТДЕЛЬНОЙ СТРОКОЙ ряд пяти значков этапов — «чтобы
  /// правило было видно, а не только прочитано». На витрине этого ряда нет.
  final bool stageRow;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final rules = <(PlanIcon, String)>[
      (PlanIcon.ruleSituation, l.planRuleSituation),
      (PlanIcon.ruleStages, l.planRuleStages),
      (PlanIcon.ruleReturn, l.planRuleReturn),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final (icon, text) in rules) ...[
          if (icon != rules.first.$1) const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 1),
                child: PlanIconMark(icon: icon, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      text,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 15,
                        height: 1.4,
                        color: AppColors.ink,
                      ),
                    ),
                    if (stageRow && icon == PlanIcon.ruleStages) ...[
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          for (final kind in PlanStageMarkKind.values) ...[
                            if (kind != PlanStageMarkKind.values.first)
                              const SizedBox(width: 12),
                            PlanStageMark(
                              kind: kind,
                              state: PlanStageMarkState.current,
                              onDark: false,
                            ),
                          ],
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}
