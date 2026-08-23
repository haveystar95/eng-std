import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter/foundation.dart' show debugPrint;
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../practice/learning_ladder.dart';

part 'app_database.g.dart';

// The local mirror of the sync payload. Screens read from here; the background sync writes here.
// Deletes are modelled as actual row removals when a tombstone (op:delete) arrives — the client
// keeps no `deleted_at`, it just drops the row, so a screen query never sees a ghost.

/// Owned collections. Mirrors `changes.collections` (upserts only; deletes remove the row).
class Collections extends Table {
  TextColumn get id => text()();
  TextColumn get title => text().nullable()();
  TextColumn get description => text().nullable()();
  TextColumn get topic => text().nullable()();
  TextColumn get sourceLang => text().nullable()();
  TextColumn get targetLang => text().nullable()();
  IntColumn get itemsCount => integer().withDefault(const Constant(0))();
  TextColumn get source => text().nullable()(); // curated | ai | user — origin badge
  TextColumn get type => text().nullable()(); // system | shared | custom
  // Pexels cover + attribution (A3). Populated by sync; screens/badges are Part B.
  TextColumn get imageUrl => text().nullable()();
  TextColumn get imageAuthor => text().nullable()();
  TextColumn get imageAuthorUrl => text().nullable()();

  /// «Сохранённые»: the one folder a one-tap save from search lands in. Exactly one per owner,
  /// renameable, never deletable — the shelf greys its delete action out on this flag rather than
  /// on the title, which the owner may have changed.
  BoolColumn get isDefault => boolean().withDefault(const Constant(false))();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// Collection ↔ term membership. Mirrors `changes.collection_items`.
class CollectionItems extends Table {
  TextColumn get collectionId => text()();
  TextColumn get termId => text()();
  IntColumn get position => integer().withDefault(const Constant(0))();
  TextColumn get note => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {collectionId, termId};
}

/// Global term content. Mirrors `changes.terms`. `termText` (getter renamed to dodge the
/// inherited `text()` builder) is the term itself.
class Terms extends Table {
  TextColumn get id => text()();
  TextColumn get termText => text().named('text').nullable()();
  TextColumn get type => text().withDefault(const Constant('word'))();
  TextColumn get transcription => text().nullable()();
  TextColumn get translation => text().nullable()();
  TextColumn get example => text().nullable()();
  TextColumn get exampleTranslation => text().nullable()();

  /// What the word MEANS, written in the language BEING LEARNED — the whole question of a
  /// `description_match` card. Mirrored rather than only carried on the card, because the device
  /// builds its own practice sessions offline: a trainer whose content never reaches the phone is a
  /// trainer that silently never appears there. Null on everything written before descriptions
  /// existed (the store catalogue), and the gate refuses those terms by content.
  TextColumn get description => text().nullable()();
  // Pexels photo + attribution (A3). Populated by sync; the card image is Part B.
  TextColumn get imageUrl => text().nullable()();
  TextColumn get imageAuthor => text().nullable()();
  TextColumn get imageAuthorUrl => text().nullable()();

  /// Other answers that also count as correct, as a JSON array of strings. Needed OFFLINE: the
  /// instant check grades against `{termText} ∪ acceptedVariants`, so a device without them would
  /// reject an answer the server accepts.
  ///
  /// JSON in a column rather than a child table on purpose — `/sync` sends a term's whole variant
  /// list on every term upsert, so one write replaces the whole set atomically and there is no
  /// orphan row to clean up. A child table would buy queryability nothing here needs.
  TextColumn get acceptedVariants => text().nullable()();

  /// Wrong versions of [example], as a JSON array of objects. Mirrored ahead of the trainer that
  /// reads them, so it works offline the day it is switched on.
  TextColumn get exampleDistractors => text().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// (user, term) progress. Single-user app, so no user_id column. Mirrors `changes.progress`.
///
/// TWO INDEPENDENT DIMENSIONS live here, exactly as they do server-side, and no local write moves
/// both: [state] and the SM-2 columns say WHEN the pair comes back; [acquisition] and
/// [learningStep] say WHAT it comes back as. The scheduler is the server's alone — the client never
/// writes the first group — while the ladder advances locally the moment an intro is acknowledged,
/// because the session it belongs to has to keep running in airplane mode.
class TermProgress extends Table {
  TextColumn get termId => text()();
  TextColumn get state => text().withDefault(const Constant('new'))();
  RealColumn get easeFactor => real().withDefault(const Constant(2.5))();
  IntColumn get intervalDays => integer().withDefault(const Constant(0))();
  DateTimeColumn get dueAt => dateTime().nullable()();
  IntColumn get reps => integer().withDefault(const Constant(0))();
  IntColumn get lapses => integer().withDefault(const Constant(0))();
  DateTimeColumn get lastReviewedAt => dateTime().nullable()();

  /// The acquisition ladder: `new` (never shown) | `learning` (on the recognition rungs) |
  /// `graduated`. Defaults to `graduated` for rows that already existed when the ladder landed —
  /// the safe direction, since the alternative pushes a known word back to an intro card.
  TextColumn get acquisition => text().withDefault(const Constant('graduated'))();

  /// The rung while [acquisition] is `learning` (1 or 2). Not derivable from any counter: a failed
  /// recognition step is re-queued as the same step but is still logged.
  IntColumn get learningStep => integer().withDefault(const Constant(0))();

  /// Correct non-practice reviews since the pair graduated — what the rungs ABOVE assembly are
  /// counted in. Deliberately not [reps], which counts how many times the server's scheduler was
  /// CALLED, `again` included: reading the rung off that promoted words the learner had only ever
  /// got wrong, because a miss re-schedules the pair immediately (QA-18).
  IntColumn get successfulReviews => integer().withDefault(const Constant(0))();

  /// THE POOL — a third dimension, independent of the two above: not when the pair comes back, nor
  /// as what, but WHETHER it comes back at all. Non-null = the learner is studying this word;
  /// null = it is in the catalogue only (never taken into study, marked «знаю», or paused).
  ///
  /// Null is NOT a tombstone. The row and its whole history stay, which is exactly what makes
  /// «Убрать из изучения» a pause the learner can undo — a returned word resumes at the rung and
  /// the due date it left with.
  DateTimeColumn get enrolledAt => dateTime().nullable()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// The durable queue of un-uploaded INTRO acknowledgements — «this word was shown».
///
/// The durable queue of un-uploaded POOL changes — «Учить это слово» and «Убрать из изучения».
///
/// Keyed by the TERM and holding the DESIRED state rather than an event, which is the whole shape
/// of the thing: membership is a set, the two verbs are idempotent, and the last intent is the only
/// one that matters. Enrol-then-remove offline collapses to one row saying «out», and replaying it
/// lands exactly where replaying both would have. That is why this is not an append-only log like
/// [ReviewQueueRows]: an order there changes the outcome, here it cannot.
///
/// It exists because these two taps are the ONLY way a word reaches the trainer. Dropping one made
/// in the metro would silently cost the learner a word they asked for — and they would have no way
/// to tell, because the local mirror already shows it enrolled.
class PoolQueueRows extends Table {
  TextColumn get termId => text()();

