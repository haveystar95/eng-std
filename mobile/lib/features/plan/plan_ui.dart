/// THE PLAN'S OWN MATERIALS — the handful of shapes every plan screen is built from.
///
/// One rule holds the whole set together and it comes straight off the frames («Фаза 4»):
/// **латунь — служебная метка плана, терракота — единственное действие.** Brass says «this belongs
/// to the plan» and never «press me»; the one terracotta thing on a screen is the one thing to do.
/// Everything here is either a brass mark or a surface, and none of them is a button.
library;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

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

/// The stage a word stands on — a brass A / B / C at the right edge of the register.
///
/// A LETTER and not a dot, unlike the acquisition ladder's five dots: the plan's ladder is three
/// named stages the learner is told about by name in the session header, and a second dot language
/// on the same screens would be two vocabularies for one idea.
class PlanStageMark extends StatelessWidget {
  const PlanStageMark(this.letter, {super.key, this.suffix});

  final String letter;

  /// «· со дня 1» — where a carried word came from, set beside the letter in the same brass.
  final String? suffix;

  @override
  Widget build(BuildContext context) => Text(
    suffix == null ? letter : '$letter $suffix',
    style: AppText.translation.copyWith(
      fontSize: 10.5,
      fontWeight: FontWeight.w600,
      letterSpacing: 0.4,
      color: AppColors.brassInk,
    ),
  );
}

/// One line of «Ты уже можешь» / «Что закроем» — a ✓ or an em dash, then the ability.
///
/// The unhit lines are grey and keep their full text: a plan's promises are what the learner bought,
/// and hiding the ones not yet delivered would leave the list looking shorter every time it grew.
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

/// «2 сентября» — an event date written the way a person says it.
///
/// The plan speaks in DATES and in «через N дней», never in a countdown of hours: the event is a day
/// in the learner's calendar, and an app that said «через 47 часов» would be describing its own
/// clock rather than their appointment.
String planDateLabel(BuildContext context, String isoDate, {bool short = false}) {
  final parsed = DateTime.tryParse(isoDate);
  if (parsed == null) return isoDate;
  final locale = Localizations.localeOf(context).languageCode;

  // `intl` and not a table of month names here: a month name is UI copy in a Russian genitive
  // («2 сентября»), and copy does not live in a Dart file — the cyrillic guard says so, and it is
  // right. The same call the home screen's «Следующий повтор» line already makes.
  return DateFormat(short ? 'd MMM' : 'd MMMM', locale).format(parsed);
}
