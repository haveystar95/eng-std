import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter/services.dart';

import '../api_client.dart';

/// РЕГИСТРАЦИЯ PUSH-ТОКЕНА (наряд PLAN-UI-3 §4 и доработка).
///
/// После разрешения на уведомления телефон просит у iOS APNs-токен (канал `com.denis.engstd/push`,
/// AppDelegate) и отдаёт его серверу `PUT /devices/push-token`. Ответ — `push_enabled`: доставляет ли
/// сервер письма сам. Он уходит в [onPushEnabled], и по нему телефон либо снимает свои локальные
/// напоминания (true), либо продолжает их ставить (false).
///
/// У бесплатной Personal Team нет entitlement `aps-environment`: iOS отвечает ошибкой — это ожидаемо,
/// строка уходит в лог, `push_enabled` остаётся false, и локальные напоминания стоят.
class PushRegistration {
  PushRegistration(this._api, {this.channel = const MethodChannel('com.denis.engstd/push')});

  final ApiClient _api;
  final MethodChannel channel;

  Future<void> register({String? locale, String? timezone, required Future<void> Function(bool enabled) onPushEnabled}) async {
    channel.setMethodCallHandler((call) async {
      switch (call.method) {
        case 'token':
          final token = '${call.arguments}';
          debugPrint('[push] token ${token.length} hex');
          try {
            final enabled = await _api.putPushToken(token: token, locale: locale, timezone: timezone);
            debugPrint('[push] push_enabled: $enabled');
            await onPushEnabled(enabled);
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
