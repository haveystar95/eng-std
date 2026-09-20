/// THE DAY FOR THE SESSION — the body of `GET /plans/{id}/days/{n}` as the session reads it (work order
/// SESSION-1b): stages with cards by `position`, the day status, the scene and the day window (minutes of the
/// current stage).
///
/// The server is the source of truth: the session has no local progress. Every entry into the session reads the
/// day anew, and the session continues from the first unanswered card.
library;

import 'package:flutter/foundation.dart';

import '../day_window.dart';
import '../plan_models.dart';
import 'session_models.dart';
import 'speech_match.dart';

/// A day stage with its cards.
class SessionStageCards {
  const SessionStageCards({
    required this.stage,
    required this.state,
    required this.total,
    required this.done,
    required this.cards,
  });

  final PlanStage stage;
  final PlanStageState state;
  final int total;
  final int done;

  /// By `position`; cards of unknown kinds do not get here.
  final List<SessionCard> cards;
}

/// The `GET …/days/{n}` response as read by the session.
class SessionDay {
  const SessionDay({
    required this.planId,
    required this.day,
    required this.stages,
    this.scene,
    this.window,
    this.speech = SpeechRules.none,
    this.skipped = 0,
  });

  final String planId;
  final PlanDayRoute day;
  final PlanScene? scene;
  final List<SessionStageCards> stages;

  /// WHAT A COMPARISON OF SPEECH KNOWS ABOUT THE TARGET LANGUAGE (work order FIX-2, item 2) — the target pack's own
  /// lists and the loosening handle, once for the whole day. The phone grades a spoken attempt by these, not by a
  /// copy of English in Dart; a day without the block forgives nothing.
  final SpeechRules speech;

  /// The day window — the current stage's «≈ N min» comes from here. Null if the window did not parse: then
  /// there are no minutes.
  final DayWindow? window;

  /// How many cards were skipped: the kind is unknown to this build or the payload is broken.
  final int skipped;

  /// The cards are dealt: an undealt day has empty lists (the outline has no id).
  bool get dealt => stages.any((s) => s.cards.isNotEmpty);

  SessionStageCards? stageOf(PlanStage stage) {
    for (final s in stages) {
      if (s.stage == stage) return s;
    }
    return null;
  }

  /// The frame [frameRef] as a whole phrase — for the «Combination» options (polish pass SESSION-1b′, item 1),
  /// whose payload has only the frame itself: what was said in the dialogue, from the intro (`said`), otherwise
  /// the frame with the filler from the dialogue / the first one, from any card of the day with this frame. The
  /// frame is not in the day — null.
  String? frameSentence(String frameRef) {
    CardFrame? seen;
    for (final card in stages.expand((s) => s.cards)) {
      final payload = card.payload;
      if (payload is PhraseIntroPayload && payload.frame.ref == frameRef) return payload.said.textTarget;
      final frame = switch (payload) {
        PhraseAssemblePayload(:final frame) ||
        PhraseSlotPayload(:final frame) ||
        PhraseSlotListenPayload(:final frame) ||
        PhraseRepeatPayload(:final frame) ||
        PhraseOtherSlotPayload(:final frame) => frame,
        _ => null,
      };
      if (frame != null && frame.ref == frameRef) seen ??= frame;
    }
    return seen?.spoken();
  }

  /// The day's word [unitRef] in the target language, from any card of the day that carries the term. «By ear»
  /// (31-5) has no target text since SESSION-1e — without a file the phone reads this. Not in the day — null.
  String? termText(String unitRef) {
    for (final card in stages.expand((s) => s.cards)) {
      final term = switch (card.payload) {
        WordIntroPayload(:final term) || WordRepeatPayload(:final term) || WordAssemblePayload(:final term) => term,
        _ => null,
      };
      if (term != null && term.ref == unitRef) return term.textTarget;
    }
    return null;
  }

  /// THE EXCHANGE THE LEARNER'S LINE [ownLineTarget] IS SAID IN — «В разговоре» under a phrase whose
  /// frame has nothing to change (кадр 32-1, third state).
  ///
  /// Matched by the TEXT of the line, because that is all there is: `phrase_intro` carries no `usage`
  /// the way a word does (`PlanWindowWord.usage`), so the day's own dialogue is asked instead. No
  /// match — no block: the client does not put the phrase under some other exchange.
  WindowPair? exchangeOf(String ownLineTarget) {
    final needle = ownLineTarget.trim();
    if (needle.isEmpty) return null;
    for (final pair in window?.program.dialogue ?? const <WindowPair>[]) {
      if (pair.learner?.text.trim() == needle) return pair;
    }
    return null;
  }

  /// «≈ N min» — only for the window's current stage; for the others the server does not send the number.
  int? minutesLeft(PlanStage stage) {
    for (final s in window?.stages ?? const <WindowStage>[]) {
      if (s.stage == stage) return s.minutesLeft;
    }
    return null;
  }

  factory SessionDay.fromJson(Map<String, dynamic> j) {
    var skipped = 0;
    final stages = <SessionStageCards>[];
    for (final raw in (j['stages'] as List?) ?? const []) {
      if (raw is! Map<String, dynamic>) continue;
      final stage = PlanStage.fromWire(raw['stage'] as String?);
      if (stage == PlanStage.unknown) continue;
      final cards = <SessionCard>[];
      for (final c in (raw['cards'] as List?) ?? const []) {
        if (c is! Map<String, dynamic>) continue;
        try {
          final card = SessionCard.fromJson(c);
          if (card == null) {
            // A kind this build does not know — skipped without a request to the server.
            skipped++;
            continue;
          }
          cards.add(card);
        } on FormatException catch (e) {
          skipped++;
          debugPrint('[session] card ${c['id']} skipped: $e');
        } on TypeError catch (e) {
          skipped++;
          debugPrint('[session] card ${c['id']} skipped: $e');
        }
      }
      cards.sort((a, b) => a.position.compareTo(b.position));
      stages.add(SessionStageCards(
        stage: stage,
        state: PlanStageState.fromWire(raw['state'] as String?),
        total: (raw['total'] as num?)?.toInt() ?? cards.length,
        done: (raw['done'] as num?)?.toInt() ?? 0,
        cards: cards,
      ));
    }
    DayWindow? window;
    try {
      window = j['window'] == null ? null : DayWindow.fromJson(j['window']);
    } on FormatException catch (e) {
      debugPrint('[session] window unreadable: $e');
    }
    return SessionDay(
      planId: (j['plan_id'] as String?) ?? '',
      day: PlanDayRoute.fromJson(j['day'] as Map<String, dynamic>),
      scene: j['scene'] is Map<String, dynamic> ? PlanScene.fromJson(j['scene'] as Map<String, dynamic>) : null,
      stages: stages,
      window: window,
      speech: j['speech'] is Map<String, dynamic> ? SpeechRules.fromJson(j['speech'] as Map<String, dynamic>) : SpeechRules.none,
      skipped: skipped,
    );
  }
}
