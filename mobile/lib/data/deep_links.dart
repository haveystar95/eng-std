import 'dart:async';

import 'package:flutter/foundation.dart' show ValueNotifier, debugPrint;
import 'package:flutter/services.dart';

/// ССЫЛКИ ВНУТРЬ ПРИЛОЖЕНИЯ — `engstd://…` (наряд DAY-UI).
///
/// Штатный вход в кабинет дня: по ней идут уведомления плана, QA-прогон (`simctl openurl`) и
/// любой внешний переход. Маршруты, которые понимает [DayLink.parse]:
///
///   engstd://plan/day/{dayId}            — кабинет дня по id дня текущего плана
///   engstd://plan/{planId}/day/{number}  — кабинет дня по номеру
///   engstd://plan/current                — кабинет текущего дня текущего плана
///
/// Нативная сторона (`AppDelegate.swift`) шлёт URL в канал и держит ссылку холодного старта, пока
/// Dart её не заберёт ([init]). Один потребитель — хост планового контура — читает [pending].
class DeepLinks {
  static const _channel = MethodChannel('com.denis.engstd/links');

  /// Ссылка, которую ещё никто не открыл. ValueNotifier, а не поток: значение должно пережить
  /// холодный старт, когда хост ещё не смонтирован.
  static final ValueNotifier<Uri?> pending = ValueNotifier<Uri?>(null);

  static bool _ready = false;

  static Future<void> init() async {
    if (_ready) return;
    _ready = true;
    _channel.setMethodCallHandler((call) async {
      if (call.method == 'open' && call.arguments is String) {
        pending.value = Uri.tryParse(call.arguments as String);
      }
    });
    try {
      final initial = await _channel.invokeMethod<String>('initial');
      if (initial != null) pending.value = Uri.tryParse(initial);
    } catch (e) {
      // Канала нет (тест, другая платформа) — ссылок холодного старта тоже нет.
      debugPrint('[links] initial: $e');
    }
  }
}

/// Куда ведёт ссылка на кабинет дня.
class DayLink {
  const DayLink({this.planId, this.dayId, this.number});

  final String? planId;
  final String? dayId;
  final int? number;

  /// null — ссылка не про день плана.
  static DayLink? parse(Uri uri) {
    if (uri.scheme != 'engstd') return null;
    final parts = [
      if (uri.host.isNotEmpty) uri.host,
      ...uri.pathSegments.where((s) => s.isNotEmpty),
    ];
    if (parts.isEmpty || parts.first != 'plan') return null;
    if (parts.length == 2 && parts[1] == 'current') return const DayLink();
    if (parts.length == 3 && parts[1] == 'day') return DayLink(dayId: parts[2]);
    if (parts.length == 4 && parts[2] == 'day') {
      final n = int.tryParse(parts[3]);
      if (n == null) return null;
      return DayLink(planId: parts[1], number: n);
    }
    return null;
  }

  static Uri forDay(String dayId) => Uri.parse('engstd://plan/day/$dayId');
  static Uri forNumber(String planId, int number) => Uri.parse('engstd://plan/$planId/day/$number');
  static Uri get current => Uri.parse('engstd://plan/current');
}
