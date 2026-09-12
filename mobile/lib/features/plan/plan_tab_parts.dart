import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/plan/plan_models.dart';
import 'plan_format.dart';

/// THE TAB'S OWN BLOCKS — each a widget with one job (наряд PLAN-UI, кадры 21-x).

/// The ink button every card of the tab ends on — 52 / radius 16 / 17 / 700 with the arrow.
class PlanInkButton extends StatelessWidget {
  const PlanInkButton({super.key, required this.label, this.onTap, this.arrow = true});

  final String label;
  final VoidCallback? onTap;
  final bool arrow;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: Material(
      color: AppColors.ink,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          height: 52,
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                  color: AppColors.paper,
                ),
              ),
              if (arrow) ...[
                const SizedBox(width: 9),
                const Icon(LucideIcons.arrowRight, size: 17, color: AppColors.paper),
              ],
            ],
          ),
        ),
      ),
    ),
  );
}

/// The quiet text action — 48 high, 15/600 ink-body, no frame (кадр 21-14 «Перенести дату»).
class PlanTextButton extends StatelessWidget {
  const PlanTextButton({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Container(
        height: 48,
        alignment: Alignment.center,
        child: Text(
          label,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: AppColors.inkBody,
          ),
        ),
      ),
    ),
  );
}

/// The section label — 11 / 700 / .14em caps tertiary («ЗАВЕРШЁННЫЕ ПЛАНЫ», «СПАСАТЕЛЬНЫЙ НАБОР»).
class PlanSectionLabel extends StatelessWidget {
  const PlanSectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: const TextStyle(
      fontFamily: AppFonts.inter,
      fontSize: 11,
      fontWeight: FontWeight.w700,
      letterSpacing: 1.54,
      color: AppColors.tertiary,
    ),
  );
}

/// A first-time hint (кадры 21-2c, 21-4c): one secondary line, no frame, no cross; fades out in
/// 160 ms after the first action, its place collapsing in 220 ms — under «уменьшение движения»
/// only the fade remains (4о).
class PlanHintLine extends StatelessWidget {
  const PlanHintLine({super.key, required this.text, required this.visible});

  final String text;
  final bool visible;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final line = AnimatedOpacity(
      opacity: visible ? 1 : 0,
      duration: const Duration(milliseconds: 160),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6),
        child: Text(
          text,
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 14,
            height: 1.45,
            color: AppColors.secondary,
          ),
        ),
      ),
    );

    if (reduce) return visible ? line : const SizedBox.shrink();

    return AnimatedSize(
      duration: const Duration(milliseconds: 220),
      curve: AppMotion.easeOut,
      alignment: Alignment.topCenter,
      child: visible ? line : const SizedBox(width: double.infinity),
    );
  }
}

/// ШАПКА ПЛАНА — бровь, короткое название и полоса дня (кадры 21-2 … 21-14, 22-5a/b/c).
///
/// СЛОВА «ПЛАН» В ЗАГОЛОВКЕ НЕТ: канва вычла его вместе со старой шапкой — вкладка и так
/// называется планом, а строчку заняла бровь «План · день 2 из 7». Дальше идёт КОРОТКОЕ НАЗВАНИЕ
/// плана Literata 30 («Спина и врач») — название ПЛАНА, не дня.
///
/// Фолбэк 21-6: когда сервер короткого названия не дал, его место занимает формулировка человека
/// Inter 21/600 — до трёх строк, четвёртая ОБРЕЗАЕТСЯ БЕЗ ТРОЕТОЧИЯ (`max-height:82px;
/// overflow:hidden` в канве), потому что троеточие в такой строке читается как «тут что-то
/// потеряли», а обрыв — как «тут ещё есть».
///
/// Латуни в шапке нет ни в одном состоянии: латунь метит ДЕНЬ, а не план.
class PlanHeader extends StatelessWidget {
  const PlanHeader({
    super.key,
    required this.brow,
    required this.shortTitle,
    required this.goal,
    required this.total,
    required this.closed,
    required this.onMenu,
    this.trailing,
    this.leading,
  });

  /// «План · день 2 из 7» — бровь 11/700/.14em caps.
  final String brow;

  /// Короткое название плана, или null → фолбэк 21-6 на [goal].
  final String? shortTitle;
  final String goal;

  final int total;

  /// Сколько дней закрыто — заливка полосы (1 из 7 = 14 %, как в кадре).
  final int closed;

  /// Меню открывается от САМОЙ кнопки «…» (4в), поэтому наружу уходит её контекст.
  final void Function(BuildContext anchor) onMenu;

  /// Кружок-аватар; в режиме чтения завершённого плана — ничего.
  final Widget? trailing;

