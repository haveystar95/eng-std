/// SESSION QUEUE — which card is current and what is left (work order SESSION-1b, section 1).
///
/// A stage's queue is its cards without `result`, by `position`. There is no local progress: the queue is built
/// from the server's response and changes only through its own responses — an answered card is replaced by the
/// card from the response, the copy after the first failure (`requeued`) goes to the end of the stage (it has the
/// next position).
///
/// The header count and the beads are by UNITS (`unit.ref`, with its scene where a stage walks several), not by
/// cards: «4 words left».
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

  /// «Once more» (SESSION-2a §4): the day as it is, except [stage] — its cards as they were dealt, unanswered on the
  /// phone, and without the copies a first failure added: the stage is walked again, not its retries.
  SessionQueue.replaying(List<SessionStageCards> stages, PlanStage stage)
    : _cards = {
        for (final s in stages)
          s.stage: s.stage == stage
              ? [for (final c in s.cards) if (c.retryOf == null) c.fresh()]
              : List.of(s.cards),
      };

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

  /// THE UNIT'S KEY IN ITS STAGE — its `unit.ref`, and in a stage that walks SEVERAL scenes the ref with its scene
  /// (наряд CLIENT-CONV-1b). Every scene numbers its units from the first (x1, p1, v1), so the rehearsal's «Вспомнить»
  /// and a review hold x3 of one scene beside x3 of another — the live pass counted nine retells of two scenes as six
  /// lines. A stage of one scene keys by the bare ref, as it always did.
  String unitKey(SessionCard card) => _sceneCount(card.stage) > 1 ? '${card.payload.sceneId}/${card.unit.ref}' : card.unit.ref;

  int _sceneCount(PlanStage stage) => {
    for (final c in _cards[stage] ?? const <SessionCard>[])
      if (!c.unit.isDay) c.payload.sceneId,
  }.length;

  /// The unit's first card in the stage — its scene and its bare ref.
  SessionCard? unitCard(PlanStage stage, String key) {
    for (final c in cardsOf(stage)) {
      if (!c.unit.isDay && unitKey(c) == key) return c;
    }
    return null;
  }

  /// The stage's units in order of first appearance, by [unitKey].
  List<String> unitsOf(PlanStage stage) {
    final seen = <String>{};
    return [
      for (final c in cardsOf(stage))
        if (!c.unit.isDay && seen.add(unitKey(c))) unitKey(c),
    ];
  }

  /// The unit is closed in the stage — all of its cards are answered.
  bool unitDone(PlanStage stage, String key) => cardsOf(stage).where((c) => !c.unit.isDay && unitKey(c) == key).every((c) => c.isAnswered);

  /// «N words left» — units that still have an unanswered card in the stage (the current one too). [openUnit] — a unit
  /// counted as left even once its cards are answered: the conversation stages keep the count of the card on screen
  /// until it is left (33-5, 35-3, 35-4 hold one number across all their states; «0 lines left» is never drawn).
  int unitsLeft(PlanStage stage, {String? openUnit}) =>
      unitsOf(stage).where((ref) => ref == openUnit || !unitDone(stage, ref)).length;

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

  /// Beads by CARD, for a stage whose unit is the whole visit («Listen and answer», SESSION-1c): one bead per card of
  /// [kinds] — answered in sage, [currentCardId] in brass, the rest outlined.
  List<SessionBead> cardBeads(PlanStage stage, {required Set<SessionKind> kinds, String? currentCardId}) => [
    for (final c in cardsOf(stage))
      if (kinds.contains(c.kind))
        if (c.id == currentCardId && !c.isAnswered)
          SessionBead.current
        else if (c.isAnswered)
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
    final keys = <String>{};
    for (final c in cardsOf(stage)) {
      if (c.returns && !c.unit.isDay) keys.add(unitKey(c));
    }
    return [for (final key in unitsOf(stage)) if (keys.contains(key)) key];
  }

  /// The unit's first card in the stage that has [T] — the unit's captions come from here (word, phrase, photo).
  T? payloadOfUnit<T extends CardPayload>(PlanStage stage, String key) {
    for (final c in cardsOf(stage)) {
      if (!c.unit.isDay && unitKey(c) == key && c.payload is T) return c.payload as T;
    }
    return null;
  }
}