  /// true = «должно быть в пуле», false = «должно быть вне пула».
  BoolColumn get enrolled => boolean()();
  DateTimeColumn get changedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// Separate from [ReviewQueueRows] because an exposure is not an answer: no mode, no response, no
/// grade, and no `client_seq`. Sequence numbers exist to order events whose ORDER changes the
/// outcome; an exposure has none — it is idempotent on the pair, it is applied before the batch's
/// answers, and a second one for the same word is not a later event but the same one re-sent.
///
/// The PRIMARY KEY is the TERM, mirroring the server's `(user_id, term_id)`: a term is introduced
/// once, so idempotency is a property of the table rather than of whoever is draining it.
class ExposureQueueRows extends Table {
  TextColumn get termId => text()();
  TextColumn get shownAt => text()(); // ISO-8601 UTC, reference-only (device clock)
  TextColumn get sessionId => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// The durable queue of un-uploaded session COMPLETIONS — «this run was played to its end».
///
/// Its own table for the same reason exposures have one: a completion is not an answer. It carries
/// no term, no mode and no response, and the server takes nothing from it but the time — what
/// happened during the run it recomputes from the run's own logs.
///
/// The PRIMARY KEY is the SESSION, which is what makes the queue idempotent by construction: a run
/// finishes once, so a re-queued completion is the same event rather than a later one. The server's
/// write is conditional on the row still being open, so the two idempotencies agree.
class SessionCompletionQueueRows extends Table {
  TextColumn get sessionId => text()();
  TextColumn get endedAt => text()(); // ISO-8601 UTC, the moment the learner actually stopped

  @override
  Set<Column<Object>> get primaryKey => {sessionId};
}

/// Small key/value store for sync bookkeeping: the sync cursor (`server_time`) and cached
/// stats numbers that aren't in the delta feed (streak, reviews-today). Lives in the DB — NOT
/// the keychain — so a reinstall wipes the cursor with the data and the next sync is a full snapshot.
class SyncMeta extends Table {
  TextColumn get key => text()();
  TextColumn get value => text().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {key};
}

/// Local mirror of the server's `term_triages` exclusion, keyed by (user→implicit, term). A term
/// swiped in triage is marked here so it leaves the offline deck and does not return — the server's
/// term_triages isn't in the delta feed, and an `unknown` swipe writes NO progress row, so without
/// this marker such a term would resurrect after a sync (BUG-1). Wiped with the DB on sign-out /
/// reinstall (a fresh install re-triages from scratch, same as everything else re-syncing).
class TriagedTerms extends Table {
  TextColumn get termId => text()();
  TextColumn get collectionId => text().nullable()(); // where it was swiped (informational)
  DateTimeColumn get decidedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {termId};
}

/// A generation the user kicked off, tracked locally so the pending card survives an app kill and
/// reconciles on launch. NOT synced — purely client-side bookkeeping. `id` is the server request id.
class PendingGenerations extends Table {
  TextColumn get id => text()();
  TextColumn get topic => text()();
  TextColumn get status =>
      text().withDefault(const Constant('pending'))(); // pending|running|succeeded|failed
  TextColumn get collectionId => text().nullable()();
  TextColumn get error => text().nullable()();
  IntColumn get requested => integer().nullable()();
  IntColumn get delivered => integer().nullable()();
  // Original request params — kept so «повторить» works even after an app kill.
  TextColumn get sourceLang => text().withDefault(const Constant('ru'))();
  TextColumn get targetLang => text().withDefault(const Constant('en'))();
  TextColumn get levelsCsv => text().withDefault(const Constant('A2,B1'))();
  IntColumn get size => integer().withDefault(const Constant(15))();
  // Whether the POST has reached the server. false → still in the durable offline prompt queue
  // (client ULID pre-upload, B2): re-sent when the network returns, NEVER polled (it has no server
  // row yet) and NEVER dropped as a ghost — a not-yet-sent generation is a promise we still owe.
  BoolColumn get sent => boolean().withDefault(const Constant(true))();
  // B4: send `target_lang` on the POST only when the user explicitly chose it; otherwise omit it
  // and let the server fall back to the profile's target_language.
  BoolColumn get targetLangExplicit => boolean().withDefault(const Constant(true))();
  DateTimeColumn get createdAt => dateTime()(); // device time — drives the >24h drop rule
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// The durable queue of un-uploaded answers. Lives here, NOT in the Keychain, for two reasons that
/// only showed up under measurement (F20-r2):
///
///  * the Keychain store held the queue as ONE JSON blob, so every answer re-serialised and rewrote
///    the whole thing on the UI isolate — a cost that grows with the queue, and one `record()` was
///    caught taking 650 ms once the queue stopped draining;
///  * drift runs in a background isolate, so an insert costs the UI isolate nothing.
///
/// It is not a secret either — it is application data, and the Keychain was simply the wrong store.
/// The per-user monotonic `SeqCounter` deliberately STAYS in the Keychain: it must survive this
/// queue being cleared, which is the whole reason it was put under its own key.
///
/// Ordering rides on [clientSeq], never on row order or device time.
class ReviewQueueRows extends Table {
  TextColumn get id => text()(); // client ULID — the /reviews/batch idempotency key
  TextColumn get termId => text()();
  TextColumn get exerciseMode => text()();
  TextColumn get response => text()();
  IntColumn get clientSeq => integer()();
  TextColumn get answeredAt => text()(); // ISO-8601 UTC, reference-only
  BoolColumn get usedHint => boolean().withDefault(const Constant(false))();
  BoolColumn get isPractice => boolean().withDefault(const Constant(false))();
  IntColumn get latencyMs => integer().nullable()();
  TextColumn get sessionId => text().nullable()();

  /// The rung the card was dealt at, echoed back with the answer (1–5; null off the ladder).
  /// Rung 1 is graded by IDENTITY server-side, and the server only takes that path when this says
  /// so — without it a tapped term id is graded as text against the term's own forms and a correct
  /// tap is folded as a lapse. Queued with the answer because the pair's rung MOVES as the answer
  /// is folded, so nothing else can still say what the card asked.
  IntColumn get ladderStep => integer().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {id};
}

/// Per-day review count — the source for the Progress screen's activity chart, week calendar and
/// «за неделю»/«сегодня» counters (кадр 2.6). NOT synced: purely local, incremented only in
/// [ReviewSync.record] (session + free practice; triage is deliberately excluded — the chart must
/// converge with the streak dots beside it, and the streak is "days with reviews"). Accumulates
/// from the current day forward with no backfill (there is no server history endpoint). `day` is a
/// local calendar day key `YYYY-MM-DD`.
class DailyActivity extends Table {
  TextColumn get day => text()();
  IntColumn get reviews => integer().withDefault(const Constant(0))();

  @override
  Set<Column<Object>> get primaryKey => {day};
}

/// One term as shown on the collection view screen: content joined with its live position and
/// (optional) learning state, so a single reactive query feeds the whole screen.
class CollectionTermRow {
  const CollectionTermRow({
    required this.term,
    required this.position,
    this.state,
    this.triaged = false,
    this.acquisition,
    this.learningStep = 0,
    this.successfulReviews = 0,
    this.enrolled = false,
  });
  final Term term; // generated data class for the Terms table
  final int position;
  final String? state; // null → not started (no progress row)
  final bool
  triaged; // swiped in triage — lets the UI distinguish "не знаю" (still new) from untouched

  /// The ACQUISITION ladder, alongside [state]'s scheduling one. Null → no progress row at all,
  /// which the ladder reads as «never shown» (rung 0).
  final String? acquisition;
  final int learningStep;

