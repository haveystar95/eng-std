/// Domain models for the realtime practice dialog (голосовой разговор по коллекции).
///
/// This is a PRACTICE feature: nothing here ever writes to reviews or progress. The shapes mirror
/// the fixed `/practice/dialogs` contract exactly, so the [FakeDialogRepository] and the real
/// [ApiDialogRepository] are interchangeable behind [DialogRepository].
library;

/// Who spoke a line — the value is exactly what `POST /practice/dialogs/{id}/transcripts` expects.
enum DialogRole {
  user('user'),
  assistant('assistant');

  const DialogRole(this.wire);
  final String wire;

  static DialogRole fromWire(String? v) =>
      v == 'assistant' ? DialogRole.assistant : DialogRole.user;
}

/// One transcribed line (bot reply or user ASR). [ts] is an epoch-millis timestamp; the server
/// dedups a re-sent batch by `(role, ts)`, so replaying a batch after a flaky flush is safe.
class TranscriptEvent {
  final DialogRole role;
  final String text;
  final int ts;

  const TranscriptEvent({required this.role, required this.text, required this.ts});

  Map<String, dynamic> toJson() => {'role': role.wire, 'text': text, 'ts': ts};

  factory TranscriptEvent.fromJson(Map<String, dynamic> j) => TranscriptEvent(
        role: DialogRole.fromWire(j['role'] as String?),
        text: (j['text'] as String?) ?? '',
        ts: (j['ts'] as int?) ?? 0,
      );
}

/// A target word from the collection, with the server's authoritative [used] flag. The coverage
/// bar renders one chip per word; [used] flips false→true as the server recognises it in speech.
class TargetWord {
  final String termId;
  final String text;
  final bool used;

  const TargetWord({required this.termId, required this.text, this.used = false});

  TargetWord copyWith({bool? used}) =>
      TargetWord(termId: termId, text: text, used: used ?? this.used);

  factory TargetWord.fromJson(Map<String, dynamic> j) => TargetWord(
        termId: (j['term_id'] as String?) ?? '',
        text: (j['text'] as String?) ?? '',
        used: (j['used'] as bool?) ?? false,
      );
}

/// The started dialog: what `POST /practice/dialogs` returns. [realtimeToken] is an ephemeral
/// OpenAI Realtime token the transport uses to connect directly (audio never transits our server);
/// [durationSeconds] is the token TTL and thus the length of the conversation (default 200s).
class DialogStart {
  final String dialogId;
  final String realtimeToken;
  final DateTime expiresAt;
  final String model;
  final List<TargetWord> targetWords;
  final int durationSeconds;

  /// Which realtime transport the server minted this dialog for: `openai` (WebRTC, default) or
  /// `gemini` (Gemini Live WebSocket). Absent → `openai`, so existing dialogs are unaffected.
  final String provider;

  /// The WS/connection endpoint the client connects to (Gemini bare-token path). Null for providers
  /// that embed the endpoint elsewhere (OpenAI uses its own `/v1/realtime/calls`).
  final String? endpoint;

  /// A ready `BidiGenerateContentSetup` the client sends verbatim as its first WS message (Gemini
  /// bare-token path). Null when the session is baked into the token (OpenAI / constrained Gemini).
  final Map<String, dynamic>? sessionSetup;

  /// Provider-specific connection extras (additive, opaque to the shared pipeline). Legacy/forward-
  /// compat; the Gemini fields above are the current backend contract.
  final Map<String, dynamic>? connection;

  const DialogStart({
    required this.dialogId,
    required this.realtimeToken,
    required this.expiresAt,
    required this.model,
    required this.targetWords,
    required this.durationSeconds,
    this.provider = 'openai',
    this.endpoint,
    this.sessionSetup,
    this.connection,
  });

  factory DialogStart.fromJson(Map<String, dynamic> j) => DialogStart(
        dialogId: (j['dialog_id'] as String?) ?? '',
        realtimeToken: (j['realtime_token'] as String?) ?? '',
        expiresAt: DateTime.tryParse((j['expires_at'] as String?) ?? '')?.toLocal() ??
            DateTime.now().add(const Duration(seconds: 200)),
        model: (j['model'] as String?) ?? '',
        targetWords: ((j['target_words'] as List?) ?? const [])
            .map((e) => TargetWord.fromJson(e as Map<String, dynamic>))
            .toList(),
        durationSeconds: (j['duration_seconds'] as int?) ?? 200,
        provider: (j['provider'] as String?) ?? 'openai',
        endpoint: j['endpoint'] as String?,
        sessionSetup: j['session_setup'] as Map<String, dynamic>?,
        connection: j['connection'] as Map<String, dynamic>?,
      );
}

/// The wrap-up: what `POST /practice/dialogs/{id}/finish` returns. [summary] is model-written prose
/// about the conversation; [wordsUsed]/[wordsTotal] drive the «N из M слов прозвучало» line.
class DialogSummary {
  final String summary;
  final int wordsUsed;
  final int wordsTotal;

  const DialogSummary({
    required this.summary,
    required this.wordsUsed,
    required this.wordsTotal,
  });

  factory DialogSummary.fromJson(Map<String, dynamic> j) => DialogSummary(
        summary: (j['summary'] as String?) ?? '',
        wordsUsed: (j['words_used'] as int?) ?? 0,
        wordsTotal: (j['words_total'] as int?) ?? 0,
      );
}

/// Why a dialog could not start (or finished abnormally). [subscriptionRequired] = 403 (not
/// premium); [rateLimited] = 429 with a [resetsAt]; [offline]/[network] are transport failures.
enum DialogErrorKind { subscriptionRequired, rateLimited, offline, network, unknown }

class DialogException implements Exception {
  final DialogErrorKind kind;
  final DateTime? resetsAt; // set for rateLimited

  const DialogException(this.kind, {this.resetsAt});

  @override
  String toString() => 'DialogException($kind${resetsAt != null ? ', resetsAt: $resetsAt' : ''})';
}

/// The transport's current state, surfaced to the centre indicator: connecting → the bot is
/// [botSpeaking] («говорит») ↔ [listening] («слушаю тебя») → [closed] (bot wrapped up / TTL hit).
enum DialogPhase { connecting, botSpeaking, listening, closed }

/// The result of a collection's most recent finished dialog (`GET /practice/collections/{id}/
/// last-dialog`). Absent (null) when the collection has never had a dialog. An early-exit dialog
/// still produces one — a result is a result.
class LastDialogResult {
  final DateTime finishedAt;
  final int wordsUsed;
  final int wordsTotal;

  const LastDialogResult({
    required this.finishedAt,
    required this.wordsUsed,
    required this.wordsTotal,
  });

  factory LastDialogResult.fromJson(Map<String, dynamic> j) => LastDialogResult(
        finishedAt: DateTime.tryParse((j['finished_at'] ?? j['created_at'] ?? '') as String)?.toLocal() ??
            DateTime.now(),
        wordsUsed: (j['words_used'] as int?) ?? 0,
        wordsTotal: (j['words_total'] as int?) ?? 0,
      );
}
