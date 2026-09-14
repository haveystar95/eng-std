import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'plan_models.dart';

/// ЧТО ЧИТАЕТ ОКНО ДНЯ (наряды DAY-UI, DAY-UI-2). План и плиту таба держит `planTabProvider`
/// (`features/plan/plan_providers.dart`) — здесь только то, что нужно самому дню.
///
/// Адрес — план и номер дня: по номеру день живёт в контракте (`…/days/{n}`), и по нему же его
/// открывает ссылка `engstd://plan/{planId}/day/{n}`.
typedef DayAddress = ({String planId, int number});

/// День — `GET …/days/{n}` с блоком `window`. Тот же ответ, что таб читает для плиты, но этот
/// провайдер живёт отдельно: окно открывают и по ссылке, без таба на экране.
///
/// Живёт, пока окно открыто (`autoDispose`): каждый вход в окно читает сервер заново. Живой прогон
/// 14.09 поймал обратное — день прошёл, таб это показал, а окно на втором входе рисовало ответ из
/// памяти с «Слова · идёт · 0 / 32».
final dayRoomProvider = FutureProvider.autoDispose.family<PlanDayRoom, DayAddress>(
  (ref, a) => ref.watch(apiClientProvider).planDayRoom(a.planId, a.number),
);
