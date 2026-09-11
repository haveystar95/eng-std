import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../../data/plan/day_rules.dart';
import '../../../data/plan/plan_contract.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_diagnostics.dart';
import '../../../data/speech/speech_recognizer.dart';
import '../../../data/speech/speech_turn.dart';

/// ЧЕМ КОНЧИЛАСЬ ПОПЫТКА ГОЛОСОМ на карточке дня.
enum SpeechAttemptOutcome {
  /// Зачёт — [SpeechAttemptController.transcript] содержит ключ или ≥ порога слов.
  accepted,

  /// Не расслышали / мимо — «Ещё раз».
  retry,
}

/// ОДИН ХОД МИКРОФОНОМ 80 на трёх карточках дня — «произнеси», «повтори вслух», «говорю сам».
///
/// Держит движок ([SpeechTurn]) и состояние кнопки; зачёт считает [DayRules.spokenAccepted] по
/// ключу, тексту и вариантам карточки. Произношение никогда не «неверно»: незачёт — «Не
/// расслышали. Ещё раз», после двух попыток — «Пропустить». Ничего не рисует.
class SpeechAttemptController extends ChangeNotifier {
  SpeechAttemptController({
    required this._recognizer,
    this._diagnostics,
    required this.localeId,
    required this.expected,
    required this.coverage,
    this.key,
    this.variants = const [],
    this.contextualStrings = const [],
  });

  final SpeechRecognizer _recognizer;
  final SpeechDiagnostics? _diagnostics;
  final String localeId;
  final String expected;
  final double coverage;
  final String? key;
  final List<String> variants;
  final List<String> contextualStrings;

  SpeechTurn? _turn;
  RecordState _state = RecordState.idle;
  double _level = 0;
  String _partial = '';
  String _transcript = '';
  int _attempts = 0;
  bool _accepted = false;
  bool _micUnavailable = false;
  SpeechProbe? _probe;
  String? _pendingInjection;
  Timer? _thinking;

  RecordState get state => _state;
  double get level => _level;
  String get partial => _partial;
  String get transcript => _transcript;
  int get attempts => _attempts;
  bool get accepted => _accepted;
  bool get micUnavailable => _micUnavailable;
  SpeechProbe? get probe => _probe;
  bool get canSkip => !_accepted && DayRules.canSkip(attempts: _attempts, micUnavailable: _micUnavailable);
  bool get isListening => _state == RecordState.listening;

  /// Ждать динамик: реплика собеседника ещё звучит.
  void waitForPartner(bool speaking) {
    if (_accepted || _state == RecordState.listening || _state == RecordState.thinking) return;
    _set(speaking ? RecordState.waiting : RecordState.idle);
  }

  bool _disposed = false;

