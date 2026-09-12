import 'package:flutter/material.dart';
import 'package:flutter/scheduler.dart';

import '../theme/theme.dart';

/// ЖИВОЙ ПРЕЛОАДЕР ПЛАНА — кадры 22-4a (на бумаге) и 22-5a (на угольной плите).
///
/// Три узла 34 зажигаются по одному, в каждый влетает карточка-слово и складывается в него, линия
/// между узлами дорисовывается, хвост тянется к следующему; под ними три строки статуса по кругу.
/// Тайминги — `@keyframes om-pre-*` канвы один в один, в процентах цикла:
///
/// | ключ            | цикл  | задержки         | кадры                                              | кривая                  |
/// |-----------------|-------|------------------|----------------------------------------------------|-------------------------|
/// | `om-pre-node`   | 2.4 с | 0 / 0.45 / 0.9 с | 0–8 % scale .4 op 0 → 18–100 % scale 1 op 1        | cubic(.34, 1.4, .5, 1)  |
/// | `om-pre-card`   | 2.4 с | 0 / 0.45 / 0.9 с | 0–10 % (26,−16) 8° op 0 → 24 % (0,0) op 1 → 34–100 % (0,4) op 0 | cubic(.4, 0, .2, 1) |
/// | `om-pre-link`   | 2.4 с | 0 / 0.45 с       | 0–20 % scaleX 0 → 34–100 % scaleX 1                | cubic(.4, 0, .2, 1)     |
/// | `om-pre-tail`   | 2.4 с | 0                | 0–72 % scaleX 0 → 88–100 % scaleX 1                | cubic(.4, 0, .2, 1)     |
/// | `om-pre-line1…3`| 6 с   | 0                | 1: 0–28 % op 1 → 34 % 0; 2: 32 % 0 → 38–62 % 1 → 68 % 0; 3: 66 % 0 → 72–94 % 1 → 99 % 0 | linear |
///
/// Кривая CSS действует между соседними ключами — так же и здесь ([Keyframes]). До своей задержки
/// узел стоит в состоянии 0 % (погашен): в CSS он на эти 0.45 с показывался бы целым, и это
/// артефакт `animation-delay`, а не замысел «зажигаются по одному».
///
/// Под «уменьшением движения» (и в снимках) — неподвижный конечный кадр: три узла, линии дорисованы,
/// первая строка статуса.
class PlanPreloader extends StatefulWidget {
  const PlanPreloader({super.key, required this.lines, this.onDark = false});

  /// Три строки статуса — вызывающий (в `lib/ui/` строк нет).
  final List<String> lines;

  /// 22-5a: на угольной плите — третий узел, карточки и строки бумажные.
  final bool onDark;

  @override
  State<PlanPreloader> createState() => _PlanPreloaderState();
}

class _PlanPreloaderState extends State<PlanPreloader> with SingleTickerProviderStateMixin {
  /// Время от появления на экране. Не зацикленный контроллер: циклы 2.4 с и 6 с считаются остатком
  /// от деления, и задержка «до первого зажигания» случается ровно один раз.
  late final Ticker _ticker = createTicker((elapsed) => setState(() => _elapsed = elapsed));
  Duration _elapsed = Duration.zero;

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

  @override
  Widget build(BuildContext context) {
    final still = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    final seconds = still ? null : _elapsed.inMicroseconds / Duration.microsecondsPerSecond;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Padding(padding: const EdgeInsets.only(top: 18), child: _Nodes(seconds: seconds, onDark: widget.onDark)),
        const SizedBox(height: 22),
        _Lines(seconds: seconds, lines: widget.lines, onDark: widget.onDark),
      ],
    );
  }
}

/// Ключи CSS: значения в долях цикла, кривая между соседними ключами.
class Keyframes {
  const Keyframes(this.stops, [this.curve = Curves.linear]);

  final List<(double, double)> stops;
  final Curve curve;

  double at(double progress) {
    if (progress <= stops.first.$1) return stops.first.$2;
    for (var i = 1; i < stops.length; i++) {
      final (b, vb) = stops[i];
      if (progress <= b) {
        final (a, va) = stops[i - 1];
        final local = b == a ? 1.0 : (progress - a) / (b - a);

        return va + (vb - va) * curve.transform(local);
      }
    }

    return stops.last.$2;
  }
}

const _grow = Cubic(.34, 1.4, .5, 1);
const _ease = Cubic(.4, 0, .2, 1);

