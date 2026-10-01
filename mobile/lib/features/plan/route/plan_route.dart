import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/plan/plan_models.dart';
import '../plan_format.dart';
import '../plan_stage_text.dart';
import 'route_line.dart';
import 'route_view.dart';

/// МАРШРУТ ПЛАНА НА ТАБЕ (кадры 21-2, 21-2b, 21-3, 21-4, 21-5, 21-6, 21-7, 21-13, 21-14, 22-5a).
///
/// Переводит ответ сервера в строки [RouteLine] и больше ничего не решает: узлы этапов — ровно те,
/// что пришли в `days[].stages` (нет этапа — нет узла), их состояние — слово сервера, дни — три
/// состояния по кадру (сегодня / пройден / заперт).
///
/// ЗАПЕРТЫЙ ДЕНЬ НЕ ОТКРЫВАЕТСЯ. Тап по нему не зовёт [onOpenDay]: он дописывает в мету этого дня
/// ту же причину, что носит первый запертый день, — «откроется после дня N» или «откроется
/// завтра». Экрана нет, сообщения нет — строка на своём месте. Тот же ответ тап получает, когда
/// день просит ссылка или уведомление ([explainDay]).
class PlanRoute extends StatefulWidget {
  const PlanRoute({super.key, required this.plan, this.onOpenDay, this.explainDay, this.focus, this.onSubscription});

  /// День, к которому прокрутить маршрут (тап по уведомлению). Счётчик — чтобы второй тап по тому
  /// же дню тоже прокрутил.
  final ({int day, int seq})? focus;

  final Plan plan;

  /// Тап по сегодняшнему или пройденному дню — кабинет. Null — режим чтения, узлы не нажимаются.
  final ValueChanged<PlanDayRoute>? onOpenDay;

  /// A tap on a day that opens with a subscription — the paywall's place (44-1b); until PAY-1, the profile's
  /// subscription group.
  final VoidCallback? onSubscription;

  /// Номер дня, чью причину запрета показать сразу (ссылка на запертый день, 409 `plan_day_locked`).
  final int? explainDay;

  @override
  State<PlanRoute> createState() => _PlanRouteState();
}

class _PlanRouteState extends State<PlanRoute> {
  int? _explained;

  final Map<int, GlobalKey> _anchors = {};

  GlobalKey _anchor(int day) => _anchors.putIfAbsent(day, GlobalKey.new);

  @override
  void initState() {
    super.initState();
    _explained = widget.explainDay;
    if (widget.focus != null) _scrollTo(widget.focus!.day);
  }

  @override
  void didUpdateWidget(PlanRoute old) {
    super.didUpdateWidget(old);
    if (widget.explainDay != null && widget.explainDay != old.explainDay) _explained = widget.explainDay;
    if (widget.focus != null && widget.focus != old.focus) _scrollTo(widget.focus!.day);
  }

  void _scrollTo(int day) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctx = _anchors[day]?.currentContext;
      if (ctx == null || !ctx.mounted) return;
      final still = MediaQuery.maybeDisableAnimationsOf(ctx) ?? false;
      unawaited(Scrollable.ensureVisible(ctx, alignment: .2, duration: still ? Duration.zero : const Duration(milliseconds: 320)));
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).languageCode;
    final plan = widget.plan;
    final days = plan.days;
    final firstLocked = days.indexWhere((d) => PlanRouteDayState.of(d) == RouteDayTone.locked);
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final native = NativeTypesetter.of(context);

    return RouteLine(
      days: [
        for (var i = 0; i < days.length; i++)
          _view(
            l,
            native,
            locale,
            plan,
            days[i],
            previous: i == 0 ? null : days[i - 1],
            explain: i == firstLocked || days[i].number == _explained,
            dpr: dpr,
          ),
      ],
      event: planRouteEvent(l, native, locale, plan),
    );
  }

  RouteDayView _view(
    AppLocalizations l,
    NativeTypesetter native,
    String locale,
    Plan plan,
    PlanDayRoute day, {
    required PlanDayRoute? previous,
    required bool explain,
    required double dpr,
  }) {
    final tone = PlanRouteDayState.of(day);
    final reached = plan.status.isLive &&
        (tone != RouteDayTone.locked || previous == null || previous.isClosed);
    final onOpen = widget.onOpenDay;

    return RouteDayView(
      anchor: _anchor(day.number),
      title: l.planRouteDayTitle(day.number, planRouteDayName(l, native, plan, day)),
      meta: _meta(l, locale, day, tone, previous: previous, explain: explain),
      circle: day.lockedBySubscription ? const RouteSubscriptionMark() : planRouteCircle(plan, day, dpr),
      tone: tone,
      trailingToday: tone == RouteDayTone.current && day.slot.labelNative != null ? native.composed(day.slot.labelNative!) : null,
      passedLine: reached,
      children: [
        for (final s in day.stages)
          RouteChildView(
            label: planStageName(l, s.stage),
            mark: switch (s.state) {
              PlanRouteStageState.done => RouteChildMark.done,
              PlanRouteStageState.current => RouteChildMark.current,
              PlanRouteStageState.locked => RouteChildMark.locked,
            },
          ),
      ],
      onTap: onOpen == null
          ? null
          : day.lockedBySubscription
          ? () => widget.onSubscription?.call()
          : tone == RouteDayTone.locked
          ? () => _explain(day)
          : () => onOpen(day),
    );
  }

  void _explain(PlanDayRoute day) {
    AppHaptics.warning();
    setState(() => _explained = day.number);
  }

  /// «10 сентября · пройден · 17 мин» / «сегодня · 75 карточек» / «12 сентября · откроется после
  /// дня 2». Номер дня стоит в заголовке, а не здесь.
  String? _meta(
    AppLocalizations l,
    String locale,
    PlanDayRoute day,
    RouteDayTone tone, {
    required PlanDayRoute? previous,
    required bool explain,
  }) {
    final parts = <String>[];
    final date = PlanFormat.parseWireDate(day.slot.date);
    switch (tone) {
      case RouteDayTone.current:
        // «сегодня» стоит справа латунью; в мете его второй раз нет — канва держит там «≈ 20 мин»,
        // а оценки минут контракт не отдаёт, поэтому мета — число карточек дня, если оно есть.
        if (day.cardsTotal > 0) parts.add(l.planCardsCount(day.cardsTotal));
      case RouteDayTone.passed:
        if (date != null) parts.add(PlanFormat.date(date, locale));
        parts.add(l.planRouteMetaPassed);
        if (day.minutesSpent > 0) parts.add(l.planMinutesShort(day.minutesSpent));
      case RouteDayTone.locked || RouteDayTone.plain:
        if (date != null) parts.add(PlanFormat.date(date, locale));
        // By subscription: every such day says so — the first «откроется с подпиской», the rest «по подписке».
        if (day.lockedBySubscription) {
          parts.add(previous?.lockedBySubscription == true ? l.planRouteMetaBySubscription : l.planRouteMetaOpensWithSubscription);
        } else if (explain) {
          parts.add(planLockReason(l, day, previous));
        }
    }

    return parts.isEmpty ? null : dotJoin(parts);
  }
}

