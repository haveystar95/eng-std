/// THE PLAN'S OWN MATERIALS — the handful of shapes every plan screen is built from.
///
/// One rule holds the whole set together and it comes straight off the frames («Фаза 4»):
/// **латунь — служебная метка плана, терракота — единственное действие.** Brass says «this belongs
/// to the plan» and never «press me»; the one terracotta thing on a screen is the one thing to do.
/// Everything here is either a brass mark or a surface, and none of them is a button.
library;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import 'package:eng_std/data/config.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan_models.dart' show PlanDayState;
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// A brass caption — «ПЛАН · ДЕНЬ 2 ИЗ 3», «АКТИВНЫЙ ПЛАН», «ДЕНЬ 1».
///
/// 11 / 700 / .14em, uppercased by the widget rather than by the caller, so a translator cannot
/// forget it and a Russian string with a lowercase «плана» in the middle still comes out as a label.
class PlanLabel extends StatelessWidget {
  const PlanLabel(this.text, {super.key, this.color = AppColors.brassInk, this.fontSize = 11});

  final String text;
  final Color color;
  final double fontSize;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: AppText.blockLabel.copyWith(
      color: color,
      fontSize: fontSize,
      letterSpacing: fontSize * 0.14,
    ),
  );
}

/// The plan's brass PILL — the mark the session header wears («План · День 2»), and the one the
/// rehearsal wears («Репетиция · приём сегодня»). Outline, never a fill: a filled brass badge would
/// read as the screen's action.
class PlanPill extends StatelessWidget {
  const PlanPill(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    constraints: const BoxConstraints(minHeight: 24),
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: AppColors.brassFrame),
    ),
    child: PlanLabel(text, fontSize: 11),
  );
}

/// The dark plate — the plan as «купленная вещь»: the preview's header, the plan tab's headline,
/// the day's opened conversation.
///
/// The same gradient the session tile on the home screen wears, because the token list gives the
/// dark surface exactly one recipe and a second flat-black plate beside it would read as a
/// different material rather than as the same one.
class PlanPlate extends StatelessWidget {
  const PlanPlate({super.key, required this.child, this.padding = const EdgeInsets.all(22)});

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [AppColors.plateTop, AppColors.plateBottom],
      ),
      borderRadius: BorderRadius.circular(18),
      boxShadow: AppShadows.plate,
    ),
    padding: padding,
    child: child,
  );
}

/// Brass-outlined paper — the plan card on the home screen (кадр 08) and the «срок мал»
/// recommendation (Б-05). A recommendation, not an alarm: there is no red anywhere in this product
/// and a plan that is behind is still a plan the learner may keep.
class PlanBrassCard extends StatelessWidget {
  const PlanBrassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.fromLTRB(20, 18, 20, 18),
    this.onTap,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(18);
    Widget content = Padding(padding: padding, child: child);
    if (onTap != null) {
      content = InkWell(onTap: onTap, borderRadius: radius, child: content);
    }

    return DecoratedBox(
      decoration: BoxDecoration(
        color: AppColors.brassWash,
        borderRadius: radius,
        border: Border.all(color: AppColors.brassFrame),
      ),
      child: Material(type: MaterialType.transparency, borderRadius: radius, child: content),
    );
  }
}

class PlanAbilityRow extends StatelessWidget {
  const PlanAbilityRow({
    super.key,
    required this.text,
    required this.hit,
    this.trailing,
    this.divider = true,
  });

  final String text;
  final bool hit;
  final Widget? trailing;
  final bool divider;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 12),
    decoration: divider
        ? const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.dividerFaint)))
        : null,
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 18,
          child: Text(
            hit ? '✓' : '—',
            style: AppText.translation.copyWith(
              fontSize: 14,
              color: hit ? AppColors.brassInk : AppColors.tertiary,
            ),
          ),
        ),
        Expanded(
          child: Text(
            text,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.4,
              color: hit ? AppColors.ink : AppColors.secondary,
            ),
          ),
        ),
        if (trailing != null) ...[const SizedBox(width: AppSpacing.s12), trailing!],
      ],
    ),
  );
}

/// The readiness bar — brass on a track, and never a percentage twice on one line.
class PlanReadinessBar extends StatelessWidget {
  const PlanReadinessBar({super.key, required this.value, this.onDark = false});

  final double value;
  final bool onDark;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(2),
    child: LinearProgressIndicator(
      value: value.clamp(0.0, 1.0),
      minHeight: 4,
      backgroundColor: onDark ? AppColors.paper.withValues(alpha: 0.18) : AppColors.track,
      valueColor: AlwaysStoppedAnimation(onDark ? AppColors.brass : AppColors.brassInk),
    ),
  );
}

