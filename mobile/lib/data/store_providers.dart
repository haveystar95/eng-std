import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'models.dart';
import 'providers.dart';

/// A language pair the store is browsed for (source → target). Records compare by value, so this is
/// a stable family key.
typedef StoreLangPair = ({String source, String target});

/// The pair the store is currently showing. Seeded from the profile (native → target); the
/// language-pair row (кадр 2.8) overrides the target. Read-only screens never touch it.
class StoreLangPairNotifier extends Notifier<StoreLangPair?> {
  @override
  StoreLangPair? build() {
    final profile = ref.watch(authControllerProvider).value?.profile;
    if (profile == null) return null;
    return (source: profile.nativeLanguage, target: profile.targetLanguage);
  }

  void setPair(StoreLangPair pair) => state = pair;
}

final storeLangPairProvider =
    NotifierProvider<StoreLangPairNotifier, StoreLangPair?>(StoreLangPairNotifier.new);

/// One store section — a topic header + its cards, in the server's order.
class StoreSection {
  const StoreSection({required this.topic, required this.items});

  /// Section key the client groups by; null → an "other" bucket the screen labels locally.
  final String? topic;
  final List<StoreCollection> items;
}

/// The store catalogue for one language pair, grouped into [StoreSection]s by `topic` (the feed is
/// already ordered by topic, so grouping preserves that order). Reads `GET /store/collections`.
/// Falls back to a small built-in mock catalogue when the feed is empty AND [AppConfig.storeMockFallback]
/// is set — so the store UI is exercisable on device before Session B publishes real content.
final storeCollectionsProvider =
    FutureProvider.family<List<StoreSection>, StoreLangPair>((ref, pair) async {
  final api = ref.watch(apiClientProvider);
  final page = await api.storeCollections(
    sourceLang: pair.source,
    targetLang: pair.target,
    limit: 100,
  );
  var items = page.items;
  if (items.isEmpty && AppConfig.storeMockFallback) {
    items = _mockStore(pair);
  }
  return groupByTopic(items);
});

/// Groups an ordered store feed into sections, preserving the first-seen topic order.
List<StoreSection> groupByTopic(List<StoreCollection> items) {
  final order = <String?>[];
  final byTopic = <String?, List<StoreCollection>>{};
  for (final c in items) {
    final t = c.topic;
    if (!byTopic.containsKey(t)) {
      order.add(t);
      byTopic[t] = [];
    }
    byTopic[t]!.add(c);
  }
  return [for (final t in order) StoreSection(topic: t, items: byTopic[t]!)];
}

/// Subscribe/unsubscribe outcome, so the caller can react (refresh, paywall, error).
enum StoreSubscribeResult { subscribed, unsubscribed, subscriptionRequired, error }

/// Subscribes to a store collection (adds it to «Мои»). On success it triggers a sync so the set
/// flows into the local mirror, and invalidates the store list so the card flips to «В моих». A
/// premium collection on a free tier returns [StoreSubscribeResult.subscriptionRequired] (the server
/// 403 `subscription_required`) — the caller routes to the paywall.
Future<StoreSubscribeResult> subscribeToStore(WidgetRef ref, StoreCollection c) =>
    _mutate(ref, c, subscribe: true);

Future<StoreSubscribeResult> unsubscribeFromStore(WidgetRef ref, StoreCollection c) =>
    _mutate(ref, c, subscribe: false);

Future<StoreSubscribeResult> _mutate(WidgetRef ref, StoreCollection c, {required bool subscribe}) async {
  final api = ref.read(apiClientProvider);
  try {
    if (subscribe) {
      await api.subscribeStore(c.id);
    } else {
      await api.unsubscribeStore(c.id);
    }
    // Pull the new library membership into the local mirror, then refresh the store cards.
    await ref.read(syncServiceProvider).sync();
    ref.invalidate(storeCollectionsProvider);
    return subscribe ? StoreSubscribeResult.subscribed : StoreSubscribeResult.unsubscribed;
  } catch (e) {
    if (_isSubscriptionRequired(e)) return StoreSubscribeResult.subscriptionRequired;
    return StoreSubscribeResult.error;
  }
}

bool _isSubscriptionRequired(Object e) {
  // A DioException surfaces `response.statusCode`; a 403 from the subscribe route is the
  // `subscription_required` gate (the only 403 the contract defines there). Kept string-based so
  // this file doesn't depend on the dio type.
  final s = e.toString();
  return s.contains('403');
}

/// A tiny mock catalogue (dev only, [AppConfig.storeMockFallback]) mirroring кадр 2.8's shape — two
/// sections, a mix of free and premium sets — so the store screen shows cards on device before
/// Session B publishes real content. Titles/topics are ASCII dev placeholders on purpose (the
/// cyrillic-guard forbids Russian literals in `lib/`; real content is Russian and comes from the
/// server). Deterministic ids so re-fetches are stable.
List<StoreCollection> _mockStore(StoreLangPair pair) {
  StoreCollection mk(String id, String title, String topic, int n, bool premium,
          {bool subscribed = false}) =>
      StoreCollection(
        id: 'mock_$id',
        title: title,
        topic: topic,
        sourceLang: pair.source,
        targetLang: pair.target,
        isPremium: premium,
        isSubscribed: subscribed,
        itemsCount: n,
      );
  return [
    mk('cafe', 'Cafe', 'Everyday', 16, false),
    mk('market', 'Market', 'Everyday', 20, false),
    mk('rent', 'Renting', 'Everyday', 18, false, subscribed: true),
    mk('interview', 'Job interview', 'Work', 22, true),
    mk('office', 'Office calls', 'Work', 19, true),
    mk('firstday', 'First day', 'Work', 14, false),
  ];
}
