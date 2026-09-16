/// ПРАВИЛА ЗАЧЁТА СЕССИИ — что клиент вправе записать и как он сверяет ответ (наряд SESSION-1b).
///
/// Зачитывает клиент, без сети: выбор — id варианта против `correct`, плитки — против `expected`, голос —
/// покрытие речи по `coverage_min` ([SpeechCoverage]). Судейские виды зачитывает только сервер. Последствия
/// (копия в конце этапа, возврат единицы) ведёт сервер — здесь их нет.
///
/// Чистые функции, ни одного виджета.
library;

import 'package:flutter/foundation.dart';

import 'session_models.dart';
import 'speech_coverage.dart';

/// Кусок собранной строки фразы: плитка или окно с наполнением.
sealed class AssemblyPiece {
  const AssemblyPiece();
}

/// Плитка лотка — по месту в `tiles`: две одинаковые плитки остаются разными плитками.
class TilePiece extends AssemblyPiece {
  const TilePiece(this.index, this.text);

  final int index;
  final String text;

  @override
  bool operator ==(Object other) => other is TilePiece && other.index == index && other.text == text;

  @override
  int get hashCode => Object.hash(index, text);
}

/// Окно каркаса с выбранным наполнением.
class SlotPiece extends AssemblyPiece {
  const SlotPiece(this.filler);

  final CardFiller filler;

  @override
  bool operator ==(Object other) => other is SlotPiece && other.filler.index == filler.index;

  @override
  int get hashCode => filler.index.hashCode;
}

abstract final class SessionRules {
  /// Голосовая карточка: после стольких попыток без зачёта она закрывается `skipped`.
  static const int voiceAttempts = 2;

  /// ЧТО ПРИНИМАЕТ СЕРВЕР (`CardKind::allows`, `docs/plan-api.md` «Что клиент шлёт»); чужой итог — 422.
  static Set<SessionResult> serverAccepts(SessionKind kind) => switch (kind.grading) {
    SessionGrading.choice => const {SessionResult.passed, SessionResult.hinted, SessionResult.failed, SessionResult.skipped},
    SessionGrading.voice => const {SessionResult.passed, SessionResult.hinted, SessionResult.skipped},
    SessionGrading.judge => const {SessionResult.skipped},
    SessionGrading.pass => const {SessionResult.passed, SessionResult.skipped},
  };

  /// ЧТО ЭТОТ КЛИЕНТ ПИШЕТ (наряд SESSION-1b, разд. 1) — уже, чем принимает сервер: выбор и плитки —
  /// `passed` | `failed`; голос — `passed` | `skipped` и никогда `failed`; судейский вид — только
  /// `skipped` («Пропустить»); прохождение — `passed`.
  static Set<SessionResult> clientWrites(SessionKind kind) => switch (kind.grading) {
    SessionGrading.choice => const {SessionResult.passed, SessionResult.failed},
    SessionGrading.voice => const {SessionResult.passed, SessionResult.skipped},
    SessionGrading.judge => const {SessionResult.skipped},
    SessionGrading.pass => const {SessionResult.passed},
  };

  /// Проверка перед отправкой: итог, которого этот клиент не пишет, — ошибка программы, а не ответ.
  static bool mayWrite(SessionKind kind, SessionResult result) => clientWrites(kind).contains(result);

  /// Выбор: id варианта = `correct`.
  static bool choiceCorrect(ChoicePayload payload, String optionId) => optionId == payload.correct;

  /// `word_assemble`: собранное = `expected` по порядку, слово в слово (одно написание с плитками).
  static bool wordAssembled(List<String> placed, List<String> expected) => listEquals(placed, expected);

  /// Ожидаемая строка фразы: слова каркаса и окно на месте `slot_at`.
  static List<Object> _expectedSequence(PhraseAssemblePayload p) {
    final seq = <Object>[...p.expectedWords];
    final at = p.slotAt.clamp(0, seq.length);
    seq.insert(at, _Slot(p.fillerIndex));
    return seq;
  }

