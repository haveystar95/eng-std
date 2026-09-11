import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../data/deep_links.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/providers.dart';
import '../plan_providers.dart';
import 'day_room_screen.dart';

/// ОДНА ДВЕРЬ В КАБИНЕТ ДНЯ — с плиты таба «План», из уведомления и по ссылке `engstd://…`.
///
/// Открывает [DayRoomScreen] дня [number] (по умолчанию — текущего дня плана). По возвращении
/// перечитывает состояние таба: кабинет мог закрыть день, и плита обязана показать это тем же
/// движением, а не при следующем заходе.
Future<void> openDayRoom(BuildContext context, WidgetRef ref, {Plan? plan, int? number}) async {
  final p = plan ?? await _plan(ref);
  if (p == null || !context.mounted) return;
  final n = number ?? p.currentDay?.number ?? 1;
  AppHaptics.light();
  await Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => DayRoomScreen(plan: p, number: n)),
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
