/// ОЧЕРЕДЬ СЕССИИ — какая карточка сейчас и что осталось (наряд SESSION-1b, разд. 1).
///
/// Очередь этапа — его карточки без `result`, по `position`. Локального прогресса нет: очередь строится
/// из ответа сервера и меняется только его же ответами — отвеченная карточка заменяется карточкой из
/// ответа, копия после первого провала (`requeued`) встаёт в конец этапа (у неё следующая позиция).
///
/// Счёт шапки и бусины — по ЕДИНИЦАМ (`unit.ref`), не по карточкам: «ещё 4 слова».
library;

import '../plan_models.dart';
import 'session_day.dart';
import 'session_models.dart';

/// Бусина единицы под шапкой (30-2).
enum SessionBead {
  /// Все карточки единицы в этапе отвечены — шалфей.
  done,

  /// Единица текущей карточки — латунь.
  current,

  /// Впереди — контур.
  ahead,
}

class SessionQueue {
  SessionQueue(List<SessionStageCards> stages)
    : _cards = {for (final s in stages) s.stage: List.of(s.cards)};

  final Map<PlanStage, List<SessionCard>> _cards;

  /// Карточки этапа по `position`.
  List<SessionCard> cardsOf(PlanStage stage) {
    final list = List.of(_cards[stage] ?? const <SessionCard>[]);
    list.sort((a, b) => a.position.compareTo(b.position));
    return list;
  }

  /// Первая неотвеченная карточка этапа — с неё этап продолжается.
  SessionCard? nextIn(PlanStage stage) {
    for (final c in cardsOf(stage)) {
      if (!c.isAnswered) return c;
    }
    return null;
  }

  /// У этапа есть карточки, и все отвечены.
  bool isDone(PlanStage stage) {
    final cards = _cards[stage] ?? const <SessionCard>[];
    return cards.isNotEmpty && cards.every((c) => c.isAnswered);
  }

  bool hasCards(PlanStage stage) => (_cards[stage] ?? const <SessionCard>[]).isNotEmpty;

  /// Первый этап дня, где ещё есть что отвечать; null — всё отвечено.
  PlanStage? firstOpenStage() {
    for (final s in PlanStage.known) {
      if (nextIn(s) != null) return s;
    }
    return null;
  }

  /// Этап после [stage] в порядке дня, у которого есть карточки.
  PlanStage? stageAfter(PlanStage stage) {
    final known = PlanStage.known;
    for (var i = known.indexOf(stage) + 1; i < known.length; i++) {
      if (hasCards(known[i])) return known[i];
    }
    return null;
  }

  /// Ответ сервера: карточка заменяет свою прежнюю копию; копия после провала — в конец этапа.
  void apply({SessionCard? answered, SessionCard? requeued}) {
    if (answered != null) _put(answered);
    if (requeued != null) _put(requeued);
  }

  /// Отметить карточку отвеченной до ответа сервера (ответ ушёл в очередь отправки).
  void markAnswered(SessionCard card, SessionResult result, int attempts) {
    _put(SessionCard(
      id: card.id,
      stage: card.stage,
      position: card.position,
      kind: card.kind,
      unit: card.unit,
      returned: card.returned,
      sourceDay: card.sourceDay,
      retryOf: card.retryOf,
      payload: card.payload,
      result: result,
      attempts: attempts,
      response: card.response,
      returns: card.returns,
    ));
  }

  void _put(SessionCard card) {
    final list = _cards.putIfAbsent(card.stage, () => []);
    final i = list.indexWhere((c) => c.id == card.id);
    if (i >= 0) {
      list[i] = card;
    } else {
      list.add(card);
    }
  }

  /// Единицы этапа в порядке первого появления.
  List<String> unitsOf(PlanStage stage) {
    final seen = <String>{};
    return [
      for (final c in cardsOf(stage))
        if (!c.unit.isDay && seen.add(c.unit.ref)) c.unit.ref,
    ];
  }

  /// Единица закрыта в этапе — все её карточки отвечены.
  bool unitDone(PlanStage stage, String ref) => cardsOf(stage).where((c) => c.unit.ref == ref).every((c) => c.isAnswered);

  /// «ещё N слов» — единицы, у которых в этапе осталась неотвеченная карточка (текущая тоже).
  int unitsLeft(PlanStage stage) => unitsOf(stage).where((ref) => !unitDone(stage, ref)).length;

  /// Бусины этапа по единицам: пройденные шалфеем, текущая латунью.
  List<SessionBead> beads(PlanStage stage, {String? currentUnit}) => [
    for (final ref in unitsOf(stage))
      if (ref == currentUnit && !unitDone(stage, ref))
        SessionBead.current
      else if (unitDone(stage, ref))
        SessionBead.done
      else
        SessionBead.ahead,
  ];

  /// Полоса шапки: доля отвеченных карточек этапа.
  double progress(PlanStage stage) {
    final cards = cardsOf(stage);
    if (cards.isEmpty) return 0;
    return cards.where((c) => c.isAnswered).length / cards.length;
  }

  /// Единицы этапа, которые вернутся на следующий день: у их карточки сервер поставил `returns`.
  List<String> returningUnits(PlanStage stage) {
    final refs = <String>{};
    for (final c in cardsOf(stage)) {
      if (c.returns && !c.unit.isDay) refs.add(c.unit.ref);
    }
    return [for (final ref in unitsOf(stage)) if (refs.contains(ref)) ref];
  }

  /// Первая карточка единицы в этапе, у которой есть [T], — отсюда подписи единицы (слово, фраза, фото).
  T? payloadOfUnit<T extends CardPayload>(PlanStage stage, String ref) {
    for (final c in cardsOf(stage)) {
      if (c.unit.ref == ref && c.payload is T) return c.payload as T;
    }
    return null;
  }
}
