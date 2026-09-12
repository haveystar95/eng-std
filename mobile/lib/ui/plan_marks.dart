/// ЗНАЧКИ ПЛАНА — три набора из канвы `plan-canvas.dc.html`, и правила их тонировки.
///
/// Канва кладёт в проект svg (`assets/stages/`, `assets/system-days/`, `assets/icons/`) и говорит
/// про них одно и то же три раза, поэтому правило живёт здесь, а не в вызывающих:
///
/// * **этап** (серия 21, «Вычтено» под 21-2): значок 20/1.5 стоит СЛЕВА В СТРОКЕ вместо кружка, и
///   состояние он несёт тонировкой — пройден шалфеем с галкой-бейджем 10, текущий paper на тёмной
///   плите и ink на светлой, заперт 35 %. Кружков-маркеров нет ни в одной строке;
/// * **системный день** (21-2b): иллюстрация 34 внутри круга узла 56 — у повторения, репетиции и
///   события своя, а не общая заглушка;
/// * **иконка серии 22** (список ассетов): 20 × 20, линия 1.5, латунь #8C6A3A на бумаге и paper на
///   угольной плите, 12 до текста, и «никогда не единственный носитель смысла» — подпись словами
///   рядом остаётся всегда, поэтому ни один из этих виджетов не умеет стоять без текста.
///
/// Ни один значок не читает `AppLocalizations`: `lib/ui/` языков не знает.
library;

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'package:eng_std/theme/theme.dart';

/// Пять этапов дня — порядок канвы: слова → фразы → диалог → слушаю и отвечаю → говорю сам.
enum PlanStageMarkKind {
  words('assets/stages/words.svg'),
  phrases('assets/stages/phrases.svg'),
  dialogue('assets/stages/dialogue.svg'),
  listen('assets/stages/listen.svg'),
  speak('assets/stages/speak.svg');

  const PlanStageMarkKind(this.asset);

  final String asset;
}

/// Состояние этапа — оно же вся его тонировка.
enum PlanStageMarkState { done, current, locked }

/// ЗНАЧОК ЭТАПА 20 × 20 (кадры 21-2, 21-3, 21-4, 21-8, 22-5b).
///
/// [onDark] — плита: тёмная (текущий этап paper) или светлая бумага закрытого дня (текущий ink).
class PlanStageMark extends StatelessWidget {
  const PlanStageMark({
    super.key,
    required this.kind,
    required this.state,
    this.onDark = true,
  });

  final PlanStageMarkKind kind;
  final PlanStageMarkState state;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    // «пройден — шалфей с галкой 10, текущий — paper на тёмной и ink на светлой, заперт — 35 %».
    final base = onDark ? AppColors.paper : AppColors.ink;
    final (color, opacity) = switch (state) {
      PlanStageMarkState.done => (AppColors.verdictKnown, 1.0),
      PlanStageMarkState.current => (base, 1.0),
      PlanStageMarkState.locked => (base, .35),
    };

    final glyph = SizedBox(
      width: 20,
      height: 20,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Opacity(
            opacity: opacity,
            child: SvgPicture.asset(
              kind.asset,
              width: 20,
              height: 20,
              colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
            ),
          ),
          // «Aa» ВНУТРИ РАМКИ ЭТАПА «СЛОВА» — буквами Inter, а не контуром в svg: `<text>` в svg
          // рисуется чужим шрифтом и в снимок попадает по-разному, а рамка без букв читается как
          // пустая карточка. В файле остаётся рамка, буквы ставит этот виджет.
          if (kind == PlanStageMarkKind.words)
            Positioned.fill(
              child: Opacity(
                opacity: opacity,
                child: Center(
                  child: Text(
                    'Aa',
                    style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 7.5,
                      fontWeight: FontWeight.w600,
                      height: 1,
                      color: color,
                    ),
                  ),
                ),
              ),
            ),
          // ГАЛКА-БЕЙДЖ 10 у пройденного (21-3, 21-4): шалфейный кружок 10 с галкой 6 бумагой —
          // не просто галка поверх значка, иначе она теряется на самом рисунке.
          if (state == PlanStageMarkState.done)
            Positioned(
              right: -2,
              bottom: -2,
              child: Container(
                width: 10,
                height: 10,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.verdictKnown,
                ),
                child: const Icon(LucideIcons.check, size: 7, color: AppColors.paper),
              ),
            ),
        ],
      ),
    );

    return glyph;
  }
}

