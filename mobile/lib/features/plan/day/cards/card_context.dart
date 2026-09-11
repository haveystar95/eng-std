import 'package:flutter/material.dart';

import '../../../../data/plan/day_session.dart';
import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/day_contract.dart';
import '../day_voice.dart';
import '../speech_attempt.dart';

/// ЧТО ЗНАЕТ КАЖДАЯ КАРТОЧКА ДНЯ сверх своего пейлоада: сессия (ответы, повторы), голос,
/// уровень, язык и роль собеседника, номер дня возврата.
class DayCardContext {
  const DayCardContext({
    required this.session,
    required this.voice,
    required this.level,
    required this.targetLang,
    required this.localeId,
    required this.partnerRole,
    required this.returnDay,
    required this.dayWords,
    required this.autoPronounce,
    required this.speech,
    this.returnedFromDay,
  });

  final DaySession session;
  final DayVoice voice;
  final PlanLevel level;
  final String targetLang;

  /// Локаль распознавания — `en_US`.
  final String localeId;

  /// «Врач» — роль собеседника на языке поддержки, для лейблов «ВРАЧ ГОВОРИТ» и «ВРАЧ».
  final String partnerRole;

  /// «Вернётся в день N».
  final int returnDay;

  /// Номер дня, из которого вернулась карточка ([DayCard.sourceDayId] → номер), по id дня.
  final int Function(String? dayId)? returnedFromDay;

  /// Слова дня — подсказка распознавателю.
  final List<String> dayWords;
  final bool autoPronounce;

  /// Контроллер микрофона для карточки речи — движок и журнал из провайдеров оболочки.
  final SpeechAttemptController Function(DayCard card) speech;

  /// «ВРАЧ ГОВОРИТ» / «ВРАЧ ОТВЕЧАЕТ» — роль из реплики, если она есть, иначе из сцены.
  String roleOf(DayMessage? m) {
    final r = m?.roleNative?.trim() ?? '';
    return r.isEmpty ? partnerRole : r;
  }
}

/// ОБЩИЙ ИНТЕРФЕЙС КАРТОЧКИ: ответ уходит в сессию, «дальше» — тоже.
abstract class DayCardWidget extends StatefulWidget {
  const DayCardWidget({super.key, required this.card, required this.context, required this.onNext});

  final DayCard card;
  final DayCardContext context;

  /// Карточка закрыта — сессия идёт дальше.
  final VoidCallback onNext;
}
