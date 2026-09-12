import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// Что сказать баннером.
class PlanBannerData {
  const PlanBannerData({required this.title, required this.body, required this.dayNumber});

  final String title;
  final String body;
  final int dayNumber;
}

/// БАННЕР ПЛАНА ВНУТРИ ПРИЛОЖЕНИЯ — вид уведомления кадра 22-6.
///
/// «План готов» и «день собран» без push: показываются, когда человек возвращается в приложение.
/// Карточка 342 на бумаге .92, radius 20, тень `0 18 40 rgba(0,0,0,.28)`, знак приложения 34 с
/// буквой, заголовок 15/700 и «сейчас», тело 15/1.4. Приезжает сверху за 220 мс, уходит сама
/// через 5 с или по тапу — тап открывает таб «План» на этом дне.
class PlanBanner extends StatefulWidget {
  const PlanBanner({super.key, required this.data, required this.onTap, required this.onGone});

  final PlanBannerData data;
  final VoidCallback onTap;
  final VoidCallback onGone;

  static const Duration enter = Duration(milliseconds: 220);
  static const Duration stay = Duration(seconds: 5);

  @override
  State<PlanBanner> createState() => _PlanBannerState();
}

class _PlanBannerState extends State<PlanBanner> with SingleTickerProviderStateMixin {
  late final AnimationController _in = AnimationController(vsync: this, duration: PlanBanner.enter)..forward();
  Timer? _stay;

  @override
  void initState() {
    super.initState();
    _stay = Timer(PlanBanner.stay, () async {
      if (!mounted) return;
      await _in.reverse();
      if (mounted) widget.onGone();
    });
  }

  @override
  void dispose() {
    _stay?.cancel();
    _in.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final top = MediaQuery.viewPaddingOf(context).top + 8;

    return Positioned(
      top: top,
      left: 0,
      right: 0,
      child: SlideTransition(
        position: Tween(begin: const Offset(0, -1.4), end: Offset.zero).animate(CurvedAnimation(parent: _in, curve: Curves.easeOut)),
        child: Center(
          child: GestureDetector(
            onTap: widget.onTap,
            child: Container(
              width: 342,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: AppColors.paper90,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [BoxShadow(color: AppColors.ink.withValues(alpha: .28), blurRadius: 40, offset: const Offset(0, 18))],
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(color: AppColors.ink, borderRadius: BorderRadius.circular(9)),
                    child: Text(
                      l.planBannerAppMark,
                      style: const TextStyle(fontFamily: AppFonts.literata, fontSize: 18, fontWeight: FontWeight.w500, color: AppColors.paper),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.baseline,
                          textBaseline: TextBaseline.alphabetic,
                          children: [
                            Expanded(
                              child: Text(
                                widget.data.title,
                                style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.ink),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Text(l.planBannerNow, style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 13, color: AppColors.tertiary)),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          widget.data.body,
                          style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 1.4, color: AppColors.ink),
                        ),
                      ],
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
