/// ЗАЧЁТ ВСЕЙ ФРАЗЫ НА ТЕЛЕФОНЕ — зеркало серверного `SpokenLine` (наряд SPEECH-2, Ч.3).
///
/// Зеркало, а не второе мнение. Инвариант проекта — «проверка на телефоне НИКОГДА не строже
/// серверной», и держится он не бдительностью, а тем, что обе стороны считают ОДНО и тем же:
/// пороги приезжают с сервера ({@see SpeechGradingConfig}), таблица аббревиатур приезжает с
/// сервера, а правило написано здесь теми же тремя ветками, что и там.
///
/// Почему это вообще должен уметь телефон: он показывает вердикт до того, как батч уехал, и
/// работает офлайн. Экран, который ждал бы серверного ответа, молчал бы секунду после каждой
/// реплики — а в разговоре секунда молчания и есть провал.
library;

import 'package:flutter/foundation.dart';

import '../../../data/speech/speech_grading_config.dart';
import 'session_grading.dart';

/// ЧТО СКАЗАТЬ ЧЕЛОВЕКУ — три слова, зеркало серверного `SpokenCredit` (Ч.3.5).
enum SpokenCredit {
  /// «Верно».
  correct,

  /// «Почти — не хватило: …». НЕ зачёт: в журнал уходит тот же промах. Отдельным словом — потому
  /// что «Не то» на реплике, из которой не хватило одного слова, неправда, и человек, услышавший
  /// её трижды, перестаёт говорить вслух.
  almost,

  /// «Не то».
  wrong;

  bool get isAccepted => this == SpokenCredit.correct;
}

/// ВЕРДИКТ ПРО ОДНУ СКАЗАННУЮ РЕПЛИКУ — со всем, чем его можно объяснить.
@immutable
class SpokenVerdict {
  const SpokenVerdict(
    this.credit, {
    this.coverage = 0,
    this.missing = const [],
    this.threshold = '',
    this.normalized = '',
  });

  final SpokenCredit credit;

  /// Доля слов цели, которые прозвучали, 0…1.
  final double coverage;

  /// Каких слов ЦЕЛИ не хватило — в том виде, в каком они написаны на карточке. Это то, что
  /// печатается после «почти — не хватило:».
  final List<String> missing;

  /// `read_aloud` | `key_and_rest` | `whole_line` — какое правило применилось. Для служебной строки.
  final String threshold;

  /// Транскрипт после таблицы аббревиатур — то, что на самом деле сравнивалось.
  final String normalized;

  bool get isAccepted => credit.isAccepted;
}

