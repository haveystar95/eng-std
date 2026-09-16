/// ЖИВАЯ СТРОКА МИКРОФОНА (кадр 30-3): что распознаватель уже услышал, словами — совпавшие с ожидаемым
/// текстом шалфеем, последнее слово, пока запись идёт, серым, остальные чернилами.
///
/// Чистая функция: слова сравниваются в канонической форме покрытия речи ([SpeechCoverage.words]) и
/// мультимножеством — слово, которое в ожидаемом тексте одно, шалфеем красится один раз. Поверхностное
/// слово распознавателя может дать несколько канонических («doesn't» → `does not`, «X-ray» → `x ray`):
/// совпавшим оно считается, только когда совпали все.
library;

import 'speech_coverage.dart';

/// Цвет слова живой строки.
enum LiveTone {
  /// Совпало с ожидаемым текстом — шалфей.
  matched,

  /// Последнее слово, пока запись идёт, — серым: распознаватель его ещё уточняет.
  pending,

  /// Не совпало — чернила.
  plain,
}

/// Одно слово живой строки — как его написал распознаватель.
typedef LiveWord = ({String text, LiveTone tone});

abstract final class LiveLine {
  /// Слова [heard] с цветом. [listening] — запись ещё идёт: последнее слово серое.
  static List<LiveWord> of(String heard, String expected, {required bool listening}) {
    final surface = heard.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    if (surface.isEmpty) return const [];
    final available = <String, int>{};
    for (final w in SpeechCoverage.words(expected)) {
      available[w] = (available[w] ?? 0) + 1;
    }
    final out = <LiveWord>[];
    for (var i = 0; i < surface.length; i++) {
      final text = surface[i];
      if (listening && i == surface.length - 1) {
        out.add((text: text, tone: LiveTone.pending));
        continue;
      }
      final tokens = SpeechCoverage.words(text);
      final need = <String, int>{};
      for (final t in tokens) {
        need[t] = (need[t] ?? 0) + 1;
      }
      final matched = tokens.isNotEmpty && need.entries.every((e) => (available[e.key] ?? 0) >= e.value);
      if (matched) {
        for (final e in need.entries) {
          available[e.key] = available[e.key]! - e.value;
        }
      }
      out.add((text: text, tone: matched ? LiveTone.matched : LiveTone.plain));
    }
    return out;
  }
}
