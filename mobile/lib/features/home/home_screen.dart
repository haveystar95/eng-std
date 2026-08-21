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

  void _select(int i) {
    if (i == _index) return;
    AppHaptics.light();
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
      TrainingHomeScreen(onOpenCollections: () => _select(1)),
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
          IndexedStack(index: _index, children: pages),
          const Positioned(top: 0, left: 0, right: 0, child: _SyncIndicator()),
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

/// A hairline progress bar just under the status bar, shown only while a background sync is in
/// flight. Offline is deliberately silent — being offline is normal, not a fault to flag.
///
/// The one loud case is [ReviewSync.stuck]: the queue is at its cap and still holds answers that
/// carry progress. Those are never dropped to make room, so the honest thing is to say so rather
/// than lose them quietly (F20-r2).
class _SyncIndicator extends ConsumerWidget {
  const _SyncIndicator();

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
          child: s == SyncState.syncing
              ? const SizedBox(
                  height: 2,
                  child: LinearProgressIndicator(
                    minHeight: 2,
                    backgroundColor: Colors.transparent,
                    valueColor: AlwaysStoppedAnimation(AppColors.ink),
                  ),
                )
              : const SizedBox(height: 2, width: double.infinity),
        ),
        ),
      ),
    );
  }
}

/// «Ответы не уходят на сервер» — the queue is full of answers that move progress and cannot be
/// dropped. Quiet paper/ink strip, not a modal: nothing is lost yet, it just isn't leaving.
class _StuckBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: AppColors.faintInk,
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH, vertical: 8),
      child: Text(
        AppLocalizations.of(context).syncStuckBanner,
        textAlign: TextAlign.center,
        style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
      ),
    );
  }
}
