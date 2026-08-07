import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'config.dart';
import 'providers.dart';

/// Runtime feature flags for the A3.9 store + paywall block.
///
/// Each flag's default is its compile-time [AppConfig] value (off in release); a
/// stored dev-menu override (drift `SyncMeta`, never synced) wins when present, so
/// Den can flip a surface on device without a rebuild. [devPremium] is the fake
/// "purchase" the dev paywall grants — a client-only premium that unlocks the
/// gated surfaces for testing; the server tier (`/me`) remains the real gate for
/// anything that hits the API (e.g. subscribing to a premium store collection).
class FeatureFlags {
  const FeatureFlags({
    required this.storeEnabled,
    required this.paywallEnabled,
    required this.devPremium,
  });

  final bool storeEnabled;
  final bool paywallEnabled;
  final bool devPremium;

  /// Compile-time defaults — the value before the stored overrides load, and the
  /// release baseline (store/paywall off, no fake premium).
  static const fromConfig = FeatureFlags(
    storeEnabled: AppConfig.storeEnabled,
    paywallEnabled: AppConfig.paywallEnabled,
    devPremium: false,
  );

  FeatureFlags copyWith({bool? storeEnabled, bool? paywallEnabled, bool? devPremium}) =>
      FeatureFlags(
        storeEnabled: storeEnabled ?? this.storeEnabled,
        paywallEnabled: paywallEnabled ?? this.paywallEnabled,
        devPremium: devPremium ?? this.devPremium,
      );
}

abstract final class _Keys {
  static const store = 'ff_store';
  static const paywall = 'ff_paywall';
  static const devPremium = 'ff_dev_premium';
}

/// Reads/persists the runtime flags. Returns the compile-time defaults **synchronously** so gates can
/// read them the instant the app starts (no AsyncLoading window that would fall back to the wrong
/// default), then patches in the stored dev-menu overrides once the local DB read completes. A
/// missing key keeps the compile-time default, so `--dart-define` sets the baseline until overridden.
class FeatureFlagsController extends Notifier<FeatureFlags> {
  @override
  FeatureFlags build() {
    _load();
    return FeatureFlags.fromConfig;
  }

  Future<void> _load() async {
    final db = ref.read(appDatabaseProvider);
    final store = await db.getMeta(_Keys.store);
    final paywall = await db.getMeta(_Keys.paywall);
    final premium = await db.getMeta(_Keys.devPremium);
    state = FeatureFlags(
      storeEnabled: store == null ? AppConfig.storeEnabled : store == '1',
      paywallEnabled: paywall == null ? AppConfig.paywallEnabled : paywall == '1',
      devPremium: premium == '1',
    );
  }

  Future<void> setStoreEnabled(bool on) => _set(_Keys.store, on, (f) => f.copyWith(storeEnabled: on));
  Future<void> setPaywallEnabled(bool on) => _set(_Keys.paywall, on, (f) => f.copyWith(paywallEnabled: on));
  Future<void> setDevPremium(bool on) => _set(_Keys.devPremium, on, (f) => f.copyWith(devPremium: on));

  Future<void> _set(String key, bool on, FeatureFlags Function(FeatureFlags) update) async {
    await ref.read(appDatabaseProvider).setMeta(key, on ? '1' : '0');
    state = update(state);
  }
}

final featureFlagsProvider =
    NotifierProvider<FeatureFlagsController, FeatureFlags>(FeatureFlagsController.new);

/// Effective premium = the server tier (`/me` → `profile.tier`) OR the dev fake
/// purchase. Everything that GATES on premium in the UI (the profile subscription
/// row, the realtime-dialog entry, the store premium lock) reads this, so a dev
/// "purchase" lights the app up end-to-end without a real StoreKit transaction.
final premiumProvider = Provider<bool>((ref) {
  final serverPremium = ref.watch(authControllerProvider).value?.profile?.isPremium ?? false;
  final devPremium = ref.watch(featureFlagsProvider).devPremium;
  return serverPremium || devPremium;
});