  /// The ladder's counter — what the rungs above assembly are read off. The scheduler's `reps` is
  /// deliberately NOT carried here: nothing on this screen has a use for it, and it is the counter
  /// the rung used to be read off by mistake (QA-18).
  final int successfulReviews;

  /// Is the word in the learner's POOL? The collection screen is a catalogue view now, so it says
  /// which of its words are actually being studied — the rest carry a quiet «в каталоге».
  final bool enrolled;
}

/// One word of the learner's POOL, as «Мои слова» shows it: the content, where it stands on the
/// acquisition ladder, which collections it came from (possibly none — a pool word outlives its
/// collection), and when it was taken into study.
class PoolWordRow {
  const PoolWordRow({
    required this.term,
    required this.position,
    required this.collectionIds,
    required this.enrolledAt,
  });

  final Term term;

  /// The rung, ready to hand to the same five dots the collection screen draws.
  final LadderPosition position;

  /// Source collections, for the «откуда это слово» filter. Mutable on purpose: the query folds a
  /// word's several join rows into one of these.
  final List<String> collectionIds;

  final DateTime enrolledAt;
}

/// (collectionId, term progress snapshot) for deriving per-collection progress locally.
class ItemProgressRow {
  const ItemProgressRow({
    required this.collectionId,
    required this.termId,
    this.state,
    this.intervalDays,
    this.dueAt,
  });
  final String collectionId;
  final String termId;
  final String? state;
  final int? intervalDays;
  final DateTime? dueAt;
}

/// Index of the on-disk image byte cache: one row per cached remote image.
///
/// The BYTES live in files (an image in a SQLite row would be read through the DB isolate on
/// every decode); this table is what makes the cache accountable — exact sizes for the ceiling,
/// and a last-used stamp for the LRU sweep. Keeping it here rather than scanning the directory
/// means the sweep never has to stat a thousand files to find the coldest one.
///
/// Client-only, never synced. Wiped with the account on sign-out (another account's photos are
/// not ours to keep) — see [AppDatabase.clearAll].
class CachedImages extends Table {
  TextColumn get url => text()();
  TextColumn get file => text()(); // relative to the cache directory
  IntColumn get bytes => integer()();
  DateTimeColumn get usedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {url};
}

@DriftDatabase(
  tables: [
    Collections,
    CollectionItems,
    Terms,
    TermProgress,
    SyncMeta,
    TriagedTerms,
    PendingGenerations,
    DailyActivity,
    ReviewQueueRows,
    ExposureQueueRows,
    SessionCompletionQueueRows,
    PoolQueueRows,
    CachedImages,
  ],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_open());
  AppDatabase.forTesting(super.e);

  @override
  int get schemaVersion => 16;

  /// `addColumn`, but a no-op when the column is already there (QA-23).
  ///
  /// Every step below has to be safe to run TWICE, because a migration that fails half-way is not
  /// rolled back: SQLite applies each `ALTER TABLE` as it goes, and `user_version` only advances
  /// once the whole `onUpgrade` returns. So one failing step leaves the schema partly migrated at
  /// the OLD version number — and on the next launch the run starts again from that same old
  /// number, hits the column it already added, and dies on «duplicate column name». Every launch
  /// after that dies the same way, so the local store is bricked permanently and by design.
  ///
  /// That is exactly what a device reported: `duplicate column name: accepted_variants` on
  /// `ALTER TABLE "terms" ADD COLUMN "accepted_variants"` — step 10 re-running forever. Since the
  /// whole database is a MIRROR of the server, a bricked one takes the entire app with it: no
  /// sync, no collections, and no generation (its first act is a local write).
  ///
  /// `createTable` needs no such guard — drift already emits `CREATE TABLE IF NOT EXISTS`.
  static Future<void> _addColumnIfMissing(
    Migrator m,
    TableInfo<Table, dynamic> table,
    GeneratedColumn<Object> column,
  ) async {
    final rows = await m.database.customSelect('PRAGMA table_info(${table.actualTableName})').get();
    final present = rows.any((r) => r.read<String>('name') == column.$name);
    if (!present) await m.addColumn(table, column);
  }

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (m) => m.createAll(),
    onUpgrade: (m, from, to) async {
      if (from < 2) await m.createTable(triagedTerms); // triage-from-local-DB
      if (from < 3) {
        await _addColumnIfMissing(m, collections, collections.source); // origin badge
        await _addColumnIfMissing(m, collections, collections.type);
      }
      if (from < 4) {
        // Pexels imagery (A3): cover on collections, photo on terms, + attribution each.
        await _addColumnIfMissing(m, collections, collections.imageUrl);
        await _addColumnIfMissing(m, collections, collections.imageAuthor);
        await _addColumnIfMissing(m, collections, collections.imageAuthorUrl);
        await _addColumnIfMissing(m, terms, terms.imageUrl);
        await _addColumnIfMissing(m, terms, terms.imageAuthor);
        await _addColumnIfMissing(m, terms, terms.imageAuthorUrl);
      }
      if (from < 5) await m.createTable(pendingGenerations); // pending-generation card (Part B)
      if (from < 6) {
        // Offline prompt queue (A3.5): a generation may sit un-sent until the network returns.
        await _addColumnIfMissing(m, pendingGenerations, pendingGenerations.sent);
        await _addColumnIfMissing(m, pendingGenerations, pendingGenerations.targetLangExplicit);
      }
      if (from < 7) await m.createTable(dailyActivity); // Progress-screen activity (A3.6)
      // Durable review queue moved out of the Keychain (F20-r2). The existing blob is imported
      // once at start-up by ReviewQueue.migrateFromKeychain — not here, because the migration
      // needs the Keychain, which the DB layer must not know about.
      if (from < 8) await m.createTable(reviewQueueRows);
      // Disk cache for remote images (F22): photos seen once stay visible offline and across
      // restarts. The files are created lazily, so there is nothing to backfill here.
      if (from < 9) await m.createTable(cachedImages);
      if (from < 10) {
        // Enrichment станок: accepted variants (needed for offline typed grading) and example
        // distractors (mirrored ahead of the trainer that reads them).
        await _addColumnIfMissing(m, terms, terms.acceptedVariants);
        await _addColumnIfMissing(m, terms, terms.exampleDistractors);
        // Drop the sync cursor so the next sync is a FULL snapshot. A delta only carries terms
        // whose `updated_at` moved, so terms already mirrored here would otherwise keep their
        // new columns null forever — and a null variant list is exactly the state where the
        // client grades an answer wrong that the server grades right.
        await m.database.customStatement("DELETE FROM sync_meta WHERE key = 'sync_cursor'");
      }
      if (from < 11) {
        // The acquisition ladder. Existing rows take the column default `graduated`, which is
        // the same backfill the server did and for the same reason: a word already being
        // reviewed must not be pushed back to an intro card.
        await _addColumnIfMissing(m, termProgress, termProgress.acquisition);
        await _addColumnIfMissing(m, termProgress, termProgress.learningStep);
        await m.createTable(exposureQueueRows);
        // Full snapshot on the next sync, for the same reason as v10: a delta carries only rows
        // whose `updated_at` moved, so pairs already mirrored here would keep the default
        // `graduated` forever — and a word the server has at rung 0 would never get its intro.
        await m.database.customStatement("DELETE FROM sync_meta WHERE key = 'sync_cursor'");
      }
      if (from < 12) {
        // The rung a queued answer was dealt at, so rung-1 taps upload as identity answers.
        // Rows already queued stay null on purpose: they were recorded as TEXT, and stamping a
        // rung on them now would tell the server to read that text as a term id. They upload
        // exactly as they would have before this version — see the ladder_step contract note.
        await _addColumnIfMissing(m, reviewQueueRows, reviewQueueRows.ladderStep);
      }
      if (from < 13) {
        // Session completions ride their own durable queue, so a run finished in airplane mode
        // still reaches `study_sessions.ended_at` when the network returns (QA-12).
        await m.createTable(sessionCompletionQueueRows);
      }
      if (from < 14) {
        // The ladder's own counter (QA-18). Rungs 4 and 5 used to be read off `reps`, which
        // counts scheduler calls of every grade, so misses carried words upward.
        await _addColumnIfMissing(m, termProgress, termProgress.successfulReviews);
        // Full snapshot on the next sync, for the same reason as v10 and v11: a delta carries
        // only rows whose `updated_at` moved, so every pair already mirrored here would keep
        // the column default 0 — and a word the owner HAS earned typing on would sit back at
        // assembly until it happened to be answered again. The server has the honest number
        // for all of them; the cheapest way to get it is to ask for everything once.
        await m.database.customStatement("DELETE FROM sync_meta WHERE key = 'sync_cursor'");
      }
      if (from < 15) {
        // The POOL. A word reaches the trainer only once the learner has taken it into study.
        await _addColumnIfMissing(m, termProgress, termProgress.enrolledAt);
        // Local backfill, mirroring the server's migration exactly: every pair that already
        // exists was created by a deliberate act, so it is enrolled — except a «знаю»
        // self-assessment, whose row exists only to carry a verification check. Done here as
        // well as asked for over the wire because the phone must not show an empty «Мои слова»
        // in the minutes (or the flight) between the update and the next sync.
        await m.database.customStatement(
          "UPDATE term_progress SET enrolled_at = updated_at WHERE state <> 'known'",
        );
        // …and a full snapshot on the next sync, for the same reason as v10, v11 and v14: a
        // delta carries only rows whose `updated_at` moved, so the server's real enrolment
        // moments (and any word paused on another device) would never arrive. The backfill
        // above is the offline stand-in; this is the truth replacing it.
        await m.database.customStatement("DELETE FROM sync_meta WHERE key = 'sync_cursor'");
        // The two pool taps ride their own durable queue, for the same reason answers and
        // session completions do: they are the only way a word reaches the trainer, so one
        // made in airplane mode must not be lost.
        await m.createTable(poolQueueRows);
      }
      if (from < 16) {
        // The word's DESCRIPTION (the description_match trainer's question) and the flag that
        // says which folder is «Сохранённые».
        await _addColumnIfMissing(m, terms, terms.description);
        await _addColumnIfMissing(m, collections, collections.isDefault);
        // …and a full snapshot on the next sync, for the same reason as v10, v11, v14 and v15:
        // a delta carries only rows whose `updated_at` moved, so every term and folder already
        // mirrored here would keep the column default forever. There is no offline stand-in for
        // either value — the server is the only place they exist.
        await m.database.customStatement("DELETE FROM sync_meta WHERE key = 'sync_cursor'");
      }
    },
  );

