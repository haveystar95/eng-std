import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/features/practice_dialog/dialog_controller.dart';
import 'package:eng_std/features/practice_dialog/dialog_models.dart';
import 'package:eng_std/features/practice_dialog/dialog_repository.dart';
import 'package:eng_std/features/practice_dialog/realtime_channel.dart';

/// Integration on the scripted [FakeRealtimeChannel], accelerated (millisecond beats): a full
/// conversation flows channel → controller → repository → coverage → finale. Also pins the start
/// error mapping (403 / 429) and that a dialog never schedules — it's practice.
void main() {
  late AppDatabase db;
  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  final t0 = DateTime.utc(2026, 8, 7, 9);

  Future<void> seed() => db.applyDelta(
    collectionUpserts: [
      CollectionsCompanion.insert(id: 'c1', updatedAt: t0, title: const Value('At the Bank')),
    ],
    termUpserts: [
      TermsCompanion.insert(id: 't1', updatedAt: t0, termText: const Value('withdraw')),
      TermsCompanion.insert(id: 't2', updatedAt: t0, termText: const Value('account')),
      TermsCompanion.insert(id: 't3', updatedAt: t0, termText: const Value('balance')),
    ],
    itemUpserts: [
      CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't1', updatedAt: t0),
      CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't2', updatedAt: t0),
      CollectionItemsCompanion.insert(collectionId: 'c1', termId: 't3', updatedAt: t0),
    ],
  );

  DialogController build() => DialogController(
    repository: FakeDialogRepository(db, durationSeconds: 200),
    channel: FakeRealtimeChannel(
      connectDelay: const Duration(milliseconds: 2),
      botBeat: const Duration(milliseconds: 2),
      listenBeat: const Duration(milliseconds: 2),
      thinkGap: const Duration(milliseconds: 1),
    ),
    collectionId: 'c1',
    clientId: 'CID1',
    flushEvery: const Duration(milliseconds: 2),
    flushBatchSize: 2,
  );

  test('scripted dialog fills coverage and reaches the finale', () async {
    await seed();
    final c = build();
    await c.start();

    expect(c.status, DialogStatus.active);
    expect(c.targetWords.length, 3);
    expect(c.usedCount, 0); // nothing spoken yet

    await _until(() => c.status == DialogStatus.finished);

    expect(c.status, DialogStatus.finished);
    expect(c.feed, isNotEmpty); // both bot + user lines landed
    expect(c.usedCount, 3); // the script spoke all three target words
    expect(c.summary, isNotNull);
    expect(c.summary!.wordsUsed, 3);
    expect(c.summary!.wordsTotal, 3);
    c.dispose();
  });

  test('coverage flips a word to used only after it is spoken', () async {
    await seed();
    final c = build();
    await c.start();
    // Let the first user line ("... withdraw ...") flush before the others.
    await _until(() => c.usedCount >= 1);
    expect(c.targetWords.firstWhere((w) => w.termId == 't1').used, isTrue);
    // The dialog keeps going; eventually all three fill.
    await _until(() => c.status == DialogStatus.finished);
    expect(c.usedCount, 3);
    c.dispose();
  });

  test('the agent speaks first — the opening feed line is the bot, before any user line', () async {
    await seed();
    final c = build();
    await c.start();
    await _until(() => c.feed.isNotEmpty);
    expect(c.feed.first.role, DialogRole.assistant);
    c.dispose();
  });

  test(
    'a user-initiated finish (early exit) still produces a summary — a result is a result',
    () async {
      await seed();
      final c = build();
      await c.start();
      await _until(() => c.usedCount >= 1);
      final usedSoFar = c.usedCount;
      await c.finish();
      expect(c.status, DialogStatus.finished);
      expect(c.summary, isNotNull);
      expect(c.summary!.wordsUsed, greaterThanOrEqualTo(usedSoFar));
      c.dispose();
    },
  );

  test(
    'assistant transcripts are uploaded too, so the server can credit coverage from them',
    () async {
      await seed();
      final repo = _RecordingRepo(const ['withdraw', 'account', 'balance']);
      final c = DialogController(
        repository: repo,
        channel: FakeRealtimeChannel(
          connectDelay: const Duration(milliseconds: 2),
          botBeat: const Duration(milliseconds: 2),
          listenBeat: const Duration(milliseconds: 2),
          thinkGap: const Duration(milliseconds: 1),
        ),
        collectionId: 'c1',
        clientId: 'CID1',
        flushEvery: const Duration(milliseconds: 2),
        flushBatchSize: 2,
      );
      await c.start();
      await _until(() => c.status == DialogStatus.finished);
      // The frontend must send BOTH roles upstream — the bot's acks mention target words too.
      expect(repo.uploaded.any((e) => e.role == DialogRole.assistant), isTrue);
      expect(repo.uploaded.any((e) => e.role == DialogRole.user), isTrue);
      c.dispose();
    },
  );

  test('start maps a 403 to subscriptionRequired and never enters the conversation', () async {
    final c = DialogController(
      repository: const _ThrowingRepo(DialogException(DialogErrorKind.subscriptionRequired)),
      channel: FakeRealtimeChannel(),
      collectionId: 'c1',
      clientId: 'CID1',
    );
    await c.start();
    expect(c.status, DialogStatus.error);
    expect(c.errorKind, DialogErrorKind.subscriptionRequired);
    expect(c.targetWords, isEmpty);
    c.dispose();
  });

  test('start maps a 429 to rateLimited with the reset instant', () async {
    final resets = DateTime(2026, 8, 8, 6);
    final c = DialogController(
      repository: _ThrowingRepo(DialogException(DialogErrorKind.rateLimited, resetsAt: resets)),
      channel: FakeRealtimeChannel(),
      collectionId: 'c1',
      clientId: 'CID1',
    );
    await c.start();
    expect(c.status, DialogStatus.error);
    expect(c.errorKind, DialogErrorKind.rateLimited);
    expect(c.rateResetsAt, resets);
    c.dispose();
  });
}

