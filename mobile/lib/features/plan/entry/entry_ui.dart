/// THE ENTRY'S OWN MATERIALS — the shapes серия «Вход v4» is built from.
///
/// One rule from the записка holds the whole series together and it is not the app's usual one:
/// **терракота #9A4430 — только CTA и курсор, латунь — служебная метка плана.** Everywhere else in
/// the app the main button is ink ([PrimaryButton]); inside the plan's entry it is terracotta,
/// because the token list says so out loud (правило 23: «главное действие экрана остаётся
/// терракотовым даже внутри плана») and every frame of the series draws it that way.
///
/// So this file exists rather than a flag on the shared button: the entry is a series with its own
/// CTA, its own disabled state (a 16% terracotta wash, never grey) and its own selected state (warm
/// sand with a brass tick). A boolean on [PrimaryButton] would have spread that decision across the
/// whole app, which is exactly what the token list does not say.
library;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// The one action of an entry screen — «Дальше», «Собрать план», «Начать первый день».
///
/// Disabled is a WASH and not a grey plate: «Неактивная кнопка: заливка 16% терракоты, подпись
/// rgba(46,38,32,.42) — светлая подпись на приглушённой заливке не читается» (записка). The button
/// stays exactly where it was, at the same size — «„Дальше“ приглушена, пока поле пусто, но
/// остаётся на месте» (кадр V4·01).
class EntryCta extends StatelessWidget {
  const EntryCta({
    super.key,
    required this.label,
    this.onPressed,
    this.enabled = true,
    this.minHeight = 54,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool enabled;
  final double minHeight;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onPressed != null;
    final radius = BorderRadius.circular(16);

    return Semantics(
      button: true,
      enabled: on,
      label: label,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: on ? AppColors.destructiveText : AppColors.destructiveText.withValues(alpha: 0.16),
          borderRadius: radius,
          boxShadow: on
              ? [
                  BoxShadow(
                    color: AppColors.destructiveText.withValues(alpha: 0.24),
                    blurRadius: 18,
                    offset: const Offset(0, 6),
                  ),
                ]
              : null,
        ),
        child: Material(
          type: MaterialType.transparency,
          borderRadius: radius,
          child: InkWell(
            borderRadius: radius,
            onTap: on
                ? () {
                    AppHaptics.light();
                    onPressed!();
                  }
                : null,
            child: Container(
              constraints: BoxConstraints(minHeight: minHeight),
              alignment: Alignment.center,
              padding: const EdgeInsets.symmetric(horizontal: 18),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[
                    Icon(
                      icon,
                      size: 15,
                      color: on ? AppColors.paper : AppColors.ink.withValues(alpha: 0.42),
                    ),
                    const SizedBox(width: 9),
                  ],
                  Flexible(
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      style: AppText.translation.copyWith(
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                        color: on ? AppColors.paper : AppColors.ink.withValues(alpha: 0.42),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// The second, quiet action — «Пропустить», «Изменить ответы», «Написать нам».
///
/// Deliberately the SAME HEIGHT as [EntryCta] wherever the frame pairs them («„Пропустить“ такой же
/// высоты и без уговоров», кадр V4·03): a skip that is visibly smaller than the offer is a skip the
/// screen is arguing against.
class EntrySecondary extends StatelessWidget {
  const EntrySecondary({
    super.key,
    required this.label,
    this.onPressed,
    this.outlined = true,
    this.minHeight = 54,
    this.enabled = true,
    this.footnote,
  });

  final String label;
  final VoidCallback? onPressed;

  /// An outlined button (paired with the CTA) or a bare link (under it).
  final bool outlined;
  final double minHeight;
  final bool enabled;

  /// The honest line under a button that cannot work — «Уведомления ещё не подключены».
  final String? footnote;

  @override
  Widget build(BuildContext context) {
    final on = enabled && onPressed != null;
    final radius = BorderRadius.circular(16);

    final child = Container(
      constraints: BoxConstraints(minHeight: minHeight),
      alignment: Alignment.center,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Text(
        label,
        textAlign: TextAlign.center,
        style: AppText.translation.copyWith(
          fontSize: outlined ? 16 : 15,
          color: on ? (outlined ? AppColors.ink : AppColors.secondary) : AppColors.planInactive,
        ),
      ),
    );

    final button = Semantics(
      button: true,
      enabled: on,
      child: Material(
        type: MaterialType.transparency,
        borderRadius: radius,
        child: InkWell(
          borderRadius: radius,
          onTap: on
              ? () {
                  AppHaptics.light();
                  onPressed!();
                }
              : null,
          child: outlined
              ? DecoratedBox(
                  decoration: BoxDecoration(
                    borderRadius: radius,
                    border: Border.all(color: on ? AppColors.track : AppColors.dividerFaint),
                    color: AppColors.surfaceRaised,
                  ),
                  child: child,
                )
              : child,
        ),
      ),
    );

    if (footnote == null) return button;

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        button,
        const SizedBox(height: 6),
        Text(
          footnote!,
          textAlign: TextAlign.center,
          style: AppText.translation.copyWith(fontSize: 12, color: AppColors.tertiary),
        ),
      ],
    );
  }
}

/// The header every step of the entry wears: back, a brass kicker, and the progress dots.
///
/// FOUR dots, always, even when the listening step will not be offered. The step exists in the flow
/// whether or not the warm-up could be written, and a header that grew a dot halfway through would
/// tell the learner the road got longer.
class EntryHeader extends StatelessWidget {
  const EntryHeader({
    super.key,
    required this.kicker,
    required this.step,
    required this.steps,
    this.onBack,
    this.trailing,
    this.dotsAllDone = false,
  });

  final String kicker;

  /// 1-based. The dark dot.
  final int step;
  final int steps;
  final VoidCallback? onBack;

  /// «Хватит» on the player — the way out of a step, in the place the back chevron would be mirrored.
  final Widget? trailing;

  /// Every dot brass — «три латунные точки закрылись: шаг прожит» (кадр V4·03в).
  final bool dotsAllDone;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 44,
    // «Три латунные точки закрылись» stand in the MIDDLE of the header on the verdict frame
    // (V4·03в) and at the right edge on every step of the flow. One header, two placements,
    // decided by whether there is anything else in the row to hold the line.
    child: kicker.isEmpty && onBack == null && trailing == null
        ? Center(
            child: EntryDots(step: step, steps: steps, allDone: dotsAllDone),
          )
        : Row(
            children: [
              SizedBox(
                width: 24,
                child: onBack == null
                    ? null
                    : Semantics(
                        button: true,
                        child: InkResponse(
                          onTap: onBack,
                          radius: 22,
                          child: const Icon(
                            Icons.chevron_left,
                            size: 22,
                            color: AppColors.secondary,
                          ),
                        ),
                      ),
              ),
              Expanded(
                child: Center(
                  child: kicker.isEmpty
                      ? const SizedBox.shrink()
                      : Text(
                          kicker.toUpperCase(),
                          style: AppText.blockLabel.copyWith(
                            fontSize: 10.5,
                            letterSpacing: 1.9,
                            color: AppColors.brassInk,
                          ),
                        ),
                ),
              ),
              SizedBox(
                width: 78,
                child: Align(
                  alignment: Alignment.centerRight,
                  child: trailing ?? EntryDots(step: step, steps: steps, allDone: dotsAllDone),
                ),
              ),
            ],
          ),
  );
}

/// The progress dots — «точки прогресса не переезжают: активная перекрашивается за 180 мс».
class EntryDots extends StatelessWidget {
  const EntryDots({super.key, required this.step, required this.steps, this.allDone = false});

  final int step, steps;
  final bool allDone;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      for (var i = 1; i <= steps; i++) ...[
        if (i > 1) const SizedBox(width: 7),
        AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          width: 6,
          height: 6,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: allDone
                ? AppColors.brass
                : (i == step ? AppColors.ink : AppColors.ink.withValues(alpha: 0.16)),
          ),
        ),
      ],
    ],
  );
}

/// A small-caps section label — «ТАК ТОЖЕ ПОДХОДИТ», «КАК ОЩУЩАЕТСЯ».
class EntryOverline extends StatelessWidget {
  const EntryOverline(this.text, {super.key, this.color = AppColors.tertiary, this.center = false});