  // ---- Reads (reactive) -----------------------------------------------------

  /// All owned collections, ordered by title for a stable list (created_at isn't synced).
  Stream<List<Collection>> watchCollections() {
    return (select(collections)..orderBy([(t) => OrderingTerm(expression: t.title)])).watch();
  }

  /// The terms of one collection with content + live status, in study order (position).
  Stream<List<CollectionTermRow>> watchCollectionTerms(String collectionId) {
    final query =
        select(collectionItems).join([
            innerJoin(terms, terms.id.equalsExp(collectionItems.termId)),
            leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
            leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
          ])
          ..where(collectionItems.collectionId.equals(collectionId))
          ..orderBy([OrderingTerm(expression: collectionItems.position)]);

    return query.watch().map(
      (rows) => rows.map((r) {
        final progress = r.readTableOrNull(termProgress);
        return CollectionTermRow(
          term: r.readTable(terms),
          position: r.readTable(collectionItems).position,
          state: progress?.state,
          triaged: r.readTableOrNull(triagedTerms) != null,
          acquisition: progress?.acquisition,
          learningStep: progress?.learningStep ?? 0,
          successfulReviews: progress?.successfulReviews ?? 0,
          enrolled: progress?.enrolledAt != null,
        );
      }).toList(),
    );
  }

  /// Every progress row — the input for the local stats derivation.
  Stream<List<TermProgressData>> watchAllProgress() => select(termProgress).watch();

  /// All synced term content — powers the deterministic client-side «Слово дня».
  /// Reactive, so the block appears once the first sync lands. No network.
  Stream<List<Term>> watchAllTerms() => select(terms).watch();

  /// Every synced term of one collection, in study order. One-shot: free practice builds its whole
  /// session from this snapshot on the device, so it must not depend on a live stream.
  Future<List<Term>> collectionTerms(String collectionId) {
    final query =
        select(collectionItems).join([innerJoin(terms, terms.id.equalsExp(collectionItems.termId))])
          ..where(collectionItems.collectionId.equals(collectionId))
          ..orderBy([OrderingTerm(expression: collectionItems.position)]);

    return query.map((row) => row.readTable(terms)).get();
  }

  /// One synced term by id (or null) — used by the exercise-session feedback to pull the photo,
  /// which the `/study/sessions` shape does not carry. One-shot: the image is already synced.
  Future<Term?> termById(String id) =>
      (select(terms)..where((t) => t.id.equals(id))).getSingleOrNull();

  /// One term's progress, reactive — the exercise feedback shows «увидишь снова через N дней»
  /// from the REAL server `due_at`, which lands here after the answer's upload + sync (never a
  /// client-computed interval — the server is the only scheduler). Null until progress exists.
  Stream<TermProgressData?> watchProgressFor(String termId) =>
      (select(termProgress)..where((p) => p.termId.equals(termId))).watchSingleOrNull();

  /// Every term's (state, intervalDays) — all terms left-joined with their progress, so untouched
  /// terms (no progress row) surface as null. Feeds the global density bar on the Progress screen
  /// («Все N слов»): each term folds into exactly one of confirmed/familiar/in-progress. Reactive.
  Stream<List<({String? state, int? intervalDays})>> watchTermStates() {
    final query = select(
      terms,
    ).join([leftOuterJoin(termProgress, termProgress.termId.equalsExp(terms.id))]);
    return query.watch().map(
      (rows) => rows.map((r) {
        final p = r.readTableOrNull(termProgress);
        return (state: p?.state, intervalDays: p?.intervalDays);
      }).toList(),
    );
  }

  // ---- Daily activity (client-only, not synced) -----------------------------

  /// Live per-day review counts — feeds the Progress-screen activity chart, week calendar and
  /// «за неделю»/«сегодня» counters. Newest first isn't needed; the screen indexes by day key.
  Stream<List<DailyActivityData>> watchDailyActivity() => select(dailyActivity).watch();

