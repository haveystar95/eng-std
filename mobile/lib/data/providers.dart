import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'api_client.dart';
import 'auth_repository.dart';
import 'models.dart';
import 'token_store.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

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
}

final authControllerProvider =
    AsyncNotifierProvider<AuthController, AppUser?>(AuthController.new);

// ---- Data providers ---------------------------------------------------------

final collectionsProvider = FutureProvider<List<WordCollection>>((ref) async {
  return ref.watch(apiClientProvider).collections();
});

final collectionWordsProvider =
    FutureProvider.family<List<Word>, int>((ref, collectionId) async {
  return ref.watch(apiClientProvider).collectionWords(collectionId);
});

final dueCardsProvider = FutureProvider<List<ReviewCard>>((ref) async {
  return ref.watch(apiClientProvider).dueCards();
});

final statsProvider = FutureProvider<Stats>((ref) async {
  return ref.watch(apiClientProvider).stats();
});

typedef SessionArgs = ({int? collectionId, bool shuffle});

/// Cards for one training session (mixed due, or a specific collection).
final sessionCardsProvider =
    FutureProvider.family<List<ReviewCard>, SessionArgs>((ref, args) async {
  return ref.watch(apiClientProvider).dueCards(
        collectionId: args.collectionId,
        shuffle: args.shuffle,
      );
});