/// Polls a condition with tiny real delays — the scripted channel runs on millisecond beats, so the
/// whole conversation completes in well under the timeout.
Future<void> _until(bool Function() cond, {Duration timeout = const Duration(seconds: 5)}) async {
  final end = DateTime.now().add(timeout);
  while (!cond() && DateTime.now().isBefore(end)) {
    await Future<void>.delayed(const Duration(milliseconds: 3));
  }
  if (!cond()) fail('condition not met within $timeout');
}

/// Records every uploaded transcript event and credits coverage by substring from ANY role, so the
/// test can assert the frontend sends assistant lines upstream (server-side crediting territory).
class _RecordingRepo implements DialogRepository {
  _RecordingRepo(List<String> words)
    : _words = [
        for (var i = 0; i < words.length; i++) TargetWord(termId: 't${i + 1}', text: words[i]),
      ];

  List<TargetWord> _words;
  final List<TranscriptEvent> uploaded = [];
  final StringBuffer _spoken = StringBuffer();

  @override
  Future<DialogStart> start({required String collectionId, required String clientId}) async =>
      DialogStart(
        dialogId: 'rec',
        realtimeToken: 'rec',
        expiresAt: DateTime(2026, 8, 7, 12),
        model: 'rec',
        targetWords: _words,
        durationSeconds: 200,
      );

  @override
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events) async {
    uploaded.addAll(events);
    for (final e in events) {
      _spoken.write(' ${e.text.toLowerCase()}'); // credit ANY role
    }
    final s = _spoken.toString();
    _words = [for (final w in _words) w.copyWith(used: w.used || s.contains(w.text.toLowerCase()))];
    return _words;
  }

  @override
  Future<DialogSummary> finish(String dialogId) async {
    final used = _words.where((w) => w.used).length;
    return DialogSummary(summary: 'done', wordsUsed: used, wordsTotal: _words.length);
  }
}

/// A repository whose [start] always throws — for the error-mapping tests.
class _ThrowingRepo implements DialogRepository {
  const _ThrowingRepo(this.error);
  final DialogException error;

  @override
  Future<DialogStart> start({required String collectionId, required String clientId}) async =>
      throw error;

  @override
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events) async =>
      throw UnimplementedError();

  @override
  Future<DialogSummary> finish(String dialogId) async => throw UnimplementedError();
}