  /// Шеврон «назад» — завершённый план открывается поверх таба и уходить ему больше некуда.
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final reduce = MediaQuery.of(context).disableAnimations;
    final value = total == 0 ? 0.0 : (closed / total).clamp(0.0, 1.0);
    final named = (shortTitle ?? '').trim();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ?leading,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      brow,
                      style: const TextStyle(
                        fontFamily: AppFonts.inter,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.54, // .14em
                        height: 1.2,
                        color: AppColors.tertiary,
                      ),
                    ),
                    const SizedBox(height: 7),
                    if (named.isNotEmpty)
                      Text(
                        named,
                        style: const TextStyle(
                          fontFamily: AppFonts.literata,
                          fontSize: 30,
                          fontWeight: FontWeight.w500,
                          letterSpacing: -0.6, // -.02em
                          height: 1.15,
                          color: AppColors.ink,
                        ),
                      )
                    else
                      Text(
                        goal,
                        maxLines: 3,
                        // Без троеточия — канва режет четвёртую строку рамкой, а не многоточием.
                        overflow: TextOverflow.clip,
                        style: const TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 21,
                          fontWeight: FontWeight.w600,
                          letterSpacing: -0.21, // -.01em
                          height: 1.3,
                          color: AppColors.ink,
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    Builder(
                      builder: (anchor) => Semantics(
                        button: true,
                        label: l.planMenuLabel,
                        child: InkResponse(
                          radius: 20,
                          onTap: () => onMenu(anchor),
                          child: const SizedBox(
                            width: 32,
                            height: 32,
                            child: Icon(LucideIcons.ellipsis, size: 18, color: AppColors.tertiary),
                          ),
                        ),
                      ),
                    ),
                    if (trailing != null) ...[const SizedBox(width: 12), trailing!],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          // ПОЛОСА ДНЯ 4 px — заполняется за 420 мс, когда день закрывается (21-4).
          TweenAnimationBuilder<double>(
            tween: Tween(begin: value, end: value),
            duration: reduce ? Duration.zero : AppMotion.goalBar,
            curve: AppMotion.easeOut,
            builder: (context, v, _) => ProgressLine(
              value: v,
              height: 4,
              trackColor: AppColors.ink.withValues(alpha: .12),
            ),
          ),
        ],
      ),
    );
  }
}

/// «До приёма · 5 дней» — the countdown over the route, READY from the server (`until_phrase`).
/// The part before « · » is the header's bold word, the rest its secondary number.
class PlanRouteHeader extends StatelessWidget {
  const PlanRouteHeader({super.key, required this.phrase});

  final String phrase;

  @override
  Widget build(BuildContext context) {
    final parts = phrase.split(' · ');
    final head = parts.first;
    final tail = parts.length > 1 ? parts.sublist(1).join(' · ') : null;

    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 0, 2, 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.baseline,
        textBaseline: TextBaseline.alphabetic,
        children: [
          Text(
            head,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 17,
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
            ),
          ),
          if (tail != null) ...[
            const Text(
              '  ·  ',
              style: TextStyle(fontFamily: AppFonts.inter, fontSize: 15, color: AppColors.secondary),
            ),
            Text(
              tail,
              style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 17, color: AppColors.secondary),
            ),
          ],
        ],
      ),
    );
  }
}

/// ПЛАШКА ПЕРЕСБОРКИ МАРШРУТА (кадр 21-13) — над плитой, и она ОСТАЁТСЯ на экране.
///
/// Это состояние плана, а не сообщение: кнопки у неё нет, закрыть её нечем, и действие экрана
/// по-прежнему «Начать» на плите. Плашка говорит только то, что сделала система, — без укора.
class PlanNoticeBanner extends StatelessWidget {
  const PlanNoticeBanner({super.key, required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    decoration: BoxDecoration(
      color: AppColors.brassWash,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: AppColors.brassHairline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 1),
          child: PlanIconMark(icon: PlanIcon.route, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 14,
              height: 1.4,
              color: AppColors.ink,
            ),
          ),
        ),
      ],
    ),
  );
}
/// «Завершённые планы» (кадры 21-1, 21-2b): rows of 60 between hairlines, title 16/600, «завершён
/// 12 августа» 14 tertiary, a chevron. Absent when there is nothing to list.
class PlanFinishedList extends StatelessWidget {
  const PlanFinishedList({super.key, required this.rows, required this.onOpen});