  /// Increment today's review tally by one (atomic upsert). Called once per graded answer from
  /// [ReviewSync.record] — session reviews and free practice, never triage (see [DailyActivity]).
  ///
  /// `customInsert` with an explicit `updates`, NOT `customStatement`: drift cannot see which tables
  /// a raw statement writes, so the bumps landed in the table without waking [watchDailyActivity].
  /// The goal card read whatever the count had been when the screen first subscribed — zero — and
  /// stayed there for the whole run however many answers went in (QA-10).
  Future<void> bumpDailyActivity(String day) => customInsert(
    'INSERT INTO daily_activity (day, reviews) VALUES (?, 1) '
    'ON CONFLICT(day) DO UPDATE SET reviews = reviews + 1',
    variables: [Variable<String>(day)],
    updates: {dailyActivity},
  );

  /// Merge the server's activity calendar (F18) into the local map: a day the client hasn't
  /// recorded lights up (count 1); a day it already counted keeps its exact optimistic tally
  /// (max(existing, 1) via ON CONFLICT DO NOTHING). So a relogin/reinstall restores the whole
  /// calendar from `/stats` without clobbering today's live count.
  Future<void> mergeActiveDays(List<String> days) => transaction(() async {
    for (final day in days) {
      await customInsert(
        'INSERT INTO daily_activity (day, reviews) VALUES (?, 1) ON CONFLICT(day) DO NOTHING',
        variables: [Variable<String>(day)],
        updates: {dailyActivity}, // same reason as bumpDailyActivity: raw SQL wakes no stream
      );
    }
  });

  // ---- Durable review queue (client-only, not synced) -----------------------

  /// The queue in upload order. `client_seq` is the ONLY ordering — never row order, never a
  /// device timestamp.
  Future<List<ReviewQueueRow>> reviewQueue() =>
      (select(reviewQueueRows)..orderBy([(t) => OrderingTerm(expression: t.clientSeq)])).get();

  /// Append ONE answer. A single insert, on the background isolate — this is the whole point of
  /// moving the queue here: the Keychain store rewrote the entire blob on every answer.
  Future<void> enqueueReview(ReviewQueueRowsCompanion row) =>
      into(reviewQueueRows).insertOnConflictUpdate(row);

  /// Drop uploaded (or permanently rejected) answers by client ULID.
  Future<void> dequeueReviews(Iterable<String> ids) =>
      (delete(reviewQueueRows)..where((t) => t.id.isIn(ids.toList()))).go();

  Future<int> reviewQueueLength() async {
    final row = await customSelect(
      'SELECT COUNT(*) AS c FROM review_queue_rows',
      readsFrom: {reviewQueueRows},
    ).getSingle();
    return row.read<int>('c');
  }

  /// The oldest PRACTICE answers, for the queue cap. Scheduling answers are never offered here —
  /// they carry progress the server has not seen and must never be dropped to make room.
  Future<List<String>> oldestPracticeReviewIds(int limit) async {
    final rows =
        await (select(reviewQueueRows)
              ..where((t) => t.isPractice.equals(true))
              ..orderBy([(t) => OrderingTerm(expression: t.clientSeq)])
              ..limit(limit))
            .get();
    return [for (final r in rows) r.id];
  }

  /// Idempotent bulk insert used by the one-time Keychain import: re-running it can only re-insert
  /// the same client ULIDs, so a half-finished migration heals on the next launch.
  Future<void> importReviewQueue(List<ReviewQueueRowsCompanion> rows) async {
    if (rows.isEmpty) return;
    await batch((b) => b.insertAll(reviewQueueRows, rows, mode: InsertMode.insertOrIgnore));
  }

  /// Where each of these terms stands on the acquisition ladder. Terms with no progress row are
  /// absent from the result — the caller reads them as [LadderPosition.untouched], which is the
  /// same thing said once instead of a row seeded for every unseen word.
  Future<Map<String, LadderPosition>> ladderPositions(List<String> termIds) async {
    if (termIds.isEmpty) return const {};
    final rows = await (select(termProgress)..where((p) => p.termId.isIn(termIds))).get();
    return {
      for (final r in rows)
        r.termId: LadderPosition(
          acquisition: Acquisition.fromWire(r.acquisition),
          learningStep: r.learningStep,
          successfulReviews: r.successfulReviews,
          isKnown: r.state == 'known',
          enrolled: r.enrolledAt != null,
        ),
    };
  }

  // ---- Durable exposure queue + the local ladder ----------------------------

  /// Un-uploaded intro acknowledgements, oldest first. Order is informational here — the server
  /// keys them by the pair, so replaying them in any order lands the same rows.
  Future<List<ExposureQueueRow>> exposureQueue() =>
      (select(exposureQueueRows)..orderBy([(t) => OrderingTerm(expression: t.shownAt)])).get();

  /// Record that a word was SHOWN. `insertOrIgnore`, not upsert: the pair is the key and the FIRST
  /// `shown_at` is the one that is true, exactly as on the server.
  Future<void> enqueueExposure(ExposureQueueRowsCompanion row) =>
      into(exposureQueueRows).insert(row, mode: InsertMode.insertOrIgnore);

  Future<void> dequeueExposures(Iterable<String> termIds) =>
      (delete(exposureQueueRows)..where((t) => t.termId.isIn(termIds.toList()))).go();

  // ---- Durable session-completion queue (client-only, not synced) -----------

  Future<List<SessionCompletionQueueRow>> completionQueue() => (select(
    sessionCompletionQueueRows,
  )..orderBy([(t) => OrderingTerm(expression: t.endedAt)])).get();

  /// Record that a run was played to its end. `insertOrIgnore`, not upsert: the session is the key
  /// and the FIRST `ended_at` is the one that is true — a run finishes once.
  Future<void> enqueueCompletion(SessionCompletionQueueRowsCompanion row) =>
      into(sessionCompletionQueueRows).insert(row, mode: InsertMode.insertOrIgnore);

  Future<void> dequeueCompletions(Iterable<String> sessionIds) => (delete(
    sessionCompletionQueueRows,
  )..where((t) => t.sessionId.isIn(sessionIds.toList()))).go();

  /// Step a pair off rung 0 onto the first recognition rung, locally.
  ///
  /// Writes the LADDER columns only — never a scheduling field. The server is the only scheduler,
  /// and an intro produces no grade for it to schedule from; what this buys is a session that keeps
  /// running in airplane mode, with the same word not offered its intro twice.
  ///
  /// Idempotent by the same rule the server uses: a pair that has already left `new` is untouched,
  /// so a replayed acknowledgement cannot push it back down.
  Future<void> markIntroduced(String termId, DateTime at) async {
    final existing = await (select(
      termProgress,
    )..where((p) => p.termId.equals(termId))).getSingleOrNull();

    if (existing == null) {
      await into(termProgress).insert(
        TermProgressCompanion.insert(
          termId: termId,
          updatedAt: at,
          acquisition: const Value('learning'),
          learningStep: const Value(1),
        ),
      );
      return;
    }
    if (existing.acquisition != 'new') return; // already past the intro — leave it alone

    await (update(termProgress)..where((p) => p.termId.equals(termId))).write(
      TermProgressCompanion(
        acquisition: const Value('learning'),
        learningStep: const Value(1),
        updatedAt: Value(at),
      ),
    );
  }

  // ---- The pool -------------------------------------------------------------

