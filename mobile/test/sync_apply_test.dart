import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';
import 'package:flutter_test/flutter_test.dart';

/// One page of `/sync`, exactly as the wire shape allows it — used to pin the READER rather than
/// the writer: what the service does with a field that is there, a field that is not, and a list
/// that is empty.
class _OnePageApi implements ApiClient {
  _OnePageApi(this._terms);

  final List<Map<String, dynamic>> _terms;

  @override
  Future<Map<String, dynamic>> syncDelta({String? since, String? cursor, int limit = 500}) async => {
    'server_time': '2026-08-25T10:00:00Z',
    'has_more': false,
    'changes': {'terms': _terms},
  };

  @override
  Future<Stats> stats() async => throw Exception('offline'); // the cache refresh is best-effort

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The delta-application logic can't be exercised on-device from a unit test, but its correctness
/// (upsert = last-write-wins, tombstone = row delete, inclusive-boundary re-send = no-op) is the
/// riskiest part of the offline path, so it's pinned here against an in-memory SQLite.
void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 3, 12);

  Future<void> upsertOne() => db.applyDelta(
    collectionUpserts: [
      CollectionsCompanion.insert(
        id: 'c1',
        updatedAt: t0,
        title: const Value('Метро'),
        itemsCount: const Value(1),
      ),
    ],
    termUpserts: [
      TermsCompanion.insert(
        id: 't1',
        updatedAt: t0,
        termText: const Value('ticket'),
        translation: const Value('билет'),
      ),
    ],
    itemUpserts: [
      CollectionItemsCompanion.insert(
        collectionId: 'c1',
        termId: 't1',
        updatedAt: t0,
        position: const Value(0),
      ),
    ],
    progressUpserts: [
      TermProgressCompanion.insert(
        termId: 't1',
        updatedAt: t0,
        state: const Value('review'),
        intervalDays: const Value(30),
      ),
    ],
  );

  test('upsert lands a collection, its term, and the joined status', () async {
    await upsertOne();

    final cols = await db.watchCollections().first;
    expect(cols.map((c) => c.id), ['c1']);
    expect(cols.single.title, 'Метро');

    final terms = await db.watchCollectionTerms('c1').first;
    expect(terms.single.term.termText, 'ticket');
    expect(terms.single.term.translation, 'билет');
    expect(terms.single.state, 'review'); // per-word status is joined in
  });

  test('re-applying the inclusive boundary row is a no-op (LWW by id)', () async {
    await upsertOne();
    await upsertOne(); // the same second re-sent
    expect((await db.watchCollections().first).length, 1);
    expect((await db.watchCollectionTerms('c1').first).length, 1);
  });

  test('an item tombstone removes the local row (no ghost)', () async {
    await upsertOne();
    await db.applyDelta(itemDeletes: [('c1', 't1')]);
    expect(await db.watchCollectionTerms('c1').first, isEmpty);
    // The term row itself survives (terms are global); only the membership is gone.
    expect((await db.watchAllProgress().first).length, 1);
  });

  test('a collection tombstone removes the collection and its membership rows', () async {
    await upsertOne();
    await db.applyDelta(collectionDeletes: ['c1']);
    expect(await db.watchCollections().first, isEmpty);
    expect(await db.watchCollectionTerms('c1').first, isEmpty);
  });

  test('a later upsert overwrites earlier fields (last write wins)', () async {
    await upsertOne();
    await db.applyDelta(
      collectionUpserts: [
        CollectionsCompanion.insert(
          id: 'c1',
          updatedAt: t0.add(const Duration(days: 1)),
          title: const Value('Метро 2'),
        ),
      ],
    );
    expect((await db.watchCollections().first).single.title, 'Метро 2');
  });

  test('sync meta round-trips (cursor storage)', () async {
    expect(await db.getMeta('sync_cursor'), isNull);
    await db.setMeta('sync_cursor', '2026-08-03T12:00:00Z');
    expect(await db.getMeta('sync_cursor'), '2026-08-03T12:00:00Z');
    await db.setMeta('sync_cursor', '2026-08-04T00:00:00Z');
    expect(await db.getMeta('sync_cursor'), '2026-08-04T00:00:00Z');
  });

  test('a triage upsert lands the local deck-exclusion marker', () async {
    await upsertOne();
    // The delta carries the governing triage verdict; applyDelta mirrors it into the marker so an
    // unknown swipe (no progress row) stays out of the deck after a sign-out wipe + re-login.
    await db.applyDelta(
      triageUpserts: [
        TriagedTermsCompanion.insert(termId: 't1', collectionId: const Value('c1'), decidedAt: t0),
      ],
    );
    expect(await db.triageEligible('c1'), isEmpty); // t1 marked → nothing eligible
  });

  /// Ч.1 — ядро v15 lands on the term through `/sync` and nowhere else. Three ADDITIVE fields, and
  /// for each of them the same three states have to be legal: the server sent it, the server did
  /// not send the key at all (every build before the станок), and the server sent an empty list.
  /// None of the three is an error, and none of them may throw.
  group('ридер /sync · синонимы, транслитерация, доп-переводы', () {
    Future<Term?> readBack(Map<String, dynamic> term) async {
      await SyncService(_OnePageApi([term]), db).sync();

      return db.termById(term['id'] as String);
    }

    Map<String, dynamic> base(Map<String, dynamic> extra) => {
      'id': 't1',
      'op': 'upsert',
      'updated_at': '2026-08-25T09:00:00Z',
      'text': 'knife',
      'translation': 'нож',
      ...extra,
    };

    test('all three present — stored as sent, the lists as JSON', () async {
      final term = await readBack(
        base({
          'synonyms': ['blade', 'dagger'],
          'transliteration': 'найф',
          'translations': ['нож', 'тесак'],
        }),
      );

      expect(term?.transliteration, 'найф');
      expect(decodeStringList(term?.synonyms), ['blade', 'dagger']);
      expect(decodeStringList(term?.translations), ['нож', 'тесак']);
      // The pinned translation is untouched — the list carries it, it does not replace it.
      expect(term?.translation, 'нож');
    });

    test('none of the three keys present — a legal term, not a failure', () async {
      final term = await readBack(base(const {}));

      expect(term, isNotNull);
      expect(term?.termText, 'knife');
      expect(term?.transliteration, isNull);
      expect(term?.synonyms, isNull);
      expect(term?.translations, isNull);
      expect(decodeStringList(term?.synonyms), isEmpty);
    });

    test('empty lists and a null reading read exactly like «none»', () async {
      final term = await readBack(
        base({'synonyms': const [], 'translations': const [], 'transliteration': null}),
      );

      expect(term?.synonyms, isNull);
      expect(term?.translations, isNull);
      expect(term?.transliteration, isNull);
    });

    test('a field in a shape this build has never seen degrades to «none»', () async {
      // Defensive on purpose: an additive field is exactly the field a later server might send
      // differently, and a word card is not worth a crash.
      final term = await readBack(
        base({'synonyms': 'blade', 'transliteration': 42, 'translations': const [null, 'тесак']}),
      );

      expect(term?.synonyms, isNull);
      expect(term?.transliteration, isNull);
      expect(decodeStringList(term?.translations), ['тесак']);
    });
  });

  test('clearAll wipes every table and the cursor (sign-out / reinstall symmetry)', () async {
    await upsertOne();
    await db.setMeta('sync_cursor', 'x');
    await db.clearAll();
    expect(await db.watchCollections().first, isEmpty);
    expect(await db.watchAllProgress().first, isEmpty);
    expect(await db.getMeta('sync_cursor'), isNull);
  });
}
