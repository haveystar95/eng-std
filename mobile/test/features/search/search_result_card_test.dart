import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/search/search_result_card.dart';
import 'package:eng_std/features/word_card/word_card_subject.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Кадр 03 in one sentence: the word that was asked for is the only lifted leaf on the page, it
/// shows enough to be recognised, and the way on is a single terracotta line.
Future<int> _pump(
  WidgetTester tester,
  WordCardSubject subject, {
  bool showTransliteration = false,
}) async {
  var opened = 0;
  await tester.pumpWidget(
    MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(
        body: SearchResultCard(
          subject: subject,
          onOpen: () => opened++,
          showTransliteration: showTransliteration,
        ),
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

  /// Ч.3 — the translator's own card. Same three additive products as the word card, same rule:
  /// a missing one is a missing block, never an empty one.
  group('ядро v15 · чтение и доп-переводы', () {
    WordCardSubject saved({
      String? transliteration,
      List<String> translations = const [],
      List<String> synonyms = const [],
    }) => WordCardSubject(
      termId: 'ID',
      text: 'knife',
      type: 'word',
      transcription: 'naɪf',
      transliteration: transliteration,
      translation: 'нож',
      translations: translations,
      synonyms: synonyms,
    );

    testWidgets('the reading sits beside the IPA, in brackets — and only when the switch is on', (
      tester,
    ) async {
      await _pump(tester, saved(transliteration: 'найф'), showTransliteration: true);

      expect(find.text('/naɪf/'), findsOneWidget); // the IPA never moves
      expect(find.text('[найф]'), findsOneWidget);
    });

    testWidgets('the switch off hides the reading and nothing else', (tester) async {
      await _pump(tester, saved(transliteration: 'найф'));

      expect(find.text('[найф]'), findsNothing);
      expect(find.text('/naɪf/'), findsOneWidget);
      expect(find.text('нож'), findsOneWidget);
    });

    testWidgets('a word with no reading draws no bracket at all', (tester) async {
      await _pump(tester, saved(), showTransliteration: true);

      expect(find.textContaining('['), findsNothing);
      expect(find.text('/naɪf/'), findsOneWidget);
    });

    testWidgets('extra readings follow the pinned one through « / »', (tester) async {
      await _pump(tester, saved(translations: const ['нож', 'тесак']));

      expect(find.text('нож / тесак'), findsOneWidget);
    });

    testWidgets('one reading prints exactly as it did before the list existed', (tester) async {
      await _pump(tester, saved(translations: const ['нож']));

      expect(find.text('нож'), findsOneWidget);
    });

    testWidgets('synonyms belong to the CARD — the leaf stays a recognition aid', (tester) async {
      // The «также: …» line is part of the article, like the description above it. Putting it on
      // the leaf would make «Открыть карточку» a link to something already read.
      await _pump(tester, saved(synonyms: const ['blade']));

      expect(find.textContaining('также'), findsNothing);
      expect(find.text('blade'), findsNothing);
    });
  });
}
