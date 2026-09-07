import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import '../../data/speech/speech_diagnostics.dart';

/// СЛУЖЕБНАЯ СТРОКА МИКРОФОНА — наряд DAY-GATE-1, Ч.0.1.
///
/// Всё, чего не хватало 07.09, когда «Слушаю…» ничего не значило, в одном месте и живьём:
///
///   * оба разрешения ПОРОЗНЬ — распознавание и микрофон это два разных разрешения, и они могут
///     разойтись (см. `packages/speech_to_text/UPSTREAM.md`, правка 3);
///   * есть ли распознаватель для языка цели, жив ли он сейчас, умеет ли без сети;
///   * стадия хода — {@see SpeechPhase}: ждём роль / открываем / слушаем / закрылся тишиной /
///     закрылся сторожем / отказ с кодом;
///   * последний частичный текст и счётчик эхо-сбросов — «Слушаю…» без текста бывает и у мёртвого
///     канала, и у исправного, который честно выбрасывает эхо динамика.
///
/// ТЕКСТ АНГЛИЙСКИЙ И КОДАМИ, БЕЗ `AppLocalizations`, и это не небрежность: строка читается
/// разработчиком по кодам платформы (`not_determined`, `error_listen_failed`), а перевод кодов на
/// русский добавил бы шаг догадки между тем, что ответила iOS, и тем, что видно на экране. Кроме
/// того, она копируется в отчёт «жалобы» (Ч.0.5) как есть.
///
/// Виджет БЕЗ оболочки строки: его ставит в `_RowShell` дев-секция профиля — одна ответственность,
/// и никакой копии чужой геометрии здесь.
class QaSpeechView extends StatefulWidget {
  const QaSpeechView({super.key, required this.diagnostics, required this.localeId});

  final SpeechDiagnostics diagnostics;

  /// Язык, про который спрашиваем ОС, — тот же `sttLocaleFor(<язык цели>)`, что уходит в микрофон.
  final String localeId;

  @override
  State<QaSpeechView> createState() => _QaSpeechViewState();
}

class _QaSpeechViewState extends State<QaSpeechView> {
  /// Разрешения меняются В НАСТРОЙКАХ, то есть за пределами приложения и без единого события к нам.
  /// Опрос — единственный способ увидеть отзыв разрешения, не выходя из этого экрана; вызов
  /// читающий и ничего не поднимает, поэтому две секунды здесь ничего не стоят.
  static const _pollEvery = Duration(seconds: 2);

  Timer? _poll;

  @override
  void initState() {
    super.initState();
    unawaited(widget.diagnostics.refresh(widget.localeId));
    _poll = Timer.periodic(_pollEvery, (_) {
      if (mounted) unawaited(widget.diagnostics.refresh(widget.localeId));
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.diagnostics,
      builder: (context, _) {
        final d = widget.diagnostics;
        final probe = d.probe;
        final partial = d.lastPartial.trim();

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Microphone (QA)',
              style: TextStyle(
                fontFamily: AppFonts.inter,
                fontSize: 15.5,
                fontWeight: FontWeight.w500,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 6),
            _line('speech  ${_word(probe?.recognition)}   mic  ${_word(probe?.microphone)}'),
            _line(
              'recognizer ${widget.localeId}  '
              'supported=${probe?.recognizerSupported ?? '?'}  '
              'available=${probe?.recognizerAvailable ?? '?'}  '
              'onDevice=${probe?.onDeviceSupported ?? '?'}',
            ),
            _line('turn  ${d.phase.name}${d.lastErrorCode == null ? '' : '  · ${d.lastErrorCode}'}'),
            _line('heard  ${partial.isEmpty ? '—' : partial}   echoes ${d.echoes}'),
          ],
        );
      },
    );
  }

  static String _word(SpeechPermission? permission) => (permission ?? SpeechPermission.unknown).name;

  static Widget _line(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 2),
    child: Text(
      text,
      style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.secondary),
    ),
  );
}
