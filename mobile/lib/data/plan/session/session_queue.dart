/// SESSION QUEUE — which card is current and what is left (work order SESSION-1b, section 1).
///
/// A stage's queue is its cards without `result`, by `position`. There is no local progress: the queue is built
/// from the server's response and changes only through its own responses — an answered card is replaced by the
/// card from the response, the copy after the first failure (`requeued`) goes to the end of the stage (it has the
/// next position).
///
/// The header count and the beads are by UNITS (`unit.ref`), not by cards: «4 words left».
library;

import '../plan_models.dart';
import 'session_day.dart';
import 'session_models.dart';

/// A unit's bead under the header (30-2).
enum SessionBead {
  /// All of the unit's cards in the stage are answered — sage.
  done,

  /// The current card's unit — brass.
  current,

  /// Ahead — outline.
  ahead,
}

class SessionQueue {
  SessionQueue(List<SessionStageCards> stages)
    : _cards = {for (final s in stages) s.stage: List.of(s.cards)};

  final Map<PlanStage, List<SessionCard>> _cards;

  /// The stage's cards by `position`.
  List<SessionCard> cardsOf(PlanStage stage) {
    final list = List.of(_cards[stage] ?? const <SessionCard>[]);
    list.sort((a, b) => a.position.compareTo(b.position));
    return list;
  }

  /// The stage's first unanswered card — the stage continues from it.
  SessionCard? nextIn(PlanStage stage) {
    for (final c in cardsOf(stage)) {
      if (!c.isAnswered) return c;
    }
    return null;
  }

  /// The stage has cards, and all of them are answered.
  bool isDone(PlanStage stage) {
    final cards = _cards[stage] ?? const <SessionCard>[];
    return cards.isNotEmpty && cards.every((c) => c.isAnswered);
  }

  bool hasCards(PlanStage stage) => (_cards[stage] ?? const <SessionCard>[]).isNotEmpty;

  /// The day's first stage that still has something to answer; null — everything is answered.
  PlanStage? firstOpenStage() {
    for (final s in PlanStage.known) {
      if (nextIn(s) != null) return s;
    }
    return null;
  }

  /// The stage after [stage] in the day's order that has cards.
  PlanStage? stageAfter(PlanStage stage) {
    final known = PlanStage.known;
    for (var i = known.indexOf(stage) + 1; i < known.length; i++) {
      if (hasCards(known[i])) return known[i];
    }
    return null;
  }

  /// The server's response: a card replaces its previous copy; the copy after a failure — to the end of the stage.
  void apply({SessionCard? answered, SessionCard? requeued}) {
    if (answered != null) _put(answered);
    if (requeued != null) _put(requeued);
  }

  /// Mark a card answered before the server's response (the answer went into the send queue).
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

  /// The stage's units in order of first appearance.
  List<String> unitsOf(PlanStage stage) {
    final seen = <String>{};
    return [
      for (final c in cardsOf(stage))
        if (!c.unit.isDay && seen.add(c.unit.ref)) c.unit.ref,
    ];
  }

  /// The unit is closed in the stage — all of its cards are answered.
  bool unitDone(PlanStage stage, String ref) => cardsOf(stage).where((c) => c.unit.ref == ref).every((c) => c.isAnswered);

  /// «N words left» — units that still have an unanswered card in the stage (the current one too).
  int unitsLeft(PlanStage stage) => unitsOf(stage).where((ref) => !unitDone(stage, ref)).length;

  /// The stage's beads by unit: completed ones in sage, the current one in brass.
  List<SessionBead> beads(PlanStage stage, {String? currentUnit}) => [
    for (final ref in unitsOf(stage))
      if (ref == currentUnit && !unitDone(stage, ref))
        SessionBead.current
      else if (unitDone(stage, ref))
        SessionBead.done
      else
        SessionBead.ahead,
  ];

  /// The header bar: the share of the stage's cards that are answered.
  double progress(PlanStage stage) {
    final cards = cardsOf(stage);
    if (cards.isEmpty) return 0;
    return cards.where((c) => c.isAnswered).length / cards.length;
  }

  /// The stage's units that will return the next day: the server set `returns` on their card.
  List<String> returningUnits(PlanStage stage) {
    final refs = <String>{};
    for (final c in cardsOf(stage)) {
      if (c.returns && !c.unit.isDay) refs.add(c.unit.ref);
    }
    return [for (final ref in unitsOf(stage)) if (refs.contains(ref)) ref];
  }

  /// The unit's first card in the stage that has [T] — the unit's captions come from here (word, phrase, photo).
  T? payloadOfUnit<T extends CardPayload>(PlanStage stage, String ref) {
    for (final c in cardsOf(stage)) {
      if (c.unit.ref == ref && c.payload is T) return c.payload as T;
    }
    return null;
  }
}
