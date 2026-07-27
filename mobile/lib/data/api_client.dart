import 'package:dio/dio.dart';

import '../core/config.dart';
import 'models.dart';
import 'token_store.dart';

/// HTTP client for the Laravel backend. Attaches the Sanctum bearer token
/// (from [TokenStore]) to every request via an interceptor.
class ApiClient {
  ApiClient(TokenStore tokens) : _dio = _buildDio(tokens);

  final Dio _dio;

  static Dio _buildDio(TokenStore tokens) {
    final dio = Dio(BaseOptions(
      baseUrl: '${AppConfig.apiBaseUrl}/api',
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 40),
      headers: {
        'Accept': 'application/json',
        // Skip ngrok's browser interstitial for API calls.
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

  // ---- Auth -----------------------------------------------------------------

  /// Exchange a Google ID token for a Sanctum token + user.
  Future<({String token, AppUser user})> googleLogin(String idToken) async {
    final r = await _dio.post('/auth/google', data: {'id_token': idToken});
    final data = r.data as Map<String, dynamic>;
    return (
      token: data['token'] as String,
      user: AppUser.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  Future<AppUser> me() async {
    final r = await _dio.get('/auth/me');
    return AppUser.fromJson(r.data as Map<String, dynamic>);
  }

  Future<void> logout() async {
    await _dio.post('/auth/logout');
  }

  // ---- Profile --------------------------------------------------------------

  Future<Profile> updateProfile(Map<String, dynamic> changes) async {
    final r = await _dio.put('/profile', data: changes);
    return Profile.fromJson(r.data as Map<String, dynamic>);
  }

  // ---- Collections ----------------------------------------------------------

  Future<List<WordCollection>> collections() async {
    final r = await _dio.get('/collections');
    return (r.data as List)
        .map((e) => WordCollection.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<Word>> collectionWords(int collectionId) async {
    final r = await _dio.get('/collections/$collectionId');
    return ((r.data as Map<String, dynamic>)['words'] as List)
        .map((e) => Word.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<WordCollection> createCollection({required String title, String? emoji}) async {
    final r = await _dio.post('/collections', data: {'title': title, 'emoji': ?emoji});
    return WordCollection.fromJson(r.data as Map<String, dynamic>);
  }

  Future<WordCollection> updateCollection(int id, {String? title, String? emoji}) async {
    final r = await _dio.put('/collections/$id', data: {'title': ?title, 'emoji': ?emoji});
    return WordCollection.fromJson(r.data as Map<String, dynamic>);
  }

  Future<void> deleteCollection(int id) async {
    await _dio.delete('/collections/$id');
  }

  Future<Word> addWord(int collectionId, Map<String, dynamic> data) async {
    final r = await _dio.post('/collections/$collectionId/words', data: data);
    return Word.fromJson(r.data as Map<String, dynamic>);
  }

  Future<Word> updateWord(int collectionId, int wordId, Map<String, dynamic> data) async {
    final r = await _dio.put('/collections/$collectionId/words/$wordId', data: data);
    return Word.fromJson(r.data as Map<String, dynamic>);
  }

  Future<void> deleteWord(int collectionId, int wordId) async {
    await _dio.delete('/collections/$collectionId/words/$wordId');
  }

  // ---- Training -------------------------------------------------------------

  Future<List<ReviewCard>> dueCards({int limit = 40, int? collectionId, bool shuffle = false}) async {
    final r = await _dio.get('/reviews/due', queryParameters: {
      'limit': limit,
      'collection_id': ?collectionId,
      if (shuffle) 'shuffle': 1,
    });
    return (r.data as List)
        .map((e) => ReviewCard.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Stats> stats() async {
    final r = await _dio.get('/stats');
    return Stats.fromJson(r.data as Map<String, dynamic>);
  }

  Future<void> answer(int wordId, Rating rating) async {
    await _dio.post('/reviews/$wordId/answer', data: {'rating': rating.value});
  }

  // ---- AI -------------------------------------------------------------------

  /// Kicks off async generation; returns the job id to poll.
  Future<String> generateCollection({
    required String topic,
    required List<String> levels,
    required int size,
  }) async {
    final r = await _dio.post('/collections/generate',
        data: {'topic': topic, 'levels': levels, 'size': size});
    return (r.data as Map<String, dynamic>)['job_id'] as String;
  }

  /// Poll an AI job: returns (status, collectionId, error).
  Future<({String status, int? collectionId, String? error})> jobStatus(String jobId) async {
    final r = await _dio.get('/ai/jobs/$jobId');
    final data = r.data as Map<String, dynamic>;
    return (
      status: data['status'] as String,
      collectionId: data['collection_id'] as int?,
      error: data['error'] as String?,
    );
  }

  Future<AiCheckResult> check({
    required int wordId,
    required String userAnswer,
    String mode = 'translation',
  }) async {
    final r = await _dio.post('/ai/check',
        data: {'word_id': wordId, 'user_answer': userAnswer, 'mode': mode});
    return AiCheckResult.fromJson(r.data as Map<String, dynamic>);
  }
}
