import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../data/deep_links.dart';
import '../../../data/plan/plan_contract.dart';
import '../../../data/plan/plan_providers.dart';
import '../../../data/providers.dart';
import 'day_room_screen.dart';

/// ОДНА ДВЕРЬ В КАБИНЕТ ДНЯ — с таба, с домашней карточки, из уведомления и после сборки плана.
///
/// Открывает [DayRoomScreen] текущего дня плана (или дня [number]); без плана — ничего. По
/// возвращении перечитывает план: кабинет мог закрыть день, и таб должен это увидеть.
Future<void> openDayRoom(BuildContext context, WidgetRef ref, {Plan? plan, int? number}) async {
  final p = plan ?? await _currentPlan(ref);
  if (p == null || !context.mounted) return;
  final n = number ?? p.currentDay?.number ?? 1;
  AppHaptics.light();
  await Navigator.of(context).push(
    MaterialPageRoute(builder: (_) => DayRoomScreen(plan: p, number: n)),
  );
  ref.invalidate(currentPlanProvider);
}

/// Кабинет по ссылке `engstd://…` — уведомления, QA-прогон, внешний переход. Не про день — ничего.
Future<void> openDayLink(BuildContext context, WidgetRef ref, Uri uri) async {
  final link = DayLink.parse(uri);
  if (link == null) return;
  Plan? plan;
  if (link.planId case final id?) {
    try {
      plan = await ref.read(apiClientProvider).planById(id);
    } catch (_) {
      plan = null;
    }
  } else {
    plan = await _currentPlan(ref);
  }
  if (plan == null || !context.mounted) return;
  int? number = link.number;
  if (link.dayId case final dayId?) {
    for (final d in plan.days) {
      if (d.id == dayId) number = d.number;
    }
    if (number == null) return;
  }
  await openDayRoom(context, ref, plan: plan, number: number);
}

Future<Plan?> _currentPlan(WidgetRef ref) async {
  try {
    return await ref.read(currentPlanProvider.future);
  } catch (_) {
    return null;
  }
}