const _nodeScale = Keyframes([(0, .4), (.08, .4), (.18, 1), (1, 1)], _grow);
const _nodeOpacity = Keyframes([(0, 0), (.08, 0), (.18, 1), (1, 1)], _grow);
const _cardDx = Keyframes([(0, 26), (.10, 26), (.24, 0), (.34, 0), (1, 0)], _ease);
const _cardDy = Keyframes([(0, -16), (.10, -16), (.24, 0), (.34, 4), (1, 4)], _ease);
const _cardTurn = Keyframes([(0, 8), (.10, 8), (.24, 0), (1, 0)], _ease);
const _cardOpacity = Keyframes([(0, 0), (.10, 0), (.24, 1), (.34, 0), (1, 0)], _ease);
const _link = Keyframes([(0, 0), (.20, 0), (.34, 1), (1, 1)], _ease);
const _tail = Keyframes([(0, 0), (.72, 0), (.88, 1), (1, 1)], _ease);
const _line1 = Keyframes([(0, 1), (.28, 1), (.34, 0), (1, 0)]);
const _line2 = Keyframes([(0, 0), (.32, 0), (.38, 1), (.62, 1), (.68, 0), (1, 0)]);
const _line3 = Keyframes([(0, 0), (.66, 0), (.72, 1), (.94, 1), (.99, 0), (1, 0)]);

/// Доля цикла [period] для элемента с задержкой [delay]; null — неподвижный конечный кадр, а до
/// задержки — ключ 0 %.
double _phase(double? seconds, double period, double delay) {
  if (seconds == null) return 1;
  final t = seconds - delay;
  if (t < 0) return 0;

  return (t % period) / period;
}

class _Nodes extends StatelessWidget {
  const _Nodes({required this.seconds, required this.onDark});

  final double? seconds;
  final bool onDark;

  static const double nodeSize = 34;
  static const double barWidth = 30;

  @override
  Widget build(BuildContext context) {
    final lastNode = onDark ? AppColors.paper : AppColors.ink;
    final tailColor = onDark ? AppColors.paperTrack : AppColors.routeQuiet;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _Node(color: AppColors.brassInk, progress: _phase(seconds, 2.4, 0), onDark: onDark),
        _Bar(color: AppColors.brassInk, scale: _linkScale(0)),
        _Node(color: AppColors.verdictKnown, progress: _phase(seconds, 2.4, .45), onDark: onDark),
        _Bar(color: AppColors.brassInk, scale: _linkScale(.45)),
        _Node(color: lastNode, progress: _phase(seconds, 2.4, .9), onDark: onDark),
        _Bar(color: tailColor, scale: _tail.at(_phase(seconds, 2.4, 0))),
      ],
    );
  }

  double _linkScale(double delay) => _link.at(_phase(seconds, 2.4, delay));
}

class _Node extends StatelessWidget {
  const _Node({required this.color, required this.progress, required this.onDark});

  final Color color;
  final double progress;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final card = Transform.translate(
      offset: Offset(_cardDx.at(progress), _cardDy.at(progress)),
      child: Transform.rotate(
        angle: _cardTurn.at(progress) * 3.14159265 / 180,
        child: Opacity(
          opacity: _cardOpacity.at(progress).clamp(0.0, 1.0),
          child: Container(
            width: 34,
            height: 18,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: onDark ? AppColors.paper : AppColors.surfaceRaised,
              borderRadius: BorderRadius.circular(5),
              boxShadow: [BoxShadow(color: AppColors.ink.withValues(alpha: .18), blurRadius: 8, offset: const Offset(0, 3))],
            ),
            child: Container(
              width: 16,
              height: 2,
              decoration: BoxDecoration(
                color: AppColors.ink.withValues(alpha: onDark ? .40 : .45),
                borderRadius: BorderRadius.circular(1),
              ),
            ),
          ),
        ),
      ),
    );

    return SizedBox(
      width: _Nodes.nodeSize,
      height: _Nodes.nodeSize,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            child: Opacity(
              opacity: _nodeOpacity.at(progress).clamp(0.0, 1.0),
              child: Transform.scale(
                scale: _nodeScale.at(progress),
                child: DecoratedBox(decoration: BoxDecoration(shape: BoxShape.circle, color: color)),
              ),
            ),
          ),
          Positioned(left: 6, top: -14, child: card),
        ],
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.color, required this.scale});

  final Color color;
  final double scale;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: _Nodes.barWidth,
    height: 1.5,
    child: Align(
      alignment: Alignment.centerLeft,
      child: FractionallySizedBox(widthFactor: scale.clamp(0.0, 1.0), heightFactor: 1, child: ColoredBox(color: color)),
    ),
  );
}

class _Lines extends StatelessWidget {
  const _Lines({required this.seconds, required this.lines, required this.onDark});

  final double? seconds;
  final List<String> lines;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final p = seconds == null ? 0.0 : (seconds! % 6) / 6;
    final fades = [_line1, _line2, _line3];
    final style = TextStyle(
      fontFamily: AppFonts.inter,
      fontSize: 15,
      height: 1.4,
      color: onDark ? AppColors.paper.withValues(alpha: .62) : AppColors.secondary,
    );

    return SizedBox(
      width: 290,
      height: 22,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          for (var i = 0; i < lines.length && i < 3; i++)
            Positioned.fill(
              child: Opacity(
                opacity: fades[i].at(p).clamp(0.0, 1.0),
                child: Text(lines[i], textAlign: TextAlign.center, maxLines: 1, softWrap: false, overflow: TextOverflow.visible, style: style),
              ),
            ),
        ],
      ),
    );
  }
}
