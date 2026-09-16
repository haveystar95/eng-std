/// THE VISIT PLAYER'S TIMELINE (`listen_dialogue`, work order SESSION-1c, section 3; canvas 34-1) — the order the
/// lines play in, where each line starts, the exchange marks on the bar and the whole length.
///
/// The files play in the order of `lines[]`, one stream. The length is `total_ms`, otherwise the sum of the lines'
/// `duration_ms`; when neither is known the bar moves by lines and the marks stand evenly. A mark closes an exchange
/// (`exchange_step`): «By parts» pauses right after the last line of each exchange.
///
/// Pure functions, not a single widget.
library;

import 'package:flutter/foundation.dart';

import 'session_models.dart';

/// A mark on the player's bar — the end of one exchange.
@immutable
class ListenMark {
  const ListenMark({required this.step, required this.lastLine, required this.at});

  /// `exchange_step` of the exchange; null — the line has no step.
  final int? step;

  /// The index of the exchange's last line in `lines[]`.
  final int lastLine;

  /// Where the mark stands on the bar, 0…1.
  final double at;
}

@immutable
class ListenTimeline {
  const ListenTimeline._({required this.lines, required this.starts, required this.totalMs, required this.marks});

  factory ListenTimeline.of(ListenDialoguePayload p) {
    final lines = p.lines;
    final durations = [for (final l in lines) l.audio?.durationMs];
    final known = durations.every((d) => d != null && d > 0);
    final sum = known ? durations.fold<int>(0, (a, d) => a + d!) : null;
    final total = (p.totalMs != null && p.totalMs! > 0) ? p.totalMs : sum;
    final starts = <int>[];
    var at = 0;
    for (final d in durations) {
      starts.add(at);
      at += d ?? 0;
    }
    // The last line of every exchange: the next line belongs to another step (or there is no next line).
    final ends = <int>[
      for (var i = 0; i < lines.length; i++)
        if (i == lines.length - 1 || lines[i + 1].exchangeStep != lines[i].exchangeStep) i,
    ];
    final marks = <ListenMark>[
      for (final (k, end) in ends.indexed)
        ListenMark(
          step: lines[end].exchangeStep,
          lastLine: end,
          at: known && sum! > 0 ? (starts[end] + durations[end]!) / sum : (k + 1) / ends.length,
        ),
    ];
    return ListenTimeline._(lines: lines, starts: known ? starts : null, totalMs: total, marks: marks);
  }

  final List<CardVisitLine> lines;

  /// Where each line starts, ms; null — the lines' lengths are not all known.
  final List<int>? starts;

  /// The whole visit, ms; null — unknown.
  final int? totalMs;
  final List<ListenMark> marks;

  /// How many exchanges the visit has — «8 exchanges».
  int get exchanges => marks.length;

  /// Line [index] is the last one of its exchange — the «By parts» pause point.
  bool endsExchange(int index) => marks.any((m) => m.lastLine == index);

  /// The ordinal (from 1) of the exchange line [index] belongs to.
  int exchangeOrdinal(int index) {
    for (final (k, m) in marks.indexed) {
      if (index <= m.lastLine) return k + 1;
    }
    return marks.length;
  }

  /// The bar's fill when line [index] has played for [intoLine]: by time when the lengths are known, otherwise by
  /// lines (the playing line counts half).
  double progress(int index, Duration intoLine) {
    if (lines.isEmpty) return 0;
    if (index >= lines.length) return 1;
    final s = starts;
    final d = lines[index].audio?.durationMs;
    if (s != null && d != null) {
      final sum = s.last + (lines.last.audio?.durationMs ?? 0);
      if (sum <= 0) return 0;
      final into = intoLine.inMilliseconds.clamp(0, d);
      return ((s[index] + into) / sum).clamp(0.0, 1.0);
    }
    return ((index + 0.5) / lines.length).clamp(0.0, 1.0);
  }

  /// Played time when line [index] has played for [intoLine] — known lengths only.
  Duration? elapsed(int index, Duration intoLine) {
    final s = starts;
    if (s == null || lines.isEmpty) return null;
    if (index >= lines.length) return totalMs == null ? null : Duration(milliseconds: totalMs!);
    final d = lines[index].audio?.durationMs ?? 0;
    return Duration(milliseconds: s[index] + intoLine.inMilliseconds.clamp(0, d));
  }
}
