import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/providers.dart';
import '../collections/collections_screen.dart';
import '../profile/profile_screen.dart';
import '../training/training_home_screen.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> with WidgetsBindingObserver {
  int _index = 0;

  static const _pages = [TrainingHomeScreen(), CollectionsScreen(), ProfileScreen()];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Drain any answers left over from a previous (possibly offline) session.
    WidgetsBinding.instance.addPostFrameCallback((_) => ref.read(reviewSyncProvider).flush());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(reviewSyncProvider).flush();
    }
  }

  static const _items = [
    (icon: Icons.school_outlined, active: Icons.school_rounded, label: 'Тренировка'),
    (icon: Icons.style_outlined, active: Icons.style_rounded, label: 'Коллекции'),
    (icon: Icons.person_outline_rounded, active: Icons.person_rounded, label: 'Профиль'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      extendBody: true,
      backgroundColor: AppColors.bg,
      body: IndexedStack(index: _index, children: _pages),
      bottomNavigationBar: _GlassNavBar(
        index: _index,
        items: _items,
        onTap: (i) {
          if (i == _index) return;
          AppFeedback.select();
          setState(() => _index = i);
          // Refresh on tab entry so a create/delete on another tab is reflected without a
          // manual pull. Screens keep showing current data during the reload (no spinner).
          ref.invalidate(collectionsProvider);
          ref.invalidate(collectionsProgressProvider);
          ref.invalidate(statsProvider);
          ref.invalidate(dueCardsProvider);
        },
      ),
    );
  }
}

typedef _NavItem = ({IconData icon, IconData active, String label});

/// Floating frosted tab bar — real backdrop blur over the ambient background.
class _GlassNavBar extends StatelessWidget {
  const _GlassNavBar({required this.index, required this.items, required this.onTap});
  final int index;
  final List<_NavItem> items;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.md, 0, AppSpacing.md, AppSpacing.sm),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadii.xl),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AppRadii.xl),
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    Colors.white.withValues(alpha: 0.14),
                    Colors.white.withValues(alpha: 0.05),
                  ],
                ),
                border: Border.all(color: Colors.white.withValues(alpha: 0.14), width: 1),
                boxShadow: AppShadows.card,
              ),
              child: Row(
                children: [
                  for (var i = 0; i < items.length; i++)
                    Expanded(
                      child: _NavButton(
                        item: items[i],
                        selected: i == index,
                        onTap: () => onTap(i),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavButton extends StatelessWidget {
  const _NavButton({required this.item, required this.selected, required this.onTap});
  final _NavItem item;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      feedback: false, // parent fires a single select tick on index change
      scale: 0.9,
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.md),
          gradient: selected ? AppGradients.brand : null,
          boxShadow: selected ? AppShadows.glow(AppColors.primary) : null,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              selected ? item.active : item.icon,
              color: selected ? Colors.white : AppColors.textMuted,
              size: 24,
            ),
            const SizedBox(height: 4),
            Text(
              item.label,
              style: TextStyle(
                color: selected ? Colors.white : AppColors.textMuted,
                fontSize: 11,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