/// Системные дни маршрута — у каждого своя иллюстрация (21-2b).
enum PlanSystemDay {
  review('assets/system-days/review.svg'),
  rehearsal('assets/system-days/rehearsal.svg'),
  event('assets/system-days/event.svg');

  const PlanSystemDay(this.asset);

  final String asset;
}

/// ИЛЛЮСТРАЦИЯ СИСТЕМНОГО ДНЯ 34 внутри круга узла (21-2b, 22-4b).
class PlanSystemDayMark extends StatelessWidget {
  const PlanSystemDayMark({super.key, required this.day, required this.color, this.size = 34});

  final PlanSystemDay day;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SvgPicture.asset(
    day.asset,
    width: size,
    height: size,
    colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
  );
}

/// Иконки серии 22 — по строке, в которой стоят.
///
/// Где канва нарисовала ВАРИАНТЫ одного значка, вариант — отдельное значение, а не параметр:
/// уровень бывает в одну и в две полоски, а календарь — с числом, с вопросом и с плюсом, и это
/// три разных утверждения про дату, не три состояния одной иконки.
enum PlanIcon {
  goal('assets/icons/goal.svg'),
  globe('assets/icons/globe.svg'),
  route('assets/icons/route.svg'),
  calendar('assets/icons/calendar.svg'),

  /// Рамка календаря без числа — число ставит [PlanDateMark] буквами Inter.
  calendarDate('assets/icons/calendar-date.svg'),
  calendarUnknown('assets/icons/calendar-unknown.svg'),
  calendarPlus('assets/icons/calendar-plus.svg'),
  quote('assets/icons/quote.svg'),
  level1('assets/icons/level-1.svg'),
  level2('assets/icons/level-2.svg'),
  mic('assets/icons/mic.svg'),

  /// Значки извещений превью 40 × 40 (22-4c, 22-4d).
  noticeFailed('assets/icons/notice-failed.svg'),
  noticeUnclear('assets/icons/notice-unclear.svg'),

  /// Три значка правил плана 24 × 24 — одни и те же на витрине (21-1) и в листе (21-8).
  ruleSituation('assets/icons/rule-situation.svg'),
  ruleStages('assets/icons/rule-stages.svg'),
  ruleReturn('assets/icons/rule-return.svg');

  const PlanIcon(this.asset);

  final String asset;
}

/// КАЛЕНДАРЬ С ЧИСЛОМ (кадр 22-3b) — рамка из канвы и день месяца буквами Inter внутри.
///
/// Число не в svg по той же причине, что «Aa» у этапа «Слова»: `<text>` в svg рисуется чужим
/// шрифтом, а здесь оно ещё и меняется от выбранной даты.
class PlanDateMark extends StatelessWidget {
  const PlanDateMark({super.key, required this.day, required this.color, this.size = 20});

  /// День месяца — «17».
  final int day;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: size,
    height: size,
    child: Stack(
      children: [
        PlanIconMark(icon: PlanIcon.calendarDate, color: color, size: size),
        Positioned(
          left: 0,
          right: 0,
          // Число сидит в нижней части рамки, под её перекладиной.
          bottom: size * .1,
          child: Text(
            '$day',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: size * .35,
              fontWeight: FontWeight.w700,
              height: 1,
              color: color,
            ),
          ),
        ),
      ],
    ),
  );
}

/// ИКОНКА СТРОКИ 20 × 20 (серия 22): латунь на бумаге, paper на угольной плите.
class PlanIconMark extends StatelessWidget {
  const PlanIconMark({super.key, required this.icon, this.color = AppColors.brassInk, this.size = 20});

  final PlanIcon icon;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SvgPicture.asset(
    icon.asset,
    width: size,
    height: size,
    colorFilter: ColorFilter.mode(color, BlendMode.srcIn),
  );
}
