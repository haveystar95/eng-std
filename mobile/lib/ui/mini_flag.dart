import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// Круглый мини-флаг (§4б). Диаметр 22, внутренний контур
/// `inset 0 0 0 1px rgba(46,38,32,.20)`. Rule 14: только в языковых контекстах
/// (список языков онбординга, дропдаун «Язык изучения», языковая пара в сторе).
/// На карточках слов и коллекций флагов нет.
///
/// Поддержаны языки из кадров: en, pt, de, es, fr. Прочие → нейтральный кружок
/// с кодом языка (без декоративной краски).
class MiniFlag extends StatelessWidget {
  const MiniFlag({super.key, required this.languageCode, this.size = 22});

  final String languageCode;
  final double size;

  @override
  Widget build(BuildContext context) {
    final code = languageCode.toLowerCase();
    final painter = _flagPainters[code];

    final Widget face = painter == null
        ? _NeutralFlag(code: code, size: size)
        : CustomPaint(size: Size.square(size), painter: painter);

    return SizedBox(
      width: size,
      height: size,
      child: ClipOval(
        child: Stack(
          fit: StackFit.expand,
          children: [
            face,
            // внутренний контур .20
            DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.ink.withValues(alpha: 0.20), width: 1),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final Map<String, CustomPainter> _flagPainters = {
  'en': _GbPainter(),
  'gb': _GbPainter(),
  'pt': _PtPainter(),
  'de': _DePainter(),
  'es': _EsPainter(),
  'fr': _FrPainter(),
};

class _NeutralFlag extends StatelessWidget {
  const _NeutralFlag({required this.code, required this.size});
  final String code;
  final double size;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColors.faintInk,
      child: Center(
        child: Text(
          (code.isEmpty ? '?' : code).toUpperCase(),
          style: AppText.badge.copyWith(fontSize: size * 0.38, color: AppColors.secondary),
        ),
      ),
    );
  }
}

// ── painters ── (упрощённые версии из кадров §4б)

class _GbPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height;
    canvas.drawRect(Offset.zero & s, Paint()..color = FlagPalette.gbNavy);
    final white = Paint()..color = FlagPalette.gbWhite;
    final red = Paint()..color = FlagPalette.gbRed;
    final barW = w * 0.18, barR = w * 0.09;
    // белый крест
    canvas.drawRect(Rect.fromLTWH(0, h / 2 - barW / 2, w, barW), white);
    canvas.drawRect(Rect.fromLTWH(w / 2 - barW / 2, 0, barW, h), white);
    // красный крест поверх, тоньше
    canvas.drawRect(Rect.fromLTWH(0, h / 2 - barR / 2, w, barR), red);
    canvas.drawRect(Rect.fromLTWH(w / 2 - barR / 2, 0, barR, h), red);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class _PtPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height;
    canvas.drawRect(Rect.fromLTWH(0, 0, w * 0.4, h), Paint()..color = FlagPalette.ptGreen);
    canvas.drawRect(Rect.fromLTWH(w * 0.4, 0, w * 0.6, h), Paint()..color = FlagPalette.ptRed);
    canvas.drawCircle(Offset(w * 0.4, h * 0.5), w * 0.16, Paint()..color = FlagPalette.ptYellow);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class _DePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height, t = h / 3;
    canvas.drawRect(Rect.fromLTWH(0, 0, w, t), Paint()..color = FlagPalette.deBlack);
    canvas.drawRect(Rect.fromLTWH(0, t, w, t), Paint()..color = FlagPalette.deRed);
    canvas.drawRect(Rect.fromLTWH(0, 2 * t, w, t), Paint()..color = FlagPalette.deGold);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class _EsPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height;
    canvas.drawRect(Offset.zero & s, Paint()..color = FlagPalette.esRed);
    canvas.drawRect(Rect.fromLTWH(0, h * 0.25, w, h * 0.5), Paint()..color = FlagPalette.esGold);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}

class _FrPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size s) {
    final w = s.width, h = s.height, t = w / 3;
    canvas.drawRect(Rect.fromLTWH(0, 0, t, h), Paint()..color = FlagPalette.frBlue);
    canvas.drawRect(Rect.fromLTWH(t, 0, t, h), Paint()..color = FlagPalette.frWhite);
    canvas.drawRect(Rect.fromLTWH(2 * t, 0, t, h), Paint()..color = FlagPalette.frRed);
  }

  @override
  bool shouldRepaint(covariant CustomPainter old) => false;
}
