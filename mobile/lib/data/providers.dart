import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth_repository.dart';
import 'models.dart';
import 'review_queue.dart';
import 'review_sync.dart';
import 'token_store.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

final reviewQueueProvider = Provider<ReviewQueue>((ref) => ReviewQueue());

/// Offline-first review upload pipeline (record locally → batch flush).
final reviewSyncProvider = Provider<ReviewSync>((ref) {
  return ReviewSync(ref.watch(apiClientProvider), ref.watch(reviewQueueProvider), ref);
});

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(ref.watch(tokenStoreProvider));
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider), ref.watch(tokenStoreProvider));
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
    state = const AsyncData(null);
  }

  /// Persist profile changes and refresh the in-memory user.
  Future<void> updateProfile(Map<String, dynamic> changes) async {
    final user = await ref.read(apiClientProvider).updateProfile(changes);
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

// ---- Data providers ---------------------------------------------------------

final collectionsProvider = FutureProvider<List<WordCollection>>((ref) async {
  return ref.watch(apiClientProvider).collections();
});

final collectionWordsProvider =
    FutureProvider.family<List<Word>, String>((ref, collectionId) async {
  return ref.watch(apiClientProvider).collectionWords(collectionId);
});

final dueCardsProvider = FutureProvider<List<ReviewCard>>((ref) async {
  return ref.watch(apiClientProvider).dueCards();
});

final statsProvider = FutureProvider<Stats>((ref) async {
  return ref.watch(apiClientProvider).stats();
});

/// Derived per-collection progress (total/learned/mastered/due), keyed by collection id.
final collectionsProgressProvider = FutureProvider<Map<String, CollectionProgress>>((ref) async {
  return ref.watch(apiClientProvider).collectionsProgress();
});

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
