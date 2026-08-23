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

final storeLangPairProvider = NotifierProvider<StoreLangPairNotifier, StoreLangPair?>(
  StoreLangPairNotifier.new,
);

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
final storeCollectionsProvider = FutureProvider.family<List<StoreSection>, StoreLangPair>((
  ref,
  pair,
) async {
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

/// The preview (first terms + total) for one store collection's sheet (кадры 8c/8d). Network-backed;
/// consumers read via `.when` so ANY non-success degrades to «no list» rather than surfacing.
/// Capped at ~8s so a missing endpoint (404), a hang, or a slow tunnel fails fast and collapses the
/// skeleton instead of lingering until the default receive timeout.
final storePreviewProvider = FutureProvider.family<StorePreview, String>((ref, id) async {
  return ref.watch(apiClientProvider).storePreview(id).timeout(const Duration(seconds: 8));
});

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

/// Unsubscribe a store set from «Мои» by collection id (the collection screen / list menu). Drops
/// the local row optimistically (the delta feed's collection tombstone is unreliable — same reason
/// as delete), then resync to reconcile. Returns false if the server call failed (keep the row).
Future<bool> unsubscribeCollectionById(WidgetRef ref, String id) async {
  try {
    await ref.read(apiClientProvider).unsubscribeStore(id);
  } catch (_) {
    return false;
  }
  await ref.read(appDatabaseProvider).deleteCollectionLocal(id);
  try {
    await ref.read(syncServiceProvider).resync();
  } catch (_) {
    /* offline/transient — next sync reconciles */
  }
  ref.invalidate(storeCollectionsProvider);
  return true;
}

Future<StoreSubscribeResult> _mutate(
  WidgetRef ref,
  StoreCollection c, {
  required bool subscribe,
}) async {
  final api = ref.read(apiClientProvider);
  // 1) The subscribe/unsubscribe itself — its success is what the result reports. A failure of the
  //    follow-up sync must NOT read back as "add failed".
  try {
    if (subscribe) {
      await api.subscribeStore(c.id);
    } else {
      await api.unsubscribeStore(c.id);
    }
  } catch (e) {
    if (_isSubscriptionRequired(e)) return StoreSubscribeResult.subscriptionRequired;
    return StoreSubscribeResult.error;
  }
  // 2) Pull the new library membership into the local mirror. A newly-subscribed store collection is
  //    NOT owned and its `updated_at` predates the sync cursor, so the incremental delta (`since`)
  //    can't carry it — force a FULL snapshot via resync(). (Requires the backend sync feed to
  //    include subscribed collections; today it filters to owner_id only — see the tracked chip.)
  try {
    await ref.read(syncServiceProvider).resync();
  } catch (_) {
    /* offline/transient — the next background sync reconciles */
  }
  ref.invalidate(storeCollectionsProvider);
  return subscribe ? StoreSubscribeResult.subscribed : StoreSubscribeResult.unsubscribed;
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
  StoreCollection mk(
    String id,
    String title,
    String topic,
    int n,
    String cefr,
    bool premium, {
    bool subscribed = false,
  }) => StoreCollection(
    id: 'mock_$id',
    title: title,
    description: 'Sample store set for UI checks',
    topic: topic,
    sourceLang: pair.source,
    targetLang: pair.target,
    isPremium: premium,
    isSubscribed: subscribed,
    itemsCount: n,
    cefr: cefr,
  );
  return [
    mk('cafe', 'Cafe', 'Everyday', 16, 'A2', false),
    mk('market', 'Market', 'Everyday', 20, 'A2–B1', false),
    mk('rent', 'Renting', 'Everyday', 18, 'B1', false, subscribed: true),
    mk('interview', 'Job interview', 'Work', 22, 'B1–B2', true),
    mk('office', 'Office calls', 'Work', 19, 'B1', true),
    mk('firstday', 'First day', 'Work', 14, 'A2', false),
  ];
}