/// THE DOT THAT SAYS «ЭТО СЕЙЧАС» — a slow pulse on the step being worked on.
///
/// A list of statuses where the finished ones are filled and the rest are outlines cannot say WHICH
/// one is in flight, so a generation that takes forty seconds reads as a screen that has stopped.
/// The pulse is the whole answer: it is on exactly one row at a time, it needs no percentage it
/// cannot honestly give, and it stops the moment that row is done.
///
/// Slow on purpose — 1.1s each way, opacity only. A fast blink is an alarm, and nothing here is
/// wrong; the plan is being written. Under reduce-motion it holds at full strength: the learner
/// still needs to see which row is current, and that is a fact, not an animation.
class PlanPulsingDot extends StatefulWidget {
  const PlanPulsingDot({super.key, required this.color, this.size = 15});

  final Color color;
  final double size;

  @override
  State<PlanPulsingDot> createState() => _PlanPulsingDotState();
}

class _PlanPulsingDotState extends State<PlanPulsingDot> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final dot = DecoratedBox(
      decoration: BoxDecoration(shape: BoxShape.circle, color: widget.color),
      child: SizedBox(width: widget.size, height: widget.size),
    );

    if (MediaQuery.of(context).disableAnimations) return dot;

    return FadeTransition(
      // Never to zero: a dot that disappears reads as a row that was removed. It breathes between
      // «here» and «here, quietly».
      opacity: Tween<double>(begin: 0.28, end: 1).animate(
        CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
      ),
      child: dot,
    );
  }
}

/// «● Собираю…» — a pulsing dot beside a line of text, for a wait with no steps to show.
///
/// The two paid calls the learner watches from a button — building the skeleton and rebuilding it —
/// take fifteen to thirty seconds behind a greyed-out label. A label alone cannot tell «working»
/// from «stuck»; the dot can, and it is the same dot the day's steps use, so the whole feature has
/// one way of saying «сейчас».
class PlanBusyLine extends StatelessWidget {
  const PlanBusyLine({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisAlignment: MainAxisAlignment.center,
    children: [
      const PlanPulsingDot(color: AppColors.brassInk, size: 7),
      const SizedBox(width: AppSpacing.s8),
      Flexible(
        child: Text(
          text,
          style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.brassInk),
        ),
      ),
    ],
  );
}

