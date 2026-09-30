import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../data/deep_links.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../plan_providers.dart';
import '../route/plan_route.dart';
import '../route/route_view.dart';
import 'day_window_screen.dart';

/// ОДНА ДВЕРЬ В ОКНО ДНЯ — с плиты таба «План», из уведомления и по ссылке `engstd://…`.
///
/// Открывает [DayWindowScreen] дня [number] (по умолчанию — текущего дня плана). По возвращении
/// перечитывает состояние таба: окно могло закрыть день, и плита обязана показать это тем же
/// движением, а не при следующем заходе.
Future<void> openDayRoom(BuildContext context, WidgetRef ref, {Plan? plan, int? number}) async {
  final p = plan ?? await _plan(ref);
  if (p == null || !context.mounted) return;
  final n = number ?? p.currentDay?.number ?? 1;
  final day = p.days.where((d) => d.number == n).firstOrNull;
  // ЗАПЕРТЫЙ ДЕНЬ НЕ ОТКРЫВАЕТСЯ (наряд PLAN-UI-3): ни плита, ни ссылка, ни уведомление не ведут в
  // кабинет дня, который сервер ещё не открыл. Таб называет причину строкой на маршруте.
  // A day locked by the SUBSCRIPTION does open: «бесплатному видно всё, заперты только дни 2+» — its window shows it
  // whole, with «Откроется с подпиской» in place of «Начать» (23-0a «по подписке», CLIENT-START §6).
  if (day != null && PlanRouteDayState.of(day) == RouteDayTone.locked && !day.lockedBySubscription) {
    AppHaptics.warning();
    ref.read(planExplainDayProvider.notifier).explain(n);

    return;
  }
  AppHaptics.light();
  await Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => DayWindowScreen(plan: p, number: n)),
  );
  if (!context.mounted) return;
  await ref.read(planTabProvider.notifier).refresh();
}

/// Кабинет по ссылке `engstd://…` — уведомления, QA-прогон, внешний переход. Ссылка не про день —
/// ничего не открывается.
Future<void> openDayLink(BuildContext context, WidgetRef ref, Uri uri) async {
  final link = DayLink.parse(uri);
  if (link == null) return;
  Plan? plan;
  if (link.planId case final id?) {
    try {
      plan = await ref.read(apiClientProvider).plan(id);
    } catch (_) {
      plan = null;
    }
  } else {
    plan = await _plan(ref);
  }
  if (plan == null || !context.mounted) return;
  var number = link.number;
  if (link.dayId case final dayId?) {
    for (final d in plan.days) {
      if (d.id == dayId) number = d.number;
    }
    if (number == null) return;
  }
  await openDayRoom(context, ref, plan: plan, number: number);
}

/// План, на котором стоит ученик, — из состояния таба: там он уже прочитан и закэширован для
/// офлайна, и второй запрос был бы вторым мнением о том же.
Future<Plan?> _plan(WidgetRef ref) async {
  try {
    final state = await ref.read(planTabProvider.future);

    return state.plan;
  } catch (_) {
    return null;
  }
}
