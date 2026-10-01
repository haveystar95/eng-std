import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';

import '../../../support/nbsp.dart';
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart';

/// TEXT ON SESSION CARDS IS NEVER CUT (the owner's rule of 16.09, polish pass SESSION-1b′): a line wraps in full
/// whatever its length — no line cap and no ellipsis anywhere in the session's code.
void main() {
  // CATCHES: the partner's line on step 2 of 32-8 cut inside the canvas height 48 (to two lines, with or without an
  // ellipsis), and a long line that runs into the sheet below it.
  testWidgets('phrase_combine step 2: a long partner line wraps in full above the sheet on a small screen', (tester) async {
    final raw = serverFixtureJson('day-doctor');
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == 'phrase_combine');
    final partner = (json['payload'] as Map<String, dynamic>)['partner_line'] as Map<String, dynamic>;
    partner['text_target'] =
        'Where exactly does it hurt the most right now: in his upper back, in his lower back, or somewhere near his neck and shoulders?';
    partner['text_native'] = 'Где именно сейчас болит сильнее всего: вверху спины, в пояснице или где-то рядом с шеей и плечами?';
    final card = SessionCard.fromJson(json)!;

    await pumpCard(tester, probeEnv(card, CardProbe()), size: const Size(375, 497));
    await tapText(tester, 'It hurts in his lower back.');
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();

    final line = find.byKey(const ValueKey('combine-partner-line'));
    expect(line, findsOneWidget);
    final text = tester.widget<Text>(line);
    expect(text.data, '${partner['text_target']} · ${nt(partner['text_native'] as String)}', reason: 'the whole line, both languages — the native half set');
    expect(text.maxLines, isNull);
    expect(text.overflow, isNull);
    final paragraph = tester.renderObject<RenderParagraph>(find.descendant(of: line, matching: find.byType(RichText)));
    expect(paragraph.didExceedMaxLines, isFalse);
    expect(
      paragraph.size.height,
      paragraph.getMaxIntrinsicHeight(paragraph.size.width),
      reason: 'every line of the text is laid out — no fixed height cuts it',
    );
    expect(paragraph.size.height, greaterThan(48), reason: 'the long line wraps past the canvas row height');
    expect(
      tester.getRect(line).bottom,
      lessThanOrEqualTo(tester.getRect(find.byType(SessionFrameText)).top),
      reason: 'the line stays above the sheet',
    );
    expect(tester.takeException(), isNull);
    await settleCard(tester);
  });

  // CATCHES: a line cap, an ellipsis overflow or a drawn ellipsis character put back into any session card or part.
  // An ellipsis inside a RegExp is punctuation the code reads, not text it draws.
  test('the session code has no line caps and no ellipsis', () {
    final offenders = <String>[];
    final files = Directory('lib/features/plan/session').listSync(recursive: true).whereType<File>().where((f) => f.path.endsWith('.dart'));
    for (final file in files) {
      for (final (i, line) in file.readAsLinesSync().indexed) {
        final code = line.trimLeft();
        if (code.startsWith('//')) continue;
        final drawnEllipsis = code.contains('…') && !code.contains('RegExp(');
        if (code.contains('maxLines') || code.contains('TextOverflow.ellipsis') || code.contains('TextOverflow.fade') || drawnEllipsis) {
          offenders.add('${file.path}:${i + 1}: ${code.trim()}');
        }
      }
    }
    expect(files, isNotEmpty);
    expect(offenders, isEmpty);
  });
}
