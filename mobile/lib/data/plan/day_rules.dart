/// ПРАВИЛА ЗАЧЁТА ДНЯ — канон наряда DAY-UI, раздел 5. Чистые функции, ни одного виджета.
///
/// Выбор и сборка: верно с первого раза → `passed`; ошибка → карточка в конец этапа; вторая
/// ошибка → `failed` («вернётся в день N»), этап идёт дальше. Микрофон: зачёт, если услышан
/// `speaking_key` или ≥ 70 % слов любого варианта или текста; вторая попытка без зачёта →
/// «Пропустить» = `skipped` без штрафа; произношение никогда не «неверно». Подсказка-ключ —
/// `passed`; подсказка-текст — `hinted`.
///
/// Покрытие считается ТЕМ ЖЕ токенайзером, что на сервере (`Words::tokens` / `Words::coverage`):
/// нижний регистр, знаки препинания сняты, апостроф и дефис внутри слова остаются, мультимножество
/// слов ожидаемого текста против сказанного.
library;

import 'plan_models.dart';
import 'day_contract.dart';

/// Что случилось с оценённой карточкой после одного хода.
enum DayAttempt {
  /// Верно — карточка закрыта `passed`.
  passed,

  /// Первая ошибка — карточка вернётся в конец этапа.
  requeue,

  /// Вторая ошибка — `failed`, вернётся в следующий день.
  failed,
}

abstract final class DayRules {
  /// После скольких попыток без зачёта произносимая карточка предлагает «Пропустить».
  static const spokenAttemptsBeforeSkip = 2;

  /// Порог покрытия по умолчанию, если пейлоад его не принёс.
  static const defaultCoverage = 0.7;

  /// Выбор и сборка. [isRetry] — эта карточка уже вернулась в конец этапа после первой ошибки
  /// (`retry_of != null`): её ошибка — вторая.
  static DayAttempt graded({required bool correct, required bool isRetry}) {
    if (correct) return DayAttempt.passed;
    return isRetry ? DayAttempt.failed : DayAttempt.requeue;
  }

  /// Результат, который уходит на сервер за ход выбора/сборки: `passed` или `failed`; повтор в
  /// конце этапа сервер ставит сам по первому `failed` ([DayAnswerOutcome.requeued]).
  static DayCardResult resultOf(DayAttempt attempt) =>
      attempt == DayAttempt.passed ? DayCardResult.passed : DayCardResult.failed;

  /// Зачёт речи: услышан ключ (целиком, как последовательность слов) — или покрытие ожидаемого
  /// текста либо любого варианта не ниже [coverage].
  static bool spokenAccepted({
    required String transcript,
    required String expected,
    String? key,
    List<String> variants = const [],
    double coverage = defaultCoverage,
  }) {
    final heard = tokens(transcript);
    if (heard.isEmpty) return false;
    if (key != null && key.trim().isNotEmpty && containsRun(heard, tokens(key))) return true;
    for (final target in [expected, ...variants]) {
      if (target.trim().isEmpty) continue;
      if (coverageOf(expected: target, spoken: transcript) >= coverage) return true;
    }
    return false;
  }

  /// Результат произносимой карточки после зачёта с подсказкой уровня [hintLevel]: 0 или 1 (ключ —
  /// опора, не подсказка) → `passed`; 2 (весь текст) → `hinted`.
  static DayCardResult spokenResult({required int hintLevel}) =>
      hintLevel >= 2 ? DayCardResult.hinted : DayCardResult.passed;

  /// Показывать ли «Пропустить»: после [spokenAttemptsBeforeSkip] попыток без зачёта — или сразу,
  /// если микрофона нет.
  static bool canSkip({required int attempts, required bool micUnavailable}) =>
      micUnavailable || attempts >= spokenAttemptsBeforeSkip;

  /// Слова в нижнем регистре без знаков; апостроф и дефис внутри слова остаются.
  static List<String> tokens(String text) {
    final cleaned = text.toLowerCase().replaceAll(RegExp(r"[^\p{L}\p{N}'’\s-]", unicode: true), ' ');
    return [
      for (final w in cleaned.trim().split(RegExp(r'\s+')))
        if (w.replaceAll(RegExp(r"^['’-]+|['’-]+$"), '').isNotEmpty)
          w.replaceAll(RegExp(r"^['’-]+|['’-]+$"), ''),
    ];
  }

  /// Доля слов [expected], которые есть в [spoken], 0…1 — мультимножеством.
  static double coverageOf({required String expected, required String spoken}) {
    final want = tokens(expected);
    if (want.isEmpty) return 0;
    final have = <String, int>{};
    for (final w in tokens(spoken)) {
      have[w] = (have[w] ?? 0) + 1;
    }
    var hit = 0;
    for (final w in want) {
      if ((have[w] ?? 0) > 0) {
        hit++;
        have[w] = have[w]! - 1;
      }
    }
    return hit / want.length;
  }

  /// Стоит ли [needle] в [hay] сплошным куском.
  static bool containsRun(List<String> hay, List<String> needle) {
    if (needle.isEmpty || needle.length > hay.length) return false;
    for (var i = 0; i + needle.length <= hay.length; i++) {
      var ok = true;
      for (var k = 0; k < needle.length; k++) {
        if (hay[i + k] != needle[k]) {
          ok = false;
          break;
        }
      }
      if (ok) return true;
    }
    return false;
  }

  /// Сборка: собранное совпадает с ответом по словам (регистр и знаки не считаются).
  static bool assembledMatches({required List<String> placed, required String answer}) {
    final want = tokens(answer);
    final got = tokens(placed.join(' '));
    if (want.length != got.length) return false;
    for (var i = 0; i < want.length; i++) {
      if (want[i] != got[i]) return false;
    }
    return true;
  }

  /// Индекс ЛИШНЕЙ собранной плитки — первой, которой нет в ответе; null, если все свои.
  static int? extraTileIndex({required List<String> placed, required String answer}) {
    final want = <String, int>{};
    for (final w in tokens(answer)) {
      want[w] = (want[w] ?? 0) + 1;
    }
    for (var i = 0; i < placed.length; i++) {
      final ts = tokens(placed[i]);
      var own = true;
      for (final t in ts) {
        if ((want[t] ?? 0) > 0) {
          want[t] = want[t]! - 1;
        } else {
          own = false;
        }
      }
      if (!own) return i;
    }
    return null;
  }

  /// «Услышал → собери» — только Intermediate, только утверждения ≤ 10 слов. Клиент это НЕ решает
  /// (вид карточки отдаёт сервер); правило здесь — чтобы тест канона мог его назвать.
  static bool listenAssembleAllowed({required PlanLevel level, required String partnerLine}) =>
      level == PlanLevel.intermediate &&
      !partnerLine.trim().endsWith('?') &&
      tokens(partnerLine).length <= 10;

  /// Оценка «≈ N минут» для входа в этап: у сервера минут на этап нет, клиент считает грубо.
  static int estimateMinutes(int cards) => cards <= 0 ? 0 : ((cards * 13) / 60).ceil();
}
