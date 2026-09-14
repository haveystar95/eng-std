import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import '../../../../data/local/cached_image_provider.dart';
import '../../../../data/plan/day_window.dart';

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
          color: onPhoto ? AppColors.paper90 : null,
          border: Border.all(color: AppColors.markerOutline, width: 1.5),
        ),
        WindowUnitState.done => BoxDecoration(shape: BoxShape.circle, color: AppColors.verdictKnown, boxShadow: ring),
        WindowUnitState.returnsTomorrow => BoxDecoration(shape: BoxShape.circle, color: AppColors.brassInk, boxShadow: ring),
      },
      child: state == WindowUnitState.done ? const Icon(LucideIcons.check, size: 8, color: AppColors.paper) : null,
    );
  }
}

/// ПОЛОСА 4 (ряд этапа и компактная шапка): подложка и заливка на долю [share].
class WindowBar extends StatelessWidget {
  const WindowBar({super.key, required this.share, required this.track, required this.fill});

  final double share;
  final Color track;
  final Color fill;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(2),
    child: SizedBox(
      height: 4,
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

/// ФОТО ПО ТОНУ: слот залит тоном сервера, фото растворяется поверх за 200 мс (общий `ImageLoader`
/// через [CachedNetworkImage]); фото уже в памяти встаёт сразу, не пришло — остаётся тон.
class WindowPhoto extends StatelessWidget {
  const WindowPhoto({super.key, required this.url, required this.tone});

  final String? url;
  final Color tone;

  static const fade = Duration(milliseconds: 200);

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
                  : AnimatedOpacity(opacity: frame == null ? 0 : 1, duration: fade, curve: Curves.easeOut, child: child),
              errorBuilder: (_, _, _) => const SizedBox.expand(),
            ),
    );
  }
}

/// СОДЕРЖИМОЕ ВКЛАДКИ ПОЯВЛЯЕТСЯ — `@keyframes om-cab-in`: прозрачность 0 → 1 и сдвиг 8 → 0 за
/// 200 мс с задержкой 80, ease-out. Под «уменьшением движения» — сразу.
class CabIn extends StatefulWidget {
  const CabIn({super.key, required this.child});

  final Widget child;

  @override
  State<CabIn> createState() => _CabInState();
}

class _CabInState extends State<CabIn> with SingleTickerProviderStateMixin {
  late final AnimationController _in = AnimationController(vsync: this, duration: AppMotion.windowTabContent);
  Timer? _delay;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.of(context).disableAnimations) {
      _in.value = 1;
    } else if (_in.value == 0 && _delay == null) {
      _delay = Timer(AppMotion.windowTabContentDelay, () {
        if (mounted) unawaited(_in.forward());
      });
    }
  }

  @override
  void dispose() {
    _delay?.cancel();
    _in.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _in,
    builder: (_, child) {
      final t = AppMotion.easeOut.transform(_in.value);

      return Opacity(
        opacity: t,
        child: Transform.translate(offset: Offset(0, (1 - t) * AppMotion.windowTabContentRise), child: child),
      );
    },
    child: widget.child,
  );
}
