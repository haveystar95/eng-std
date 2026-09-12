import 'package:flutter/material.dart';

import '../theme/theme.dart';

/// КРУГЛАЯ КАРТИНКА СЦЕНЫ — бесшовно (наряд PLAN-UI-3, §3).
///
/// Три слоя, и ни в один момент круг не бывает пустым: бумажный круг → заливка доминантным тоном
/// фото (он пришёл в ответе вместе с адресом, поэтому известен раньше байтов) → само фото,
/// растворением 200 мс. Фото, которое уже раскодировано в памяти, встаёт в первом же кадре без
/// растворения — повторный вход на таб не мигает.
///
/// Картинку даёт вызывающий (в плане — `CachedNetworkImage` поверх общего `ImageLoader`: дисковый
/// кэш, шесть параллельных запросов, повтор при обрыве); раскодируется она по размеру круга.
class SceneCircle extends StatelessWidget {
  const SceneCircle({
    super.key,
    required this.image,
    this.tone,
    this.size = 56,
    this.veil = false,
    this.border,
    this.shadows = const [],
  });

  /// Фото; null — фото нет, круг остаётся тоном или бумагой.
  final ImageProvider? image;
  final Color? tone;
  final double size;

  /// Запертый день — вуаль ground .6 поверх фото (не прозрачность целиком).
  final bool veil;
  final BoxBorder? border;
  final List<BoxShadow> shadows;

  static const Duration fade = Duration(milliseconds: 200);

  @override
  Widget build(BuildContext context) {
    final dpr = MediaQuery.maybeDevicePixelRatioOf(context) ?? 2;
    final src = image;

    return AnimatedContainer(
      duration: fade,
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: tone ?? AppColors.photoPlaceholder,
        border: border,
        boxShadow: shadows,
      ),
      // Кант рамки сдвигает содержимое внутрь, и квадрат фото без своего круга торчал бы углами
      // над латунной обводкой — поэтому фото режется своим кругом.
      child: ClipOval(
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (src != null)
              Image(
                image: ResizeImage(src, width: (size * dpr).round(), policy: ResizeImagePolicy.fit),
                fit: BoxFit.cover,
                gaplessPlayback: true,
                frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
                  if (wasSynchronouslyLoaded) return child;

                  return AnimatedOpacity(
                    opacity: frame == null ? 0 : 1,
                    duration: fade,
                    curve: Curves.easeOut,
                    child: child,
                  );
                },
                // Фото не пришло — тон остаётся: круг тона честнее серой дыры.
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            if (veil) const ColoredBox(color: AppColors.routeVeil),
          ],
        ),
      ),
    );
  }
}
