// Variants of the pair badge, judged IN PLACE — on a shelf row, not in a vacuum.
//
// The badge went in as type («EN→ES»), was overruled for flags, and the flags read loud on paper.
// Rather than guess at the fix, this harness puts the candidates side by side in the row they
// actually live in. Outside `lib/`, so its sample Russian copy is exempt from the cyrillic guard.
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/mini_flag.dart';

void main() => runApp(const _App());

class _App extends StatelessWidget {
  const _App();

  @override
  Widget build(BuildContext context) => MaterialApp(
    theme: buildAppTheme(),
    debugShowCheckedModeBanner: false,
    home: Scaffold(
      backgroundColor: AppColors.paper,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 8, AppSpacing.screenH, 24),
          children: const [
            _Variant(label: 'A · сейчас: 15 + стрелка', badge: _A()),
            _Variant(label: 'B · внахлёст, без стрелки', badge: _B()),
            _Variant(label: 'C · в тихой пилюле, 12', badge: _C()),
            _Variant(label: 'D · мельче и тише: 12, зазор', badge: _D()),
            _Variant(label: 'E · флаг + код поддержки', badge: _E()),
            _Variant(label: 'F · только под обложкой', badge: _B(), underCover: true),
          ],
        ),
      ),
    ),
  );
}

class _Variant extends StatelessWidget {
  const _Variant({required this.label, required this.badge, this.underCover = false});
  final String label;
  final Widget badge;
  final bool underCover;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 14),
        Text(label, style: AppText.sectionLabel),
        const SizedBox(height: 6),
        DecoratedBox(
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.hairline)),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: AppSpacing.s16),
            child: Row(
              children: [
                Column(
                  children: [
                    Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        color: AppColors.faintInk,
                        borderRadius: BorderRadius.circular(AppRadii.card),
                      ),
                    ),
                    if (underCover) ...[const SizedBox(height: 6), badge],
                  ],
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'Praca po polsku',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: AppText.collectionNameCard,
                            ),
                          ),
                          if (!underCover) ...[const SizedBox(width: 8), badge],
                        ],
                      ),
                      const SizedBox(height: 5),
                      Text(
                        '2 слова · освоено 0',
                        style: AppText.translation.copyWith(fontSize: 12.5),
                      ),
                      const SizedBox(height: 11),
                      Container(height: 6, color: AppColors.faintInk),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

const _learned = 'pl', _support = 'ru';

class _A extends StatelessWidget {
  const _A();
  @override
  Widget build(BuildContext context) => const Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      MiniFlag(languageCode: _learned, size: 15),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: 3.3),
        child: Icon(LucideIcons.arrowRight, size: 9.3, color: AppColors.tertiary),
      ),
      MiniFlag(languageCode: _support, size: 15),
    ],
  );
}

/// Two coins, overlapping like a stack: one OBJECT rather than two marks and a glyph between them.
/// Order still says which is learned, and the width drops by a third.
class _B extends StatelessWidget {
  const _B();
  @override
  Widget build(BuildContext context) => const SizedBox(
    width: 15 + 15 * 0.68,
    height: 15,
    child: Stack(
      children: [
        Positioned(left: 15 * 0.68, child: MiniFlag(languageCode: _support, size: 15)),
        Positioned(left: 0, child: MiniFlag(languageCode: _learned, size: 15)),
      ],
    ),
  );
}

/// Framed: the colour sits inside a quiet paper chip instead of floating on the sheet.
class _C extends StatelessWidget {
  const _C();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
    decoration: BoxDecoration(
      color: AppColors.faintInk,
      borderRadius: BorderRadius.circular(999),
    ),
    child: const Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        MiniFlag(languageCode: _learned, size: 12),
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 3),
          child: Icon(LucideIcons.arrowRight, size: 8, color: AppColors.tertiary),
        ),
        MiniFlag(languageCode: _support, size: 12),
      ],
    ),
  );
}

class _D extends StatelessWidget {
  const _D();
  @override
  Widget build(BuildContext context) => const Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      MiniFlag(languageCode: _learned, size: 12),
      SizedBox(width: 4),
      MiniFlag(languageCode: _support, size: 12),
    ],
  );
}

/// One flag for the language being LEARNED (the fact that matters), the support side as a code.
class _E extends StatelessWidget {
  const _E();
  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      const MiniFlag(languageCode: _learned, size: 14),
      const SizedBox(width: 5),
      Text(
        _support.toUpperCase(),
        style: const TextStyle(
          fontFamily: AppFonts.inter,
          fontWeight: FontWeight.w700,
          fontSize: 10.5,
          letterSpacing: 0.7,
          color: AppColors.tertiary,
        ),
      ),
    ],
  );
}