/// Три состояния дня на табе — по слову сервера о слоте и статусе, не по дате на телефоне.
abstract final class PlanRouteDayState {
  static RouteDayTone of(PlanDayRoute day) {
    if (day.isClosed) return RouteDayTone.passed;
    // Locked by the subscription even when its slot is today: it does not open, whatever the calendar says (ACC-1).
    if (day.lockedBySubscription) return RouteDayTone.locked;
    if (day.slot.code == PlanSlotCode.today) return RouteDayTone.current;

    return RouteDayTone.locked;
  }
}

/// «откроется после дня N», а когда день перед ним уже пройден — «откроется завтра» (21-4): ждать
/// осталось календарь, а не работу.
String planLockReason(AppLocalizations l, PlanDayRoute day, PlanDayRoute? previous) {
  if (previous == null) return l.planRouteMetaOpensTomorrow;

  return previous.isClosed ? l.planRouteMetaOpensTomorrow : l.planRouteMetaOpensAfter(previous.number);
}

/// Название дня: сцена — её название, повторение и репетиция — свои слова. The scene's title is the server's, in the
/// learner's language — set by [native] (наряд CLIENT-22-1 §2).
String planRouteDayName(AppLocalizations l, NativeTypesetter native, Plan plan, PlanDayRoute day) => switch (day.type) {
  PlanDayType.review => l.planRouteDayReview,
  PlanDayType.rehearsal => l.planRouteDayRehearsal,
  PlanDayType.scene || PlanDayType.unknown => native(day.titleNative ?? plan.sceneOf(day)?.titleNative ?? ''),
};

/// Круг дня: фото сцены (кроп под плотность и тон), или своя иллюстрация системного дня.
RouteCircle planRouteCircle(Plan plan, PlanDayRoute day, double dpr) => switch (day.type) {
  PlanDayType.review => const RouteSystemMark(PlanSystemDay.review),
  PlanDayType.rehearsal => const RouteSystemMark(PlanSystemDay.rehearsal),
  PlanDayType.scene || PlanDayType.unknown => () {
    final image = plan.sceneOf(day)?.image;

    return RoutePhoto(url: image?.urlFor(56, dpr), tone: AppColors.wireTone(image?.tone));
  }(),
};

/// Мишень события — «Приём · 17 сентября · четверг», пунктир без даты, ink с галкой после.
RouteEventView? planRouteEvent(AppLocalizations l, NativeTypesetter native, String locale, Plan plan) {
  final event = (plan.eventNative ?? '').trim();
  final date = PlanFormat.parseWireDate(plan.eventDate);
  if (event.isEmpty && date == null) return null;

  return RouteEventView(
    title: event.isEmpty ? l.planRouteEventFallback : native(event),
    meta: date == null ? l.planRouteEventNoDate : '${PlanFormat.date(date, locale)} · ${PlanFormat.weekday(date, locale)}',
    passed: plan.status == PlanStatus.overdue || plan.status == PlanStatus.finished,
    dated: date != null,
  );
}

/// Адреса фото, которые таб качает заранее: дни, до которых маршрут уже дошёл (они над текущим и
/// на экране), и три следующих (§3 наряда).
List<String> planRoutePrefetch(Plan plan, double dpr) {
  final current = plan.currentDay?.number ?? 1;
  final urls = <String>[];
  for (final d in plan.days) {
    if (d.number > current + 3) continue;
    final url = plan.sceneOf(d)?.image?.urlFor(56, dpr);
    if (url != null) urls.add(url);
  }

  return urls;
}
