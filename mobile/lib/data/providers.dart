import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth_repository.dart';
import 'local/app_database.dart';
import 'local/sync_service.dart';
import 'models.dart';
import 'review_queue.dart';
import 'review_sync.dart';
import 'seq_counter.dart';
import 'token_store.dart';
import 'triage_queue.dart';
import 'triage_sync.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

/// The local-first store. One instance for the app; screens read through it.
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});

/// Background delta-sync: pulls GET /sync into the local DB. Never on a read path.
final syncServiceProvider = Provider<SyncService>((ref) {
  return SyncService(ref.watch(apiClientProvider), ref.watch(appDatabaseProvider));
});

/// Whether the device currently has any network. Seeded with the current state, then live.
/// Read screens don't need this (they read the local DB); it's for the login screen, the one
/// place offline honestly can't proceed and should say so up front.
final connectivityProvider = StreamProvider<bool>((ref) async* {
  final conn = Connectivity();
  bool online(List<ConnectivityResult> rs) => rs.any((r) => r != ConnectivityResult.none);
  yield online(await conn.checkConnectivity());
  yield* conn.onConnectivityChanged.map(online);
});

/// Per-user monotonic sequence counters (triage / review), persisted in the keychain
/// separately from the durable queues so they survive a queue clear.
final seqCounterProvider = Provider<SeqCounter>((ref) => SeqCounter());

final reviewQueueProvider = Provider<ReviewQueue>((ref) => ReviewQueue());

/// Offline-first review upload pipeline (record locally → batch flush).
final reviewSyncProvider = Provider<ReviewSync>((ref) {
  return ReviewSync(ref.watch(apiClientProvider), ref.watch(reviewQueueProvider), ref);
});

final triageQueueProvider = Provider<TriageQueue>((ref) => TriageQueue());

/// Offline-first triage upload pipeline (record locally → batch flush).
final triageSyncProvider = Provider<TriageSync>((ref) {
  return TriageSync(
    ref.watch(apiClientProvider),
    ref.watch(triageQueueProvider),
    ref.watch(seqCounterProvider),
    ref.watch(appDatabaseProvider),
    ref,
  );
});

/// The triage deck for one collection, built ENTIRELY from the local DB (no network) so it opens
/// on a plane: the collection's never-studied, never-triaged terms in study order, capped at the
/// page limit. `remaining` is the eligible terms beyond this page — same rule as the cards, from
/// the same query, so counter and deck can't diverge (BUG-1). Loaded as a snapshot on entry; the
/// screen manages the session (swipes advance a local index and mark the term triaged, which the
/// next entry's query excludes). GET /triage/queue still exists on the backend but is unused here.
final triageDeckProvider = FutureProvider.family<TriageDeck, String>((ref, collectionId) async {
  const pageLimit = 40;
  final eligible = await ref.watch(appDatabaseProvider).triageEligible(collectionId);
  final page = eligible.take(pageLimit).map(_toTriageCard).toList();
  return TriageDeck(cards: page, remaining: eligible.length - page.length);
});

TriageCard _toTriageCard(Term t) => TriageCard(
      termId: t.id,
      text: t.termText ?? '',
      translation: t.translation ?? '',
      type: t.type,
      transcription: t.transcription,
      example: t.example,
      exampleTranslation: t.exampleTranslation,
    );

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(ref.watch(tokenStoreProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStoreProvider),
    ref.watch(seqCounterProvider),
  );
});

/// Holds the signed-in user (or null). `loading` while restoring/authing.
class AuthController extends AsyncNotifier<AppUser?> {
  @override
  Future<AppUser?> build() async {
    return ref.read(authRepositoryProvider).restore();
  }

  Future<void> signIn() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).signInWithGoogle(),
    );
  }

  Future<void> signOut() async {
    await ref.read(authRepositoryProvider).signOut();
    // Wipe the local mirror + sync cursor so the next account starts from a full snapshot,
    // never a delta against someone else's data.
    await ref.read(appDatabaseProvider).clearAll();
    state = const AsyncData(null);
  }

  /// Persist profile changes and refresh the in-memory user (and the offline cache).
  Future<void> updateProfile(Map<String, dynamic> changes) async {
    final user = await ref.read(apiClientProvider).updateProfile(changes);
    await ref.read(tokenStoreProvider).saveUser(jsonEncode(user.toJson()));
    state = AsyncData(user);
  }
}

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AppUser?>(AuthController.new);

/// Whether first-run onboarding has been completed (per device). Re-evaluated
/// whenever the signed-in user changes so a fresh login re-checks the flag.
final onboardedProvider = FutureProvider<bool>((ref) async {
  final user = ref.watch(authControllerProvider).value;
  if (user == null) return true; // not applicable when signed out
  return ref.read(tokenStoreProvider).isOnboarded();
});

// ---- Data providers (read-through: local DB is the source of truth) ----------
//
// These read the local mirror, never the network. The background SyncService writes the mirror;
// drift's reactive `watch` queries push each write straight into these streams, so a screen
// rebuilds on sync without invalidation. No data yet → an empty list/zeroes, never a spinner or
// error. The write paths (create/delete a collection, add/remove a word) still POST to the API;
// the change comes back through the next sync — until then the mutating screen refetches once.

