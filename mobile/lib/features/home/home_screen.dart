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
      ref.read(reviewSyncProvider).flush();
      // Reconcile generations that were in flight when the app was last killed (poll / drop / retry).
      ref.read(generationControllerProvider).reconcile();
    });
    // Network returned → pull fresh data, push the queued answers, and re-send any offline
    // generation prompts sitting in the durable queue.
    _connSub = Connectivity().onConnectivityChanged.listen((results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        ref.read(syncServiceProvider).sync();
        ref.read(reviewSyncProvider).flush();
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
    final pages = [
      TrainingHomeScreen(onOpenCollections: () => _select(1)),
      const CollectionsScreen(),
      const ProgressScreen(),
      const ProfileScreen(),
    ];
    final items = [
      FloatingTabItem(icon: LucideIcons.house, label: l.tabHome),
      FloatingTabItem(icon: LucideIcons.layoutGrid, label: l.tabCollections),
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
class _SyncIndicator extends ConsumerWidget {
  const _SyncIndicator();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final syncState = ref.watch(syncServiceProvider).state;
    return SafeArea(
      bottom: false,
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
    );
  }
}
