import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth_repository.dart';
import 'device_timezone.dart';
import 'exposure_sync.dart';
import 'generation_controller.dart';
import 'local/app_database.dart';
import 'local/cached_image_provider.dart';
import 'local/image_disk_cache.dart';
import 'local/sync_service.dart';
import 'practice/learning_ladder.dart';
import 'practice/local_session_builder.dart';
import 'practice/practice_mode_selector.dart';
import 'models.dart';
import 'review_queue.dart';
import 'review_sync.dart';
import 'pool_sync.dart';
import 'seq_counter.dart';
import 'session_completion_sync.dart';
import 'speech/speech_recognizer.dart';
import 'token_store.dart';
import 'triage_queue.dart';
import 'triage_sync.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

/// On-device speech recognition, shared by the speaking card and the intro's echo.
///
/// ONE instance for the app, because the permission and the engine are one thing: the learner is
/// asked once, on the first record button they tap, and every later surface finds it already
/// prepared. Overridden in tests with a fake — a simulator has no microphone, so a widget test of
/// the speaking card can only exist through this provider.
final speechRecognizerProvider = Provider<SpeechRecognizer>((ref) {
  final recognizer = PluginSpeechRecognizer();
  ref.onDispose(recognizer.cancel);
  return recognizer;
});

/// The local-first store. One instance for the app; screens read through it.
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});

