import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/pending_generation_card.dart';
import 'package:eng_std/l10n/app_localizations.dart';

PendingGeneration _row({
  required String status,
  String? collectionId,
  int? requested,
  int? delivered,
  String? error,
  bool sent = true,
}) =>
    PendingGeneration(
      id: 'g1',
      topic: 'иду в банк',
      status: status,
      collectionId: collectionId,
      error: error,
      requested: requested,
      delivered: delivered,
      sourceLang: 'ru',
      targetLang: 'en',
      levelsCsv: 'A2,B1',
      size: 15,
      sent: sent,
      targetLangExplicit: true,
      createdAt: DateTime(2026, 8, 4),
      updatedAt: DateTime(2026, 8, 4),
    );

Future<void> _pump(WidgetTester tester, PendingGeneration row, {List<WordCollection> collections = const []}) async {
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
      home: Scaffold(body: PendingGenerationCard(row: row)),
    ),
  ));
  await tester.pump(); // let the collections stream + localizations resolve
}

void main() {
  testWidgets('generating state shows the shimmer label and topic', (tester) async {
    await _pump(tester, _row(status: 'pending'));
    expect(find.text('Собираем коллекцию…'), findsOneWidget);
    expect(find.textContaining('иду в банк'), findsOneWidget);
  });

  testWidgets('failed state shows the reason and a retry action', (tester) async {
    await _pump(tester, _row(status: 'failed'));
    expect(find.text('Не получилось'), findsOneWidget);
    expect(find.textContaining('Генерация не потрачена'), findsOneWidget);
    expect(find.text('Повторить'), findsOneWidget);
    expect(find.text('Скрыть'), findsOneWidget);
  });

  testWidgets('ready state shows the collection and an under-delivery badge', (tester) async {
    final col = WordCollection(
      id: 'c1', title: 'В банке', source: 'ai', type: 'custom', wordsCount: 12,
      sourceLang: 'ru', targetLang: 'en',
    );
    await _pump(
      tester,
      _row(status: 'succeeded', collectionId: 'c1', requested: 15, delivered: 12),
      collections: [col],
    );
    expect(find.text('Готово'), findsOneWidget);
    expect(find.text('В банке'), findsOneWidget);
    expect(find.text('12 из 15'), findsOneWidget);
  });
}
