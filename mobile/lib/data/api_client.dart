import 'dart:math';

import 'package:dio/dio.dart';

import '../core/config.dart';
import 'models.dart';
import 'review_queue.dart';
import 'token_store.dart';
import 'triage_queue.dart';

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
  Future<({String token, AppUser user})> googleLogin(String idToken) async {
    final r = await _dio.post('/auth/google', data: {'id_token': idToken});
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

  // ---- Profile --------------------------------------------------------------

  Future<AppUser> updateProfile(Map<String, dynamic> changes) async {
    final r = await _dio.put('/profile', data: changes);
    return AppUser.fromJson(_data(r) as Map<String, dynamic>);
  }

  // ---- Collections ----------------------------------------------------------

  Future<List<WordCollection>> collections() async {
    final r = await _dio.get('/collections');
    return (_data(r) as List)
        .map((e) => WordCollection.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Word>> collectionWords(String collectionId) async {
    final r = await _dio.get('/collections/$collectionId');
    return (((_data(r) as Map<String, dynamic>)['items']) as List)
        .map((e) => Word.fromJson(e as Map<String, dynamic>))
        .toList();
  }

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
  Future<void> addWord(
    String collectionId, {
    required String text,
    required String translation,
    String type = 'word',
  }) async {
    await _dio.post('/collections/$collectionId/items',
        data: {'text': text, 'translation': translation, 'type': type});
  }

  Future<void> removeWord(String collectionId, String termId) async {
    await _dio.delete('/collections/$collectionId/items/$termId');
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

  /// Derived per-collection progress, keyed by collection id.
  Future<Map<String, CollectionProgress>> collectionsProgress() async {
    final r = await _dio.get('/study/progress');
    final map = <String, CollectionProgress>{};
    for (final e in _data(r) as List) {
      final p = CollectionProgress.fromJson(e as Map<String, dynamic>);
      map[p.collectionId] = p;
    }
    return map;
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

  // ---- Triage ---------------------------------------------------------------

  /// The first-pass swipe queue for one collection — its not-yet-triaged,
  /// not-yet-studied terms, self-contained so the whole stack swipes offline.
  Future<List<TriageCard>> triageQueue(String collectionId, {int limit = 40}) async {
    final r = await _dio.get('/triage/queue', queryParameters: {
      'collection_id': collectionId,
      'limit': limit,
    });
    return (_data(r) as List)
        .map((e) => TriageCard.fromJson(e as Map<String, dynamic>))
        .toList();
  }

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
  Future<String> generateCollection({
    required String topic,
    required List<String> levels,
    required int size,
    String? sourceLang,
    String? targetLang,
  }) async {
    final r = await _dio.post('/generations', data: {
      'prompt': topic,
      'levels': levels,
      'size': size,
      'source_lang': ?sourceLang,
      'target_lang': ?targetLang,
    });
    return (_data(r) as Map<String, dynamic>)['id'] as String;
  }

  /// Poll a generation request: returns (status, collectionId, error).
  Future<({String status, String? collectionId, String? error})> jobStatus(String id) async {
    final r = await _dio.get('/generations/$id');
    final data = _data(r) as Map<String, dynamic>;
    return (
      status: data['status'] as String,
      collectionId: data['collection_id'] as String?,
      error: data['error'] as String?,
    );
  }
}
