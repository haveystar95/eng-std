import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/local/cached_image_provider.dart';
import '../../data/plan/plan_models.dart';
import 'plan_format.dart';

/// МАРШРУТ — узел = КРУГЛАЯ КАРТИНКА 56 БЕЗ БЕЙДЖА (кадры 21-2, 21-2b, 21-5, 21-6, 21-7, 22-4b).
///
/// Что канва вычла здесь и почему это видно в коде: бейджи-номера с фото ушли, номер дня стоит
/// ПЕРВЫМ СЛОВОМ мета-строки («День 3 · 12 сентября · откроется после дня 2»). Внутри узла —
/// заголовок → 4 → описание → 4 → мета, три строки ВСЕГДА в этом порядке; между узлами 24 чистого
/// воздуха, шаг 96; линия 1.5 идёт ЗА картинками через их центры. Заперт = картинка 55 % и серый
/// текст, не замок; у системных дней своя иллюстрация вместо фото; латунная обводка — только у
/// текущего дня, и это единственная латунь на экране.
///
/// Ни одна строка не обрезается троеточием: заголовки и описания переносятся (`ellipsis 0` —
/// проверка канвы). Ширину держит сам узел.
class PlanRoute extends StatelessWidget {
  const PlanRoute({super.key, required this.plan, this.preview = false, this.onOpenDay});

  final Plan plan;

  /// Кадр 22-4b: тот же маршрут в превью — без слота «сегодня» и без обводки, дни ещё не начаты,
  /// и у каждого дня-ситуации стоят его собственные цели словами.
  final bool preview;

  /// Тап по узлу — кабинет того же дня (21-2b, 240 мс). null — узлы не нажимаются (превью).
  final ValueChanged<PlanDayRoute>? onOpenDay;

  @override
  Widget build(BuildContext context) {
    final days = plan.days;

    // «День N+1 открывается только когда день N пройден»: первый ЗАПЕРТЫЙ день носит причину
    // («откроется после дня N» или «откроется завтра»), остальные — только номер и дату. Причина
    // на каждом узле превратила бы маршрут в список запретов.
    var firstLocked = -1;
    for (var i = 0; i < days.length; i++) {
      final d = days[i];
      if (!d.isClosed && d.slot.code != PlanSlotCode.today && d.slot.code != PlanSlotCode.past) {
        firstLocked = i;
        break;
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < days.length; i++)
          _Node(
            plan: plan,
            day: days[i],
            last: false,
            preview: preview,
            explainLock: !preview && i == firstLocked,
            onOpen: onOpenDay,
          ),
        _EventNode(plan: plan),
      ],
    );
  }
}

/// ОДИН ДЕНЬ МАРШРУТА.
class _Node extends StatelessWidget {
  const _Node({
    required this.plan,
    required this.day,
    required this.last,
    required this.preview,
    required this.explainLock,
    this.onOpen,
  });

  final Plan plan;
  final PlanDayRoute day;
  final bool last, preview, explainLock;
  final ValueChanged<PlanDayRoute>? onOpen;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final scene = plan.sceneOf(day);
    final closed = !preview && day.isClosed;
    final today = !preview && day.slot.code == PlanSlotCode.today;
    final locked = !closed && !today;
    final isScene = day.type == PlanDayType.scene;

    final (title, teaches) = switch (day.type) {
      PlanDayType.scene => (
        day.titleNative ?? scene?.titleNative ?? '',
        day.teachesNative ?? scene?.teachesNative ?? '',
      ),
      PlanDayType.review => (l.planRouteDayRepeat, _repeatSub(l)),
      PlanDayType.rehearsal => (l.planRouteDayRehearsal, l.planRouteDayRehearsalSub),
      PlanDayType.unknown => (day.titleNative ?? '', day.teachesNative ?? ''),
    };

    // Заголовок: ink у пройденного и у сегодняшнего, tertiary у запертого («серый текст»).
    final titleColor = locked ? AppColors.tertiary : AppColors.ink;
    final teachesColor = locked ? AppColors.tertiary : AppColors.secondary;

    final node = _NodeCircle(
      scene: isScene ? scene : null,
      system: switch (day.type) {
        PlanDayType.review => PlanSystemDay.review,
        PlanDayType.rehearsal => PlanSystemDay.rehearsal,
        _ => null,
      },
      today: today,
      passed: closed,
      dim: locked,
    );

