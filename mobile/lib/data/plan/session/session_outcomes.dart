/// WHAT THE SESSION SENDS AND WHAT IT GETS BACK — `POST …/cards/{id}/answer` and `POST …/cards/{id}/judge`
/// (work order SESSION-1b; schemas `PlanAnswerOutcome`, `PlanJudgeOutcome`, `PlanCardAnswerResponse`).
library;

import '../plan_models.dart';
import 'session_models.dart';

/// The answer's `response` — only the contract's keys; empty ones are not sent.
class SessionResponse {
  const SessionResponse({this.heard, this.hintedAt, this.slotValue, this.fillerIndex, this.mode, this.noMic});

  /// What the microphone recognized (≤ 1000).
  final String? heard;

  /// Which hint was opened (≤ 40).
  final String? hintedAt;

  /// What was said in the slot (≤ 200).
  final String? slotValue;

  /// The chosen filler (0…9).
  final int? fillerIndex;

  /// `chips` | `tiles` | `voice_hint` | `voice_blind`.
  final String? mode;

  /// There was no microphone.
  final bool? noMic;

  static const int heardMax = 1000;
  static const int hintedAtMax = 40;
  static const int slotValueMax = 200;

  Map<String, dynamic> toJson() => {
    if (heard != null) 'heard': _cut(heard!, heardMax),
    if (hintedAt != null) 'hinted_at': _cut(hintedAt!, hintedAtMax),
    if (slotValue != null) 'slot_value': _cut(slotValue!, slotValueMax),
    if (fillerIndex != null && fillerIndex! >= 0 && fillerIndex! <= 9) 'filler_index': fillerIndex,
    if (mode != null) 'mode': mode,
    if (noMic != null) 'no_mic': noMic,
  };

  bool get isEmpty => toJson().isEmpty;

  static String _cut(String s, int max) => s.length <= max ? s : s.substring(0, max);
}

/// The body of `POST …/answer`: the result, the number of attempts, what is left of the attempt.
class SessionAnswer {
  const SessionAnswer({required this.result, required this.attempts, this.response, this.choice});

  final SessionResult result;

  /// ≥ 1 — how many attempts there were on the card, including this one.
  final int attempts;
  final SessionResponse? response;

  /// The id of the option chosen in a check that stands on the card BESIDE its own task — 33-5 (BACK-TAILS-1 §1,
  /// доработка). It rides next to [result], not inside [response]: a wrong choice has the same consequences as a
  /// wrong choice card (a copy at the end of the stage, then the exchange's return). Null — the card has no check.
  final String? choice;

  /// The same answer with the check's choice added — the voice result is recorded when the learner spoke, the
  /// choice only when they answered the question, and both fly as ONE answer (33-5).
  SessionAnswer withChoice(String optionId) =>
      SessionAnswer(result: result, attempts: attempts, response: response, choice: optionId);

  Map<String, dynamic> toJson() => {
    'result': result.wire,
    'attempts': attempts < 1 ? 1 : attempts,
    if (response != null && !response!.isEmpty) 'response': response!.toJson(),
    if (choice != null) 'choice': choice,
  };
}

/// What the answer changed in the unit.
class SessionUnitOutcome {
  const SessionUnitOutcome({required this.ref, required this.returnsTomorrow, this.returnsDay});

  final String ref;

  /// Failed twice — the unit will return (exactly once, on the nearest following day).
  final bool returnsTomorrow;
  final int? returnsDay;
}

/// The `POST …/answer` response: `{card, requeued, unit, day, stage}`.
class SessionAnswerOutcome {
  const SessionAnswerOutcome({
    required this.card,
    this.requeued,
    required this.unit,
    required this.dayCardsTotal,
    required this.dayCardsDone,
    required this.dayMinutesSpent,
    required this.stage,
    required this.stageMinutesSpent,
  });

  /// The card after the answer; null — the kind is unknown to this build (this does not happen for a card this
  /// build answered).
  final SessionCard? card;

  /// The copy at the end of the stage after the FIRST failure of a choice; null — otherwise, and always in the
  /// listening stage.
  final SessionCard? requeued;
  final SessionUnitOutcome unit;
  final int dayCardsTotal;
  final int dayCardsDone;
  final int dayMinutesSpent;
  final PlanStage stage;

  /// The stage's minutes — the stage summary (30-6) is written from here.
  final int stageMinutesSpent;

  factory SessionAnswerOutcome.fromJson(Map<String, dynamic> j) {
    final unit = (j['unit'] as Map?)?.cast<String, dynamic>() ?? const {};
    final day = (j['day'] as Map?)?.cast<String, dynamic>() ?? const {};
    final stage = (j['stage'] as Map?)?.cast<String, dynamic>() ?? const {};
    return SessionAnswerOutcome(
      card: j['card'] is Map<String, dynamic> ? SessionCard.fromJson(j['card'] as Map<String, dynamic>) : null,
      requeued: j['requeued'] is Map<String, dynamic> ? SessionCard.fromJson(j['requeued'] as Map<String, dynamic>) : null,
      unit: SessionUnitOutcome(
        ref: (unit['ref'] as String?) ?? '',
        returnsTomorrow: unit['returns_tomorrow'] == true,
        returnsDay: (unit['returns_day'] as num?)?.toInt(),
      ),
      dayCardsTotal: (day['cards_total'] as num?)?.toInt() ?? 0,
      dayCardsDone: (day['cards_done'] as num?)?.toInt() ?? 0,
      dayMinutesSpent: (day['minutes_spent'] as num?)?.toInt() ?? 0,
      stage: PlanStage.fromWire(stage['stage'] as String?),
      stageMinutesSpent: (stage['minutes_spent'] as num?)?.toInt() ?? 0,
    );
  }
}

/// The `POST …/judge` response: `{accepted, slot_value, reason_native, result, attempts, card}`.
class SessionJudgeOutcome {
  const SessionJudgeOutcome({
    required this.accepted,
    this.slotValue,
    this.reasonNative,
    this.result,
    required this.attempts,
    this.card,
    this.heard,
  });

  final bool accepted;

  /// WHAT THE JUDGE JUDGED — `heard` as it reached the server (наряд CONV-2, п. 8): «услышал: …» under a refusal is
  /// this, so a learner tells a recogniser that misheard from a judge that misjudged. Null — a server before that (or a
  /// verdict the phone gave itself), and the screen prints what the phone recognised.
  final String? heard;

  /// What the server heard in the slot.
  final String? slotValue;

  /// One sentence in the native language about what was missing; null on a pass.
  final String? reasonNative;

  /// `passed` (`hinted` is not written for a frame shown on screen); null — the attempt did not pass, the card
  /// waits.
  final SessionResult? result;
  final int attempts;
  final SessionCard? card;

  factory SessionJudgeOutcome.fromJson(Map<String, dynamic> j) => SessionJudgeOutcome(
    accepted: j['accepted'] == true,
    slotValue: j['slot_value'] is String && (j['slot_value'] as String).trim().isNotEmpty ? j['slot_value'] as String : null,
    reasonNative: j['reason_native'] is String && (j['reason_native'] as String).trim().isNotEmpty
        ? j['reason_native'] as String
        : null,
    result: SessionResult.fromWire(j['result']),
    attempts: (j['attempts'] as num?)?.toInt() ?? 0,
    card: j['card'] is Map<String, dynamic> ? SessionCard.fromJson(j['card'] as Map<String, dynamic>) : null,
    heard: j['heard'] is String && (j['heard'] as String).trim().isNotEmpty ? j['heard'] as String : null,
  );
}
