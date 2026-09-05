import 'dart:io' show SocketException;
import 'dart:math';

import 'package:dio/dio.dart';

import '../features/search/search_pair.dart' show SearchLanguages;
import 'config.dart';
import 'exposure_sync.dart';
import 'models.dart';
import 'plan_models.dart';
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

/// A write refused because the word is not of the collection's pair — 422 `term_language_mismatch`,
/// with `meta.expected_lang` (what the collection studies) and `meta.actual_lang` (the word's).
///
/// «Одна коллекция — одна пара» is a DOMAIN rule and the server is where it is enforced (DECISIONS
/// п. 141), so the client reads the refusal rather than predicting it. The sheet filters its list by
/// pair, which means this normally cannot happen — but «normally» here means «unless the list was
/// built from a mirror the server has since moved past», which is a race, not an impossibility.
/// Returns null for every other failure, including the offline ones, so a caller can tell «the pair
/// is wrong» from «the save did not happen».
({String expected, String actual})? termLanguageMismatch(Object? error) {
  if (error is! DioException || problemCode(error) != 'term_language_mismatch') return null;
  final meta = (error.response?.data as Map?)?['meta'];
  if (meta is! Map) return null;
  final expected = meta['expected_lang'], actual = meta['actual_lang'];

  return (expected is String && actual is String) ? (expected: expected, actual: actual) : null;
}

