import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/languages.dart';
import '../../../data/plan/plan_models.dart';
import 'entry_scaffold.dart';
import 'entry_tape.dart';

/// ШАГ ЯЗЫКА И УРОВНЯ (кадр 22-2): the languages the app studies as paper chips, the two level
/// cards with the ink fill for the chosen one, and — when «Средний» was preselected off the
/// profile — the one line saying intermediate is enough.
class EntryLanguageStep extends StatelessWidget {
  const EntryLanguageStep({
    super.key,
    required this.tape,
    required this.languages,
    required this.targetLang,
    required this.level,
    required this.fluentNote,
    required this.onLanguage,
    required this.onLevel,
  });

  final List<EntryTapeRow> tape;
  final List<Language> languages;
  final String targetLang;
  final PlanLevel level;

  /// «Для подготовки к ситуации среднего уровня достаточно» — shown while «Средний» stands.
  final bool fluentNote;
  final ValueChanged<String> onLanguage;
  final ValueChanged<PlanLevel> onLevel;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Чип называет язык в языке интерфейса — «Английский», как в таблице канваса
    // (`entry.language.name`, кадр 22-2), а не эндонимом: это строка ПРО язык, а не на нём.
    final ui = Localizations.localeOf(context).languageCode;

    return EntryContent(
      children: [
        EntryTape(rows: tape),
        const SizedBox(height: 22),
        EntryQuestion(l.planEntryLanguageTitle),
        const SizedBox(height: 16),
        ChipWrap(
          children: [
            for (final lang in languages)
              AppChip(
                label: languageNameFor(lang.code, ui),
                paper: true,
                selected: lang.code == targetLang,
                onTap: () => onLanguage(lang.code),
              ),
          ],
        ),
        const SizedBox(height: 26),
        Text(
          l.planEntryLevelLabel.toUpperCase(),
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.99,
            color: AppColors.tertiary,
          ),
        ),
        const SizedBox(height: 10),
        ChoiceCard(
          title: l.planEntryLevelBeginner,
          subtitle: l.planEntryLevelBeginnerSub,
          selected: level == PlanLevel.beginner,
          onTap: () => onLevel(PlanLevel.beginner),
        ),
        const SizedBox(height: 10),
        ChoiceCard(
          title: l.planEntryLevelIntermediate,
          subtitle: l.planEntryLevelIntermediateSub,
          selected: level == PlanLevel.intermediate,
          onTap: () => onLevel(PlanLevel.intermediate),
        ),
        if (fluentNote && level == PlanLevel.intermediate) ...[
          const SizedBox(height: 14),
          Text(
            l.planEntryLevelFluentNote,
            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.45, color: AppColors.tertiary),
          ),
        ],
      ],
    );
  }
}
