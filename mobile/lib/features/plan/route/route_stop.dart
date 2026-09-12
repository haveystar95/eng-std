import 'package:flutter/material.dart';

/// ОДНА ОСТАНОВКА МАРШРУТА — узел на линии и то, что стоит справа от него (канва PLAN-DES-3).
///
/// Геометрия из кода кадров, а не из подписи: центры соседних узлов — через 44, узел дня 56, узел
/// этапа 14, линия 2 проходит за узлами через их центры (`left:27`), текст — с 70. Узел дня занимает
/// над своим центром 28, узел этапа — 22, но этап СРАЗУ ПОСЛЕ дня садится на 16 (и этап перед днём
/// оставляет под собой 16), иначе шаг «картинка → первый этап» вышел бы 50, а не 44.
///
/// Остановка не сжимает текст ради шага: длинный заголовок или цель переносятся, остановка растёт
/// вниз, и линия дорастает вместе с ней — обе половины линии рисует сама остановка, поэтому разрыва
/// не бывает при любой высоте (кадр 21-6: «ни одна строка не сжата ради длины»).
class RouteStop extends StatelessWidget {
  const RouteStop({
    super.key,
    required this.big,
    required this.node,
    required this.child,
    this.previousBig,
    this.nextBig,
    this.incoming,
    this.outgoing,
    this.onTap,
    this.semanticsLabel,
  });

  /// Узел дня или события (56) — иначе узел этапа или цели (14).
  final bool big;

  /// Соседи: null — соседа нет (первая / последняя остановка).
  final bool? previousBig;
  final bool? nextBig;

  /// Цвет линии над центром и под ним; null — линии с этой стороны нет.
  final Color? incoming;
  final Color? outgoing;

  final Widget node;
  final Widget child;
  final VoidCallback? onTap;
  final String? semanticsLabel;

  static const double step = 44;
  static const double bigNode = 56;
  static const double smallNode = 14;
  static const double lineWidth = 2;
  static const double textLeft = 70;

  /// Воздух между двумя узлами дней подряд (день без узлов этапов, событие после дня без этапов):
  /// круги 56 не должны касаться — 24 чистого воздуха, как было у узла без этапов.
  static const double bigToBigAir = 24;

  /// Над центром.
  double get _above {
    if (big) return bigNode / 2;

    return previousBig == true ? step - bigNode / 2 : step / 2;
  }

  /// Под центром — минимум; растёт, когда текст длиннее.
  double get _below {
    if (big) return nextBig == true ? bigNode / 2 + bigToBigAir : bigNode / 2;

    return nextBig == true ? step - bigNode / 2 : step / 2;
  }

  @override
  Widget build(BuildContext context) {
    final above = _above;
    final below = _below;
    // Текст стоит так, чтобы его первая строка (узел дня — весь блок 56) была на уровне центра.
    final textBox = big ? bigNode : _smallLine;
    final content = Padding(
      padding: EdgeInsets.only(top: above - textBox / 2),
      child: ConstrainedBox(
        constraints: BoxConstraints(minHeight: textBox / 2 + below),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(width: textLeft),
            Expanded(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: textBox),
                child: Align(alignment: Alignment.centerLeft, child: child),
              ),
            ),
          ],
        ),
      ),
    );

    final stack = Stack(
      children: [
        if (incoming != null)
          Positioned(left: bigNode / 2 - lineWidth / 2, top: 0, height: above, width: lineWidth, child: ColoredBox(color: incoming!)),
        if (outgoing != null)
          Positioned(left: bigNode / 2 - lineWidth / 2, top: above, bottom: 0, width: lineWidth, child: ColoredBox(color: outgoing!)),
        Positioned(
          left: big ? 0 : bigNode / 2 - smallNode / 2,
          top: above - (big ? bigNode : smallNode) / 2,
          child: node,
        ),
        content,
      ],
    );

    if (onTap == null) return stack;

    return Semantics(
      button: true,
      label: semanticsLabel,
      child: GestureDetector(behavior: HitTestBehavior.opaque, onTap: onTap, child: stack),
    );
  }

  /// Высота одной строки метки этапа — 15 / 1.3 (кадр: блок 22 по центру узла).
  static const double _smallLine = 22;
}
