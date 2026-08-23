import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/search/search_result_card.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Кадр 03 in one sentence: the word that was asked for is the only lifted leaf on the page, it
/// shows enough to be recognised, and the way on is a single terracotta line.
Future<int> _pump(WidgetTester tester, WordCardSubject subject) async {
  var opened = 0;
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(
        body: SearchResultCard(subject: subject, onOpen: () => opened++),
      ),
    ),
  );
  await tester.pump();

  return opened;
}

WordCardSubject _subject() => WordCardSubject.fromHit(
  const SearchHit(
    termId: '01KZETAAA50EMHCN6SP80T8DHC',
    text: 'invoice',
    type: 'word',
    transcription: 'ˈɪnvɔɪs',
    translation: 'счёт',
    description: 'A paper that says how much money you must pay for something.',
    cefr: 'B1',
  ),
);

void main() {
  testWidgets('shows the word, its transcription, translation and level', (tester) async {
    await _pump(tester, _subject());

    expect(find.text('invoice'), findsOneWidget);
    expect(find.text('/ˈɪnvɔɪs/'), findsOneWidget);
    expect(find.text('счёт'), findsOneWidget);
    expect(find.text('B1'), findsOneWidget);
  });

  testWidgets('the description stays OFF the leaf — it belongs to the card', (tester) async {
    // The result is a recognition aid, not the article. Putting the English definition here would
    // make «Открыть карточку» a link to something the learner had already read.
    await _pump(tester, _subject());

    expect(find.text('A paper that says how much money you must pay for something.'), findsNothing);
  });

  testWidgets('the terracotta line opens the card — and so does the leaf itself', (tester) async {
    var opened = 0;
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        home: Scaffold(
          body: SearchResultCard(subject: _subject(), onOpen: () => opened++),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('Открыть карточку'));
    await tester.pump();
    expect(opened, 1);

    await tester.tap(find.text('invoice'));
    await tester.pump();
    expect(opened, 2);
  });

  testWidgets('a word with no photo still draws its plate — the leaf never collapses', (
    tester,
  ) async {
    await _pump(tester, _subject());

    // 88 pt of warm plate, exactly where a photo would be. The composition of кадр 03 does not
    // depend on whether the catalogue happens to have a picture for this word.
    final thumb = tester.widget<SizedBox>(
      find.descendant(
        of: find.byType(SearchResultCard),
        matching: find.byWidgetPredicate((w) => w is SizedBox && w.width == 88 && w.height == 88),
      ),
    );
    expect(thumb.width, 88);
  });
}
