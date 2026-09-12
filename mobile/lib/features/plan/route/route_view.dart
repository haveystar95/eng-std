/// ЧТО НАРИСОВАТЬ НА МАРШРУТЕ — один язык для таба (21-2 … 21-14), превью (22-4b), витрины (21-1) и
/// плиты во время сборки (22-5a). Каждый экран переводит СВОИ данные в эти строки, а рисует их один
/// виджет [RouteLine]; поэтому шаг, линия и узлы не могут разойтись между экранами.
library;

import 'package:flutter/widgets.dart';

import '../../../ui/plan_marks.dart';

/// Три состояния дня на табе — по кадру, без прозрачности целиком: цвет текста и вуаль на картинке.
enum RouteDayTone {
  /// Сегодняшний: латунная обводка 2, заголовок 600, «сегодня» латунью справа.
  current,

  /// Пройденный: заголовок 500, галка шалфеем справа, этапы .7.
  passed,

  /// Запертый: вуаль ground .6 на картинке, текст .4.
  locked,

  /// Превью и витрина: полный контраст, дни не заперты и не пройдены — прогресса ещё нет.
  plain,
}

/// Узел под днём.
enum RouteChildMark {
  /// Этап пройден — шалфей с галкой 10.
  done,

  /// Текущий этап — латунь с кольцом 3.
  current,

  /// Этап впереди — контур 1.5.
  locked,

  /// Цель дня в превью и на витрине — латунная точка, без кольца.
  goal,
}

/// Строка под днём: этап на табе, цель в превью.
class RouteChildView {
  const RouteChildView({required this.label, required this.mark});

  final String label;
  final RouteChildMark mark;
}

/// Что стоит в круге 56.
sealed class RouteCircle {
  const RouteCircle();
}

/// Фото сцены: адрес кропа под плотность экрана и тон, которым круг залит, пока фото в пути.
class RoutePhoto extends RouteCircle {
  const RoutePhoto({this.url, this.tone});

  final String? url;
  final Color? tone;
}

/// Своя иллюстрация дня повторения / репетиции / события (`assets/system-days/`).
class RouteSystemMark extends RouteCircle {
  const RouteSystemMark(this.day);

  final PlanSystemDay day;
}

/// Один день маршрута.
class RouteDayView {
  const RouteDayView({
    required this.title,
    required this.circle,
    required this.tone,
    this.meta,
    this.children = const [],
    this.trailingToday,
    this.passedLine = false,
    this.onTap,
    this.anchor,
  });

  /// Ключ узла дня — по нему таб прокручивает маршрут к дню из уведомления.
  final Key? anchor;

  /// «День 2 · Приём у врача».
  final String title;

  /// «10 сентября · пройден · 17 мин» — под заголовком, не на линии. Null — строки нет.
  final String? meta;
  final RouteCircle circle;
  final RouteDayTone tone;
  final List<RouteChildView> children;

  /// «сегодня» латунью справа — только у текущего дня.
  final String? trailingToday;

  /// До узла дня дошли — отрезок в него шалфеем (см. `route_fill.dart`).
  final bool passedLine;

  /// Тап по дню: кабинет, или строка «откроется после дня N» у запертого — решает экран.
  final VoidCallback? onTap;
}

/// Мишень события — последний узел маршрута; на экране она одна.
class RouteEventView {
  const RouteEventView({required this.title, required this.meta, this.passed = false, this.dated = true});

  /// «Приём».
  final String title;

  /// «17 сентября · четверг» или «указать дату».
  final String meta;

  /// Событие прошло — мишень залита ink и помечена галкой (21-14).
  final bool passed;

  /// Без даты — пунктир вокруг круга.
  final bool dated;
}