/// The on-disk image byte cache, installed once so [CachedNetworkImage] can find it. Awaited by
/// nothing: until it is ready, images load from the network exactly as they always did, so a slow
/// disk cannot delay the first screen.
final imageDiskCacheProvider = FutureProvider<ImageDiskCache?>((ref) async {
  return installImageDiskCache(ref.watch(appDatabaseProvider));
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

/// The durable review queue lives in the local DB, not the Keychain (F20-r2) — an append is one
/// insert on the background isolate instead of a whole-blob rewrite on the UI isolate.
final reviewQueueProvider =
    Provider<ReviewQueue>((ref) => ReviewQueue(ref.watch(appDatabaseProvider)));

/// Offline-first review upload pipeline (record locally → batch flush). Carries the monotonic
/// `seq_review` counter so each raw answer gets a `client_seq` the server folds by.
final reviewSyncProvider = Provider<ReviewSync>((ref) {
  return ReviewSync(
    ref.watch(apiClientProvider),
    ref.watch(reviewQueueProvider),
    ref.watch(seqCounterProvider),
    ref,
  );
});

/// Offline-first INTRO pipeline (record the meeting locally + step the ladder → batch flush). Its
/// own queue, not the review one: an exposure is not an answer, and the review log must never hold
/// a retrieval that never happened.
final exposureSyncProvider = Provider<ExposureSync>((ref) {
  return ExposureSync(
    ref.watch(apiClientProvider),
    ref.watch(appDatabaseProvider),
    ref,
  );
});

/// Offline-first pipeline for «this run was played to its end» (QA-12). Its own queue, keyed by
/// session, for the same reason the exposure one is separate: a completion is not an answer.
final sessionCompletionSyncProvider = Provider<SessionCompletionSync>((ref) {
  return SessionCompletionSync(
    ref.watch(apiClientProvider),
    ref.watch(appDatabaseProvider),
  );
});

/// «Учить это слово» / «Убрать из изучения» — the two acts that decide what the trainer works on.
/// Its own durable queue, keyed by the TERM and holding the desired membership rather than a log:
/// the verbs are idempotent and there is no order to protect.
final poolSyncProvider = Provider<PoolSync>((ref) {
  return PoolSync(
    ref.watch(apiClientProvider),
    ref.watch(appDatabaseProvider),
  );
});

/// «Мои слова» — every word the learner has taken into study, newest first, straight from the local
/// mirror so the screen opens in airplane mode like every other one.
final poolProvider = StreamProvider<List<PoolWordRow>>((ref) {
  return ref.watch(appDatabaseProvider).watchPool();
});

/// How many pool words have never been shown — the «Учить N» number.
///
/// Read off the POOL, not off the collections: a word whose collection was deleted is still a word
/// the learner asked to learn, and the session builder will still deal it.
final learnableCountProvider = StreamProvider<int>((ref) {
  return ref.watch(appDatabaseProvider).watchLearnableCount();
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
      imageUrl: t.imageUrl,
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
    final repo = ref.read(authRepositoryProvider);
    final user = await repo.restore();
    if (user != null) {
      // Heal a stale/tier-less cache in-session: refresh from /me and push the fresh user (tier +
      // quota) into state when online. Best-effort — offline keeps the cached user.
      unawaited(() async {
        try {
          final fresh = await repo.refresh();
          if (fresh != null) state = AsyncData(fresh);
        } catch (_) {/* offline / transient — keep the cached user */}
      }());
    }
    return user;
  }

  Future<void> signIn() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).signInWithGoogle(),
    );
  }

  Future<void> signInWithApple() async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).signInWithApple(),
    );
  }

  Future<void> signOut() async {
    await ref.read(authRepositoryProvider).signOut();
    // Wipe the local mirror + sync cursor so the next account starts from a full snapshot,
    // never a delta against someone else's data.
    //
    // A wipe that FAILS must still leave the person signed out (QA-24). This used to be a bare
    // await: on a device whose local store could not be opened it threw here, after the token was
    // already gone — so the app was signed out at the server, still «signed in» on screen, and
    // carrying the previous account's rows with nothing to remove them. The safety net is the
    // snapshot reap in SyncService, which now clears exactly this on the next login; what matters
    // here is that the failure is logged and does not swallow the sign-out itself.
    try {
      await ref.read(appDatabaseProvider).clearAll();
    } catch (e) {
      debugPrint('[auth] local wipe on sign-out failed: $e');
    }
    state = const AsyncData(null);
  }

  /// Permanently delete the account (B3) and drop to the signed-out state. The local mirror is
  /// wiped just like sign-out so nothing of the deleted account lingers on the device.
  Future<void> deleteAccount() async {
    await ref.read(authRepositoryProvider).deleteAccount();
    await ref.read(appDatabaseProvider).clearAll();
    state = const AsyncData(null);
  }

  /// Persist profile changes and refresh the in-memory user (and the offline cache). Every profile
  /// write also refreshes the device timezone (F19: «в PUT /profile при смене») — cheap, keeps the
  /// server's due-rounding zone current if the user has travelled, and covers the onboarding-finish
  /// call. A caller-supplied `timezone` wins.
  Future<void> updateProfile(Map<String, dynamic> changes) async {
    final withTz = {'timezone': await deviceTimezone(), ...changes};
    final user = await ref.read(apiClientProvider).updateProfile(withTz);
    await ref.read(tokenStoreProvider).saveUser(jsonEncode(user.toJson()));
    state = AsyncData(user);
  }
}

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AppUser?>(AuthController.new);

