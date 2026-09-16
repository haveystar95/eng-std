/// ЧТО СЕССИЯ ШЛЁТ И ЧТО ЕЙ ОТВЕЧАЮТ — `POST …/cards/{id}/answer` и `POST …/cards/{id}/judge`
/// (наряд SESSION-1b; схемы `PlanAnswerOutcome`, `PlanJudgeOutcome`, `PlanCardAnswerResponse`).
library;

import '../plan_models.dart';
import 'session_models.dart';

/// `response` ответа — только ключи контракта, пустые не шлются.
class SessionResponse {
  const SessionResponse({this.heard, this.hintedAt, this.slotValue, this.fillerIndex, this.mode, this.noMic});

  /// Что распознал микрофон (≤ 1000).
  final String? heard;

  /// Какая подсказка была открыта (≤ 40).
  final String? hintedAt;

  /// Что сказано в окне (≤ 200).
  final String? slotValue;

  /// Выбранное наполнение (0…9).
  final int? fillerIndex;

  /// `chips` | `tiles` | `voice_hint` | `voice_blind`.
  final String? mode;

  /// Микрофона не было.
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

/// Тело `POST …/answer`: итог, число попыток, что осталось от попытки.
class SessionAnswer {
  const SessionAnswer({required this.result, required this.attempts, this.response});

  final SessionResult result;

  /// ≥ 1 — сколько попыток было на карточке, включая эту.
  final int attempts;
  final SessionResponse? response;

  Map<String, dynamic> toJson() => {
    'result': result.wire,
    'attempts': attempts < 1 ? 1 : attempts,
    if (response != null && !response!.isEmpty) 'response': response!.toJson(),
  };
}

/// Что ответ изменил в единице.
class SessionUnitOutcome {
  const SessionUnitOutcome({required this.ref, required this.returnsTomorrow, this.returnsDay});

  final String ref;

  /// Провалена дважды — единица вернётся (ровно один раз, в ближайший следующий день).
  final bool returnsTomorrow;
  final int? returnsDay;
}

/// Ответ `POST …/answer`: `{card, requeued, unit, day, stage}`.
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

  /// Карточка после ответа; null — вид незнаком этой сборке (так не бывает у отвеченной ею карточки).
  final SessionCard? card;

  /// Копия в конце этапа после ПЕРВОГО провала выбора; null — иначе и всегда в этапе слушания.
  final SessionCard? requeued;
  final SessionUnitOutcome unit;
  final int dayCardsTotal;
  final int dayCardsDone;
  final int dayMinutesSpent;
  final PlanStage stage;

  /// Минуты этапа — итог этапа (30-6) пишется отсюда.
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

/// Ответ `POST …/judge`: `{accepted, slot_value, reason_native, result, attempts, card}`.
class SessionJudgeOutcome {
  const SessionJudgeOutcome({
    required this.accepted,
    this.slotValue,
    this.reasonNative,
    this.result,
    required this.attempts,
    this.card,
  });

  final bool accepted;

  /// Что сервер услышал в окне.
  final String? slotValue;

  /// Одна фраза на родном, чего не хватило; null при зачёте.
  final String? reasonNative;

  /// `passed` (`hinted` у каркаса на экране не пишется); null — попытка не зачтена, карточка ждёт.
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
  );
}
