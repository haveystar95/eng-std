import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

import 'package:eng_std/theme/theme.dart';

/// ВОЛНА НАД ПОЛЕМ ЦЕЛИ — от громкости, а не орнамент (кадр 22-1, `@keyframes om-wave`).
///
/// 26 столбиков 3 px через 3, высоты и циклы — из кода кадра: каждый дышит своим периодом
/// 0.5–1.5 с `ease-in-out` со своей задержкой (scaleY .35 → 1 → .35). Но размах дыхания умножен на
/// настоящий уровень звука: молчит человек — столбики лежат на .35, говорит громче — поднимаются
/// выше. Под «уменьшением движения» столбики стоят по уровню без дыхания.
class DictationWave extends StatefulWidget {
  const DictationWave({super.key, required this.level});

  /// 0…1.
  final double level;

  /// (высота, период с, задержка с) — 26 столбиков кадра по порядку.
  static const bars = <(double, double, double)>[
    (10, .50, 0), (18, .90, .65), (26, 1.30, .45), (14, .60, .25), (22, 1.00, .05), (30, 1.40, .70),
    (16, .70, .50), (11, 1.10, .30), (20, 1.50, .10), (24, .80, .75), (13, 1.20, .55), (28, .50, .35),
    (17, .90, .15), (10, 1.30, .80), (21, .60, .60), (25, 1.00, .40), (12, 1.40, .20), (19, .70, 0),
    (29, 1.10, .65), (15, 1.50, .45), (23, .80, .25), (11, 1.20, .05), (27, .50, .70), (14, .90, .50),
    (20, 1.30, .30), (16, .60, .10),
  ];

  @override
  State<DictationWave> createState() => _DictationWaveState();
}

class _DictationWaveState extends State<DictationWave> with SingleTickerProviderStateMixin {
  late final Ticker _ticker = createTicker((e) => setState(() => _t = e.inMicroseconds / 1e6));
  double _t = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final still = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (still && _ticker.isActive) _ticker.stop();
    if (!still && !_ticker.isActive) _ticker.start();
  }

  @override
  void dispose() {
    _ticker.dispose();
    super.dispose();
  }

  /// `om-wave`: 0 % и 100 % — .35, 50 % — 1, `ease-in-out` на каждой половине.
  static double breath(double seconds, double period, double delay) {
    final t = seconds - delay;
    if (t < 0) return .35;
    final p = (t % period) / period;
    final half = p < .5 ? p * 2 : (1 - p) * 2;

    return .35 + .65 * Curves.easeInOut.transform(half);
  }

  @override
  Widget build(BuildContext context) {
    final still = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    final level = widget.level.clamp(0.0, 1.0);

    return SizedBox(
      height: 30,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (var i = 0; i < DictationWave.bars.length; i++) ...[
            if (i > 0) const SizedBox(width: 3),
            () {
              final (h, period, delay) = DictationWave.bars[i];
              final breathing = still ? 1.0 : breath(_t, period, delay);
              final scale = .35 + (breathing - .35) * level;

              return Container(
                width: 3,
                height: math.max(3, h * scale),
                decoration: BoxDecoration(color: AppColors.brassInk, borderRadius: BorderRadius.circular(2)),
              );
            }(),
          ],
        ],
      ),
    );
  }
}

/// ПЕЧАТАЮЩИЙСЯ ТЕКСТ ГОЛОСА (кадр 22-1): буква появляется за 40 мс, последнее слово серое — оно
/// ещё уточняется, и чернеет за 120 мс, когда за ним пришло следующее.
class DictatedText extends StatefulWidget {
  const DictatedText({super.key, required this.base, required this.heard, required this.style});

  /// Написанное до записи — стоит сразу, чёрным.
  final String base;

  /// Сказанное за эту запись.
  final String heard;
  final TextStyle style;

  static const Duration perLetter = Duration(milliseconds: 40);
  static const Duration settle = Duration(milliseconds: 120);

  @override
  State<DictatedText> createState() => _DictatedTextState();
}

class _DictatedTextState extends State<DictatedText> with TickerProviderStateMixin {
  late final Ticker _typing = createTicker(_tick);
  late final AnimationController _ink = AnimationController(vsync: this, duration: DictatedText.settle, value: 1);
  Duration _lastTick = Duration.zero;
  Duration _carry = Duration.zero;
  int _shown = 0;
  int _settledWords = 0;

  @override
  void initState() {
    super.initState();
    _shown = 0;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) _shown = widget.heard.length;
    _ensureTyping();
  }

  @override
  void didUpdateWidget(DictatedText old) {
    super.didUpdateWidget(old);
    if (widget.heard.length < _shown) _shown = widget.heard.length; // распознавание переписало хвост
    final settled = _words(widget.heard).length - 1;
    if (settled > _settledWords) {
      _settledWords = settled;
      _ink.forward(from: 0);
    }
    _ensureTyping();
  }

  void _ensureTyping() {
    if (_shown < widget.heard.length && !_typing.isActive) {
      _lastTick = Duration.zero;
      _typing.start();
    }
  }

  void _tick(Duration elapsed) {
    _carry += elapsed - _lastTick;
    _lastTick = elapsed;
    var grew = false;
    while (_carry >= DictatedText.perLetter && _shown < widget.heard.length) {
      _carry -= DictatedText.perLetter;
      _shown++;
      grew = true;
    }
    if (_shown >= widget.heard.length) {
      _typing.stop();
      _carry = Duration.zero;
    }
    if (grew) setState(() {});
  }

  static List<String> _words(String s) => s.trim().isEmpty ? const [] : s.trim().split(RegExp(r'\s+'));

  @override
  void dispose() {
    _typing.dispose();
    _ink.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final visible = widget.heard.substring(0, _shown.clamp(0, widget.heard.length));
    final cut = visible.lastIndexOf(' ');
    final head = cut < 0 ? '' : visible.substring(0, cut + 1);
    final tail = cut < 0 ? visible : visible.substring(cut + 1);
    final prefix = widget.base.isEmpty ? '' : '${widget.base} ';

    return AnimatedBuilder(
      animation: _ink,
      builder: (context, _) {
        // Слово, ставшее предпоследним, чернеет за 120 мс; всё до него — уже чёрное.
        final lastHead = head.trimRight().lastIndexOf(' ');
        final older = lastHead < 0 ? '' : head.substring(0, lastHead + 1);
        final settling = lastHead < 0 ? head : head.substring(lastHead + 1);

        return Text.rich(
          TextSpan(
            style: widget.style,
            children: [
              TextSpan(text: prefix + older),
              TextSpan(
                text: settling,
                style: TextStyle(color: Color.lerp(AppColors.dictationPending, widget.style.color, _ink.value)),
              ),
              TextSpan(text: tail, style: const TextStyle(color: AppColors.dictationPending)),
            ],
          ),
        );
      },
    );
  }
}
