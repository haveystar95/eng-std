import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/languages.dart';
import '../../../data/plan/plan_models.dart';
import 'entry_scaffold.dart';
import 'entry_section.dart';
import 'entry_summary.dart';

/// ШАГ ЯЗЫКА И УРОВНЯ (кадр 22-2).
///
/// Канва вычла шкалу A1–C1 и тест на уровень: уровень описан ТЕМ, ЧТО ЧЕЛОВЕК УМЕЕТ — «понимаю
/// простую речь, говорю с ошибками», — а не буквой, которую надо помнить. Языки тоже перестали
/// быть чипами: у каждого своя карточка с монограммой в кружке 36 и ПРИВЕТСТВИЕМ на нём
/// («Hello, how are you?») — так видно, на что подписываешься.
///
/// Цель стоит сводкой в шапке и открывается тапом по строке: возвращаться шагами не надо.
class EntryLanguageStep extends StatelessWidget {
  const EntryLanguageStep({
    super.key,
    required this.goal,
    required this.languages,
    required this.targetLang,
    required this.level,
    required this.onLanguage,
    required this.onLevel,
    required this.onEditGoal,
  });

  final String goal;
  final List<Language> languages;
  final String targetLang;
  final PlanLevel level;
  final ValueChanged<String> onLanguage;
  final ValueChanged<PlanLevel> onLevel;
  final VoidCallback onEditGoal;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Язык назван в языке интерфейса — «Английский», а не эндонимом: это строка ПРО язык.
    final ui = Localizations.localeOf(context).languageCode;

    return EntryContent(
      children: [
        EntryQuestion(l.planEntryLanguageTitle),
        const SizedBox(height: 14),
        EntrySummaryRow(
          icon: PlanIcon.goal,
          label: l.planEntryTapeGoal,
          value: goal,
          onTap: onEditGoal,
          twoLines: true,
          last: true,
        ),
        const SizedBox(height: 32),
        EntrySectionLabel(l.planEntryLanguageLabel),
        const SizedBox(height: 10),
        for (final lang in languages) ...[
          if (lang != languages.first) const SizedBox(height: 8),
          ChoiceCard(
            title: languageNameFor(lang.code, ui),
            subtitle: greetingFor(lang.code),
            selected: lang.code == targetLang,
            onTap: () => onLanguage(lang.code),
            leading: ChoiceCardMark(
              selected: lang.code == targetLang,
              text: monogramFor(lang.code),
            ),
          ),
        ],
        const SizedBox(height: 32),
        EntrySectionLabel(l.planEntryLevelLabel),
        const SizedBox(height: 10),
        // «Средний» стоит первым: канва ставит его сверху, потому что он и выбран по умолчанию —
        // предвыбранный вариант не должен стоять вторым.
        _LevelCard(
          selected: level == PlanLevel.intermediate,
          title: l.planEntryLevelIntermediate,
          subtitle: l.planEntryLevelIntermediateSub,
          // Две полоски у «Среднего», одна у «Начального» — значок говорит то же, что подпись.
          mark: PlanIcon.level2,
          onTap: () => onLevel(PlanLevel.intermediate),
        ),
        const SizedBox(height: 8),
        _LevelCard(
          selected: level == PlanLevel.beginner,
          title: l.planEntryLevelBeginner,
          subtitle: l.planEntryLevelBeginnerSub,
          mark: PlanIcon.level1,
          onTap: () => onLevel(PlanLevel.beginner),
        ),
      ],
    );
  }
}

/// Карточка уровня — тот же выбор, но в кружке стоит значок полосок (ассеты серии 22).
class _LevelCard extends StatelessWidget {
  const _LevelCard({
    required this.selected,
    required this.title,
    required this.subtitle,
    required this.mark,
    required this.onTap,
  });

  final bool selected;
  final String title, subtitle;
  final PlanIcon mark;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ChoiceCard(
    title: title,
    subtitle: subtitle,
    selected: selected,
    onTap: onTap,
    leading: ChoiceCardMark(
      selected: selected,
      child: PlanIconMark(icon: mark, color: ChoiceCardMark.markColor(selected)),
    ),
  );
}
