import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../providers.dart';
import 'plan_contract.dart';

/// ПЛАН И ДЕНЬ ЧИТАЮТСЯ С СЕРВЕРА ЖИВЬЁМ (наряд DAY-UI). Состояния карточек, этапов и метрики
/// считает сервер по своему журналу; кэшированная копия была бы вторым, устаревшим мнением о том,
/// где стоит ученик. Экраны читают провайдеры и инвалидируют их после каждого действия, которое
/// что-то меняет: открыть день, ответить, закрыть этап, закрыть день.

/// План, на котором стоит ученик, или null — `GET /plans/current`.
final currentPlanProvider = FutureProvider<Plan?>((ref) => ref.watch(apiClientProvider).currentPlan());

/// Адрес одного дня плана.
typedef DayAddress = ({String planId, int number});

/// Кабинет дня — `GET /plans/{id}/days/{n}` (кадры 23-0a…0e).
final dayRoomProvider = FutureProvider.family<DayRoom, DayAddress>(
  (ref, a) => ref.watch(apiClientProvider).dayRoom(a.planId, a.number),
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
