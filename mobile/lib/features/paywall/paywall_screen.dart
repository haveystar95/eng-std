import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/feature_flags.dart';

/// Where the paywall was opened from — decides the headline and value line only; the benefits,
/// prices and legal block are identical (кадр 2.13, правило 22).
enum PaywallEntry { quota, store, profile }

/// Arguments for [PaywallScreen]. [collectionTitle]/[otherSetsCount] fill the store headline
/// («Собеседование и ещё 24 набора», кадр 14b).
class PaywallArgs {
  const PaywallArgs(this.entry, {this.collectionTitle, this.otherSetsCount});
  final PaywallEntry entry;
  final String? collectionTitle;
  final int? otherSetsCount;
}

/// Opens the paywall (кадры 2.13/2.14). No-op unless the paywall flag is on, so every call site can
/// wire the tap unconditionally and the surface simply doesn't appear in a release build.
/// Returns true when the user "purchased" (dev fake premium granted).
Future<bool> showPaywall(BuildContext context, WidgetRef ref, PaywallArgs args) async {
  if (!ref.read(featureFlagsProvider).paywallEnabled) return false;
  final ok = await Navigator.of(context).push<bool>(
    MaterialPageRoute(fullscreenDialog: true, builder: (_) => PaywallScreen(args: args)),
  );
  return ok ?? false;
}

/// The paywall — one screen for all three entrances. Prices are placeholders (кадр 4ж): there is no
/// real StoreKit yet, so «Продолжить» in dev grants a client-only fake premium (behind a flag). Real
/// transactions are a separate block after the Apple Developer account (TODO in ROADMAP).
class PaywallScreen extends ConsumerStatefulWidget {
  const PaywallScreen({super.key, required this.args});
  final PaywallArgs args;

  @override
  ConsumerState<PaywallScreen> createState() => _PaywallScreenState();
}

enum _Period { year, month }

class _PaywallScreenState extends ConsumerState<PaywallScreen> {
  _Period _period = _Period.year; // year selected by default (кадр 14a)

