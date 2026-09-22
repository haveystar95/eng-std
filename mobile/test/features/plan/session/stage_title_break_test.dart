import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show kSessionGutter;
import 'package:eng_std/features/plan/session/parts/session_stage.dart' show SessionStageTitle;
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;

/// 30-6 — ЗАГОЛОВОК ИТОГА ЭТАПА ПЕРЕНОСИТСЯ ТОЛЬКО ПОСЛЕ ТИРЕ, настоящими шрифтами (Literata 26 заголовка этапа).
void main() {
  setUpAll(setUpPlanGoldens);

  const stages = [PlanStage.listen, PlanStage.speak, PlanStage.recall, PlanStage.words, PlanStage.dialogue, PlanStage.repetition];
  const minutes = [1, 2, 6, 13, 21, 44];

  /// The title as [SessionStageTitle] draws it in a column [width] wide, and where its lines start.
  Future<(String, List<int>)> drawn(WidgetTester tester, String title, double width) async {
    await tester.pumpWidget(MaterialApp(
      home: Material(child: Center(child: SizedBox(width: width, child: SessionStageTitle(title: title)))),
    ));
    final text = tester.widget<Text>(find.byKey(const ValueKey('stage-summary-title'))).data!;
    final painter = TextPainter(text: TextSpan(text: text, style: AppTextSession.stageTitle), textDirection: TextDirection.ltr)
      ..layout(maxWidth: width);
    final starts = <int>{for (var i = 0; i < text.length; i++) painter.getLineBoundary(TextPosition(offset: i)).start}.toList()..sort();
    painter.dispose();
    return (text, starts);
  }

  // ПРАВИЛО (приёмка CLIENT-CONV-1c 22.09, третий заход): заголовку, которому мало строки, перенос — только после тире,
  // «Говорю сам — / пройдено · 6 минут», как кадр 31; у заголовка без тире — перед хвостом («Слова / пройдены · 1
  // минута»); хвост «пройдено · N минут» — одной строкой. Телефон 390.
  // ЛОВИТ: «Говорю сам — пройдено / · 6 минут» (кадр 21 третьего захода), «пройдено · / 6 минут» и тире в начале строки.
  testWidgets('30-6 на 390: перенос только после тире, хвост одной строкой', (tester) async {
    final l = lookupAppLocalizations(const Locale('ru'));
    for (final stage in stages) {
      for (final m in minutes) {
        final (text, starts) = await drawn(tester, SessionTexts.passed(l, stage, m), 390 - 2 * kSessionGutter);
        final tail = text.indexOf('пройден');
        expect({0, tail}.containsAll(starts), isTrue, reason: '«$text»: строки с $starts');
        expect(text, SessionTexts.passed(l, stage, m), reason: 'на 390 строка не меняется');
      }
    }
  });

  // ПРАВИЛО: экрану уже самого хвоста (320: «пройдены · 44 минуты» — 293 из 272) уступает точка — строка может
  // перенестись перед «·», а число остаётся при точке и своём слове; слово не рвётся никогда.
  // ЛОВИТ: «минут / ы» — неразрывный хвост шире строки, который движок текста режет внутри слова.
  testWidgets('30-6 на 320: слово не рвётся, число при точке', (tester) async {
    final l = lookupAppLocalizations(const Locale('ru'));
    for (final stage in stages) {
      for (final m in minutes) {
        final (text, starts) = await drawn(tester, SessionTexts.passed(l, stage, m), 320 - 2 * kSessionGutter);
        for (final s in starts.where((s) => s > 0)) {
          expect(text[s - 1], ' ', reason: '«$text»: перенос не по пробелу — перед ${text.substring(s)}');
        }
        final dot = text.indexOf('·');
        expect(starts.any((s) => s > dot && s < text.length), isFalse, reason: '«$text»: «· $m …» — одной строкой');
      }
    }
  });
}