/// The request never reached the server: no network, a dead tunnel, a timeout. Worth telling the
/// user plainly («нет соединения») instead of showing them a stack of Dio internals — and worth
/// distinguishing from a server that answered but answered badly.
bool isOffline(Object? error) {
  if (error is! DioException) return false;
  return switch (error.type) {
    DioExceptionType.connectionError ||
    DioExceptionType.connectionTimeout ||
    DioExceptionType.sendTimeout ||
    DioExceptionType.receiveTimeout => true,
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
    final dio = Dio(
      BaseOptions(
        baseUrl: '${AppConfig.apiBaseUrl}/api/v1',
        connectTimeout: const Duration(seconds: 20),
        receiveTimeout: const Duration(seconds: 40),
        headers: {'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true'},
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          final token = tokens.current;
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );

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
    final r = await _dio.post(
      '/auth/dev',
      data: {'email': email, 'device_name': 'simulator', 'timezone': ?timezone},
    );
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
    final r = await _dio.post(
      '/auth/apple',
      data: {'identity_token': identityToken, 'name': ?name},
    );
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
    final r = await _dio.post(
      '/collections',
      data: {'title': title, 'source_lang': ?sourceLang, 'target_lang': ?targetLang},
    );
    return WordCollection.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<WordCollection> updateCollection(String id, {String? title, String? description}) async {
    final r = await _dio.patch(
      '/collections/$id',
      data: {'title': ?title, 'description': ?description},
    );
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
    await _dio.post(
      '/collections/$collectionId/items',
      data: {'text': text, 'translation': ?(t == null || t.isEmpty ? null : t), 'type': type},
    );
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
    String? taughtSide,
  }) async {
    final r = await _dio.get(
      '/search',
      queryParameters: {
        'q': query,
        'limit': limit,
        'source': ?source,
        'target': ?target,
        // Which half of the pair is the language being STUDIED. The pill knows; the server would
        // otherwise guess from the profile, which is only right while somebody studies one
        // language (DECISIONS п. 147).
        'taught_side': ?taughtSide,
      },
    );
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
  Future<InstantHint> instantHint(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
  }) async {
    final r = await _dio.get(
      '/search/instant',
      queryParameters: {
        'q': query,
        'source': ?source,
        'target': ?target,
        'taught_side': ?taughtSide,
      },
    );
    return InstantHint.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// The PAID half: one cheap model call for a word the database does not have. Never fired by a
  /// debounce — the learner taps «Найти с ИИ», because this spends money.
  ///
  /// A spent daily cap is a normal 200 with [LookupOutcome.limitReached], not an error: the screen
  /// shows the free results beside an honest line. A cached word is served free and does not touch
  /// the cap at all.
  /// [retry] — the learner pressed «Собрать карточку» AGAIN on a query the model just refused.
  /// It makes the server ignore the stored «это не слово» instead of re-serving it: a person
  /// tapping twice IS the retry, and the 24-hour expiry behind it is for the paths nobody watches.
  Future<LookupOutcome> lookupWord(
    String query, {
    String? source,
    String? target,
    String? taughtSide,
    bool retry = false,
  }) async {
    final r = await _dio.post(
      '/search/lookup',
      data: {
        'query': query,
        'source': ?source,
        'target': ?target,
        'taught_side': ?taughtSide,
        if (retry) 'retry': true,
      },
    );
    return LookupOutcome.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Save a search result: term → folder → and the QUEUE only if [enroll] says so.
  /// [collectionId] null means «Сохранённые».
  ///
  /// Exactly one of [lookupId] / [termId]: the first saves a freshly looked-up word, the second one
  /// the database already had. Idempotent — `added`/`enrolled` say what actually happened.
  ///
  /// [enroll] is the ACT, and it is required here rather than defaulted: the shelf and the queue are
  /// separate (полка ≠ очередь), the two buttons in the translator mean two different things, and a
  /// default would let a new call site pick one of them by accident. The SERVER still defaults an
  /// absent field to `true` for the build already on a phone; this client always says which.
  Future<SavedSearchResult> addSearchResult({
    String? lookupId,
    String? termId,
    String? collectionId,
    required bool enroll,
  }) async {
    final r = await _dio.post(
      '/search/add',
      data: {
        'lookup_id': ?lookupId,
        'term_id': ?termId,
        'collection_id': ?collectionId,
        'enroll': enroll,
      },
    );
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
    final r = await _dio.get(
      '/store/collections',
      queryParameters: {
        'source_lang': ?sourceLang,
        'target_lang': ?targetLang,
        'cursor': ?cursor,
        'limit': limit,
      },
    );
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
    final r = await _dio.get(
      '/study/due',
      queryParameters: {'limit': limit, 'collection_id': ?collectionId},
    );
    return (_data(r) as List).map((e) => ReviewCard.fromJson(e as Map<String, dynamic>)).toList();
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
    final r = await _dio.post(
      '/study/sessions',
      data: {
        'session_id': sessionId,
        'collection_id': ?collectionId,
        'practice': practice,
        'limit': limit,
      },
    );
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

  /// The home screen's whole day (`GET /home-plan`, кадры 17a–17d): what today's session is made
  /// of, how big the pool is and when its queue moves, what is about to fall out of memory, what
  /// today produced, and the one collection that was started and left.
  ///
  /// Returned as the RAW map as well as the parsed plan: the sync service caches the map verbatim
  /// in the local DB, and the screen reads it back from there like every other screen in this app.
  Future<({HomePlan plan, Map<String, dynamic> raw})> homePlan() async {
    final r = await _dio.get('/home-plan');
    final raw = _data(r) as Map<String, dynamic>;
    return (plan: HomePlan.fromJson(raw), raw: raw);
  }

  // ---- Learning plans (PLAN-1c) --------------------------------------------
  //
  // The plan is the ONE surface here that is not read out of the local mirror. Its two headline
  // numbers — готовность and the focus day — are derived server-side from the review log on every
  // read, so a cached copy would be a second, slower opinion about where the learner is. The screens
  // therefore call these directly and say «нет сети» when there is none.

  /// The plan the learner is on, or null when there is none (the server answers 204).
  Future<LearningPlan?> activePlan() async {
    final r = await _dio.get('/plans/active');
    if (r.statusCode == 204 || r.data == null) return null;

    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Every plan the learner has run, newest first — the finished one and the archive.
  Future<List<PlanSummary>> plans() async {
    final r = await _dio.get('/plans');
    return (_data(r) as List)
        .map((e) => PlanSummary.fromJson(e as Map<String, dynamic>))
        .toList(growable: false);
  }

  Future<LearningPlan> plan(String planId) async {
    final r = await _dio.get('/plans/$planId');
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// A DRAFT. Free — no model call, no day, no word held. The support language is not sent: it is
  /// the account's own (ONB-1) and the server reads it off the profile.
  Future<LearningPlan> createPlan({
    required String goalText,
    required String targetLang,
    required String level,
    required String? eventDate,
    required int minutesPerDay,
    List<ListenAnswer> listening = const [],
  }) async {
    final r = await _dio.post(
      '/plans',
      data: {
        'goal_text': goalText,
        'target_lang': targetLang,
        'level': level,
        // ALWAYS SENT, and null is «Без даты» (кадр V4·04б). The server refuses a create with the
        // key missing on purpose — an app that simply forgot the date must not produce an undated
        // plan — so this is a plain assignment and never a `?:` conditional key.
        'event_date': eventDate,
        'minutes_per_day': minutesPerDay,
        // What the learner tapped on the listening step, line by line. The VERDICT is not sent: the
        // server derives «упор на понимание / на говорение» from these rows, so the balance the
        // plan is built on cannot disagree with the answers the learner gave.
        if (listening.isNotEmpty)
          'listening': listening.map((a) => a.toJson()).toList(growable: false),
      },
    );
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// The entry's two optional blocks, from one call — «Дописать за тебя» and «Послушать».
  ///
  /// WITHOUT [targetLang] it is the goal step (кадр V4·01в), fired on a typing pause: the language
  /// has not been chosen, and the answer is the two continuations alone. WITH it, right after the
  /// level is chosen, the answer holds the three lines too (кадр V4·03).
  ///
  /// Called BEFORE any plan exists. An EMPTY answer is legitimate and means the block is not shown:
  /// the server answers 200 with nothing when it could not be written, and this client turns a
  /// network failure into the same emptiness for the same reason — both blocks are optional, and a
  /// person who never asked for one must not be shown an error about it.
  ///
  /// It waits on a model, so it states its own timeout like [buildPlanOutline] does.
  Future<ListenWarmup> listenWarmup({
    required String goalText,
    String targetLang = '',
    required String level,
  }) async {
    try {
      final r = await _dio.post(
        '/plans/listen-warmup',
        data: {
          'goal_text': goalText,
          // OMITTED, not sent empty: on the goal step the language has not been chosen, and the
          // absence of the key is what asks for the continuations alone.
          if (targetLang.isNotEmpty) 'target_lang': targetLang,
          'level': level,
        },
        options: Options(receiveTimeout: const Duration(minutes: 2)),
      );

      return ListenWarmup.fromJson(_data(r) as Map<String, dynamic>);
    } catch (_) {
      return const ListenWarmup();
    }
  }

  /// P1 + the scheduler: the SKELETON the learner reads before committing. One model call, and
  /// re-running it rebuilds the skeleton — which is why it is a POST.
  ///
  /// THE ONE CALL IN THIS CLIENT THAT WAITS ON A MODEL IN-BAND. Everything else that costs a model
  /// call is queued and polled; this one answers with the outline itself, because the learner is
  /// looking at a spinner and the next screen IS the answer. The default 40-second receive timeout
  /// is a limit for ordinary endpoints and far too short for a live `gpt-5.4` completion, so this
  /// one states its own.
  Future<LearningPlan> buildPlanOutline(String planId) async {
    final r = await _dio.post(
      '/plans/$planId/outline',
      options: Options(receiveTimeout: const Duration(minutes: 3)),
    );
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// «Добавить 20 минут в день» / «Убрать день» / a new date — the adjustment step, which costs
  /// nothing because the scheduler is a pure function of the outline the model already produced.
  Future<LearningPlan> reschedulePlan(
    String planId, {
    int? minutesPerDay,
    String? eventDate,
    int? dropDayIndex,
  }) async {
    final r = await _dio.patch(
      '/plans/$planId/outline',
      data: {
        'minutes_per_day': ?minutesPerDay,
        'event_date': ?eventDate,
        'drop_day_index': ?dropDayIndex,
      },
    );
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// THE COMMITMENT: days start generating and words start being held.
  Future<LearningPlan> startPlan(String planId) async {
    final r = await _dio.post('/plans/$planId/start');
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<LearningPlan> pausePlan(String planId) async {
    final r = await _dio.post('/plans/$planId/pause');
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  Future<LearningPlan> abandonPlan(String planId) async {
    final r = await _dio.post('/plans/$planId/abandon');
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// «ПОДГОТОВКА ЗАВЕРШЕНА» — the plan run to its end (Д-27).
  ///
  /// Called when the final day's rehearsal is finished. A 409 means the plan is ALREADY ended — a
  /// second tap, or a retry after the response was lost — and that is a success from here: the run
  /// this reports really did happen, and the state it asks for is the state the server is in.
  Future<LearningPlan> completePlan(String planId) async {
    try {
      final r = await _dio.post('/plans/$planId/complete');
      return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
    } on DioException catch (e) {
      if (e.response?.statusCode != 409) rethrow;
      return plan(planId);
    }
  }

  /// One day with its terms and their stages.
  Future<PlanDayDetail> planDay(String planId, int dayIndex) async {
    final r = await _dio.get('/plans/$planId/days/$dayIndex');
    return PlanDayDetail.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// «Собери мне день n» — idempotent and safe to poll: it answers with the day's own status, so
  /// the «собираю день n» screen calls it to start the work and calls it again to learn whether it
  /// finished. A 409 `plan_day_capped` means the plan's own spending ceiling says «не сейчас».
  Future<PlanDayStatus> generatePlanDay(String planId, int dayIndex) async {
    final r = await _dio.post('/plans/$planId/days/$dayIndex/generate');
    return PlanDayStatus.fromWire((_data(r) as Map<String, dynamic>)['status'] as String?);
  }

  /// «Собрать заново» — one more attempt for a day that burned.
  ///
  /// A separate endpoint from [generatePlanDay] because that one is POLLED: a screen asking «is it
  /// ready yet» every second must never be able to buy a model call. This one is a button, it
  /// spends money, and it is pressed once. A day that is not `failed` is answered with the status
  /// it already has, so a double tap costs nothing.
  Future<PlanDayStatus> rebuildPlanDay(String planId, int dayIndex) async {
    final r = await _dio.post('/plans/$planId/days/$dayIndex/rebuild');
    return PlanDayStatus.fromWire((_data(r) as Map<String, dynamic>)['status'] as String?);
  }

  /// The session of ONE day. [dayIndex] null asks for the day the learner is ON — the server owns
  /// the focus, so it must be possible to ask for it without recomputing it on the device.
  Future<PlanSession> buildPlanSession({
    required String planId,
    required String sessionId,
    int? dayIndex,
  }) async {
    final path = dayIndex == null
        ? '/plans/$planId/session'
        : '/plans/$planId/days/$dayIndex/session';
    final r = await _dio.post(path, data: {'session_id': sessionId});

    return PlanSession.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// The morning of the event: the plan's phrases and nothing else (кадр 15).
  Future<PlanRehearsal> planRehearsal(String planId) async {
    final r = await _dio.post('/plans/$planId/rehearsal');
    return PlanRehearsal.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// ПРОГОН СЦЕНЫ ЗАВЕРШЁН — ходы с исходами, и ни одного посчитанного числа (наряд SCENE-RUN).
  ///
  /// «Прошёл сам 2 из 4 · сразу 1» считает сервер: это то, что человеку показывают и что потом
  /// читает зрелость сцены, и число, посчитанное на телефоне, было бы вторым источником правды о
  /// том, чего он добился.
  ///
  /// Ответы каждого хода уже уехали обычной очередью ревью — сказал это `speaking/good`, пропустил
  /// `speaking/again`. Здесь их нет: append-only журнал не должен получить один ответ дважды.
  /// ДЕВ-ДВЕРЬ СМЕНЫ ДНЕЙ (наряд DAY-FIX-2): сдвинуть «сегодня» QA-аккаунта на [days] дней.
  ///
  /// Дверь стережёт сервер — та же, что у входа без пароля и подстановки транскрипта: всем, кому
  /// она закрыта, это 404. Клиент своей проверки не держит ({@see AppUser.qaTools}).
  Future<int> setQaPlanClock(int days) async {
    final r = await _dio.post('/qa/plan-clock', data: {'days': days});
    return ((_data(r) as Map<String, dynamic>)['days'] as num?)?.toInt() ?? 0;
  }

  Future<int> qaPlanClock() async {
    final r = await _dio.get('/qa/plan-clock');
    return ((_data(r) as Map<String, dynamic>)['days'] as num?)?.toInt() ?? 0;
  }

  Future<void> recordSceneRun({
    required String planId,
    required int sceneIndex,
    required int dayIndex,
    required List<({String termId, String outcome})> turns,
  }) async {
    await _dio.post(
      '/plans/$planId/scene-runs',
      data: {
        'scene_index': sceneIndex,
        'day_index': dayIndex,
        'turns': [
          for (final turn in turns) {'term_id': turn.termId, 'outcome': turn.outcome},
        ],
      },
    );
  }

  /// «Как прошло?» — the checkpoints the learner ticked by hand after the event.
  ///
  /// It CLOSES the plan, which is why it answers with the whole plan rather than with a receipt:
  /// the screen that called it is about to become the finished-plan screen, and the completed plan
  /// is what that screen is drawn from.
  Future<LearningPlan> submitPlanFeedback(String planId, List<int> hitIndexes) async {
    final r = await _dio.post('/plans/$planId/feedback', data: {'checkpoints': hitIndexes});
    return LearningPlan.fromJson(_data(r) as Map<String, dynamic>);
  }

  /// Upload a batch of graded answers (idempotent by each review's client ULID).
  /// Returns backend2's tally so the caller can reconcile the local queue.
  Future<({int accepted, int duplicates, int unknown})> submitReviews(
    List<PendingReview> reviews,
  ) async {
    final r = await _dio.post(
      '/reviews/batch',
      data: {'reviews': reviews.map((e) => e.toBatchJson()).toList()},
    );
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
    final r = await _dio.post(
      '/reviews/batch',
      data: {'exposures': exposures.map((e) => e.toBatchJson()).toList()},
    );
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
  Future<({int accepted, int duplicates, int unknown})> submitTriages(
    List<PendingTriage> triages,
  ) async {
    final r = await _dio.post(
      '/triage/batch',
      data: {'triages': triages.map((e) => e.toBatchJson()).toList()},
    );
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
    final r = await _dio.get(
      '/sync',
      queryParameters: {'since': ?since, 'cursor': ?cursor, 'limit': limit},
    );
    return _data(r) as Map<String, dynamic>;
  }

  /// The server's client_seq high-water mark per log. Read on login to seed the
  /// local monotonic counter so a reinstall or a fresh device can't emit sequences
  /// that would lose to already-stored rows.
  Future<({int triage, int review})> syncCursor() async {
    final r = await _dio.get('/sync/cursor');
    final d = _data(r) as Map<String, dynamic>;
    return (triage: (d['max_triage_seq'] as int?) ?? 0, review: (d['max_review_seq'] as int?) ?? 0);
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
    final r = await _dio.post(
      '/generations',
      data: {
        'id': ?id,
        'prompt': topic,
        'levels': levels,
        'size': size,
        'source_lang': ?sourceLang,
        'target_lang': ?targetLang,
      },
    );
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
  Future<Map<String, dynamic>> startDialog({
    required String collectionId,
    required String clientId,
  }) async {
    final r = await _dio.post(
      '/practice/dialogs',
      data: {'collection_id': collectionId, 'client_id': clientId},
    );
    return r.data as Map<String, dynamic>;
  }

  /// Upload a batch of transcript events: `POST /practice/dialogs/{id}/transcripts`. Idempotent by
  /// (role, ts). Returns the updated `target_words` (with the server's monotonic `used` flags).
  Future<List<dynamic>> sendDialogTranscripts(
    String dialogId,
    List<Map<String, dynamic>> events,
  ) async {
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
