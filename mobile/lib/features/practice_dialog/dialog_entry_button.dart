import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/feature_flags.dart';
import '../../data/providers.dart';
import 'dialog_models.dart';
import 'dialog_providers.dart';
import 'dialog_screen.dart';

/// «Разговор · 3 мин» — the practice-dialog entry on the collection screen, under the main CTA.
///
/// Visible ONLY for premium (tier=premium from `/me`); collapses to nothing otherwise. Offline it
/// stays visible but disabled, with a «нужен интернет» hint. When the collection already has a
/// finished dialog, the button reads «Пройти ещё раз» and a compact result row (date · «слов: X из
/// Y») sits under it — an early-exit dialog counts too. Carries its own top spacing so the
/// collection screen can drop it in without leaving a gap when it collapses.
class DialogEntryButton extends ConsumerWidget {
  const DialogEntryButton({super.key, required this.collectionId, required this.title});

  final String collectionId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final profile = ref.watch(authControllerProvider).value?.profile;
    // Premium-only, and by design (rule 22 — no subscription mentions inside practice) it simply
    // collapses to nothing for free users: there is NO paywall entrance from the dialog gate. Reads
    // the effective premium (server tier OR the dev fake) so a dev "purchase" reveals it too.
    if (profile == null || !ref.watch(premiumProvider)) return const SizedBox.shrink();

    final online = ref.watch(connectivityProvider).value ?? true;
    final targetLang = profile.targetLanguage;
    final last = ref.watch(lastDialogProvider(collectionId)).value;

    void open() {
      AppHaptics.light();
      showPracticeDialogPrestart(
        context,
        collectionId: collectionId,
        title: title,
        targetLang: targetLang,
      );
    }

    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.s12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Opacity(
            opacity: online ? 1 : 0.55,
            child: _Button(
              label: last != null ? l.practiceDialogRepeat : l.practiceDialogEntry,
              subtitle: online ? l.practiceDialogEntrySubtitle : l.practiceDialogOfflineHint,
              onTap: online ? open : null,
            ),
          ),
          if (last != null) ...[const SizedBox(height: AppSpacing.s8), _ResultRow(result: last)],
        ],
      ),
    );
  }
}

class _Button extends StatelessWidget {
  const _Button({required this.label, required this.subtitle, required this.onTap});
  final String label, subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.button),
        side: const BorderSide(color: AppInkDensity.outlineColor),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
          child: Row(
            children: [
              const Icon(LucideIcons.messageCircle, size: 20, color: AppColors.ink),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: AppText.primaryButton.copyWith(color: AppColors.ink, fontSize: 16),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: AppText.primaryButtonSub.copyWith(color: AppColors.secondary),
                    ),
                  ],
                ),
              ),
              const Icon(LucideIcons.arrowRight, size: 16, color: AppColors.secondary),
            ],
          ),
        ),
      ),
    );
  }
}

/// Compact last-dialog result under the button: date · «слов: X из Y».
class _ResultRow extends StatelessWidget {
  const _ResultRow({required this.result});
  final LastDialogResult result;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final d = result.finishedAt;
    final date = '${d.day}.${d.month.toString().padLeft(2, '0')}';
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Row(
        children: [
          const Icon(LucideIcons.check, size: 13, color: AppColors.tertiary),
          const SizedBox(width: 6),
          Text(
            '$date · ${l.practiceDialogResultWords(result.wordsUsed, result.wordsTotal)}',
            style: AppText.counterSmall.copyWith(fontSize: 12, color: AppColors.tertiary),
          ),
        ],
      ),
    );
  }
}