  /// Put a word into the pool locally — the optimistic half of «Учить это слово» and of a
  /// «не знаю»/«не уверен» swipe.
  ///
  /// The server is the authority and its answer arrives by `/sync`; this write exists so the screen
  /// changes under the finger and so an enrolment made in airplane mode is visible until it does.
  /// Idempotent by the same rule the server uses: a pair already in the pool keeps its FIRST
  /// moment, so «с какого дня я это учу» is not rewritten by a second tap or a replayed swipe.
  ///
  /// [acquisition]/[learningStep] are written ONLY when the row is created — they are the ladder
  /// position the verdict implies («не знаю» → rung 0, «не уверен» → rung 1). An existing row is
  /// never moved on the ladder from here: that is the server's projection, and a swipe must not
  /// push a word that has been studied back down a rung.
  Future<void> enrollLocally(
    String termId,
    DateTime at, {
    String acquisition = 'new',
    int learningStep = 0,
  }) async {
    final existing = await (select(
      termProgress,
    )..where((p) => p.termId.equals(termId))).getSingleOrNull();

    if (existing == null) {
      await into(termProgress).insert(
        TermProgressCompanion.insert(
          termId: termId,
          updatedAt: at,
          acquisition: Value(acquisition),
          learningStep: Value(learningStep),
          enrolledAt: Value(at),
        ),
      );
      return;
    }
    if (existing.enrolledAt != null) return; // already in the pool — keep the first moment

    await (update(termProgress)..where((p) => p.termId.equals(termId))).write(
      TermProgressCompanion(enrolledAt: Value(at), updatedAt: Value(at)),
    );
  }

  /// Take a word out of the pool locally — a PAUSE. One column to null and nothing else, mirroring
  /// the server: the rung, the counter, the schedule and the answer history all stand, so bringing
  /// the word back resumes exactly where it was left.
  Future<void> unenrollLocally(String termId, DateTime at) async {
    await (update(termProgress)..where((p) => p.termId.equals(termId))).write(
      TermProgressCompanion(enrolledAt: const Value(null), updatedAt: Value(at)),
    );
  }

  /// Undo a local enrolment made by a swipe that is being taken back.
  ///
  /// Guarded to a pair the app has never SHOWN: a word already on the ladder or in the schedule was
  /// not put in the pool by the swipe being undone, and an undo must not pause it. The remaining
  /// imprecision — a never-shown word that was enrolled earlier by «Учить это слово» and is now
  /// swiped and un-swiped — resolves itself on the next sync, because the server still holds that
  /// enrolment and sends it back. The opposite choice does not self-heal: a local row the server
  /// never had is never corrected by a delta feed, and the word would sit in «Мои слова» forever.
  Future<void> unenrollLocallyIfUnshown(String termId, DateTime at) async {
    await (update(termProgress)
          ..where((p) => p.termId.equals(termId) & p.acquisition.equals('new') & p.reps.equals(0)))
        .write(TermProgressCompanion(enrolledAt: const Value(null), updatedAt: Value(at)));
  }

  /// The pool changes still to be uploaded, oldest intent first. Order is informational — the
  /// server keys them by the pair and both verbs are idempotent — but oldest-first keeps a backlog
  /// readable in the logs.
  Future<List<PoolQueueRow>> poolQueue() =>
      (select(poolQueueRows)..orderBy([(t) => OrderingTerm(expression: t.changedAt)])).get();

  /// Record the DESIRED membership. Upsert, not append: the last intent for a term is the only one
  /// worth sending, so enrol-then-remove offline leaves one row rather than two calls to replay.
  Future<void> enqueuePoolChange(String termId, {required bool enrolled, required DateTime at}) =>
      into(poolQueueRows).insertOnConflictUpdate(
        PoolQueueRowsCompanion.insert(termId: termId, enrolled: enrolled, changedAt: at),
      );

  Future<void> dequeuePoolChanges(Iterable<String> termIds) =>
      (delete(poolQueueRows)..where((t) => t.termId.isIn(termIds.toList()))).go();

  /// Everything in the pool, newest enrolment first, with each word's content, its rung and the
  /// collections it came from — the single query behind «Мои слова».
  ///
  /// A word appears here because the learner chose it, not because a collection holds it, so the
  /// LEFT join on `collection_items`: a pool word whose collection was deleted or unsubscribed is
  /// still being studied and still listed, with no source to show. The join fans a word into one
  /// row per collection; they are folded back together here.
  Stream<List<PoolWordRow>> watchPool() {
    final query =
        select(termProgress).join([
            innerJoin(terms, terms.id.equalsExp(termProgress.termId)),
            leftOuterJoin(collectionItems, collectionItems.termId.equalsExp(termProgress.termId)),
          ])
          ..where(termProgress.enrolledAt.isNotNull())
          ..orderBy([OrderingTerm(expression: termProgress.enrolledAt, mode: OrderingMode.desc)]);

    return query.watch().map((rows) {
      final byTerm = <String, PoolWordRow>{};
      final order = <String>[];
      for (final r in rows) {
        final progress = r.readTable(termProgress);
        final collectionId = r.readTableOrNull(collectionItems)?.collectionId;
        final existing = byTerm[progress.termId];
        if (existing != null) {
          if (collectionId != null) existing.collectionIds.add(collectionId);
          continue;
        }
        order.add(progress.termId);
        byTerm[progress.termId] = PoolWordRow(
          term: r.readTable(terms),
          position: LadderPosition(
            acquisition: Acquisition.fromWire(progress.acquisition),
            learningStep: progress.learningStep,
            successfulReviews: progress.successfulReviews,
            isKnown: progress.state == 'known',
            enrolled: true,
          ),
          collectionIds: collectionId != null ? [collectionId] : <String>[],
          enrolledAt: progress.enrolledAt!,
        );
      }
      return [for (final id in order) byTerm[id]!];
    });
  }

  /// How many words are in the pool but have never been shown — the «Учить N» CTA's number.
  ///
  /// Read off the POOL rather than off the collections, so a word whose collection was deleted
  /// still counts: it is still a word the learner asked to learn.
  Stream<int> watchLearnableCount() {
    final query = selectOnly(termProgress)
      ..addColumns([termProgress.termId.count()])
      ..where(termProgress.enrolledAt.isNotNull() & termProgress.acquisition.equals('new'));
    return query.map((r) => r.read(termProgress.termId.count()) ?? 0).watchSingle();
  }

  /// WHEN each pool word was taken into study — one moment per word, no other columns.
  ///
  /// This is the daily goal's whole source (QA-BUG-2). The goal is «сколько новых слов я сегодня
  /// взял в работу», and [TermProgress.enrolledAt] is the one column that records that act and
  /// nothing else: a «не знаю»/«не уверен» swipe, «Учить это слово», and a word saved from search
  /// all write it, «знаю» does not, and it is kept at the FIRST moment — so a word counts once
  /// however many trainers it then passes in the day.
  ///
  /// The instants come back raw rather than pre-counted because "today" is a LOCAL calendar day of
  /// this device (and rolls over at the learner's midnight); SQL here would have to be told the zone
  /// and the offset the day started at. The pool is a few hundred rows at most, so the day is picked
  /// in Dart, where `toLocal()` is free and testable.
  Stream<List<DateTime>> watchEnrolledAt() {
    final query = selectOnly(termProgress)
      ..addColumns([termProgress.enrolledAt])
      ..where(termProgress.enrolledAt.isNotNull());
    return query.watch().map((rows) => [for (final r in rows) r.read(termProgress.enrolledAt)!]);
  }