    final body = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Flexible(
              child: Text(
                title,
                style: TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 17,
                  fontWeight: FontWeight.w600,
                  height: 1.25,
                  color: titleColor,
                ),
              ),
            ),
            // Справа — ОДНО из двух: галка у пройденного, «сегодня» латунью у текущего.
            if (closed) ...[
              const SizedBox(width: 10),
              const Padding(
                padding: EdgeInsets.only(top: 3),
                child: Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown),
              ),
            ] else if (today && (day.slot.labelNative ?? '').isNotEmpty) ...[
              const SizedBox(width: 10),
              Text(
                day.slot.labelNative!,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  height: 1.4,
                  color: AppColors.brassInk,
                ),
              ),
            ],
          ],
        ),
        if (teaches.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            teaches,
            style: TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 14,
              height: 1.4,
              color: teachesColor,
            ),
          ),
        ],
        // Кадр 22-4b: в превью у дня-ситуации стоят его ЦЕЛИ СЛОВАМИ. Канва просит «скажешь: …» /
        // «спросишь: …», но сервер таких строк не отдаёт — только `goals_native` (расхождение в
        // отчёте), а выдумывать реплики на клиенте нельзя.
        if (preview && isScene)
          for (final goal in (scene?.goalsNative ?? const <String>[]))
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                goal,
                style: const TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 14,
                  height: 1.4,
                  color: AppColors.secondary,
                ),
              ),
            ),
        const SizedBox(height: 4),
        Text(
          _meta(l, locale, closed: closed, today: today),
          style: const TextStyle(
            fontFamily: AppFonts.inter,
            fontSize: 13,
            height: 1.4,
            color: AppColors.tertiary,
          ),
        ),
      ],
    );

    final row = Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 96),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [node, const SizedBox(width: 14), Expanded(child: body)],
        ),
      ),
    );

    final line = _LineThroughCentres(last: last);
    final stack = Stack(children: [Positioned.fill(child: line), row]);

    if (onOpen == null) return stack;

    return Semantics(
      button: true,
      child: InkResponse(
        onTap: () => onOpen!(day),
        highlightShape: BoxShape.rectangle,
        containedInkWell: true,
        child: stack,
      ),
    );
  }

  /// МЕТА-СТРОКА — номер дня первым словом, дальше дата и то, что про день известно.
  ///
  /// «≈ 20 мин» из канвы здесь нет: оценки минут для дня контракт не отдаёт (только
  /// `minutes_spent` у пройденного) — расхождение в отчёте, а не выдуманное число.
  String _meta(AppLocalizations l, String locale, {required bool closed, required bool today}) {
    final parts = <String>[l.planRouteMetaDay(day.number)];
    final date = PlanFormat.parseWireDate(day.slot.date);
    final label = day.slot.labelNative;

    if (today && (label ?? '').isNotEmpty) {
      parts.add(label!);
    } else if (date != null) {
      parts.add(PlanFormat.date(date, locale));
    }

    if (closed) {
      parts.add(l.planRouteMetaPassed);
      final minutes = day.minutesSpent;
      if (minutes > 0) parts.add(l.planMinutesShort(minutes));
    } else if (today) {
      if (day.cardsTotal > 0) parts.add(l.planCardsCount(day.cardsTotal));
    } else if (explainLock) {
      // Первый запертый день — и только он — говорит, ЧЕМ он открывается.
      parts.add(_lockReason(l));
    }

    return parts.join(' · ');
  }

  /// «откроется после дня N», а после закрытия сегодняшнего — «откроется завтра» (21-2b, 21-4):
  /// когда предыдущий день уже пройден, ждать осталось календарь, а не работу.
  String _lockReason(AppLocalizations l) {
    final previous = day.number - 1;
    final prior = plan.days.where((d) => d.number == previous).firstOrNull;
    if (prior != null && prior.isClosed) return l.planRouteMetaOpensTomorrow;

    return previous >= 1 ? l.planRouteMetaOpensAfter(previous) : l.planRouteMetaOpensTomorrow;
  }

  /// «слова и фразы дней 1–2» — дни-ситуации, которые этот день повторения собирает.
  String _repeatSub(AppLocalizations l) {
    var a = 0, b = 0;
    for (final d in plan.days) {
      if (d.number >= day.number) break;
      if (d.type == PlanDayType.scene) {
        if (a == 0) a = d.number;
        b = d.number;
      } else if (d.type == PlanDayType.review) {
        a = 0;
        b = 0;
      }
    }
    if (a == 0) return '';

    return a == b ? l.planRouteDayRepeatSubOne(a) : l.planRouteDayRepeatSub(a, b);
  }
}