/// СУДЬЯ РЕЧИ. Чистая функция; ничего не знает ни о виджетах, ни о сети.
abstract final class SpokenLine {
  /// Один вердикт про одну сказанную реплику.
  ///
  /// [printed] — стоит ли текст реплики перед глазами. Это ЕДИНСТВЕННЫЙ вопрос, который выбирает
  /// правило: с текстом задача «прочитай» и порог высокий, без текста — «вспомни», и спрашивается
  /// ключ вместе с остальной фразой.
  ///
  /// [keys] — ключ и его упрощённые формы (`SessionCard.spokenTargets`). Пусто — реплика судится
  /// целиком (фикс DAY-GATE-1: без ключа сервер судит именно так, и телефон обязан не быть строже).
  static SpokenVerdict judge({
    required String transcript,
    required String line,
    List<String> keys = const [],
    bool printed = false,
    SpeechGradingConfig config = SpeechGradingConfig.empty,
  }) {
    // АББРЕВИАТУРЫ — ДО ВСЕГО ОСТАЛЬНОГО: «sequel» это SQL, и считать его лишним словом значит
    // штрафовать человека за то, как звучит буква.
    final heard = normalizeAbbreviations(transcript, config.normalization);

    if (printed || keys.where((k) => k.trim().isNotEmpty).isEmpty) {
      return _against(
        heard: heard,
        displayed: _withoutArticleWords(line),
        target: line,
        threshold: printed ? config.readAloud : config.wholeLine,
        name: printed ? 'read_aloud' : 'whole_line',
        config: config,
        forgiveFiller: printed,
      );
    }

    // КЛЮЧ ОБЯЗАТЕЛЕН. Не найден — это не «почти»: сказано другое, и список «не хватило» из всей
    // реплики ничего бы не объяснил.
    final matched = _matchedKey(heard, keys, config);
    if (matched == null) {
      return SpokenVerdict(
        SpokenCredit.wrong,
        coverage: SessionGrader.coverageOf(heard, line, ignoreArticles: true),
        missing: _missing(heard, _withoutArticleWords(line)),
        threshold: 'key_and_rest',
        normalized: heard,
      );
    }

    // ДВА РАЗНЫХ РОДА КЛЮЧЕЙ, И ПУТАТЬ ИХ НЕЛЬЗЯ — то же различение и теми же словами, что на
    // сервере. `speaking_key` — КУСОК реплики, взятый из дырки рамки: он стоит в ней сплошным
    // куском, и с него спрашивается остальное (это и есть Ч.3.2). `speaking_keys` — ДРУГОЙ СПОСОБ
    // сказать ВСЮ реплику («my back hurts» за «It hurts in my lower back»): сплошным куском он не
    // стоит, и спрашивать с него «остальные слова реплики» значит требовать сказать её дважды.
    if (!_isRunOf(matched, line) && _wordsOf(matched).length >= 2) {
      return SpokenVerdict(
        SpokenCredit.correct,
        coverage: SessionGrader.coverageOf(heard, matched, ignoreArticles: true),
        threshold: 'key_and_rest',
        normalized: heard,
      );
    }

    final rest = _without(line, matched);
    if (rest.trim().isEmpty) {
      return SpokenVerdict(
        SpokenCredit.correct,
        coverage: 1,
        threshold: 'key_and_rest',
        normalized: heard,
      );
    }

    return _against(
      heard: heard,
      displayed: rest,
      target: rest,
      threshold: config.recallRest,
      name: 'key_and_rest',
      config: config,
      forgiveFiller: false,
    );
  }

  /// РАСПОЗНАННЫЕ ФОРМЫ → КАНОН, замена от самой длинной (Ч.4.2).
  ///
  /// Порядок здесь и есть корректность: «a p i» должно съесться целиком, а не оставить после
  /// «a i» → `ai` осиротевшее «p».
  static String normalizeAbbreviations(String transcript, Map<String, String> table) {
    if (table.isEmpty) return transcript;
    final words = _wordsOf(transcript);
    if (words.isEmpty) return transcript;

    final out = <String>[];
    var i = 0;
    while (i < words.length) {
      var matched = false;
      for (var span = _maxSpan; span >= 1; span--) {
        if (i + span > words.length) continue;
        final canon = table[words.sublist(i, i + span).join(' ')];
        if (canon != null) {
          out.add(canon);
          i += span;
          matched = true;
          break;
        }
      }
      if (!matched) {
        out.add(words[i]);
        i++;
      }
    }

    return out.join(' ');
  }

  /// Самая длинная форма таблицы, в словах — потолок окна замены. Зеркало серверного `MAX_SPAN`.
  static const _maxSpan = 4;

  static SpokenVerdict _against({
    required String heard,
    required String displayed,
    required String target,
    required double threshold,
    required String name,
    required SpeechGradingConfig config,
    required bool forgiveFiller,
  }) {
    final ratio = SessionGrader.coverageOf(heard, target, ignoreArticles: true);
    final missing = _missing(heard, displayed);

    // ОДНО ПРОПУЩЕННОЕ СЛОВО-СВЯЗКА ПРОЩАЕТСЯ (Ч.3.1) — и только там, где текст перед глазами:
    // без текста пропущенный предлог это уже другая мысль. Артикли сняты раньше и не считаются.
    final forgiven = forgiveFiller &&
        missing.isNotEmpty &&
        missing.length <= config.fillerAllowance &&
        missing.every((w) => _fillers.contains(SessionGrader.canonical(w)));

    if (ratio >= threshold || forgiven) {
      return SpokenVerdict(SpokenCredit.correct, coverage: ratio, threshold: name, normalized: heard);
    }

    return SpokenVerdict(
      ratio >= config.almostFloor ? SpokenCredit.almost : SpokenCredit.wrong,
      coverage: ratio,
      missing: missing,
      threshold: name,
      normalized: heard,
    );
  }