/// Whether first-run onboarding is done — server truth via `profile.onboarded_at` (device-batch
/// F1). Tied to the account, not the device: a relogin never re-onboards, a new account always
/// does, and it survives keychain wipe / reinstall / a new device. Re-evaluated whenever the
/// signed-in user changes (updateProfile refreshes the user with the stamped onboarded_at).
final onboardedProvider = FutureProvider<bool>((ref) async {
  final user = ref.watch(authControllerProvider).value;
  if (user == null) return true; // not applicable when signed out
  return user.profile?.onboardedAt != null;
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
/// feed).
///
/// Re-derived whenever EITHER source changes. It used to watch the progress rows alone and read the
/// cached aggregates inside that loop — so the first emission happened before `_refreshStatsCache()`
/// had written them and nothing ever emitted again, because neither a triage nor a fresh `/stats`
/// creates a progress row. The defaults (`?? 0`) were then read as «0 of 20 done today» and «no new
/// words left», and only an app restart cleared it (QA-10).
final statsProvider = StreamProvider<Stats>((ref) async* {
  final db = ref.watch(appDatabaseProvider);
  await for (final _ in db.watchStatsSources()) {
    final rows = await db.allProgress();
    final streak = int.tryParse(await db.getMeta(SyncKeys.streak) ?? '') ?? 0;
    final reviews = int.tryParse(await db.getMeta(SyncKeys.reviewsToday) ?? '') ?? 0;
    final newGoal = int.tryParse(await db.getMeta(SyncKeys.newGoal) ?? '') ?? 0;
    final newRemaining = int.tryParse(await db.getMeta(SyncKeys.newRemaining) ?? '') ?? 0;
    yield _deriveStats(rows, streak: streak, reviewsToday: reviews, newGoal: newGoal, newRemaining: newRemaining);
  }
});

/// Derived per-collection progress (total/learned/mastered/due), keyed by collection id — folded
/// locally over the synced (item, progress) pairs, the same way the server derives it.
final collectionsProgressProvider = StreamProvider<Map<String, CollectionProgress>>((ref) {
  return ref.watch(appDatabaseProvider).watchItemProgress().map(_deriveCollectionsProgress);
});

/// Eligible-for-triage term counts per collection (local DB, reactive) — powers the
/// «Разобрать N» CTA on both the home and the collection screen.
final untriagedByCollectionProvider = StreamProvider<Map<String, int>>((ref) {
  return ref.watch(appDatabaseProvider).watchUntriagedByCollection();
});

/// Learnable (triaged-«не знаю», never-studied) term counts per collection (local DB, reactive) —
/// powers the «Учить N» CTA that introduces new words after triage (device-batch F8). Without it
/// these words are neither due nor untriaged and no CTA reaches them.
final learnableByCollectionProvider = StreamProvider<Map<String, int>>((ref) {
  return ref.watch(appDatabaseProvider).watchLearnableByCollection();
});

/// The three ink-density buckets (§4) for one collection, partitioning its total:
/// confirmed (mastered) · familiar (in SRS, not yet mastered) · in-progress (new /
/// untouched). Local + reactive.
final collectionDensityProvider = StreamProvider.family<CollectionDensity, String>((ref, collectionId) {
  return ref.watch(appDatabaseProvider).watchItemProgress().map((rows) {
    var confirmed = 0, familiar = 0, inProgress = 0;
    for (final r in rows) {
      if (r.collectionId != collectionId) continue;
      switch (classifyDensity(r.state, r.intervalDays ?? 0)) {
        case DensityBucket.confirmed:
          confirmed++;
        case DensityBucket.familiar:
          familiar++;
        case DensityBucket.inProgress:
          inProgress++;
      }
    }
    return CollectionDensity(confirmed: confirmed, familiar: familiar, inProgress: inProgress);
  });
});

/// Ink-density bucket for a progress state (§4). Partitions every term into exactly
/// one bucket; verdict colours never participate. Extracted for unit tests.
enum DensityBucket { confirmed, familiar, inProgress }

DensityBucket classifyDensity(String? state, int intervalDays) {
  if (_isMastered(state, intervalDays)) return DensityBucket.confirmed; // filled
  if (state == 'review' || state == 'learning' || state == 'relearning') {
    return DensityBucket.familiar; // halftone — seen, not yet mastered
  }
  return DensityBucket.inProgress; // outline — new / untouched / triaged-unknown
}

/// The three counts behind a collection's density bar. `confirmed + familiar +
/// inProgress == collection total`.
class CollectionDensity {
  const CollectionDensity({required this.confirmed, required this.familiar, required this.inProgress});
  final int confirmed, familiar, inProgress;
  int get total => confirmed + familiar + inProgress;
}

/// Study cards stay on the network — sessions are online-only (out of the offline scope). Consumers
/// read this via `.value`, so an offline error degrades to null rather than surfacing.
final dueCardsProvider = FutureProvider<List<ReviewCard>>((ref) async {
  return ref.watch(apiClientProvider).dueCards();
});

/// One term's progress, reactive (local mirror). The exercise-session feedback watches it to show
/// «увидишь снова через N дней» from the REAL server `due_at` once the answer's upload + sync
/// lands — the client never computes the interval itself.
final termProgressForProvider =
    StreamProvider.family<TermProgressData?, String>((ref, termId) {
  return ref.watch(appDatabaseProvider).watchProgressFor(termId);
});

// ---- AI generation (client lifecycle) ---------------------------------------

/// Drives generation from the client: POST, a local pending row that survives a kill, polling and
/// start-up reconciliation. Screens watch [pendingGenerationsProvider], never poll themselves.
final generationControllerProvider = Provider<GenerationController>((ref) {
  final sync = ref.watch(syncServiceProvider);
  return GenerationController(
    ref.watch(apiClientProvider),
    ref.watch(appDatabaseProvider),
    sync.sync,
  );
});

/// In-flight / just-finished generations for the pending cards (local DB, reactive).
final pendingGenerationsProvider = StreamProvider<List<PendingGeneration>>((ref) {
  return ref.watch(appDatabaseProvider).watchPendingGenerations();
});

/// The current generation allowance, fetched fresh from /me (network). Used by the create screen to
/// grey the button + show remaining/resets_at. Invalidate after a generation to refresh. Degrades to
/// null offline (the screen then lets the user try; the server is the real gate).
final generationQuotaProvider = FutureProvider<GenerationQuota?>((ref) async {
  try {
    return (await ref.watch(apiClientProvider).me()).quota;
  } catch (_) {
    return null;
  }
});

// ---- Local mappers / derivations --------------------------------------------

WordCollection _toCollection(Collection r) => WordCollection(
      id: r.id,
      title: r.title ?? '',
      description: r.description,
      source: r.source ?? 'user', // now synced → the ИИ badge and my/store/generated origin work
      type: r.type ?? 'custom',
      wordsCount: r.itemsCount,
      sourceLang: r.sourceLang ?? 'ru',
      targetLang: r.targetLang ?? 'en',
      imageUrl: r.imageUrl, // Pexels cover (docks in via sync; null → gradient placeholder)
      imageAuthor: r.imageAuthor,
      imageAuthorUrl: r.imageAuthorUrl,
    );

/// The stored admission matrix, decoded. Anything unreadable falls back to the shipped matrix —
/// the same value a device that has never synced assumes.
List<dynamic>? _decodeList(String? raw) {
  if (raw == null || raw.isEmpty) return null;
  try {
    final decoded = jsonDecode(raw);
    return decoded is List ? decoded : null;
  } on FormatException {
    return null;
  }
}

Word _toWord(CollectionTermRow r) {
  final s = r.state;
  // Progress states map to their badge. A term with no progress state that was still swiped in
  // triage was a "не знаю" (unknown leaves it new, writes no progress row) — flag it as such so it
  // reads differently from a never-touched word; everything else untouched stays badge-less.
  final status = (s == 'known' || s == 'review' || s == 'learning' || s == 'relearning')
      ? s
      : (r.triaged ? 'unknown' : null);
  return Word(
    termId: r.term.id,
    term: r.term.termText ?? '',
    translation: r.term.translation ?? '',
    transcription: r.term.transcription,
    example: r.term.example,
    type: r.term.type,
    status: status,
    imageUrl: r.term.imageUrl, // Pexels photo (docks in via sync)
    imageAuthor: r.term.imageAuthor,
    imageAuthorUrl: r.term.imageAuthorUrl,
    // The ladder, through the one shared function — the same one the session builder asks.
    //
    // NO progress row means never shown, which is rung 0 — distinct from a row that merely predates
    // the ladder, where `fromWire` reads `graduated` so a word already being reviewed is never
    // pushed back to an intro card. The two nulls mean opposite things and are told apart here.
    ladderStep: LearningLadder.stepFor(
      acquisition: r.acquisition == null ? Acquisition.isNew : Acquisition.fromWire(r.acquisition),
      successfulReviews: r.successfulReviews,
      learningStep: r.learningStep,
      isKnown: s == 'known',
    ),
    isKnown: s == 'known',
    enrolled: r.enrolled,
  );
}

/// Mirror of the server's `Mastery`: a term is mastered once self-marked `known`, or proven by
/// exercises (`review` state) with an interval of at least this many days. Source of truth is
/// `Learning\Domain\Service\Mastery::MASTERED_INTERVAL_DAYS` — keep in step.
const int _masteredIntervalDays = 21;

bool _isMastered(String? state, int intervalDays) =>
    state == 'known' || (state == 'review' && intervalDays >= _masteredIntervalDays);

Stats _deriveStats(
  List<TermProgressData> rows, {
  required int streak,
  required int reviewsToday,
  int newGoal = 0,
  int newRemaining = 0,
}) {
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
    newGoal: newGoal,
    newRemaining: newRemaining,
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

/// Identifies one exercise session. [sessionId] is a client ULID minted once when the screen
/// opens, so `POST /study/sessions` is idempotent (a rebuild reuses the same fixed composition);
/// [collectionId] scopes to one owned collection; [practice] introduces no new terms and never
/// schedules (the «Свободная тренировка» / practice banner path).
typedef SessionArgs = ({
  String sessionId,
  String? collectionId,
  bool practice,
  int limit,
  /// «Тренировать слово» (кадр 16e): narrow the practice pool to this one term. Practice only —
  /// a scheduling session's composition is the server's to fix.
  String? onlyTermId,
});

/// The exercise cards for one session (`POST /study/sessions`): due then new, one card per
/// exercise, each with its mode + offline extras. Online-only (sessions build server-side, which
/// picks the mode and distractors); consumers read via `.when`. The photo/example shown in the
/// per-card feedback come from the LOCAL term mirror, so they render even though the session shape
/// carries no image.
final studySessionProvider =
    FutureProvider.family<StudySession, SessionArgs>((ref, args) async {
  // Free practice is built HERE, always — not "when offline". It has to work in airplane mode from
  // start to summary, and one code path is the only way both halves of that stay honest: a second,
  // online-only branch would be the one nobody exercises until it breaks. Everything it needs is
  // already mirrored locally, its pool rule is simply "the whole collection, shuffled", and it
  // schedules nothing. The session id is a client ULID the server adopts when the answers arrive.
  //
  // A scheduling session stays server-built: it spends the daily new-word quota and its
  // composition is what stops an abandoned session spending it on unseen terms.
  if (args.practice) {
    final collectionId = args.collectionId;
    if (collectionId == null) {
      // Practice is always entered from a collection; a global practice pool has no rule yet.
      throw StateError('practice needs a collection');
    }
    final db = ref.watch(appDatabaseProvider);
    final all = await db.collectionTerms(collectionId);
    // «Тренировать слово» narrows the pool to one term; everything else about the session — the
    // ladder gate, the toggles, the shuffle — is unchanged, because nothing about drilling one word
    // should make it a different kind of session.
    final terms = args.onlyTermId == null
        ? all
        : [for (final t in all) if (t.id == args.onlyTermId) t];
    // The trainer toggles the server last told us about (stored by the sync service). Read from the
    // local DB like everything else on this path, so practice keeps working offline — and so a
    // toggle flipped in the admin panel changes the offline session on the next sync.
    final enabled = PracticeModes.fromWire(await db.getMeta(SyncKeys.exerciseModes));
    // …and the acquisition ladder the same feed carried: where each pair stands, and which rung
    // opens which trainer. Practice is not exempt from it — a word met a minute ago is not dealt
    // dictation here either — which is the gate that was deferred when the ladder landed server-side.
    final admission = ModeAdmission.fromWire(_decodeList(await db.getMeta(SyncKeys.modeAdmission)));
    final ladder = await db.ladderPositions([for (final t in terms) t.id]);
    return LocalPracticeSessionBuilder.build(
      terms: terms,
      limit: args.limit,
      random: Random(),
      sessionId: args.sessionId,
      enabled: enabled,
      ladder: ladder,
      admission: admission,
    );
  }

  return ref.watch(apiClientProvider).buildSession(
        sessionId: args.sessionId,
        collectionId: args.collectionId,
        practice: args.practice,
        limit: args.limit,
      );
});