  /// Triage-eligible (never-shown AND never-triaged) term count per collection —
  /// powers the home «Разобрать N» CTA. Same rule as [triageEligible], reactive.
  Stream<Map<String, int>> watchUntriagedByCollection() {
    final query = select(collectionItems).join([
      leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
      leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
    ])..where(_neverShown & triagedTerms.termId.isNull());
    return query.watch().map((rows) {
      final map = <String, int>{};
      for (final r in rows) {
        final cid = r.readTable(collectionItems).collectionId;
        map[cid] = (map[cid] ?? 0) + 1;
      }
      return map;
    });
  }

  /// Learnable term count per collection — this collection's words that are IN THE POOL and have
  /// never been shown. Powers the collection screen's «Учить N» (a non-practice session introduces
  /// them under the daily new-quota).
  ///
  /// The rule used to be «no progress row, but a triage marker exists», which was the same set read
  /// backwards: before the pool, a «не знаю» swipe wrote no row and the marker was the only trace
  /// of it. Now the swipe enrols the pair outright, and the question is asked forwards.
  Stream<Map<String, int>> watchLearnableByCollection() {
    final query = select(collectionItems).join([
      innerJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
    ])..where(termProgress.enrolledAt.isNotNull() & termProgress.acquisition.equals('new'));
    return query.watch().map((rows) {
      final map = <String, int>{};
      for (final r in rows) {
        final cid = r.readTable(collectionItems).collectionId;
        map[cid] = (map[cid] ?? 0) + 1;
      }
      return map;
    });
  }

  /// A word the app has never actually SHOWN: no progress row at all, or one still at rung 0.
  ///
  /// «No row» stopped being the same question the moment enrolment started creating rows before a
  /// word was ever dealt — so the triage deck asks about the LADDER instead, exactly as the server's
  /// queue does. Without this, taking a word into study with «Учить это слово» would silently pull
  /// it out of the collection's swipe pass.
  Expression<bool> get _neverShown =>
      termProgress.termId.isNull() | termProgress.acquisition.equals('new');

  /// The triage-eligible terms of a collection, in study order — mirrors the server's queue rule:
  /// a collection's terms that are never-shown AND never-triaged (not in the local marker), capped
  /// at [cap]. Returned in full (not sliced to the page) so the caller can compute `remaining`
  /// exactly like the backend. This is the single source for both the deck and its counter —
  /// deriving them separately is what caused BUG-1.
  Future<List<Term>> triageEligible(String collectionId, {int cap = 500}) {
    final query =
        select(collectionItems).join([
            innerJoin(terms, terms.id.equalsExp(collectionItems.termId)),
            leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId)),
            leftOuterJoin(triagedTerms, triagedTerms.termId.equalsExp(collectionItems.termId)),
          ])
          ..where(
            collectionItems.collectionId.equals(collectionId) &
                _neverShown & // never shown (no row, or still at rung 0)
                triagedTerms.termId.isNull(),
          ) // never triaged (local marker)
          ..orderBy([OrderingTerm(expression: collectionItems.position)])
          ..limit(cap);

    return query.map((r) => r.readTable(terms)).get();
  }

  /// Mark a term triaged so it leaves the deck and stays out (durable, not synced). Idempotent.
  Future<void> markTriaged(String termId, String? collectionId, DateTime at) =>
      into(triagedTerms).insertOnConflictUpdate(
        TriagedTermsCompanion.insert(
          termId: termId,
          collectionId: Value(collectionId),
          decidedAt: at,
        ),
      );

  /// Undo a triage mark (used when the last swipe is undone before it leaves the screen).
  Future<void> unmarkTriaged(String termId) =>
      (delete(triagedTerms)..where((t) => t.termId.equals(termId))).go();

  /// Every (collection item, progress) pair — the input for per-collection progress.
  Stream<List<ItemProgressRow>> watchItemProgress() {
    final query = select(
      collectionItems,
    ).join([leftOuterJoin(termProgress, termProgress.termId.equalsExp(collectionItems.termId))]);
    return query.watch().map(
      (rows) => rows.map((r) {
        final item = r.readTable(collectionItems);
        final prog = r.readTableOrNull(termProgress);
        return ItemProgressRow(
          collectionId: item.collectionId,
          termId: item.termId,
          state: prog?.state,
          intervalDays: prog?.intervalDays,
          dueAt: prog?.dueAt,
        );
      }).toList(),
    );
  }

  // ---- Pending generations (client-only, not synced) ------------------------

  /// Live list of in-flight / just-finished generations, newest first — feeds the pending cards.
  Stream<List<PendingGeneration>> watchPendingGenerations() {
    return (select(pendingGenerations)..orderBy([(t) => OrderingTerm.desc(t.createdAt)])).watch();
  }

  /// Snapshot for start-up reconciliation (poll each, then drop/keep/mark).
  Future<List<PendingGeneration>> allPendingGenerations() => select(pendingGenerations).get();

  Future<void> upsertPendingGeneration(PendingGenerationsCompanion row) =>
      into(pendingGenerations).insertOnConflictUpdate(row);

  /// Partial update by id — only the columns set in [patch] change (status transitions).
  Future<void> updatePendingGeneration(String id, PendingGenerationsCompanion patch) =>
      (update(pendingGenerations)..where((t) => t.id.equals(id))).write(patch);

  Future<void> deletePendingGeneration(String id) =>
      (delete(pendingGenerations)..where((t) => t.id.equals(id))).go();

  // ---- Meta -----------------------------------------------------------------

  /// Ticks whenever anything the dashboard stats are derived from changes: the synced progress rows
  /// OR the cached daily aggregates the sync service writes into [syncMeta] (streak, reviews today,
  /// the new-word quota).
  ///
  /// The meta half is the point. Those numbers are not in the delta feed — they are refreshed from
  /// `/stats` AFTER the sync has applied its pages — so a stream watching progress alone emitted
  /// once, before the refresh, and then never again: nothing about a triage or a fresh `/stats`
  /// creates a progress row. The screen kept the pre-sync snapshot, which is how the daily goal read
  /// `0 / 20` and the new-word gate read «limit reached» on an untouched quota, both curable only by
  /// restarting the app (QA-10).
  Stream<void> watchStatsSources() =>
      customSelect('SELECT 1', readsFrom: {termProgress, syncMeta}).watch();

  Future<List<TermProgressData>> allProgress() => select(termProgress).get();

  Future<String?> getMeta(String key) async {
    final row = await (select(syncMeta)..where((t) => t.key.equals(key))).getSingleOrNull();
    return row?.value;
  }

  Future<void> setMeta(String key, String? value) async {
    await into(
      syncMeta,
    ).insertOnConflictUpdate(SyncMetaCompanion.insert(key: key, value: Value(value)));
  }

  /// Current row counts + the stored cursor — for the on-device sync diagnostics panel.
  Future<({int collections, int items, int terms, int progress, String? cursor})>
  debugCounts() async {
    Future<int> count(TableInfo<Table, dynamic> t) async {
      final c = countAll();
      final row = await (selectOnly(t)..addColumns([c])).getSingle();
      return row.read(c) ?? 0;
    }

    return (
      collections: await count(collections),
      items: await count(collectionItems),
      terms: await count(terms),
      progress: await count(termProgress),
      cursor: await getMeta('sync_cursor'),
    );
  }

  // ---- Writes (one sync page, atomically) -----------------------------------