  static String? _matchedKey(String heard, List<String> keys, SpeechGradingConfig config) {
    for (final key in keys) {
      if (key.trim().isEmpty) continue;
      if (SessionGrader.coverageOf(heard, key, ignoreArticles: true) >= config.wholeLine) return key;
    }

    return null;
  }

  /// Каких слов цели не хватило — теми же словами, что стоят на карточке.
  static List<String> _missing(String heard, String displayed) {
    final raw = displayed.trim();
    if (raw.isEmpty) return const [];
    final words = raw.split(RegExp(r'\s+'));
    final uncovered = SessionGrader.uncoveredWords(heard, raw, ignoreArticles: true);

    return [
      for (var i = 0; i < words.length; i++)
        if (uncovered.contains(i)) _forReading(words[i]),
    ];
  }

  /// Слово для чтения человеком: без хвостовой пунктуации предложения.
  ///
  /// Список печатается через запятую («не хватило: development., us?»), и точка внутри такого
  /// перечисления читается как конец строки. Апостроф и дефис остаются — они внутри слова.
  static String _forReading(String word) =>
      word.replaceAll(RegExp(r'''[.,!?;:»"']+$'''), '');

  /// Стоит ли ключ в реплике СПЛОШНЫМ КУСКОМ — фрагмент это или перефраз.
  static bool _isRunOf(String key, String line) {
    final needle = _wordsOf(key, ignoreArticles: true);
    final hay = _wordsOf(line, ignoreArticles: true);
    if (needle.isEmpty || needle.length > hay.length) return false;
    for (var i = 0; i + needle.length <= hay.length; i++) {
      var match = true;
      for (var k = 0; k < needle.length; k++) {
        if (hay[i + k] != needle[k]) {
          match = false;
          break;
        }
      }
      if (match) return true;
    }

    return false;
  }

  /// Реплика без слов ключа — «остальное». Мультимножеством: одно вхождение на слово, не больше.
  static String _without(String line, String key) {
    final words = _wordsOf(line, ignoreArticles: true);
    for (final word in _wordsOf(key, ignoreArticles: true)) {
      final at = words.indexOf(word);
      if (at >= 0) words.removeAt(at);
    }

    return words.join(' ');
  }

  /// Написанная реплика без артиклей, В НАПИСАННОМ ВИДЕ: слова остаются такими, какими человек их
  /// читает. Из этого строится список «не хватило», и он обязан говорить словами карточки.
  static String _withoutArticleWords(String displayed) => displayed
      .trim()
      .split(RegExp(r'\s+'))
      .where((w) => !_articles.contains(SessionGrader.canonical(w)))
      .join(' ');

  static List<String> _wordsOf(String value, {bool ignoreArticles = false}) {
    final canonical = SessionGrader.canonical(value);
    if (canonical.isEmpty) return [];
    final words = canonical.split(' ');

    return ignoreArticles ? words.where((w) => !_articles.contains(w)).toList() : words;
  }

  static const _articles = {'a', 'an', 'the'};

  /// СЛОВА-СВЯЗКИ: безударные служебные слова, которые распознаватель роняет чаще всего. Зеркало
  /// серверного списка; артиклей здесь нет — они не считаются вовсе.
  static const _fillers = {
    'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'about', 'into', 'over',
    'and', 'or', 'but', 'so', 'that', 'as', 'is', 'am', 'are', 'was', 'were', 'be', 'been',
    'do', 'does', 'did', 'it', 'up', 'out',
  };
}
