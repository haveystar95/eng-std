import 'dart:io' show SocketException;
import 'dart:math';

import 'package:dio/dio.dart';

import '../features/search/search_pair.dart' show SearchLanguages;
import 'config.dart';
import 'exposure_sync.dart';
import 'models.dart';
import 'review_queue.dart';
import 'token_store.dart';
import 'triage_queue.dart';

/// A response the client cannot fix by resending the same payload: a validation/shape
/// rejection (422) or a too-large body (413). Durable-queue flushes drop these instead of
/// retrying forever; everything else (offline, timeouts, 5xx, 401, 429) is transient and kept.
bool isPermanentReject(DioException e) {
  final status = e.response?.statusCode;
  return status == 422 || status == 413;
}

/// The RFC 7807 `code` backend2 puts on every domain error (`application/problem+json`), or null
/// when the answer isn't one of ours (a proxy 502, Laravel's own 422 shape, an offline failure).
String? problemCode(DioException e) {
  final body = e.response?.data;
  return (body is Map && body['code'] is String) ? body['code'] as String : null;
}

/// The user's daily generation allowance is spent (429 `generation_quota_exceeded`).
///
/// A bare 429 IS transient — a per-minute transport ceiling that clears by itself, so the durable
/// queues keep it. This one is not: re-sending the same prompt today gets the same refusal, and the
/// prompt queue must stop and SAY SO instead of promising «отправим, как только появится сеть»
/// forever (the owner's report: the collection card span indefinitely after a quota 429).
bool isGenerationQuotaExceeded(DioException e) =>
    e.response?.statusCode == 429 && problemCode(e) == 'generation_quota_exceeded';

/// The request never reached the server: no network, a dead tunnel, a timeout. Worth telling the
/// user plainly («нет соединения») instead of showing them a stack of Dio internals — and worth
/// distinguishing from a server that answered but answered badly.
bool isOffline(Object? error) {
  if (error is! DioException) return false;
  return switch (error.type) {
    DioExceptionType.connectionError ||
    DioExceptionType.connectionTimeout ||
    DioExceptionType.sendTimeout ||
    DioExceptionType.receiveTimeout =>
      true,
    // `unknown` wraps whatever the socket threw; a SocketException is still just "no network".
    DioExceptionType.unknown => error.error is SocketException,
    _ => false,
  };
}

/// HTTP client for the backend2 API (`/api/v1`, Sanctum bearer, snake_case,
/// single resources wrapped in `data`). Attaches the token via an interceptor.
class ApiClient {
  ApiClient(TokenStore tokens) : _dio = _buildDio(tokens);

  final Dio _dio;

  static final Random _rand = Random.secure();
  static const String _crockford = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

  /// Client-generated ULID (uppercase Crockford, 26 chars) — matches backend2 so
  /// reviews/collections created offline are idempotent.
  static String ulid() {
    var ts = DateTime.now().millisecondsSinceEpoch;
    final chars = List<String>.filled(26, '0');
    for (var i = 9; i >= 0; i--) {
      chars[i] = _crockford[ts % 32];
      ts = ts ~/ 32;
    }
    for (var i = 10; i < 26; i++) {
      chars[i] = _crockford[_rand.nextInt(32)];
    }
    return chars.join();
  }

  static Dio _buildDio(TokenStore tokens) {
    final dio = Dio(BaseOptions(
      baseUrl: '${AppConfig.apiBaseUrl}/api/v1',
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 40),
      headers: {
        'Accept': 'application/json',
        'ngrok-skip-browser-warning': 'true',
      },
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) {
        final token = tokens.current;
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
    ));