/// A quiet screen-level message with an optional action — «нет сети», «план не найден».
class PlanNotice extends StatelessWidget {
  const PlanNotice({super.key, required this.text, this.actionLabel, this.onAction});

  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenHWide),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            text,
            textAlign: TextAlign.center,
            style: AppText.translation.copyWith(
              fontSize: 15,
              height: 1.5,
              color: AppColors.secondary,
            ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: AppSpacing.s16),
            TextButton(
              onPressed: onAction,
              child: Text(
                actionLabel!,
                style: AppText.translation.copyWith(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.destructiveText,
                ),
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

/// ЗРЕЛОСТЬ ПЛАНА СЛОВАМИ — «Знакомишься с материалом · 21 из 51 карточки».
///
/// Канон §2 знает у материала три состояния: познакомился → применяю → говорю сам. Это язык, на
/// котором о плане говорит ПРОДУКТ; «ступень A», «rung», «stage» — язык, на котором о нём говорит
/// код, и на экранах его больше нет нигде (наряд DAY-2-FIX, Ч.2.1 и Ч.3а).
///
/// Считается по тому, что сервер действительно шлёт (`stage_census`), и ничего сверх того не
/// обещает: пока хоть одна карточка не закрыла знакомство — «Знакомишься с материалом»; когда все
/// закрыли — «Применяешь в разговоре». Третьего состояния здесь нет и быть не может, потому что
/// перепись ступени C на провод не приезжает, а вердикт, выведенный из данных, которых нет, — это
/// ровно тот процент, который владелец три дня читал нулём.
String planMaturityVerdict(AppLocalizations l, {required int total, required int closed}) =>
    total > 0 && closed >= total ? l.planMaturityApplying : l.planMaturityMeeting;

/// ОДНО СЛОВО О ДНЕ — «не начат» / «идёт · около N минут» / «пройден» (наряд DAY-FIX-2, Ч.3).
///
/// Слово и минуты приходят с сервера; здесь они только переводятся. Три экрана — вкладка «План»,
/// экран дня и шапка присеста — зовут ОДНУ функцию, чтобы «идёт» на одном не стало «продолжить ·
/// осталось 1» на другом. Минуты — единственная цифра, которую плановые экраны говорят вслух.
String planDayStateWord(AppLocalizations l, PlanDayState state, int minutesLeft) => switch (state) {
  PlanDayState.notStarted => minutesLeft > 0
      ? '${l.planStateNotStarted} · ${l.planStateMinutes(minutesLeft)}'
      : l.planStateNotStarted,
  PlanDayState.inProgress => minutesLeft > 0
      ? '${l.planStateInProgress} · ${l.planStateMinutes(minutesLeft)}'
      : l.planStateInProgress,
  PlanDayState.done => l.planStateDone,
};

/// ТО ЖЕ СЛОВО, КНОПКОЙ: «Начать день» / «Продолжить» / «Пройти ещё раз». Кнопка у сегодняшнего
/// дня обязана говорить то же, что слово состояния рядом с ним (наряд DAY-FIX-2, идеал).
String planDayAction(AppLocalizations l, PlanDayState state) => switch (state) {
  PlanDayState.notStarted => l.planRowStartDay,
  PlanDayState.inProgress => l.planSittingContinue,
  PlanDayState.done => l.planDayRepeat,
};

/// ЗРЕЛОСТЬ ОДНОЙ СЦЕНЫ СЛОВОМ — три состояния канона §2 (наряд SCENE-RUN, Ч.3).
///
/// Слово приходит с сервера кодом, и экран его только переводит: «говоришь сам» стоит на переписи
/// ступени C, которой у телефона нет. Код, которого эта сборка не знает, честнее показать первым
/// состоянием, чем угадать: «познакомился» верно про любую сцену, до которой человек дошёл.
String planSceneMaturity(AppLocalizations l, String maturity) => switch (maturity) {
  'speaking' => l.planMaturitySpeaking,
  'applying' => l.planMaturityApplying,
  _ => l.planMaturityMeeting,
};

/// «2 сентября» — an event date written the way a person says it.
///
/// The plan speaks in DATES and in «через N дней», never in a countdown of hours: the event is a day
/// in the learner's calendar, and an app that said «через 47 часов» would be describing its own
/// clock rather than their appointment.
/// The same label, for a plan that may have no date at all — «Без даты» (кадры V4·04б, 06б).
///
/// One helper rather than a `?? l.planNoDate` at each of the eight call sites: a plan with no date
/// is a state every screen that prints one has to answer for, and eight private answers is how one
/// of them ends up printing an empty string where a date was.
String planDateOrNone(BuildContext context, String? isoDate, {bool short = false}) {
  final date = isoDate?.trim() ?? '';

  return date.isEmpty
      ? AppLocalizations.of(context).planNoDate
      : planDateLabel(context, date, short: short);
}

/// The weekday under a chosen date — «ПОНЕДЕЛЬНИК» (кадр V4·04).
///
/// Its own helper rather than a flag on [planDateLabel], because it is a different fact: the date
/// answers «когда», the weekday answers «а это вообще рабочий день», and the frame sets them in two
/// different styles for exactly that reason.
String planWeekdayLabel(BuildContext context, String isoDate) {
  final parsed = DateTime.tryParse(isoDate);
  if (parsed == null) return '';

  return DateFormat('EEEE', Localizations.localeOf(context).languageCode).format(parsed);
}

String planDateLabel(BuildContext context, String isoDate, {bool short = false}) {
  final parsed = DateTime.tryParse(isoDate);
  if (parsed == null) return isoDate;
  final locale = Localizations.localeOf(context).languageCode;

  // `intl` and not a table of month names here: a month name is UI copy in a Russian genitive
  // («2 сентября»), and copy does not live in a Dart file — the cyrillic guard says so, and it is
  // right. The same call the home screen's «Следующий повтор» line already makes.
  return DateFormat(short ? 'd MMM' : 'd MMMM', locale).format(parsed);
}

/// «ОЗВУЧКА НЕ ДОЕХАЛА» — дев-бейдж, и он существует ради одного класса дефектов.
///
/// Труба падает ТИХО по построению: файла нет — читает системный синтез, урок идёт дальше. Значит
/// сломанная труба выглядит ровно как выключенная, и живой прогон TTS-1 это доказал: сервер отдавал
/// `http://`, iOS резал запрос по ATS, ВСЕ реплики на телефоне звучали системным голосом — на всех
/// экранах сразу, — и ни один экран об этом не сказал.
///
/// Поэтому в дев-сборке молчание становится видимым. В релизе виджет не рисуется вовсе: человеку,
/// который учит язык, нечего делать с «3 файла не скачалось», и правильное поведение для него —
/// именно то тихое, что уже есть.
///
/// Один виджет на все экраны, потому что дефект общий: путь «пейлоад → докачка → произноситель»
/// один, и различаться по экранам ему нечем.
class PlanVoiceTrouble extends StatelessWidget {
  const PlanVoiceTrouble({super.key, required this.cache});

  final LineAudioCache cache;

  @override
  Widget build(BuildContext context) {
    final trouble = cache.trouble;
    if (!AppConfig.devMenuEnabled || (trouble.downloads == 0 && trouble.silentFallbacks == 0)) {
      return const SizedBox.shrink();
    }

    final l = AppLocalizations.of(context);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.destructiveText.withValues(alpha: .45)),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.devVoiceTrouble(trouble.silentFallbacks, trouble.downloads),
            style: AppText.blockLabel.copyWith(color: AppColors.destructiveText, letterSpacing: .3),
          ),
          if (trouble.lastReason != null)
            Text(
              trouble.lastReason!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: AppText.translation.copyWith(fontSize: 11.5, color: AppColors.tertiary),
            ),
        ],
      ),
    );
  }
}