  /// Apply one delta page inside a single transaction so a screen never sees a torn state.
  /// Upserts are last-write-wins by id (drift's insertOnConflictUpdate); deletes remove the row.
  /// Re-applying an already-present row (the inclusive `since` boundary re-send) is a no-op.
  Future<void> applyDelta({
    List<CollectionsCompanion> collectionUpserts = const [],
    List<String> collectionDeletes = const [],
    List<CollectionItemsCompanion> itemUpserts = const [],
    List<(String collectionId, String termId)> itemDeletes = const [],
    List<TermsCompanion> termUpserts = const [],
    List<TermProgressCompanion> progressUpserts = const [],
    List<TriagedTermsCompanion> triageUpserts = const [],
  }) async {
    await transaction(() async {
      if (collectionUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(collections, collectionUpserts));
      }
      if (collectionDeletes.isNotEmpty) {
        await (delete(collections)..where((t) => t.id.isIn(collectionDeletes))).go();
        // Orphaned membership rows for a gone collection would otherwise linger.
        await (delete(collectionItems)..where((t) => t.collectionId.isIn(collectionDeletes))).go();
      }
      if (termUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(terms, termUpserts));
      }
      if (itemUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(collectionItems, itemUpserts));
      }
      for (final (collectionId, termId) in itemDeletes) {
        await (delete(
          collectionItems,
        )..where((t) => t.collectionId.equals(collectionId) & t.termId.equals(termId))).go();
      }
      if (progressUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(termProgress, progressUpserts));
      }
      // Triage markers restored from the delta feed: an `unknown` swipe leaves no progress row, so
      // without this a sign-out wipe would let it resurrect in the deck after re-login. Upsert (not
      // replace) — a locally-marked swipe not yet synced back keeps its row; the server echo matches.
      if (triageUpserts.isNotEmpty) {
        await batch((b) => b.insertAllOnConflictUpdate(triagedTerms, triageUpserts));
      }
    });
  }

  /// Optimistically drop a collection (and its membership rows) locally the moment the server
  /// delete succeeds — the read streams update immediately instead of waiting for the next sync
  /// tombstone (which the delta feed doesn't reliably carry for collections). Idempotent.
  Future<void> deleteCollectionLocal(String id) async {
    await transaction(() async {
      await (delete(collections)..where((t) => t.id.equals(id))).go();
      await (delete(collectionItems)..where((t) => t.collectionId.equals(id))).go();
    });
  }

  /// Reconcile against a full snapshot (the authoritative live set): drop every local collection
  /// whose id is NOT in [keep], plus its membership rows. This clears "ghost" collections that
  /// were removed server-side while no tombstone reached us (e.g. a dev-DB reset, or a hard delete).
  /// Only safe from a FULL snapshot, which lists every live collection.
  ///
  /// ⚠️ Exclusion (A3.5): a collection referenced by a live `pending_generations` row is NEVER
  /// reaped, even when absent from the snapshot. A just-generated collection can be locally present
  /// but momentarily missing from a racing full snapshot (the generation commit vs. the snapshot
  /// read); its pending card keeps it alive until the user acknowledges it. Offline-queued
  /// generations have no collection yet — they live only in `pending_generations`, which this reaper
  /// never touches — so this same rule is what keeps offline-created, not-yet-synced generations
  /// from being dropped as ghosts.
  Future<void> reconcileCollections(Set<String> keep) async {
    await transaction(() async {
      final referenced = (await select(
        pendingGenerations,
      ).get()).map((r) => r.collectionId).whereType<String>().toSet();
      final all = await select(collections).get();
      final stale = all
          .where((c) => !keep.contains(c.id) && !referenced.contains(c.id))
          .map((c) => c.id)
          .toList();
      if (stale.isEmpty) return;
      await (delete(collections)..where((t) => t.id.isIn(stale))).go();
      await (delete(collectionItems)..where((t) => t.collectionId.isIn(stale))).go();
    });
  }

  /// Reap everything a FULL snapshot did not mention (QA-24).
  ///
  /// The snapshot is the authoritative live set for the signed-in account — the server enumerates
  /// every collection, term, progress row and triage marker it has for them — so anything still
  /// here that it did not name is gone. [reconcileCollections] has always done this for
  /// collections; the per-user tables carrying the actual LEARNING STATE were never reaped, so a
  /// phone still holding rows from before a server rebuild kept scheduling them forever: the server
  /// cannot send a tombstone for a row it no longer has, which makes a snapshot the ONLY thing that
  /// can ever clear them («Повторить 62 слова» on an account with nothing in it).
  ///
  /// The durable queues are deliberately NOT consulted as protection here, unlike
  /// [reconcileCollections]'s pending-generation guard. A queued review or exposure carries its own
  /// term id and uploads perfectly well without a local term or progress row, and the server sends
  /// both back on the next sync if they really are this account's. Keeping a row alive because an
  /// un-uploaded answer mentions it is how the foreign data would survive the reap that exists to
  /// remove it.
  Future<void> reconcileSnapshot({
    required Set<String> collectionIds,
    required Set<String> termIds,
    required Set<String> progressTermIds,
    required Set<String> triageTermIds,
  }) async {
    await reconcileCollections(collectionIds);
    await transaction(() async {
      Future<void> reap<T extends Table, D>(
        TableInfo<T, D> table,
        String Function(D row) idOf,
        Set<String> keep,
        Expression<bool> Function(T t, List<String> stale) match,
      ) async {
        final stale = [
          for (final row in await select(table).get())
            if (!keep.contains(idOf(row))) idOf(row),
        ];
        if (stale.isEmpty) return;
        await (delete(table)..where((t) => match(t, stale))).go();
      }

      await reap<Terms, Term>(terms, (r) => r.id, termIds, (t, stale) => t.id.isIn(stale));
      await reap<TermProgress, TermProgressData>(
        termProgress,
        (r) => r.termId,
        progressTermIds,
        (t, stale) => t.termId.isIn(stale),
      );
      await reap<TriagedTerms, TriagedTerm>(
        triagedTerms,
        (r) => r.termId,
        triageTermIds,
        (t, stale) => t.termId.isIn(stale),
      );
    });
  }

  /// Wipe every synced table and the cursor. Used on sign-out so a different account can't read
  /// the previous one's cache. (A reinstall wipes the file outright.)
  Future<void> clearAll() async {
    await transaction(() async {
      await delete(collectionItems).go();
      await delete(collections).go();
      await delete(terms).go();
      await delete(termProgress).go();
      await delete(triagedTerms).go();
      await delete(pendingGenerations).go();
      await delete(dailyActivity).go();
      await delete(syncMeta).go();
      // The image files themselves are deleted by ImageDiskCache.clear(), which owns them; this
      // drops the index so a half-finished sign-out can't leave rows pointing at deleted files.
      await delete(cachedImages).go();
    });
  }
}

LazyDatabase _open() {
  return LazyDatabase(() async {
    try {
      final dir = await getApplicationDocumentsDirectory();
      final file = File(p.join(dir.path, 'wordtrainer.sqlite'));

      return NativeDatabase.createInBackground(file);
    } catch (e, s) {
      // Loud on purpose (QA-23). Failing to open is a real, if rare, state — a locked device's
      // protected container, a full disk, a wedged platform channel — and until now it was also a
      // COMPLETELY silent one: the error surfaced only inside whichever caller happened to touch
      // the store first, and both of those swallow it (SyncService goes quiet by design, and the
      // generate screen used to hang). One line here is the difference between «the app does
      // nothing and says nothing» and a cause you can read off the device console.
      debugPrint('[db] open failed: $e\n$s');
      rethrow;
    }
  });
}