  final String text;
  final Color color;
  final bool center;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    textAlign: center ? TextAlign.center : TextAlign.start,
    style: AppText.blockLabel.copyWith(fontSize: 10.5, letterSpacing: 1.7, color: color),
  );
}

/// A tappable suggestion card — an example goal, an addition, a ready continuation.
///
/// «Тап заполняет поле сразу, без печати по буквам» (переходы записки). Nothing animates the text
/// in: a typewriter effect on a suggestion would make the learner wait for their own tap.
class EntrySuggestion extends StatelessWidget {
  const EntrySuggestion({super.key, required this.text, required this.onTap, this.brass = false});

  final String text;
  final VoidCallback onTap;

  /// Brass outline for «Дописать за тебя»: those are offers to FIX something, and the frame draws
  /// them a shade warmer than the neutral examples.
  final bool brass;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(15);

    return Material(
      color: AppColors.surfaceRaised,
      borderRadius: radius,
      child: InkWell(
        borderRadius: radius,
        onTap: () {
          AppHaptics.light();
          onTap();
        },
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          decoration: BoxDecoration(
            borderRadius: radius,
            border: Border.all(
              color: brass ? AppColors.brassInk.withValues(alpha: 0.3) : AppColors.dividerFaint,
            ),
          ),
          child: Text(
            text,
            style: AppText.translation.copyWith(
              fontSize: 14.5,
              height: 1.45,
              color: AppColors.inkBody,
            ),
          ),
        ),
      ),
    );
  }
}