/// КРУГ УЗЛА 56 — фото сцены, иллюстрация системного дня или пустая бумага.
class _NodeCircle extends StatelessWidget {
  const _NodeCircle({
    required this.scene,
    required this.system,
    required this.today,
    required this.passed,
    required this.dim,
  });

  final PlanScene? scene;
  final PlanSystemDay? system;
  final bool today, passed, dim;

  @override
  Widget build(BuildContext context) {
    final url = scene?.image?.url;
    Widget? inner;
    if (system != null) {
      inner = Center(child: PlanSystemDayMark(day: system!, color: AppColors.ink));
    } else if (url != null) {
      inner = Image(image: CachedNetworkImage(url), fit: BoxFit.cover);
    }

    // «Заперт = картинка 55 %» — гаснет ТОЛЬКО картинка, замка нет.
    if (dim && inner != null) inner = Opacity(opacity: .55, child: inner);

    return Container(
      width: 56,
      height: 56,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: AppColors.photoPlaceholder,
        shape: BoxShape.circle,
        // Латунная обводка 2 — только у текущего дня.
        border: today ? Border.all(color: AppColors.brass, width: 2) : null,
        boxShadow: [
          if (today)
            BoxShadow(
              color: AppColors.brassInk.withValues(alpha: .28),
              blurRadius: 10,
              offset: const Offset(0, 2),
            )
          else if (passed)
            BoxShadow(
              color: AppColors.ink.withValues(alpha: .10),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
        ],
      ),
      child: inner,
    );
  }
}

/// ЛИНИЯ 1.5 ЧЕРЕЗ ЦЕНТРЫ — за картинками, одного цвета на всю длину: маршрут ведёт к событию, а
/// не показывает, сколько пройдено (это говорят сами узлы).
class _LineThroughCentres extends StatelessWidget {
  const _LineThroughCentres({required this.last});

  final bool last;

  @override
  Widget build(BuildContext context) {
    if (last) return const SizedBox.shrink();

    return Padding(
      // Центр круга 56 — 28 от верха; линия начинается там и идёт до низа строки.
      padding: const EdgeInsets.only(left: 28 - 0.75, top: 28),
      child: Align(
        alignment: Alignment.topLeft,
        child: Container(
          width: 1.5,
          height: double.infinity,
          color: AppColors.ink.withValues(alpha: .22),
        ),
      ),
    );
  }
}

/// МИШЕНЬ СОБЫТИЯ — последний узел маршрута, всегда один на экране (21-2b).
///
/// Дата есть → «17 сентября · четверг»; даты нет → пунктирный круг и «указать дату» вместо неё.
/// Прошедшее событие — мишень ink с галкой (21-14).
class _EventNode extends StatelessWidget {
  const _EventNode({required this.plan});

  final Plan plan;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final event = (plan.eventNative ?? '').trim();
    final date = PlanFormat.parseWireDate(plan.eventDate);
    if (event.isEmpty && date == null) return const SizedBox.shrink();

    final passed = plan.status == PlanStatus.overdue || plan.status == PlanStatus.finished;
    final circle = Container(
      width: 56,
      height: 56,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: passed ? AppColors.ink : AppColors.photoPlaceholder,
        shape: BoxShape.circle,
        border: passed ? null : Border.all(color: AppColors.brassHairline),
      ),
      child: PlanSystemDayMark(
        day: PlanSystemDay.event,
        color: passed ? AppColors.paper : AppColors.brassInk,
      ),
    );

    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 96),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Событие без даты — пунктир вокруг круга: «дату ещё предстоит назвать». Радиус 30 на
          // рамке 60 — это и есть круг, отдельный painter не нужен.
          date == null
              ? DottedBorderBox(
                  padding: const EdgeInsets.all(2),
                  radius: 30,
                  color: AppColors.brassHairline,
                  child: circle,
                )
              : circle,
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Flexible(
                      child: Text(
                        event.isEmpty ? l.planRouteEventFallback : event,
                        style: const TextStyle(
                          fontFamily: AppFonts.inter,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          height: 1.25,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    if (passed) ...[
                      const SizedBox(width: 10),
                      const Padding(
                        padding: EdgeInsets.only(top: 3),
                        child: Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  date == null
                      ? l.planRouteEventNoDate
                      : '${PlanFormat.date(date, locale)} · ${PlanFormat.weekday(date, locale)}',
                  style: const TextStyle(
                    fontFamily: AppFonts.inter,
                    fontSize: 13,
                    height: 1.4,
                    color: AppColors.tertiary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
