import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/collections/generate_screen.dart';
import 'package:eng_std/features/collections/pending_generation_card.dart';

/// Three device findings around creating a collection, all of them invisible from a screenshot.
///
/// QA-1: dictating the situation went through `en_US` because the locale followed the INTERFACE,
/// while the situation is written in the owner's own language.
/// QA-3: the finished collection sat in the list twice — the ready card and the real collection —
/// until an app restart reconciled the row away.
void main() {
  group('sttLocaleFor — recognise the language the situation is written in', () {
    test('the owner\'s source language decides, not the UI', () {
      expect(sttLocaleFor('ru'), 'ru_RU');
      expect(sttLocaleFor('en'), 'en_US');
      expect(sttLocaleFor('uk'), 'uk_UA');
    });

    test('an unknown code falls back to a locale the recogniser always has', () {
      expect(sttLocaleFor('xx'), 'en_US');
      expect(sttLocaleFor(''), 'en_US');
    });
  });

  group('visiblePendingGenerations — one row per collection', () {
    PendingGeneration row({required String id, required String status, String? collectionId}) =>
        PendingGeneration(
          id: id,
          topic: 'иду в банк',
          status: status,
          collectionId: collectionId,
          sourceLang: 'ru',
          targetLang: 'en',
          levelsCsv: 'A2,B1',
          size: 15,
          sent: true,
          targetLangExplicit: true,
          createdAt: DateTime(2026, 8, 18),
          updatedAt: DateTime(2026, 8, 18),
        );

    WordCollection collection(String id) => WordCollection(
      id: id,
      title: 'В банке',
      source: 'ai',
      type: 'custom',
      wordsCount: 12,
      sourceLang: 'ru',
      targetLang: 'en',
    );

    test('a succeeded row yields once its collection is mirrored', () {
      final pending = [row(id: 'g1', status: 'succeeded', collectionId: 'c1')];
      expect(visiblePendingGenerations(pending, [collection('c1')]), isEmpty);
    });

    test('…but stays while the sync is still owed — the user must see something', () {
      final pending = [row(id: 'g1', status: 'succeeded', collectionId: 'c1')];
      expect(visiblePendingGenerations(pending, []), hasLength(1));
    });

    test('an in-flight or failed row has no collection and is always shown', () {
      final pending = [row(id: 'g1', status: 'pending'), row(id: 'g2', status: 'failed')];
      expect(visiblePendingGenerations(pending, [collection('c1')]), hasLength(2));
    });

    test('only the finished row yields — a sibling still running stays', () {
      final pending = [
        row(id: 'g1', status: 'succeeded', collectionId: 'c1'),
        row(id: 'g2', status: 'running'),
      ];
      final shown = visiblePendingGenerations(pending, [collection('c1')]);
      expect(shown.map((p) => p.id), ['g2']);
    });
  });
}
