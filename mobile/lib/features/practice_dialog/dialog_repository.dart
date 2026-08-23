import 'package:dio/dio.dart';

import '../../data/api_client.dart';
import '../../data/local/app_database.dart';
import 'dialog_models.dart';

/// The backend seam for a practice dialog. The screen and [DialogController] depend only on this
/// interface, so the scripted [FakeDialogRepository] and the real [ApiDialogRepository] are drop-in
/// interchangeable — the app runs the fake until `/practice/dialogs` ships, then flips the provider.
///
/// Nothing here touches reviews or progress: a dialog is PRACTICE.
abstract interface class DialogRepository {
  /// Start a dialog for [collectionId]. [clientId] is a client ULID for idempotency. Throws a
  /// [DialogException] (subscriptionRequired / rateLimited / offline / network) on failure.
  Future<DialogStart> start({required String collectionId, required String clientId});

  /// Upload a batch of transcript [events] and get back the server's authoritative target-word
  /// coverage. Idempotent by each event's `ts`.
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events);

  /// Wrap up: the model's summary + how many target words were used.
  Future<DialogSummary> finish(String dialogId);
}

/// The real backend implementation — the LAST wiring step. Reads the collection's words + the
/// ephemeral realtime token from `/practice/dialogs`, uploads transcript batches, and finishes.
/// Kept off by default (the provider serves [FakeDialogRepository]) until the endpoints exist and
/// have been verified against `openapi.yaml`.
class ApiDialogRepository implements DialogRepository {
  ApiDialogRepository(this._api);
  final ApiClient _api;

  @override
  Future<DialogStart> start({required String collectionId, required String clientId}) async {
    try {
      final data = await _api.startDialog(collectionId: collectionId, clientId: clientId);
      return DialogStart.fromJson(data);
    } on DioException catch (e) {
      throw _mapError(e);
    }
  }

  @override
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events) async {
    final raw = await _api.sendDialogTranscripts(dialogId, events.map((e) => e.toJson()).toList());
    return raw.map((e) => TargetWord.fromJson(e as Map<String, dynamic>)).toList();
  }

  @override
  Future<DialogSummary> finish(String dialogId) async {
    return DialogSummary.fromJson(await _api.finishDialog(dialogId));
  }

  static DialogException _mapError(DioException e) {
    final status = e.response?.statusCode;
    if (status == 403) return const DialogException(DialogErrorKind.subscriptionRequired);
    if (status == 429) {
      // RFC 7807 problem: the reset instant lives under `meta.resets_at` (code
      // `practice_dialogs_quota_exceeded`).
      final body = e.response?.data;
      DateTime? resets;
      if (body is Map) {
        final meta = body['meta'];
        if (meta is Map && meta['resets_at'] is String) {
          resets = DateTime.tryParse(meta['resets_at'] as String)?.toLocal();
        }
      }
      return DialogException(DialogErrorKind.rateLimited, resetsAt: resets);
    }
    if (e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout) {
      return const DialogException(DialogErrorKind.offline);
    }
    return const DialogException(DialogErrorKind.network);
  }
}

/// Scripted stand-in used until the backend ships (and in tests). Draws the target words from the
/// LOCAL collection mirror so a real collection's words appear on the coverage bar, and computes
/// coverage the way the server would — a word is "used" once its text surfaces in the accumulated
/// user speech. Deterministic and offline: no network, no reviews, no progress.
class FakeDialogRepository implements DialogRepository {
  FakeDialogRepository(this._db, {this.durationSeconds = 200});

  final AppDatabase _db;
  final int durationSeconds;

  final StringBuffer _userSpeech = StringBuffer();
  List<TargetWord> _words = const [];

  @override
  Future<DialogStart> start({required String collectionId, required String clientId}) async {
    final rows = await _db.watchCollectionTerms(collectionId).first;
    _words = rows
        .map((r) => TargetWord(termId: r.term.id, text: r.term.termText ?? ''))
        .where((w) => w.text.isNotEmpty)
        .toList();
    _userSpeech.clear();
    return DialogStart(
      dialogId: 'fake-$clientId',
      realtimeToken: 'fake-token',
      expiresAt: DateTime.now().add(Duration(seconds: durationSeconds)),
      model: 'fake-realtime',
      targetWords: _words,
      durationSeconds: durationSeconds,
    );
  }

  @override
  Future<List<TargetWord>> sendTranscripts(String dialogId, List<TranscriptEvent> events) async {
    for (final e in events) {
      if (e.role == DialogRole.user) _userSpeech.write(' ${e.text.toLowerCase()}');
    }
    final spoken = _userSpeech.toString();
    _words = _words
        .map((w) => w.copyWith(used: w.used || spoken.contains(w.text.toLowerCase())))
        .toList();
    return _words;
  }

  @override
  Future<DialogSummary> finish(String dialogId) async {
    final used = _words.where((w) => w.used).length;
    // English (practiced language) so no Cyrillic literal lives in lib/ (cyrillic-guard);
    // the real backend returns its own model-written summary once wired.
    return DialogSummary(
      summary:
          'You kept the conversation going and used $used of the ${_words.length} target words. '
          'Nice, natural back-and-forth — keep practising out loud.',
      wordsUsed: used,
      wordsTotal: _words.length,
    );
  }
}
