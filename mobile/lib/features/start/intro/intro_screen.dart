import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import 'intro_pages.dart';

/// FIVE SHEETS «ЗАЧЕМ» (frame 41-2) — shown once per account on this phone, right after the first sign-in on it.
///
/// A horizontal swipe; the page's picture moves at [StartMotion.sheetParallax] of the swipe; a sheet comes alive
/// [StartMotion.sheetAliveDelay] after its page has settled and plays again each time it is come back to. «Пропустить»
/// top right on a–d (on a paper plate at 60 % over the photos of c and d), «Начать» on e — both call [onDone]: the gate
/// marks the sheets seen and opens the Plan tab under the dissolving sheet.
///
/// The first sheet enters from the sign-in: its picture rises out of the paper from the bottom in
/// [StartMotion.toSheetsReveal] and its text lifts after it (41-4 → 41-2a).
class IntroScreen extends StatefulWidget {
  const IntroScreen({super.key, required this.onDone, this.initialPage = 0, this.still = false});

  final VoidCallback onDone;

  /// The page to open on — the snapshots of b…e.
  @visibleForTesting
  final int initialPage;

  /// Every sheet in its final frame and nothing moving — the snapshots, and «Уменьшить движение».
  @visibleForTesting
  final bool still;

  static const pages = 5;

  @override
  State<IntroScreen> createState() => _IntroScreenState();
}

class _IntroScreenState extends State<IntroScreen> with SingleTickerProviderStateMixin {
  late final PageController _pages = PageController(initialPage: widget.initialPage);
  late final AnimationController _reveal = AnimationController(vsync: this, duration: StartMotion.toSheetsReveal);
  late int _page = widget.initialPage;

  /// Which page is alive now, and a counter that restarts it when it is come back to.
  int _alive = -1;
  int _generation = 0;
  Timer? _aliveTimer;
  bool _done = false;

  bool get _still => widget.still || (MediaQuery.maybeDisableAnimationsOf(context) ?? false);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      if (_still) {
        _reveal.value = 1;
      } else {
        _reveal.forward();
      }
      _settle(_page);
    });
  }

  @override
  void dispose() {
    _aliveTimer?.cancel();
    _reveal.dispose();
    _pages.dispose();
    super.dispose();
  }

  /// A page has come to rest: it comes alive 300 ms later (41-2 «Движение · общее»).
  void _settle(int page) {
    _aliveTimer?.cancel();
    setState(() => _alive = -1);
    _aliveTimer = Timer(_still ? Duration.zero : StartMotion.sheetAliveDelay, () {
      if (!mounted) return;
      setState(() {
        _alive = page;
        _generation++;
      });
    });
  }

  void _finish() {
    if (_done) return;
    _done = true;
    AppHaptics.light();
    widget.onDone();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final photoTop = IntroPages.photoTop(_page);
    return AnnotatedRegion<SystemUiOverlayStyle>(
      // «Статус-бар тёмный на бумаге, светлый поверх фото» (c, d).
      value: photoTop ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      // A Material, not a bare colour: the sheets stand outside any Scaffold (see StartScreen).
      child: Material(
        color: AppColors.ground,
        child: Stack(
          fit: StackFit.expand,
          children: [
            NotificationListener<ScrollEndNotification>(
              onNotification: (_) {
                final page = (_pages.page ?? _page.toDouble()).round();
                if (page != _alive) _settle(page);
                return false;
              },
              child: PageView.builder(
                controller: _pages,
                itemCount: IntroScreen.pages,
                onPageChanged: (i) => setState(() => _page = i),
                itemBuilder: (context, i) => AnimatedBuilder(
                  animation: Listenable.merge([_pages, _reveal]),
                  builder: (context, _) {
                    final offset = _pages.hasClients && _pages.position.haveDimensions
                        ? (_pages.page ?? _page.toDouble()) - i
                        : (_page - i).toDouble();
                    return IntroPage(
                      key: ValueKey('intro-page-$i'),
                      index: i,
                      swipe: offset,
                      reveal: i == widget.initialPage ? StartMotion.ease.transform(_reveal.value) : 1,
                      alive: _alive == i,
                      generation: _generation,
                      still: _still,
                      onStart: _finish,
                    );
                  },
                ),
              ),
            ),
            if (_page < IntroScreen.pages - 1)
              Positioned(
                right: 24,
                // The plate stands 4 under the status bar (canvas: 56 on a 52 bar); its tap target reaches 10 around it.
                top: MediaQuery.paddingOf(context).top + 4 - 10,
                child: _SkipButton(label: l.introSkip, onPlate: photoTop, onTap: _finish),
              ),
          ],
        ),
      ),
    );
  }
}

/// «Пропустить» — Inter 13 grey, 24 tall, on a paper plate at 60 % where it stands over a photo.
class _SkipButton extends StatelessWidget {
  const _SkipButton({required this.label, required this.onPlate, required this.onTap});

  final String label;
  final bool onPlate;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: label,
    child: GestureDetector(
      key: const ValueKey('intro-skip'),
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        // The tap target reaches 44 without moving the plate.
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: AnimatedContainer(
          duration: StartMotion.sheetsOut,
          height: 24,
          padding: const EdgeInsets.symmetric(horizontal: 8),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: onPlate ? AppColors.introPlate : AppColors.groundClear,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(label, style: AppTextStart.skip),
        ),
      ),
    ),
  );
}

/// «Начать» at the foot of the last sheet.
class IntroStartButton extends StatelessWidget {
  const IntroStartButton({super.key, required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) =>
      DockButton(key: const ValueKey('intro-start'), label: AppLocalizations.of(context).introStart, onTap: onTap);
}
