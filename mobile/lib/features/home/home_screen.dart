import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/local/sync_service.dart';
import '../../data/providers.dart';
import '../collections/collections_screen.dart';
import '../profile/profile_screen.dart';
import '../progress/progress_screen.dart';
import '../search/search_screen.dart';
import '../training/training_home_screen.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> with WidgetsBindingObserver {
  int _index = 0;
  StreamSubscription<List<ConnectivityResult>>? _connSub;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Pull the delta feed into the local DB, and drain any answers left over from a
      // previous (possibly offline) session. Both are background; neither blocks the UI.
      ref.read(syncServiceProvider).sync();
      // Import the legacy Keychain queue once (F20-r2), THEN drain — otherwise the first flush of a
      // freshly-updated install would miss answers still sitting in the old store.
      ref.read(reviewSyncProvider).migrate().then((_) => ref.read(reviewSyncProvider).flush());
      // …and any session closings left over from an offline run (QA-12).
      ref.read(sessionCompletionSyncProvider).flush();
      // …and the pool decisions («Учить это слово» / «Убрать из изучения») made while offline.
      ref.read(poolSyncProvider).flush();
      // Reconcile generations that were in flight when the app was last killed (poll / drop / retry).
      ref.read(generationControllerProvider).reconcile();
    });
    // Network returned → pull fresh data, push the queued answers, and re-send any offline
    // generation prompts sitting in the durable queue.
    _connSub = Connectivity().onConnectivityChanged.listen((results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        ref.read(syncServiceProvider).sync();
        ref.read(reviewSyncProvider).flush();
        ref.read(sessionCompletionSyncProvider).flush();
        // …and the pool decisions («Учить это слово» / «Убрать из изучения») made while offline.
        ref.read(poolSyncProvider).flush();
        ref.read(generationControllerProvider).flushQueue();
      }
    });
  }

  @override
  void dispose() {
    _connSub?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(syncServiceProvider).sync();
      ref.read(reviewSyncProvider).flush();
      ref.read(sessionCompletionSyncProvider).flush();
      // …and the pool decisions («Учить это слово» / «Убрать из изучения») made while offline.
      ref.read(poolSyncProvider).flush();
      ref.read(generationControllerProvider).reconcile();
    }
  }

  /// «или выбрать из N готовых →» on the home: the Collections tab, on its «Готовые» segment.
  ///
  /// The segment is a shared bit of state rather than a constructor argument because the tab's
  /// screen is already built and living in the IndexedStack — a second instance would be a second
  /// scroll position and a second store request.
  void _openStore() {
    ref.read(collectionsSegmentProvider).value = kCollectionsSegmentStore;
    _select(1);
  }

  void _select(int i) {
    if (i == _index) return;
    AppHaptics.light();
    // Put the keyboard away FIRST.
    //
    // The tabs live in an IndexedStack, so leaving Search does not dispose its text field — it just
    // stops being drawn, holding focus, and iOS keeps the keyboard up over whatever tab you landed
    // on. There is no field on that screen to tap out of, so it stayed until the app was
    // backgrounded. Unfocusing here rather than in the search screen's `deactivate` because THIS is
    // the moment the learner said they were done with it, and it covers every tab, not just the one
    // that happens to have a field today.
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() => _index = i);
    // Refresh on tab entry. Reads come from the local DB (a background sync keeps it
    // fresh); only the network-backed study count is invalidated here.
    ref.read(syncServiceProvider).sync();
    ref.invalidate(dueCardsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Search sits between Collections and Progress: the two library tabs together, then the two
    // «about me» ones. Adding it in the MIDDLE moves Progress and Profile one place right, which is
    // the reason _select's indices are read from this list rather than written as literals anywhere.
    final pages = [
      TrainingHomeScreen(onOpenStore: _openStore),
      const CollectionsScreen(),
      const SearchScreen(),
      const ProgressScreen(),
      const ProfileScreen(),
    ];
    final items = [
      FloatingTabItem(icon: LucideIcons.house, label: l.tabHome),
      FloatingTabItem(icon: LucideIcons.layoutGrid, label: l.tabCollections),
      FloatingTabItem(icon: LucideIcons.search, label: l.tabSearch),
      FloatingTabItem(icon: LucideIcons.barChart3, label: l.tabProgress),
      FloatingTabItem(icon: LucideIcons.user, label: l.tabProfile),
    ];

    return Scaffold(
      extendBody: true,
      backgroundColor: AppColors.paper,
      body: Stack(
        children: [
          _ShellBody(child: IndexedStack(index: _index, children: pages)),
          const Positioned(top: 0, left: 0, right: 0, child: SyncIndicator()),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: SafeArea(
              top: false,
              minimum: const EdgeInsets.only(bottom: AppTabBarMetrics.bottomInset),
              child: Center(
                child: FloatingTabBar(items: items, currentIndex: _index, onTap: _select),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// One line of 12.5px text with its padding. Fixed rather than measured, because the tabs below
/// RESERVE exactly this much when a banner is up ([_ShellBody]) — the indicator floats over them, so
/// a height the two sides guess separately is a banner sitting on top of the day's header.
///
/// Re-exported as [SyncIndicator.bannerHeight] so a test can hold the two sides to one number.
const double _kBannerHeight = 32;

/// The tabs, pushed down by whatever the indicator is showing.
///
/// The strip is an overlay: for a 2px progress bar that is invisible and right. A full banner is
/// not — it landed across «Четверг, 27 августа · Стрик 4» and made both unreadable, live, the first
/// time a sync timed out. So the body reserves the banner's height while one is up, and gives it
/// back with the same animation the banner appears with.
class _ShellBody extends ConsumerWidget {
  const _ShellBody({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final syncState = ref.watch(syncServiceProvider).state;
    final stuck = ref.watch(reviewSyncProvider).stuck;

    return ValueListenableBuilder<bool>(
      valueListenable: stuck,
      builder: (context, isStuck, _) => ValueListenableBuilder<SyncState>(
        valueListenable: syncState,
        builder: (context, s, _) => AnimatedPadding(
          duration: const Duration(milliseconds: 250),
          padding: EdgeInsets.only(
            top: isStuck || s == SyncState.offline ? _kBannerHeight : 0,
          ),
          child: child,
        ),
      ),
    );
  }
}

/// A hairline progress bar just under the status bar while a background sync is in flight — and a
/// quiet strip when the last one did NOT land.
///
/// «Being offline is silent» used to mean the failure was silent too, and that is the shape of
/// BUG-1: the phone had Wi-Fi, the SERVER was down, so the connectivity check said «онлайн», no
/// banner appeared, and the app showed a blank day as if that were the news. `SyncState.offline` is
/// the one signal that knows better — it is set by the sync's own catch, whatever the radio thinks —
/// and it now says the one honest sentence: the server is not answering, this is what was saved.
/// A successful sync clears it, because the state goes back to idle.
///
/// The louder case is [ReviewSync.stuck]: the queue is at its cap and still holds answers that
/// carry progress. Those are never dropped to make room, so the honest thing is to say so rather
/// than lose them quietly (F20-r2). It outranks the offline strip — nothing is lost yet in one
/// case, and something might be in the other.
/// Public so it can be mounted on its own: the shell around it starts a sync, a queue flush and a
/// generation reconcile in `initState`, and a test of one strip of paper has no business doing any
/// of that.
class SyncIndicator extends ConsumerWidget {
  const SyncIndicator({super.key});

  /// How tall a banner is — and therefore how much the tabs below reserve for it.
  static const double bannerHeight = _kBannerHeight;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final syncState = ref.watch(syncServiceProvider).state;
    final stuck = ref.watch(reviewSyncProvider).stuck;
    return SafeArea(
      bottom: false,
      child: ValueListenableBuilder<bool>(
        valueListenable: stuck,
        builder: (context, isStuck, child) => isStuck ? _StuckBanner() : child!,
        child: ValueListenableBuilder<SyncState>(
          valueListenable: syncState,
          builder: (_, s, _) => AnimatedSwitcher(
            duration: const Duration(milliseconds: 250),
            child: switch (s) {
              SyncState.syncing => const SizedBox(
                height: 2,
                child: LinearProgressIndicator(
                  minHeight: 2,
                  backgroundColor: Colors.transparent,
                  valueColor: AlwaysStoppedAnimation(AppColors.ink),
                ),
              ),
              SyncState.offline => const _UnreachableBanner(),
              SyncState.idle => const SizedBox(height: 2, width: double.infinity),
            },
          ),
        ),
      ),
    );
  }
}

/// «Сервер недоступен · показываю сохранённое» — the last sync did not land.
///
/// Same quiet strip as [_StuckBanner] and deliberately so: neither is an error, both are facts the
/// learner would otherwise have to guess at. This one says the app is still usable and that what is
/// on screen is the last thing the server said — which is exactly what a blank page did not say.
class _UnreachableBanner extends StatelessWidget {
  const _UnreachableBanner();

  @override
  Widget build(BuildContext context) {
    return _BannerStrip(text: AppLocalizations.of(context).syncUnreachableBanner);
  }
}

/// The shared shape of both strips: full width, opaque, and exactly [_kBannerHeight] tall — the
/// height the body below reserves. Opaque matters as much as the height: this paper palette is
/// close-valued, and a translucent strip over the day's header reads as two texts printed on
/// each other.
class _BannerStrip extends StatelessWidget {
  const _BannerStrip({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: _kBannerHeight,
      alignment: Alignment.center,
      color: AppColors.faintInk,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
      child: Text(
        text,
        textAlign: TextAlign.center,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
      ),
    );
  }
}

/// «Ответы не уходят на сервер» — the queue is full of answers that move progress and cannot be
/// dropped. Quiet paper/ink strip, not a modal: nothing is lost yet, it just isn't leaving.
class _StuckBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return _BannerStrip(text: AppLocalizations.of(context).syncStuckBanner);
  }
}