/// A chosen thing — the level card, the date, the minutes.
///
/// «Выбранная карточка не просто обводится: она темнеет до тёплого песочного, получает латунную
/// галочку и мягкую тень» (кадр V4·02). The tick is BRASS and the fill is sand; neither is the
/// accent, because a selection is not an action.
class EntryChoice extends StatelessWidget {
  const EntryChoice({
    super.key,
    required this.selected,
    required this.onTap,
    required this.child,
    this.showTick = true,
    this.padding = const EdgeInsets.fromLTRB(17, 15, 17, 15),
  });

  final bool selected;
  final VoidCallback onTap;
  final Widget child;
  final bool showTick;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(16);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 140),
      decoration: BoxDecoration(
        color: selected ? AppColors.planSelected : AppColors.surfaceRaised,
        borderRadius: radius,
        border: Border.all(
          color: selected ? AppColors.ink : AppColors.dividerFaint,
          width: selected ? 1.5 : 1,
        ),
        boxShadow: selected
            ? [
                BoxShadow(
                  color: AppColors.ink.withValues(alpha: 0.10),
                  blurRadius: 14,
                  offset: const Offset(0, 4),
                ),
              ]
            : null,
      ),
      child: Material(
        type: MaterialType.transparency,
        borderRadius: radius,
        child: InkWell(
          borderRadius: radius,
          onTap: () {
            AppHaptics.light();
            onTap();
          },
          child: Padding(
            padding: padding,
            // NO Row WHEN THERE IS NO TICK, and that is load-bearing rather than tidy: a Row with
            // an `Expanded` inside it demands a bounded width, and this box is used BOTH inside an
            // `Expanded` (the level cards, the minutes) and as a plain sibling that sizes to its
            // own content («Без даты», кадр V4·04). The second case handed the Row an unbounded
            // width and the whole step came out blank on the simulator — caught by walking it, not
            // by a test, because no test had reached that step.
            child: showTick
                ? Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: child),
                      if (selected) ...[const SizedBox(width: 12), const _BrassTick()],
                    ],
                  )
                : child,
          ),
        ),
      ),
    );
  }
}

class _BrassTick extends StatelessWidget {
  const _BrassTick();

  @override
  Widget build(BuildContext context) => TweenAnimationBuilder<double>(
    tween: Tween(begin: 0.8, end: 1),
    duration: const Duration(milliseconds: 140),
    builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
    child: Container(
      width: 22,
      height: 22,
      decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
      child: const Icon(Icons.check, size: 13, color: AppColors.brass),
    ),
  );
}

/// The ribbon of past answers — «Иду к врачу…  Изм.» stacked above the current question.
///
/// «Строка растёт по высоте за 200 мс, остальные не двигаются; „Изм.“ ведёт на тот же шаг без
/// анимации входа: экран уже знаком» (переходы записки).
class EntryRibbon extends StatelessWidget {
  const EntryRibbon({super.key, required this.rows});

  final List<EntryRibbonRow> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final row in rows)
          AnimatedSize(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOut,
            child: Container(
              padding: EdgeInsets.only(top: row == rows.first ? 0 : 11, bottom: 11),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: AppColors.brassHairline)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      row.text,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppText.translation.copyWith(
                        fontSize: 14,
                        height: 1.4,
                        color: row.muted ? AppColors.tertiary : AppColors.inkBody,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Semantics(
                    button: true,
                    child: InkWell(
                      onTap: () {
                        AppHaptics.light();
                        row.onTap();
                      },
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
                        child: Text(
                          row.action,
                          style: AppText.translation.copyWith(
                            fontSize: 13.5,
                            color: AppColors.destructiveText,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

/// One row of [EntryRibbon]: what was answered, and the word that reopens it.
class EntryRibbonRow {
  const EntryRibbonRow({
    required this.text,
    required this.action,
    required this.onTap,
    this.muted = false,
  });

  final String text;

  /// «Изм.» for an answered step, «Пройти» for the listening step that was skipped.
  final String action;
  final VoidCallback onTap;

  /// A skipped step is grey — it states an absence, not an answer.
  final bool muted;
}
