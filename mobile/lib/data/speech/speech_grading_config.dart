import 'package:flutter/foundation.dart';

/// ПОРОГИ ЗАЧЁТА РЕЧИ И ТАБЛИЦА АББРЕВИАТУР — С СЕРВЕРА, НЕ ИЗ КОДА ЭКРАНА (наряд SPEECH-2,
/// Ч.3.3 и Ч.4.2).
///
/// Приезжают в блоке `speech` контракта сессии — и учебной, и плановой. Второго словаря на клиенте
/// НЕТ и заводить его нельзя: две таблицы разъезжаются молча, и первым признаком расхождения будет
/// «телефон сказал „Не то“, а сервер в ту же секунду засчитал» — ровно поломка, которую чинил
/// DAY-GATE-1.
///
/// Значения по умолчанию здесь — не второе мнение, а то, чем карточка судит, пока пейлоад не
/// приехал (офлайн-запуск, старый кэш). Они совпадают с дефолтами `config/learning.php`, и
/// расхождение между ними — баг, а не настройка.
@immutable
class SpeechGradingConfig {
  const SpeechGradingConfig({
    this.readAloud = 0.9,
    this.recallRest = 0.6,
    this.wholeLine = 0.7,
    this.almostFloor = 0.5,
    this.fillerAllowance = 1,
    this.normalizationVersion = '',
    this.normalization = const {},
  });

  /// Фраза НА ЭКРАНЕ: доля её слов, которую надо произнести.
  final double readAloud;

  /// Текста НЕТ: доля ОСТАЛЬНЫХ слов реплики при обязательном ключе.
  final double recallRest;

  /// Реплика без ключа — целиком, этим порогом.
  final double wholeLine;

  /// Ниже этой доли «почти» превращается в «не то».
  final double almostFloor;

  /// Сколько слов-связок прощается сверх порога при чтении с экрана.
  final int fillerAllowance;

  /// Версия таблицы — попадает в служебную строку: когда телефон и сервер разойдутся в вердикте,
  /// первым вопросом будет «одну ли таблицу они читали».
  final String normalizationVersion;

  /// Что распознаватель ПИШЕТ → чем это было. Ключи канонизированы так же, как канонизирует
  /// грейдер: нижний регистр, без знаков, слова через один пробел.
  final Map<String, String> normalization;

  static const empty = SpeechGradingConfig();

  factory SpeechGradingConfig.fromJson(Map<String, dynamic>? json) {
    if (json == null) return empty;
    final thresholds = (json['thresholds'] as Map?)?.cast<String, dynamic>() ?? const {};
    final table = (json['normalization'] as Map?)?.cast<String, dynamic>() ?? const {};
    final entries = (table['entries'] as Map?)?.cast<String, dynamic>() ?? const {};

    return SpeechGradingConfig(
      readAloud: (thresholds['read_aloud_coverage'] as num?)?.toDouble() ?? 0.9,
      recallRest: (thresholds['recall_rest_coverage'] as num?)?.toDouble() ?? 0.6,
      wholeLine: (thresholds['whole_line_coverage'] as num?)?.toDouble() ?? 0.7,
      almostFloor: (thresholds['almost_floor'] as num?)?.toDouble() ?? 0.5,
      fillerAllowance: (thresholds['filler_allowance'] as num?)?.toInt() ?? 1,
      normalizationVersion: (table['version'] as String?) ?? '',
      normalization: {
        for (final e in entries.entries)
          if (e.value is String) e.key: e.value as String,
      },
    );
  }

  Map<String, dynamic> toJson() => {
    'thresholds': {
      'read_aloud_coverage': readAloud,
      'recall_rest_coverage': recallRest,
      'whole_line_coverage': wholeLine,
      'almost_floor': almostFloor,
      'filler_allowance': fillerAllowance,
    },
    'normalization': {'version': normalizationVersion, 'entries': normalization},
  };
}
