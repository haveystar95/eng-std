import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:flutter_test/flutter_test.dart';

/// A PLAN DAY IS NOT A SHELF — Д-34, Д-35, the device's half.
///
/// The folder is mirrored on purpose: a card's pair is resolved through its collection, so a plan
/// session cannot be played without it. What it is not is one of the learner's own folders — and
/// the two lists that say «what I keep» were both taking it.
void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 9, 2, 12);

  Future<void> seed() async {
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(
          id: 'mine',
          updatedAt: t0,
          title: const Value('У врача и в аптеке'),
          sourceLang: const Value('ru'),
          targetLang: const Value('en'),
          itemsCount: const Value(1),
        ),
        CollectionsCompanion.insert(
          id: 'planDay1',
          updatedAt: t0,
          title: const Value('Ответить на вопросы врача'),
          sourceLang: const Value('ru'),
          targetLang: const Value('en'),
          itemsCount: const Value(2),
          origin: const Value('plan'),
        ),
      ],
      termUpserts: [
        TermsCompanion.insert(
          id: 'collectionWord',
          updatedAt: t0,
          termText: const Value('prescription'),
          translation: const Value('рецепт'),
          example: const Value('Here is your prescription.'),
        ),
        TermsCompanion.insert(
          id: 'planLine1',
          updatedAt: t0,
          termText: const Value('I came with my son.'),
          translation: const Value('Я пришёл с сыном.'),
          example: const Value('I came with my son.'),
        ),
        TermsCompanion.insert(
          id: 'planLine2',
          updatedAt: t0,
          termText: const Value('He has a fever.'),
          translation: const Value('У него жар.'),
          example: const Value('He has a fever.'),
        ),
      ],
      itemUpserts: [
        CollectionItemsCompanion.insert(collectionId: 'mine', termId: 'collectionWord', updatedAt: t0),
        CollectionItemsCompanion.insert(collectionId: 'planDay1', termId: 'planLine1', updatedAt: t0),
        CollectionItemsCompanion.insert(collectionId: 'planDay1', termId: 'planLine2', updatedAt: t0),
      ],
    );
  }

  test('«Мои коллекции» leaves a plan day out (Д-34)', () async {
    await seed();

    final shelves = await db.watchCollections().first;

    expect(shelves.map((c) => c.id), ['mine']);
  });

  test('the word-challenge never draws on a plan day (Д-35)', () async {
    // The live card: `prescription` from a collection, offered «Я пришёл с сыном.» and «У него жар.»
    // — the plan's own replies — while the plan ran, and the same again after it was archived.
    await seed();

    final mirror = await db.challengeMirror();

    expect(mirror.map((t) => t.termId), ['collectionWord']);
  });

  test('the plan day is still mirrored — the session resolves its pair through it', () async {
    await seed();

    final terms = await db.watchCollectionTerms('planDay1').first;

    expect(terms.map((r) => r.term.id), containsAll(<String>['planLine1', 'planLine2']));
    expect(await db.pairByTerms(['planLine1']), contains('planLine1'));
  });
}