  final List<PlanRow> rows;
  final ValueChanged<PlanRow> onOpen;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          PlanSectionLabel(l.planFinishedTitle),
          const SizedBox(height: 8),
          for (var i = 0; i < rows.length; i++)
            InkWell(
              onTap: () => onOpen(rows[i]),
              child: Container(
                height: 60,
                decoration: BoxDecoration(
                  border: Border(
                    top: const BorderSide(color: AppColors.dividerFaint),
                    bottom: i == rows.length - 1 ? const BorderSide(color: AppColors.dividerFaint) : BorderSide.none,
                  ),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            rows[i].title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontFamily: AppFonts.inter,
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                              color: AppColors.ink,
                            ),
                          ),
                          if (rows[i].finishedAt != null) ...[
                            const SizedBox(height: 3),
                            Text(
                              l.planFinishedItemDate(PlanFormat.date(rows[i].finishedAt!.toLocal(), locale)),
                              style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    const Icon(LucideIcons.chevronRight, size: 15, color: AppColors.tertiary),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// «План пройден» (кадр 21-7) — and, from the finished list, its reading mode with «Открыть
/// коллекцию» in place of «Собрать новый план».
class PlanDoneCard extends StatelessWidget {
  const PlanDoneCard({
    super.key,
    required this.plan,
    required this.readOnly,
    required this.onNewPlan,
    required this.onOpenCollection,
  });

  final Plan plan;
  final bool readOnly;
  final VoidCallback onNewPlan;
  final VoidCallback? onOpenCollection;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 28,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _CardTitle(
            icon: Container(
              width: 30,
              height: 30,
              decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.ink),
              child: const Icon(LucideIcons.check, size: 16, color: AppColors.paper),
            ),
            title: l.planDoneTitle,
          ),
          const SizedBox(height: 8),
          Text(
            l.planDoneMeta(l.planDaysCount(plan.daysTotal)),
            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.secondary),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.only(top: 14),
            decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.dividerFaint))),
            child: _CollectionLine(text: l.planDoneCollection(plan.displayTitle), name: plan.displayTitle),
          ),
          const SizedBox(height: 16),
          if (readOnly)
            PlanInkButton(label: l.planDoneCtaReadonly, onTap: onOpenCollection, arrow: false)
          else
            PlanInkButton(label: l.planDoneCta, onTap: onNewPlan),
        ],
      ),
    );
  }
}

/// «Приём был вчера» (кадр 21-14): the target in brass — the screen's one brass detail — the
/// server's own sentence, «Пройдено 4 дня из 7», and two ways on.
class PlanOverdueCard extends StatelessWidget {
  const PlanOverdueCard({
    super.key,
    required this.plan,
    required this.onFinish,
    required this.onContinue,
  });

  final Plan plan;
  final VoidCallback onFinish;

  /// «Дозаниматься» — ВОЗВРАТ К ТЕКУЩЕМУ ДНЮ, а не перенос даты: событие уже прошло, переносить
  /// нечего, а дни 5–7 остаются доступными (кадр 21-14).
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 28,
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _CardTitle(
            icon: Container(
              width: 30,
              height: 30,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.brassInk, width: 1.5),
              ),
              child: Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.brassInk, width: 1.5),
                ),
              ),
            ),
            title: plan.overdueNative ?? '',
          ),
          const SizedBox(height: 8),
          Text(
            l.planOverdueMeta(l.planDaysCount(plan.closedDays), plan.daysTotal),
            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.secondary),
          ),
          const SizedBox(height: 16),
          PlanInkButton(label: l.planOverdueFinish, onTap: onFinish, arrow: false),
          const SizedBox(height: 4),
          // Сколько дней осталось — считаем из того, что уже закрыто: счёт дней идёт дальше.
          PlanTextButton(
            label: l.planOverdueContinue(l.planDaysCount(plan.daysTotal - plan.closedDays)),
            onTap: onContinue,
          ),
        ],
      ),
    );
  }
}
/// The tab without a cache when the server did not answer — a card with «Повторить» (§6).
class PlanLoadFailedCard extends StatelessWidget {
  const PlanLoadFailedCard({super.key, required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return PaperCard(
      radius: 28,
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            l.planTabLoadFailedTitle,
            style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.ink),
          ),
          const SizedBox(height: 16),
          PlanInkButton(label: l.planTabRetry, onTap: onRetry, arrow: false),
        ],
      ),
    );
  }
}

class _CardTitle extends StatelessWidget {
  const _CardTitle({required this.icon, required this.title});

  final Widget icon;
  final String title;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      icon,
      const SizedBox(width: 11),
      Expanded(
        child: Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            fontFamily: AppFonts.literata,
            fontSize: 23,
            fontWeight: FontWeight.w500,
            letterSpacing: -0.23,
            color: AppColors.ink,
          ),
        ),
      ),
    ],
  );
}

/// The collection line with the collection's NAME set bold inside it (кадр 21-7).
class _CollectionLine extends StatelessWidget {
  const _CollectionLine({required this.text, required this.name});

  final String text;
  final String name;

  @override
  Widget build(BuildContext context) {
    const base = TextStyle(fontFamily: AppFonts.inter, fontSize: 14, height: 1.5, color: AppColors.secondary);
    final at = name.isEmpty ? -1 : text.indexOf(name);
    if (at < 0) return Text(text, style: base);

    return Text.rich(
      TextSpan(
        style: base,
        children: [
          TextSpan(text: text.substring(0, at)),
          TextSpan(
            text: name,
            style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.ink),
          ),
          TextSpan(text: text.substring(at + name.length)),
        ],
      ),
    );
  }
}