  void _set(RecordState s) {
    if (_disposed || _state == s) return;
    _state = s;
    notifyListeners();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  /// Тап по микрофону: начать запись, второй тап — «Готово».
  Future<SpeechAttemptOutcome?> tap() async {
    if (_state == RecordState.listening) {
      await _turn?.stop();
      return null;
    }
    if (_state != RecordState.idle) return null;
    return listen();
  }

  /// Один ход: запись → 300 мс «думаем» → зачёт или «ещё раз».
  Future<SpeechAttemptOutcome?> listen() async {
    if (_accepted || _state == RecordState.listening || _state == RecordState.thinking) return null;
    AppHaptics.light();
    _partial = '';
    _level = 0;
    _set(RecordState.listening);

    SpeechTurnResult result;
    try {
      final turn = SpeechTurn(_recognizer, diagnostics: _diagnostics);
      _turn = turn;
      if (_pendingInjection case final text?) {
        _pendingInjection = null;
        if (turn.injectTranscript(text)) unawaited(turn.stop());
      }
      result = await turn.listen(
        expected: [if (key != null && key!.trim().isNotEmpty) key!, expected, ...variants],
        localeId: localeId,
        contextualStrings: contextualStrings,
        onPartial: (text) {
          _partial = text;
          _notify();
        },
        // iOS отдаёт децибелы примерно от −2 до 10.
        onLevel: (db) {
          _level = ((db + 2) / 12).clamp(0.0, 1.0);
          _notify();
        },
      );
      if (_turn == turn) _turn = null;
      // Карточка ушла, пока микрофон был открыт, — ход некому отдать.
      if (_disposed) return null;
    } catch (e) {
      _diagnostics?.phaseIs(SpeechPhase.failed, code: 'listen_threw: $e');
      result = const SpeechTurnResult(SpeechTurnOutcome.unavailable);
    }

    // «ДУМАЕМ» 300 мс — столбики гаснут, потом вердикт (23-3b).
    _level = 0;
    _set(RecordState.thinking);
    await Future<void>.delayed(AppMotion.speechThinking);
    if (_disposed) return null;

    switch (result.outcome) {
      case SpeechTurnOutcome.heard:
        _transcript = result.transcript;
        final ok = DayRules.spokenAccepted(
          transcript: result.transcript,
          expected: expected,
          key: key,
          variants: variants,
          coverage: coverage,
        );
        if (ok) {
          _accepted = true;
          _set(RecordState.heard);
          return SpeechAttemptOutcome.accepted;
        }
        _attempts++;
        _set(RecordState.idle);
        return SpeechAttemptOutcome.retry;
      case SpeechTurnOutcome.incomplete:
      case SpeechTurnOutcome.silent:
        _transcript = result.transcript;
        _attempts++;
        _set(RecordState.idle);
        return SpeechAttemptOutcome.retry;
      case SpeechTurnOutcome.unavailable:
        _attempts++;
        _micUnavailable = true;
        unawaited(_refreshProbe());
        _set(RecordState.idle);
        return SpeechAttemptOutcome.retry;
    }
  }

  Future<void> _refreshProbe() async {
    final d = _diagnostics;
    if (d == null) return;
    _probe = await d.refresh(localeId);
    _notify();
  }

  /// ДЕВ-ДВЕРЬ QA — подставить транскрипт вместо голоса (симулятор без микрофона). Идёт тем же
  /// путём, что живая речь: в открытый ход, потом «Готово».
  void inject(String text) {
    final turn = _turn;
    if (turn != null && _state == RecordState.listening) {
      if (turn.injectTranscript(text)) unawaited(turn.stop());
      return;
    }
    _pendingInjection = text;
    unawaited(listen());
  }

  @override
  void dispose() {
    _disposed = true;
    _thinking?.cancel();
    final turn = _turn;
    if (turn != null) {
      unawaited(turn.cancel());
    } else if (_state == RecordState.listening) {
      unawaited(_recognizer.cancel());
    }
    super.dispose();
  }
}

/// ФАБРИКА КОНТРОЛЛЕРА для карточки — движок и журнал из провайдеров, цель из пейлоада.
SpeechAttemptController speechAttemptFor(WidgetRef ref, DayCard card, {required String localeId, List<String> dayWords = const []}) {
  final expected = card.expected.isNotEmpty ? card.expected : card.textTarget;
  final strings = <String>{
    expected,
    ...expected.split(RegExp(r'\s+')),
    if (card.speakingKey case final k? when k.trim().isNotEmpty) k,
    ...card.variants,
    ...dayWords,
  }..removeWhere((s) => s.trim().isEmpty);
  return SpeechAttemptController(
    recognizer: ref.read(speechRecognizerProvider),
    diagnostics: ref.read(speechDiagnosticsProvider),
    localeId: localeId,
    expected: expected,
    coverage: card.coverage,
    key: card.speakingKey,
    variants: card.variants,
    contextualStrings: strings.take(50).toList(),
  );
}

/// ДЕВ-РЯД QA под микрофоном — «said» / «part» / «miss». Только у QA-аккаунта (решает сервер,
/// [AppUser.qaTools]); на симуляторе микрофона нет, и без этой двери карточки речи не
/// проходятся живьём. Подписи латиницей: это инструмент, а не интерфейс.
class QaSpeechRow extends ConsumerWidget {
  const QaSpeechRow({super.key, required this.controller});
  final SpeechAttemptController controller;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!(ref.watch(authControllerProvider).value?.qaTools ?? false)) return const SizedBox.shrink();
    final words = controller.expected.trim().split(RegExp(r'\s+'));
    final part = words.length < 3 ? controller.expected : words.take((words.length * 2 / 3).ceil()).join(' ');
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Expanded(child: QuietButton(label: 'QA · said', onPressed: () => controller.inject(controller.expected))),
          const SizedBox(width: 8),
          Expanded(child: QuietButton(label: 'QA · part', onPressed: () => controller.inject(part))),
          const SizedBox(width: 8),
          Expanded(child: QuietButton(label: 'QA · miss', onPressed: () => controller.inject('nothing like the line'))),
        ],
      ),
    );
  }
}

/// Строка под микрофоном: «Услышали: …» шалфеем / «Не расслышали. Ещё раз» secondary / разрешение.
class SpeechVerdictLine extends StatelessWidget {
  const SpeechVerdictLine({super.key, required this.controller, this.heardWord});
  final SpeechAttemptController controller;

  /// Что печатать после «Услышали:» — слово/фразу карточки; null — просто «Услышали».
  final String? heardWord;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (controller.accepted) {
      return Text(
        heardWord == null ? l.daySayHeardShort : l.daySayHeard(heardWord!),
        textAlign: TextAlign.center,
        style: AppTextDay.heard,
      );
    }
    final probe = controller.probe;
    if (controller.micUnavailable && probe != null && probe.blockedInSettings) {
      return SpeechPermissionNotice(
        micDenied: probe.microphone != SpeechPermission.granted,
        recognitionDenied: probe.recognition != SpeechPermission.granted,
      );
    }
    if (controller.attempts > 0 && controller.state == RecordState.idle) {
      return Text(l.daySayRetry, textAlign: TextAlign.center, style: AppTextDay.retry);
    }
    return const SizedBox(height: 18);
  }
}
