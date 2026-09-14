import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';

/// Сказать строку окна: её текст и адрес её файла (null — ещё не озвучено, читает телефон).
typedef WindowListen = void Function(String text, String? audioUrl);

/// МАРКЕР СОСТОЯНИЯ 14 (кадры 23-0b…0d): контур — не пройдено, шалфей с галкой — пройдено, латунь
/// — вернётся завтра. [onPhoto] — в углу фото карточки слова: бумажная подложка и кольцо 2.
class WindowUnitMarker extends StatelessWidget {
  const WindowUnitMarker({super.key, required this.state, this.onPhoto = false});

  final WindowUnitState state;
  final bool onPhoto;

  @override
  Widget build(BuildContext context) {
    final ring = onPhoto ? const [BoxShadow(color: AppColors.paper90, spreadRadius: 2)] : const <BoxShadow>[];

    return Container(
      width: 14,
      height: 14,
      alignment: Alignment.center,
      decoration: switch (state) {
        WindowUnitState.pending => BoxDecoration(
          shape: BoxShape.circle,
          color: AppColors.paper90,
          border: Border.all(color: AppColors.markerOutline, width: 1.5),
        ),
        WindowUnitState.done => BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown, boxShadow: ring),
        WindowUnitState.returnsTomorrow => BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk, boxShadow: ring),
      },
      child: state == WindowUnitState.done ? const Icon(LucideIcons.check, size: 8, color: AppColors.paper) : null,
    );
  }
}

/// ГАЛКА В ШАЛФЕЙНОМ КРУГЕ — итог дня (20, галка 11), «Научился:» (14, галка 8), состояние шита (20).
class WindowCheck extends StatelessWidget {
  const WindowCheck({super.key, required this.size, required this.glyph});

  final double size;
  final double glyph;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: const BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown),
    child: Icon(LucideIcons.check, size: glyph, color: AppColors.paper),
  );
}

/// ПОЛОСА (ряд этапа 3, компактная шапка 4): подложка и заливка на долю [share], скругление 2.
class WindowBar extends StatelessWidget {
  const WindowBar({super.key, required this.share, required this.track, required this.fill, this.height = 3});

  final double share;
  final Color track;
  final Color fill;
  final double height;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(2),
    child: SizedBox(
      height: height,
      child: ColoredBox(
        color: track,
        child: FractionallySizedBox(
          alignment: Alignment.centerLeft,
          widthFactor: share.clamp(0.0, 1.0),
          child: ColoredBox(color: fill),
        ),
      ),
    ),
  );
}

/// ФОТО ПО ТОНУ: слот залит тоном сервера, фото растворяется поверх за `windowPhotoFade` (общий
/// `ImageLoader` через [CachedNetworkImage]); фото уже в памяти встаёт сразу, не пришло — остаётся тон.
class WindowPhoto extends StatelessWidget {
  const WindowPhoto({super.key, required this.url, required this.tone});

  final String? url;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    final src = url;

    return ColoredBox(
      color: tone,
      child: src == null
          ? const SizedBox.expand()
          : Image(
              image: CachedNetworkImage(src),
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
              gaplessPlayback: true,
              frameBuilder: (context, child, frame, sync) => sync
                  ? child
                  : AnimatedOpacity(
                      opacity: frame == null ? 0 : 1,
                      duration: AppMotion.windowPhotoFade,
                      curve: Curves.easeOut,
                      child: child,
                    ),
              errorBuilder: (_, _, _) => const SizedBox.expand(),
            ),
    );
  }
}

/// «ПРОСЛУШАТЬ» ОКНА (кадры 23-0a…0e) — латунный контур 1.5, значок голоса в половину круга: 28 у
/// слова, фразы и реплики, 44 у слова в шите. Тап — одна пульсация и голос строки.
class WindowListenButton extends StatefulWidget {
  const WindowListenButton({super.key, required this.onTap, this.size = 28});

  final VoidCallback onTap;
  final double size;

  @override
  State<WindowListenButton> createState() => _WindowListenButtonState();
}

class _WindowListenButtonState extends State<WindowListenButton> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: AppMotion.wavePulse,
    lowerBound: 1.0,
    upperBound: 1.06,
  );

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  void _tap() {
    AppHaptics.light();
    if (!MediaQuery.of(context).disableAnimations) {
      _pulse.forward(from: 1.0).then((_) => _pulse.reverse());
    }
    widget.onTap();
  }

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: AppLocalizations.of(context).planWindowListen,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: _tap,
      child: ScaleTransition(
        scale: _pulse,
        child: Container(
          width: widget.size,
          height: widget.size,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.brassInk, width: 1.5),
          ),
          child: Icon(LucideIcons.volume1, size: widget.size / 2, color: AppColors.brassInk),
        ),
      ),
    ),
  );
}