  Future<void> _continue() async {
    final l = AppLocalizations.of(context);
    // No StoreKit — the dev "purchase" flips a local fake premium so the gated surfaces light up.
    await ref.read(featureFlagsProvider.notifier).setDevPremium(true);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l.paywallDevPurchased)));
    Navigator.of(context).pop(true);
  }

  String _title(AppLocalizations l) {
    switch (widget.args.entry) {
      case PaywallEntry.quota:
        return l.paywallTitleQuota;
      case PaywallEntry.store:
        final t = widget.args.collectionTitle;
        final n = widget.args.otherSetsCount ?? 0;
        if (t == null) return l.paywallTitleGeneric;
        return n > 0 ? l.paywallTitleStore(t, n) : t;
      case PaywallEntry.profile:
        return l.paywallTitleGeneric;
    }
  }

  String _subtitle(AppLocalizations l) => switch (widget.args.entry) {
    PaywallEntry.quota => l.paywallSubtitleQuota,
    PaywallEntry.store => l.paywallSubtitleStore,
    PaywallEntry.profile => l.paywallSubtitleGeneric,
  };

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final bottomSafe = MediaQuery.viewPaddingOf(context).bottom;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Column(
            children: [
              // Close — крестик слева сверху (кадр 4ж «закрытие крестиком слева сверху»).
              Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 6, AppSpacing.screenH, 0),
                  child: Semantics(
                    button: true,
                    label: l.paywallClose,
                    child: InkResponse(
                      radius: 24,
                      onTap: () => Navigator.of(context).maybePop(),
                      child: const SizedBox(
                        height: 36,
                        width: 36,
                        child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                      ),
                    ),
                  ),
                ),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 8, AppSpacing.screenH, 8),
                  children: [
                    Text(_title(l), style: _titleStyle),
                    const SizedBox(height: 11),
                    Text(
                      _subtitle(l),
                      style: AppText.translation.copyWith(
                        fontSize: 14.5,
                        color: AppColors.inkBody,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 22),
                    _Benefit(l.paywallBenefitGenerations),
                    const SizedBox(height: 11),
                    _Benefit(l.paywallBenefitStore),
                    const SizedBox(height: 11),
                    _Benefit(l.paywallBenefitModes),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.only(top: 15),
                      decoration: const BoxDecoration(
                        border: Border(top: BorderSide(color: AppColors.hairline)),
                      ),
                      child: Text(
                        l.paywallFreeForever,
                        style: AppText.translation.copyWith(
                          fontSize: 13.5,
                          color: AppColors.inkBody,
                          height: 1.45,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Expanded(
                          child: _PriceCard(
                            period: l.paywallPeriodYear,
                            price: l.paywallPriceYear,
                            sub: l.paywallYearPerMonth,
                            badge: l.paywallDiscountBadge,
                            selected: _period == _Period.year,
                            onTap: () {
                              AppHaptics.light();
                              setState(() => _period = _Period.year);
                            },
                          ),
                        ),
                        const SizedBox(width: 11),
                        Expanded(
                          child: _PriceCard(
                            period: l.paywallPeriodMonth,
                            price: l.paywallPriceMonth,
                            sub: l.paywallPerMonth,
                            selected: _period == _Period.month,
                            onTap: () {
                              AppHaptics.light();
                              setState(() => _period = _Period.month);
                            },
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              // Footer — «Продолжить» + юридический блок + ссылки (кадр 4ж «обязательное на экране»).
              Padding(
                padding: EdgeInsets.fromLTRB(
                  AppSpacing.screenH,
                  4,
                  AppSpacing.screenH,
                  bottomSafe + 14,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Material(
                      color: AppColors.ink,
                      borderRadius: BorderRadius.circular(AppRadii.field),
                      clipBehavior: Clip.antiAlias,
                      child: InkWell(
                        onTap: _continue,
                        child: Container(
                          height: 54,
                          alignment: Alignment.center,
                          child: Text(
                            l.paywallContinue,
                            style: AppText.primaryButton.copyWith(fontSize: 15.5),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _period == _Period.year
                          ? l.paywallLegalYear(l.paywallPriceYear)
                          : l.paywallLegalMonth(l.paywallPriceMonth),
                      style: AppText.counterSmall.copyWith(
                        fontSize: 11,
                        color: AppColors.tertiary,
                        height: 1.45,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          l.paywallRestore,
                          style: AppText.transcription.copyWith(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.secondary,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Text(
                          l.paywallTerms,
                          style: AppText.transcription.copyWith(
                            fontSize: 12,
                            color: AppColors.tertiary,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Text(
                          l.paywallPrivacy,
                          style: AppText.transcription.copyWith(
                            fontSize: 12,
                            color: AppColors.tertiary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static const _titleStyle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 32,
    height: 1.13,
    letterSpacing: -0.48,
    color: AppColors.ink,
  );
}

class _Benefit extends StatelessWidget {
  const _Benefit(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(top: 2),
          child: Icon(LucideIcons.check, size: 18, color: AppColors.ink),
        ),
        const SizedBox(width: 11),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontFamily: AppFonts.inter,
              fontSize: 15,
              height: 1.4,
              color: AppColors.ink,
            ),
          ),
        ),
      ],
    );
  }
}

/// Ценовая карточка (кадр 4ж). Выбор — чернильной заливкой, не рамкой/галочкой. Цена и период
/// явно; вёрстка держит длинную локализованную цену (FittedBox сжимает, не ломает строку).
class _PriceCard extends StatelessWidget {
  const _PriceCard({
    required this.period,
    required this.price,
    required this.sub,
    required this.selected,
    required this.onTap,
    this.badge,
  });

  final String period, price, sub;
  final bool selected;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final fg = selected ? AppColors.paper : AppColors.ink;
    final periodColor = selected ? AppColors.paper.withValues(alpha: 0.72) : AppColors.secondary;
    final subColor = selected ? AppColors.paper.withValues(alpha: 0.66) : AppColors.tertiary;

    final card = Material(
      color: selected ? AppColors.ink : AppColors.paper,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: selected ? BorderSide.none : const BorderSide(color: AppColors.hairline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(15, 16, 15, 15),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                period,
                style: TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: periodColor,
                ),
              ),
              const SizedBox(height: 7),
              // FittedBox keeps a long localized price on one line by scaling down, never wrapping.
              Align(
                alignment: Alignment.centerLeft,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    price,
                    maxLines: 1,
                    style: TextStyle(
                      fontFamily: AppFonts.inter,
                      fontSize: 24,
                      fontWeight: FontWeight.w700,
                      color: fg,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 3),
              Text(
                sub,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontFamily: AppFonts.inter,
                  fontSize: 12,
                  color: subColor,
                  fontFeatures: const [FontFeature.tabularFigures()],
                ),
              ),
            ],
          ),
        ),
      ),
    );

    if (badge == null) return card;
    // Бейдж «−50%»: всегда на годовой карточке; цвет переворачивается (paper-на-ink у выбранной).
    return Stack(
      clipBehavior: Clip.none,
      children: [
        card,
        Positioned(
          top: -9,
          left: 15,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
            decoration: BoxDecoration(
              color: selected ? AppColors.paper : AppColors.ink,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              badge!,
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.5,
                color: selected ? AppColors.ink : AppColors.paper,
                fontFeatures: const [FontFeature.tabularFigures()],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
