import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'day_contract.dart';
import 'plan_models.dart';

/// ЧТО ЧИТАЕТ КАБИНЕТ ДНЯ (наряд DAY-UI). План и плиту таба держит `planTabProvider`
/// (`features/plan/plan_providers.dart`) — здесь только то, что нужно самому дню.
///
/// Адрес — план и номер дня: по номеру день живёт в контракте (`…/days/{n}`), и по нему же его
/// открывает ссылка `engstd://plan/{planId}/day/{n}`.
typedef DayAddress = ({String planId, int number});

/// Кабинет дня — `GET …/days/{n}`. Тот же ответ, что таб читает для плиты, но этот провайдер
/// живёт отдельно: кабинет открывают и по ссылке, без таба на экране.
final dayRoomProvider = FutureProvider.family<PlanDayRoom, DayAddress>(
  (ref, a) => ref.watch(apiClientProvider).planDayRoom(a.planId, a.number),
);

/// Шит дня — слова и фразы с полными данными (кадры 23-14 / 23-15).
final daySheetProvider = FutureProvider.family<DaySheet, DayAddress>(
  (ref, a) => ref.watch(apiClientProvider).daySheet(a.planId, a.number),
);

/// Карточки дня, как их раздал сервер (`GET …/cards`) — пусто у ещё не открытого дня. Кабинет
/// читает по ним раскладку полосок (сдал · с подсказкой · вернётся) и маркеры единиц: в
/// `PlanProgramUnit` нет «с подсказкой», а полоски в трёх цветах без карточек не нарисовать.
final dayCardsProvider = FutureProvider.family<DayCards, DayAddress>(
  (ref, a) => ref.watch(apiClientProvider).dayCards(a.planId, a.number),
);