  static Object _asSequenceItem(AssemblyPiece piece) => switch (piece) {
    TilePiece(:final text) => text,
    SlotPiece(:final filler) => _Slot(filler.index),
  };

  /// `phrase_assemble`: слова = `expected.words` по порядку, окно на месте `slot_at`, наполнение —
  /// `expected.filler_index`.
  static bool phraseAssembled(PhraseAssemblePayload payload, List<AssemblyPiece> pieces) =>
      listEquals(pieces.map(_asSequenceItem).toList(), _expectedSequence(payload));

  /// Первое место, где собранное разошлось с ожидаемым (или где его не хватает), — для контура ошибки.
  static int firstMismatch(List<Object> placed, List<Object> expected) {
    for (var i = 0; i < placed.length; i++) {
      if (i >= expected.length || placed[i] != expected[i]) return i;
    }
    return placed.length < expected.length ? placed.length : -1;
  }

  /// Место первой ошибки во фразе, собранной плитками; -1 — ошибки нет.
  static int phraseMismatch(PhraseAssemblePayload payload, List<AssemblyPiece> pieces) =>
      firstMismatch(pieces.map(_asSequenceItem).toList(), _expectedSequence(payload));

  /// Что должно было стоять на месте ошибки: слово плитки или наполнение окна (null — лишнее в конце).
  static ({String? word, int? fillerIndex})? phraseExpectedAt(PhraseAssemblePayload payload, int at) {
    final seq = _expectedSequence(payload);
    if (at < 0 || at >= seq.length) return null;
    final item = seq[at];
    return item is _Slot ? (word: null, fillerIndex: item.fillerIndex) : (word: item as String, fillerIndex: null);
  }

  /// `phrase_combine`: каркас = `correct_frame`; наполнение — любое из `chips`.
  static bool combineCorrect(PhraseCombinePayload payload, String frameRef) => frameRef == payload.correctFrame;

  /// Зачёт голосовой карточки слов и фраз по услышанному.
  ///
  /// `phrase_other_slot` — каркас и окно раздельно: покрытие `expected_text` по `coverage_min` И все
  /// слова `slot_expected` в услышанном.
  static bool voiceAccepted(CardPayload payload, String heard, Set<String> articles) => switch (payload) {
    WordRepeatPayload(:final expectedText, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles),
    PhraseRepeatPayload(:final expectedText, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles),
    PhraseOtherSlotPayload(:final expectedText, :final slotExpected, :final coverageMin) =>
      SpeechCoverage.covers(heard, expectedText, coverageMin, articles) &&
          SpeechCoverage.covers(heard, slotExpected, SpeechCoverage.all, articles),
    _ => false,
  };

  /// Раздельный зачёт `phrase_other_slot`: каркас (покрытие строки) и окно (все слова окна).
  static ({bool frame, bool slot}) otherSlotParts(PhraseOtherSlotPayload p, String heard, Set<String> articles) => (
    frame: SpeechCoverage.covers(heard, p.expectedText, p.coverageMin, articles),
    slot: SpeechCoverage.covers(heard, p.slotExpected, SpeechCoverage.all, articles),
  );

  /// Что должно прозвучать — для живой строки и подсказки распознавателю.
  static String expectedSpeech(CardPayload payload) => switch (payload) {
    WordRepeatPayload(:final expectedText) => expectedText,
    PhraseRepeatPayload(:final expectedText) => expectedText,
    PhraseOtherSlotPayload(:final expectedText) => expectedText,
    PhraseOwnSlotPayload(:final frame) => frame.parts.before + frame.parts.after,
    _ => '',
  };

  /// Первое слово строки — с заглавной, когда строка собрана целиком (плитки приходят строчными).
  static String capitalized(String text) {
    if (text.isEmpty) return text;
    return text[0].toUpperCase() + text.substring(1);
  }
}

@immutable
class _Slot {
  const _Slot(this.fillerIndex);

  final int? fillerIndex;

  @override
  bool operator ==(Object other) => other is _Slot && other.fillerIndex == fillerIndex;

  @override
  int get hashCode => fillerIndex.hashCode;
}
