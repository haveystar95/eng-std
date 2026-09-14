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

/// Где стоит значок: плита таба (21-x) или плита окна дня на фото (23-0a…0c) — у окна свои тона
/// из кадров: пройденный `#9DB89F`, запертый `#BDB6AC` без прозрачности, бейдж на 3 за краем.
enum PlanStageMarkTone { plate, window }

/// ЗНАЧОК ЭТАПА 20 × 20 (кадры 21-2, 21-3, 21-4, 21-8, 22-5b, 23-0a…0c).
///
/// [onDark] — плита: тёмная (текущий этап paper) или светлая бумага закрытого дня (текущий ink).
/// [popBadge] — галка-бейдж только что закрытого этапа появляется `om-check-pop` (окно дня).
class PlanStageMark extends StatelessWidget {
  const PlanStageMark({
    super.key,
    required this.kind,
    required this.state,
    this.onDark = true,
    this.tone = PlanStageMarkTone.plate,
    this.popBadge = false,
  });

  final PlanStageMarkKind kind;
  final PlanStageMarkState state;
  final bool onDark;
  final PlanStageMarkTone tone;
  final bool popBadge;

  @override
  Widget build(BuildContext context) {
    // «пройден — шалфей с галкой 10, текущий — paper на тёмной и ink на светлой, заперт — 35 %».
    final base = onDark ? AppColors.paper : AppColors.ink;
    final window = tone == PlanStageMarkTone.window;
    final (color, opacity) = switch (state) {
      PlanStageMarkState.done => (window ? AppColors.windowDone : AppColors.verdictKnown, 1.0),
      PlanStageMarkState.current => (base, 1.0),
      PlanStageMarkState.locked => window ? (AppColors.windowAhead, 1.0) : (base, .35),
    };
    final badge = Container(
      width: 10,
      height: 10,
      alignment: Alignment.center,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
      child: Icon(LucideIcons.check, size: window ? 6 : 7, color: AppColors.paper),
    );

    return SizedBox(
      width: 20,
      height: 20,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Opacity(opacity: opacity, child: PlanStageGlyph(kind: kind, color: color)),
          // ГАЛКА-БЕЙДЖ 10 у пройденного (21-3, 21-4): шалфейный кружок 10 с галкой 6 бумагой —
          // не просто галка поверх значка, иначе она теряется на самом рисунке.
          if (state == PlanStageMarkState.done)
            Positioned(
              right: window ? -3 : -2,
              bottom: window ? -3 : -2,
              child: popBadge ? CheckPop(child: badge) : badge,
            ),
        ],
      ),
    );
  }
}

/// РИСУНОК ЭТАПА ЛЮБОГО РАЗМЕРА ОДНИМ ЦВЕТОМ — 20 в ряду этапа, 16 в сегменте пилюли вкладок окна
/// (23-0d: «Aa · ff · пузыри»). «Aa» внутри рамки «Слов» — буквами Inter, а не svg-текстом: `<text>`
/// в svg рисуется чужим шрифтом и в снимок попадает по-разному.
class PlanStageGlyph extends StatelessWidget {
  const PlanStageGlyph({super.key, required this.kind, required this.color, this.size = 20});

  final PlanStageMarkKind kind;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: size,
    height: size,
    child: Stack(
      children: [
        SvgPicture.asset(kind.asset, width: size, height: size, colorFilter: ColorFilter.mode(color, BlendMode.srcIn)),
        if (kind == PlanStageMarkKind.words)
          Positioned.fill(
            child: Center(
              child: Text(
                'Aa',
                style: TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 7.5 * size / 20,
                  fontWeight: FontWeight.w600,
                  height: 1,
                  color: color,
                ),
              ),
            ),
          ),
      ],
    ),
  );
}

/// ГАЛКА ПОЯВЛЯЕТСЯ — `@keyframes om-check-pop` канвы: масштаб 0 → 1 за 180 мс с задержкой 300,
/// `cubic-bezier(.34,1.4,.5,1)` (таблица «Тайминг · серия 23»). Под «уменьшением движения» галка
/// стоит сразу.
class CheckPop extends StatelessWidget {
  const CheckPop({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) return child;
    final delay = AppMotion.windowStageCheckDelay.inMilliseconds;
    final total = delay + AppMotion.windowStageCheck.inMilliseconds;

    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: total),
      curve: Interval(delay / total, 1, curve: AppMotion.windowStageCheckCurve),
      builder: (_, scale, child) => Transform.scale(scale: scale, child: child),
      child: child,
    );
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
/// уровень бывает плиткой и двумя пузырями, а календарь — с числом, с вопросом и с плюсом, и это
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
  /// Уровень 24 (канва PLAN-DES-3, 22-2): плитка со словом — «Начальный», два пузыря — «Средний».
  levelBeginner('assets/icons/level-beginner.svg'),
  levelIntermediate('assets/icons/level-intermediate.svg'),
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