    return dio;
  }

  /// Unwrap the `{ "data": ... }` envelope backend2 uses for single resources/lists.
  static dynamic _data(Response r) => (r.data as Map<String, dynamic>)['data'];

  // ---- Auth -----------------------------------------------------------------

  /// Exchange a Google ID token for a Sanctum token + user. (Not wrapped in `data`.)
  /// [timezone] is the device IANA zone — the backend seeds it on the profile for calendar-day due
  /// rounding (F19); omitted from the body when null so the server keeps its UTC fallback.
  Future<({String token, AppUser user})> googleLogin(String idToken, {String? timezone}) async {
    final r = await _dio.post('/auth/google', data: {'id_token': idToken, 'timezone': ?timezone});
    final body = r.data as Map<String, dynamic>;
    return (
      token: body['token'] as String,
      user: AppUser.fromJson(body['user'] as Map<String, dynamic>),
    );
  }

  /// QA dev sign-in: an email, no credential, a Sanctum token back. Same response shape as
  /// [googleLogin], so the caller's flow is one branch.
  ///
  /// Guarded twice on purpose. [kDevLoginEnabled] is `kDebugMode`, so in a release build this
  /// method is unreachable AND the compiler drops it; the explicit throw is the belt for the
  /// moment somebody calls it from a path the compiler could not prove dead. The server refuses
  /// independently (production, or `DEV_LOGIN_ENABLED` off → 404).
  Future<({String token, AppUser user})> devLogin(String email, {String? timezone}) async {
    if (!kDevLoginEnabled) {
      throw StateError('devLogin is a debug-only door');
    }
    final r = await _dio.post('/auth/dev', data: {
      'email': email,
      'device_name': 'simulator',
      'timezone': ?timezone,
    });
    final body = r.data as Map<String, dynamic>;
    return (
      token: body['token'] as String,
      user: AppUser.fromJson(body['user'] as Map<String, dynamic>),
    );
  }

  Future<AppUser> me() async {
    final r = await _dio.get('/auth/me');
    return AppUser.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<void> logout() async {
    await _dio.post('/auth/logout');
  }

  /// Exchange an Apple identity token for a Sanctum token + user. Mirrors [googleLogin].
  /// NB: the backend `/auth/apple` endpoint is not built yet (Identity module) — this call will 404
  /// until it lands. See the A3.7 notes / roadmap.
  Future<({String token, AppUser user})> appleLogin(String identityToken, {String? name}) async {
    final r = await _dio.post('/auth/apple', data: {'identity_token': identityToken, 'name': ?name});
    final body = r.data as Map<String, dynamic>;
    return (
      token: body['token'] as String,
      user: AppUser.fromJson(body['user'] as Map<String, dynamic>),
    );
  }

  /// Permanently delete the account (B3): `DELETE /auth/me` cascades every module server-side (204).
  Future<void> deleteAccount() async {
    await _dio.delete('/auth/me');
  }

  // ---- Profile --------------------------------------------------------------

  Future<AppUser> updateProfile(Map<String, dynamic> changes) async {
    final r = await _dio.put('/profile', data: changes);
    return AppUser.fromJson(_data(r) as Map<String, dynamic>);
  }

  // ---- Collections ----------------------------------------------------------

  Future<WordCollection> createCollection({
    required String title,
    String? sourceLang,
    String? targetLang,
  }) async {
    final r = await _dio.post('/collections', data: {
      'title': title,
      'source_lang': ?sourceLang,
      'target_lang': ?targetLang,
    });
    return WordCollection.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<WordCollection> updateCollection(String id, {String? title, String? description}) async {
    final r = await _dio.patch('/collections/$id', data: {
      'title': ?title,
      'description': ?description,
    });
    return WordCollection.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<void> deleteCollection(String id) async {
    await _dio.delete('/collections/$id');
  }

  /// Add a word by text; backend2 finds-or-creates the term and returns the collection.
  /// [translation] is **optional** (B1): omit it (or pass null/empty) and the backend enriches
  /// the term async — translation/transcription/example/photo arrive via a later `/sync`. The
  /// key is dropped from the body when null so the server sees "not provided", not an empty string.
  Future<void> addWord(
    String collectionId, {
    required String text,
    String? translation,
    String type = 'word',
  }) async {
    final t = translation?.trim();
    await _dio.post('/collections/$collectionId/items',
        data: {'text': text, 'translation': ?(t == null || t.isEmpty ? null : t), 'type': type});
  }

  Future<void> removeWord(String collectionId, String termId) async {
    await _dio.delete('/collections/$collectionId/items/$termId');
  }

  /// Move a term between two of the user's OWN folders. One call, not remove+add: the two halves
  /// must not be able to half-happen and leave the word in neither folder.
  Future<void> moveWord({
    required String fromCollectionId,
    required String toCollectionId,
    required String termId,
  }) async {
    await _dio.post(
      '/collections/$fromCollectionId/items/$termId/move',
      data: {'to_collection_id': toCollectionId},
    );
  }

  /// «Сохранённые» — the folder a one-tap save lands in, created server-side on first ask.
  Future<WordCollection> defaultCollection() async {
    final r = await _dio.get('/collections/default');
    return WordCollection.fromJson(_data(r) as Map<String, dynamic>);
  }

  // ---- Search ---------------------------------------------------------------

  /// The FREE half: what the database already has. Safe on a debounced keystroke — it costs nothing
  /// and calls no model. Every hit says which of the user's own folders already hold it, which is
  /// what lets the save button tell the truth instead of offering a save that would do nothing.
  Future<List<SearchHit>> search(
    String query, {
    int limit = 20,
    String? source,
    String? target,
  }) async {
    final r = await _dio.get('/search', queryParameters: {
      'q': query,
      'limit': limit,
      'source': ?source,
      'target': ?target,
    });
    return (_data(r) as List)
        .map((e) => SearchHit.fromJson(e as Map<String, dynamic>))
        .toList(growable: false);
  }

  /// Which language pairs the search pill may offer. Read once when the screen opens: this changes
  /// by deployment, not by session, so the device caches whatever it last saw.
  Future<SearchLanguages> searchLanguages() async {
    final r = await _dio.get('/search/languages');
    return SearchLanguages.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// The grey hint line: one word, one translation, as fast as it can be had.
  ///
  /// Safe on a debounce — most answers cost nothing (our own catalogue and a shared cache are
  /// checked before any vendor), and it never fails in a way the screen has to render: no key, no
  /// budget, no answer and a dead vendor all come back as a null translation.
  Future<InstantHint> instantHint(String query, {String? source, String? target}) async {
    final r = await _dio.get('/search/instant', queryParameters: {
      'q': query,
      'source': ?source,
      'target': ?target,
    });
    return InstantHint.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// The PAID half: one cheap model call for a word the database does not have. Never fired by a
  /// debounce — the learner taps «Найти с ИИ», because this spends money.
  ///
  /// A spent daily cap is a normal 200 with [LookupOutcome.limitReached], not an error: the screen
  /// shows the free results beside an honest line. A cached word is served free and does not touch
  /// the cap at all.
  Future<LookupOutcome> lookupWord(String query, {String? source, String? target}) async {
    final r = await _dio.post('/search/lookup', data: {
      'query': query,
      'source': ?source,
      'target': ?target,
    });
    return LookupOutcome.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Save a search result: term → folder → POOL. [collectionId] null means «Сохранённые».
  ///
  /// Exactly one of [lookupId] / [termId]: the first saves a freshly looked-up word, the second one
  /// the database already had. Idempotent — `added`/`enrolled` say what actually happened.
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
  }) async {
    final r = await _dio.post('/search/add', data: {
      'lookup_id': ?lookupId,
      'term_id': ?termId,
      'collection_id': ?collectionId,
    });
    return SavedSearchResult.fromJson(_data(r) as Map<String, dynamic>);
  }

  // ---- Store (B5) -----------------------------------------------------------

  /// Browse the store: public + system collections for a language pair, ordered by `topic` so the
  /// client can render sections. Keyset-paginated on `(topic, id)` via [cursor]. Returns the page of
  /// [StoreCollection]s plus the next cursor (null when there are no more).
  Future<({List<StoreCollection> items, String? nextCursor})> storeCollections({
    String? sourceLang,
    String? targetLang,
    String? cursor,
    int limit = 30,
  }) async {
    final r = await _dio.get('/store/collections', queryParameters: {
      'source_lang': ?sourceLang,
      'target_lang': ?targetLang,
      'cursor': ?cursor,
      'limit': limit,
    });
    final body = r.data as Map<String, dynamic>;
    final items = ((body['data'] as List?) ?? const [])
        .map((e) => StoreCollection.fromJson(e as Map<String, dynamic>))
        .toList();
    final meta = body['meta'] as Map<String, dynamic>?;
    return (items: items, nextCursor: meta?['next_cursor'] as String?);
  }

  /// Preview a store collection before subscribing: the first few terms + the full count
  /// (`GET /store/collections/{id}/preview`, B). Shown for premium sets too — the lock is on adding,
  /// not on seeing what's inside (кадры 8c/8d).
  Future<StorePreview> storePreview(String id) async {
    // A short receive/send timeout so a missing endpoint or a stalled tunnel fails fast (the sheet
    // then shows no list); the provider also caps the whole call at 8s.
    final r = await _dio.get(
      '/store/collections/$id/preview',
      options: Options(
        receiveTimeout: const Duration(seconds: 8),
        sendTimeout: const Duration(seconds: 8),
      ),
    );
    return StorePreview.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Subscribe (add to library) — idempotent. A premium collection on a free tier throws a
  /// [DioException] with status 403 (`subscription_required`); a private/non-store id → 404.
  Future<void> subscribeStore(String id) async {
    await _dio.post('/store/collections/$id/subscribe');
  }

  /// Unsubscribe (remove from library) — idempotent.
  Future<void> unsubscribeStore(String id) async {
    await _dio.delete('/store/collections/$id/subscribe');
  }

  // ---- Training -------------------------------------------------------------

  /// Study cards now (due + new). Scoped to one collection when [collectionId] is set.
  Future<List<ReviewCard>> dueCards({int limit = 40, String? collectionId}) async {
    final r = await _dio.get('/study/due', queryParameters: {
      'limit': limit,
      'collection_id': ?collectionId,
    });
    return (_data(r) as List)
        .map((e) => ReviewCard.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  /// Build a self-contained study session (`POST /study/sessions`): due cards then new cards, one
  /// card per exercise, each carrying its mode + offline extras (options/chips). Composition is
  /// fixed server-side under [sessionId] (a client ULID, idempotent — re-posting returns the same
  /// set). [practice] introduces no new terms and never schedules.
  Future<StudySession> buildSession({
    required String sessionId,
    String? collectionId,
    bool practice = false,
    int limit = 20,
  }) async {
    final r = await _dio.post('/study/sessions', data: {
      'session_id': sessionId,
      'collection_id': ?collectionId,
      'practice': practice,
      'limit': limit,
    });
    final d = _data(r) as Map<String, dynamic>;
    return StudySession(
      sessionId: (d['session_id'] as String?) ?? sessionId,
      cards: (d['cards'] as List? ?? const [])
          .map((e) => SessionCard.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  /// Regenerate a term's example (B6, «Новый пример»): the LLM produces a fresh example (+its
  /// translation), replaces the stored one, and returns it so the card updates in place. Counts
  /// against the daily quota — a 429 surfaces as [DioException] (status 429) for the caller to
  /// catch and show the exhausted-quota message.
  Future<({String example, String? exampleTranslation})> regenerateExample(String termId) async {
    final r = await _dio.post('/terms/$termId/regenerate-example');
    final d = _data(r) as Map<String, dynamic>;
    return (
      example: (d['example'] as String?) ?? '',
      exampleTranslation: d['example_translation'] as String?,
    );
  }

  Future<Stats> stats() async {
    final r = await _dio.get('/stats');
    return Stats.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Upload a batch of graded answers (idempotent by each review's client ULID).
  /// Returns backend2's tally so the caller can reconcile the local queue.
  Future<({int accepted, int duplicates, int unknown})> submitReviews(List<PendingReview> reviews) async {
    final r = await _dio.post('/reviews/batch', data: {
      'reviews': reviews.map((e) => e.toBatchJson()).toList(),
    });
    final d = _data(r) as Map<String, dynamic>;
    return (
      accepted: (d['accepted'] as int?) ?? 0,
      duplicates: (d['duplicates'] as int?) ?? 0,
      unknown: (d['unknown'] as int?) ?? 0,
    );
  }

  /// Upload a batch of INTRO acknowledgements — the same endpoint, a separate list. Idempotent on
  /// the (user, term) pair rather than on a client id: a term is introduced once, so a re-upload is
  /// an ignored insert that keeps the first `shown_at`.
  Future<int> submitExposures(List<PendingExposure> exposures) async {
    final r = await _dio.post('/reviews/batch', data: {
      'exposures': exposures.map((e) => e.toBatchJson()).toList(),
    });
    return ((_data(r) as Map<String, dynamic>)['exposures'] as int?) ?? 0;
  }

  /// Close a study run the learner played to its summary. Sends only WHEN it ended — what happened
  /// during it, the server recomputes from the run's own logs. Idempotent server-side (the write is
  /// conditional on the session still being open) and never an error, so the offline queue can drop
  /// the row on any 2xx.
  Future<bool> completeSession({required String sessionId, required String endedAt}) async {
    final r = await _dio.post('/study/sessions/$sessionId/complete', data: {'ended_at': endedAt});
    return ((_data(r) as Map<String, dynamic>)['completed'] as bool?) ?? false;
  }

  // ---- Triage ---------------------------------------------------------------
  //
  // The deck is now built from the local DB (see triageDeckProvider) so it opens offline; the
  // server's GET /triage/queue still exists but the client no longer calls it. Only the swipe
  // UPLOAD stays here — the one part that must reach the backend.

  /// Upload a batch of triage swipes (idempotent by each swipe's client ULID).
  Future<({int accepted, int duplicates, int unknown})> submitTriages(List<PendingTriage> triages) async {
    final r = await _dio.post('/triage/batch', data: {
      'triages': triages.map((e) => e.toBatchJson()).toList(),
    });
    final d = _data(r) as Map<String, dynamic>;
    return (
      accepted: (d['accepted'] as int?) ?? 0,
      duplicates: (d['duplicates'] as int?) ?? 0,
      unknown: (d['unknown'] as int?) ?? 0,
    );
  }

  /// Put a word into the learner's POOL — «Учить это слово». Idempotent server-side; returns
  /// whether THIS call moved anything, which a replayed request from an offline retry does not.
  ///
  /// The local mirror is written first and independently (see AppDatabase.enrollLocally): the
  /// screen must change under the finger, and an enrolment made in airplane mode has to survive
  /// until `/sync` brings the server's own answer back.
  Future<bool> enrollTerm(String termId) async {
    final r = await _dio.put('/pool/terms/$termId');
    return ((_data(r) as Map<String, dynamic>)['changed'] as bool?) ?? false;
  }

  /// Take a word out of the pool — «Убрать из изучения». A PAUSE: the server clears one column and
  /// leaves the history, the rung and the schedule standing, so enrolling again resumes them.
  Future<bool> unenrollTerm(String termId) async {
    final r = await _dio.delete('/pool/terms/$termId');
    return ((_data(r) as Map<String, dynamic>)['changed'] as bool?) ?? false;
  }

  /// One page of the delta feed the local DB mirrors. Returns the raw `data` map
  /// (`server_time`, `next_cursor`, `has_more`, `changes`) for the SyncService to apply.
  /// [since] is the last stored `server_time` (never the device clock); [cursor] pages within
  /// a frozen snapshot. Omitting [since] asks for a full snapshot (first sync after install).
  Future<Map<String, dynamic>> syncDelta({String? since, String? cursor, int limit = 500}) async {
    final r = await _dio.get('/sync', queryParameters: {
      'since': ?since,
      'cursor': ?cursor,
      'limit': limit,
    });
    return _data(r) as Map<String, dynamic>;
  }

  /// The server's client_seq high-water mark per log. Read on login to seed the
  /// local monotonic counter so a reinstall or a fresh device can't emit sequences
  /// that would lose to already-stored rows.
  Future<({int triage, int review})> syncCursor() async {
    final r = await _dio.get('/sync/cursor');
    final d = _data(r) as Map<String, dynamic>;
    return (
      triage: (d['max_triage_seq'] as int?) ?? 0,
      review: (d['max_review_seq'] as int?) ?? 0,
    );
  }

  // ---- AI generation --------------------------------------------------------

  /// Kicks off async generation; returns the request id to poll.
  ///
  /// [id] is a client-generated ULID (B2): sending it makes the POST idempotent, so the durable
  /// offline queue can re-send safely — the same id returns the **existing** request (200) with no
  /// duplicate, no second job, no extra quota spent. Omit [targetLang] to accept the profile
  /// default server-side (B4). The returned id equals [id] when one was supplied.
  Future<String> generateCollection({
    String? id,
    required String topic,
    required List<String> levels,
    required int size,
    String? sourceLang,
    String? targetLang,
  }) async {
    final r = await _dio.post('/generations', data: {
      'id': ?id,
      'prompt': topic,
      'levels': levels,
      'size': size,
      'source_lang': ?sourceLang,
      'target_lang': ?targetLang,
    });
    return (_data(r) as Map<String, dynamic>)['id'] as String;
  }

  /// Poll a generation request. Status is one of pending|running|succeeded|failed. `requested`/
  /// `delivered` let the client show "получилось N из M" on an under-delivered set.
  Future<GenerationStatusView> jobStatus(String id) async {
    final r = await _dio.get('/generations/$id');
    final data = _data(r) as Map<String, dynamic>;
    return GenerationStatusView(
      status: data['status'] as String,
      collectionId: data['collection_id'] as String?,
      error: data['error'] as String?,
      requested: data['requested'] as int?,
      delivered: data['delivered'] as int?,
    );
  }

  // ---- Practice dialog (realtime voice) -------------------------------------
  //
  // These are thin passthroughs; `ApiDialogRepository` maps the maps/errors to the domain models.
  // NB: unlike most endpoints, the practice responses are NOT wrapped in `data` (they return the
  // object directly — verified against openapi.yaml `StartedPracticeDialog` / the transcripts +
  // finish 200 shapes). A `DioException` propagates so the repository can read the 403/429 status.

  /// Start a dialog: `POST /practice/dialogs` → 201 StartedPracticeDialog (dialog_id, realtime_token,
  /// expires_at, model, target_words, duration_seconds). 403 = not premium; 429 = daily limit.
  Future<Map<String, dynamic>> startDialog({required String collectionId, required String clientId}) async {
    final r = await _dio.post('/practice/dialogs',
        data: {'collection_id': collectionId, 'client_id': clientId});
    return r.data as Map<String, dynamic>;
  }

  /// Upload a batch of transcript events: `POST /practice/dialogs/{id}/transcripts`. Idempotent by
  /// (role, ts). Returns the updated `target_words` (with the server's monotonic `used` flags).
  Future<List<dynamic>> sendDialogTranscripts(String dialogId, List<Map<String, dynamic>> events) async {
    final r = await _dio.post('/practice/dialogs/$dialogId/transcripts', data: {'events': events});
    return ((r.data as Map<String, dynamic>)['target_words'] as List?) ?? const [];
  }

  /// Finish a dialog: `POST /practice/dialogs/{id}/finish` → `{summary, words_used, words_total}`.
  Future<Map<String, dynamic>> finishDialog(String dialogId) async {
    final r = await _dio.post('/practice/dialogs/$dialogId/finish');
    return r.data as Map<String, dynamic>;
  }

  /// The collection's most recent finished dialog: `GET /practice/collections/{id}/last-dialog` →
  /// `{finished_at, words_used, words_total}`, or null when the collection has never had one
  /// (204/404, or an empty body). Not `data`-wrapped, like the other practice endpoints.
  Future<Map<String, dynamic>?> lastDialog(String collectionId) async {
    try {
      final r = await _dio.get('/practice/collections/$collectionId/last-dialog');
      final data = r.data;
      return data is Map<String, dynamic> && data.isNotEmpty ? data : null;
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) return null; // no dialog yet
      rethrow;
    }
  }
}
