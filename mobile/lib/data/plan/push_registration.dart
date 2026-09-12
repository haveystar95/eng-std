import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter/services.dart';

import '../api_client.dart';

/// РЕГИСТРАЦИЯ PUSH-ТОКЕНА (наряд PLAN-UI-3 §4).
///
/// После разрешения на уведомления телефон просит у iOS APNs-токен (канал `com.denis.engstd/push`,
/// AppDelegate) и отдаёт его серверу `PUT /devices/push-token`. У бесплатной Personal Team нет
/// entitlement `aps-environment`: iOS отвечает ошибкой — это ожидаемо, строка уходит в лог и
/// никуда больше. Человек об этом не узнаёт: уведомления, которые можно посчитать заранее, телефон
/// ставит себе сам (`plan_reminder_rules.dart`).
class PushRegistration {
  PushRegistration(this._api, {this.channel = const MethodChannel('com.denis.engstd/push')});

  final ApiClient _api;
  final MethodChannel channel;

  Future<void> register({String? locale, String? timezone}) async {
    channel.setMethodCallHandler((call) async {
      switch (call.method) {
        case 'token':
          final token = '${call.arguments}';
          debugPrint('[push] token ${token.length} hex');
          try {
            await _api.putPushToken(token: token, locale: locale, timezone: timezone);
          } catch (e) {
            debugPrint('[push] put token: $e');
          }
        case 'failed':
          debugPrint('[push] no APNs token: ${call.arguments}');
      }
    });
    try {
      await channel.invokeMethod<void>('register');
    } catch (e) {
      debugPrint('[push] register: $e');
    }
  }
}
