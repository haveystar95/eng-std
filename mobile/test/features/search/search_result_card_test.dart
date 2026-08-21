import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_result_card.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The card's job in one sentence: never offer a save that would do nothing, and never hide the
/// English half of a word behind its translation.
Future<void> _pump(
  WidgetTester tester, {
  SearchHit? hit,
  LookupCard? lookup,
  List<WordCollection> collections = const [],
}) async {
  await tester.pumpWidget(ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      collectionsProvider.overrideWith((ref) => Stream.value(collections)),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(
        body: SearchResultCard(
          hit: hit,
          lookup: lookup,
          onSpeak: () {},
          onSaved: (_) async {},
        ),
      ),
    ),
  ));
  await tester.pump();
}

SearchHit _hit({List<SavedFolder> folders = const []}) => SearchHit(
      termId: '01KZETAAA50EMHCN6SP80T8DHC',
      text: 'invoice',
      type: 'word',
      translation: 'счёт',
      description: 'A paper that says how much money you must pay for something.',
      example: 'They sent the invoice by email.',
      exampleTranslation: 'Они прислали счёт по почте.',
      cefr: 'B1',
      folders: folders,
    );

LookupCard _lookup() => const LookupCard(
      lookupId: '01KZETAAF37FWHW8WKDRGK71WN',
      text: 'reimbursement',
      type: 'word',
      translation: 'возмещение расходов',
      description: 'Money you get back after you paid for something at work.',
      example: 'She asked for reimbursement of her travel costs.',
      exampleTranslation: 'Она попросила возместить расходы на поездку.',
      cefr: 'B2',
      fresh: true,
    );

void main() {
  testWidgets('an unsaved word offers «+ Сохранённые»', (tester) async {
    await _pump(tester, hit: _hit());

    expect(find.text('+ Сохранённые'), findsOneWidget);
  });

  testWidgets('a word already in a folder NAMES the folder instead of offering the save',
      (tester) async {
    // The rule this pins: a one-tap save with nothing to do is a broken button. The learner is told
    // where the word already is, by the folder's CURRENT name — which they may have changed.
    await _pump(tester, hit: _hit(folders: [
      const SavedFolder(id: '01KZETAAB50EMHCN6SP80T8DHC', title: 'Мои находки', isDefault: true),
    ]));

    expect(find.text('В «Мои находки»'), findsOneWidget);
    expect(find.text('+ Сохранённые'), findsNothing);
  });

  testWidgets('the English description is shown, and never replaced by the translation',
      (tester) async {
    await _pump(tester, hit: _hit());

    // Both are on the card, and the description is the English one: this trainer's whole premise is
    // that reading the definition in the language being learned is part of what the word costs.
    expect(find.text('A paper that says how much money you must pay for something.'), findsOneWidget);
    expect(find.text('счёт'), findsOneWidget);
  });

  testWidgets('a looked-up word renders exactly like a database hit', (tester) async {
    // One widget for both on purpose — the learner must not be able to tell which words the app
    // happened to have already.
    await _pump(tester, lookup: _lookup());

    expect(find.text('reimbursement'), findsOneWidget);
    expect(find.text('Money you get back after you paid for something at work.'), findsOneWidget);
    expect(find.text('+ Сохранённые'), findsOneWidget);
    expect(find.text('B2'), findsOneWidget);
  });

  testWidgets('the example is shown with the term picked out, not as flat text', (tester) async {
    await _pump(tester, hit: _hit());

    final rich = tester.widget<Text>(find.byWidgetPredicate(
      (w) => w is Text && w.textSpan != null && (w.textSpan!.toPlainText()).contains('invoice'),
    ));
    final spans = (rich.textSpan! as TextSpan).children!;

    expect(spans.map((s) => (s as TextSpan).text).join(), 'They sent the invoice by email.');
    expect((spans[1] as TextSpan).text, 'invoice');
    expect((spans[1] as TextSpan).style?.fontWeight, FontWeight.w700);
  });
}