final collectionsProvider = StreamProvider<List<WordCollection>>((ref) {
  return ref.watch(appDatabaseProvider).watchCollections().map(
        (rows) => rows.map(_toCollection).toList(),
      );
});

final collectionWordsProvider = StreamProvider.family<List<Word>, String>((ref, collectionId) {
  return ref.watch(appDatabaseProvider).watchCollectionTerms(collectionId).map(
        (rows) => rows.map(_toWord).toList(),
      );
});

/// Local stats: total/learned/mastered/due are counted from the synced progress rows; streak and
/// reviews-today are read from the cache the SyncService refreshes while online (not in the delta
/// feed). Re-derived on every progress change.
final statsProvider = StreamProvider<Stats>((ref) async* {
  final db = ref.watch(appDatabaseProvider);
  await for (final rows in db.watchAllProgress()) {
    final streak = int.tryParse(await db.getMeta(SyncKeys.streak) ?? '') ?? 0;
    final reviews = int.tryParse(await db.getMeta(SyncKeys.reviewsToday) ?? '') ?? 0;
    yield _deriveStats(rows, streak: streak, reviewsToday: reviews);
  }
});

/// Derived per-collection progress (total/learned/mastered/due), keyed by collection id — folded
/// locally over the synced (item, progress) pairs, the same way the server derives it.
final collectionsProgressProvider = StreamProvider<Map<String, CollectionProgress>>((ref) {
  return ref.watch(appDatabaseProvider).watchItemProgress().map(_deriveCollectionsProgress);
});

/// Study cards stay on the network — sessions are online-only (out of the offline scope). Consumers
/// read this via `.value`, so an offline error degrades to null rather than surfacing.
final dueCardsProvider = FutureProvider<List<ReviewCard>>((ref) async {
  return ref.watch(apiClientProvider).dueCards();
});

// ---- Local mappers / derivations --------------------------------------------

WordCollection _toCollection(Collection r) => WordCollection(
      id: r.id,
      title: r.title ?? '',
      description: r.description,
      // The sync payload carries no source/type; the "ИИ" badge is a known offline gap (ROADMAP).
      source: 'user',
      type: 'custom',
      wordsCount: r.itemsCount,
      sourceLang: r.sourceLang ?? 'ru',
      targetLang: r.targetLang ?? 'en',
    );

Word _toWord(CollectionTermRow r) => Word(
      termId: r.term.id,
      term: r.term.termText ?? '',
      translation: r.term.translation ?? '',
      transcription: r.term.transcription,
      example: r.term.example,
      type: r.term.type,
      status: r.state,
    );

/// Mirror of the server's `Mastery`: a term is mastered once self-marked `known`, or proven by
/// exercises (`review` state) with an interval of at least this many days. Source of truth is
/// `Learning\Domain\Service\Mastery::MASTERED_INTERVAL_DAYS` — keep in step.
const int _masteredIntervalDays = 21;

bool _isMastered(String? state, int intervalDays) =>
    state == 'known' || (state == 'review' && intervalDays >= _masteredIntervalDays);

Stats _deriveStats(List<TermProgressData> rows, {required int streak, required int reviewsToday}) {
  final now = DateTime.now();
  var learned = 0, mastered = 0, due = 0;
  for (final r in rows) {
    if (r.state == 'review') learned++;
    if (_isMastered(r.state, r.intervalDays)) mastered++;
    if (r.state != 'new' && r.dueAt != null && !r.dueAt!.isAfter(now)) due++;
  }
  return Stats(
    totalWords: rows.length,
    learned: learned,
    mastered: mastered,
    dueToday: due,
    reviewsTotal: reviewsToday,
    streakDays: streak,
  );
}

Map<String, CollectionProgress> _deriveCollectionsProgress(List<ItemProgressRow> rows) {
  final now = DateTime.now();
  final agg = <String, ({int total, int learned, int mastered, int due})>{};
  for (final r in rows) {
    final cur = agg[r.collectionId] ?? (total: 0, learned: 0, mastered: 0, due: 0);
    final isLearned = r.state == 'review';
    final isMastered = _isMastered(r.state, r.intervalDays ?? 0);
    final isDue = r.state != null && r.state != 'new' && r.dueAt != null && !r.dueAt!.isAfter(now);
    agg[r.collectionId] = (
      total: cur.total + 1,
      learned: cur.learned + (isLearned ? 1 : 0),
      mastered: cur.mastered + (isMastered ? 1 : 0),
      due: cur.due + (isDue ? 1 : 0),
    );
  }
  return agg.map((id, v) => MapEntry(
        id,
        CollectionProgress(collectionId: id, total: v.total, learned: v.learned, mastered: v.mastered, due: v.due),
      ));
}

typedef SessionArgs = ({String? collectionId, bool shuffle});

/// Cards for one training session: a specific collection's words, or the global
/// due queue when [collectionId] is null.
final sessionCardsProvider =
    FutureProvider.family<List<ReviewCard>, SessionArgs>((ref, args) async {
  final api = ref.watch(apiClientProvider);
  // Both branches use the SRS study queue (due + new). A collection session is just the
  // same queue scoped to that collection, so mastered words stop reappearing every time.
  final cards = await api.dueCards(collectionId: args.collectionId);
  if (args.shuffle) cards.shuffle();
  return cards;
});
