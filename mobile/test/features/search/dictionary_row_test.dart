import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/search/dictionary_row.dart';
import 'package:eng_std/theme/theme.dart';

/// The row that replaced the suggestion pills. Its whole job is to be a line in a paper dictionary:
/// the word, what it means, and at most one mark on the right.
Future<void> _pump(WidgetTester tester, Widget row) async {
  await tester.pumpWidget(MaterialApp(home: Scaffold(body: row)));
  await tester.pump();
}

void main() {
  testWidgets('the typed fragment is set in WEIGHT, never in colour', (tester) async {
    // The palette is monochrome (rule 01/02) and the app has exactly one accent, which belongs to
    // the «не знаю» verdict. Weight says «this part is yours» without spending it.
    await _pump(tester, const DictionaryRow(term: 'hollow', translation: 'пустой', prefix: 'holl'));

    final text = tester.widget<Text>(find.byWidgetPredicate(
      (w) => w is Text && w.textSpan != null && w.textSpan!.toPlainText() == 'hollow',
    ));
    final spans = (text.textSpan! as TextSpan).children!.cast<TextSpan>();

    expect(spans.map((s) => s.text).join(), 'hollow');
    expect(spans[0].text, 'holl');
    expect(spans[0].style?.fontWeight, FontWeight.w500);
    expect(spans[0].style?.color, isNull, reason: 'the emphasis must not introduce a colour');
    expect(spans[1].text, 'ow');
  });

  testWidgets('a prefix the term does not actually start with is ignored', (tester) async {
    await _pump(tester, const DictionaryRow(term: 'hollow', prefix: 'xyz'));

    expect(find.text('hollow'), findsOneWidget);
  });

  testWidgets('the level shows only when the row is asked for one', (tester) async {
    await _pump(tester, const DictionaryRow(term: 'hollow', level: 'B2'));
    expect(find.text('B2'), findsNothing);

    await _pump(tester, const DictionaryRow(term: 'hollow', level: 'B2', trailing: RowTrailing.level));
    expect(find.text('B2'), findsOneWidget);
  });

  testWidgets('a row that leads somewhere carries a chevron and is a button to VoiceOver',
      (tester) async {
    var taps = 0;
    await _pump(tester, DictionaryRow(
      term: 'hollow',
      translation: 'пустой',
      trailing: RowTrailing.chevron,
      onTap: () => taps++,
    ));

    await tester.tap(find.text('пустой'));
    await tester.pump();

    expect(taps, 1);
    final semantics = tester.getSemantics(find.byType(DictionaryRow));
    expect(semantics.label, contains('hollow'));
  });

  testWidgets('the last row of a list has no rule under it', (tester) async {
    await _pump(tester, const DictionaryRow(term: 'hollow', showDivider: false));

    final container = tester.widget<Container>(find.descendant(
      of: find.byType(DictionaryRow),
      matching: find.byType(Container),
    ));
    expect(container.decoration, isNull);
  });

  testWidgets('the label above a list is set in caps', (tester) async {
    await _pump(tester, const SearchSectionLabel('Ещё в базе'));

    expect(find.text('ЕЩЁ В БАЗЕ'), findsOneWidget);
    final text = tester.widget<Text>(find.byType(Text));
    expect(text.style?.color, AppColors.tertiary);
  });
}
