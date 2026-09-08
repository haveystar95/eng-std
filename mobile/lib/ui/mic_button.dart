import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// В КАКОМ СОСТОЯНИИ КНОПКА МИКРОФОНА — наряд SPEECH-2, Ч.1.
enum MicState {
  /// Собеседник ещё говорит. Кнопка на месте, но не зовёт и не нажимается: тап посреди чужой
  /// реплики записал бы динамик.
  waiting,

  /// ТВОЯ ОЧЕРЕДЬ. Кнопка зовёт — пульсом и подписью словами, — а ЗАПИСЬ НЕ ИДЁТ. Состояние без
  /// таймаута: человек думает столько, сколько ему нужно (Ч.1.2).
  yourTurn,

  /// ПИШЕТ. Второй тап — «Готово»: остановить руками и отдать на зачёт.
  recording,
}

/// ОДНА КНОПКА МИКРОФОНА НА ВСЕ ТРИ МЕСТА — эхо знакомства «Повтори вслух», свой ход в разговоре,
/// «Скажи сам» (наряд SPEECH-2, Ч.1.3).
///
/// ## Почему кнопка, а не автооткрытие
///
/// До этого наряда микрофон в прогоне открывался САМ, по концу реплики собеседника. Замысел был
/// правильный — «в жизни собеседник договаривает, и ты отвечаешь, а не жмёшь запись», — а на
/// устройстве 08.09 он выглядел так: человек ещё не собрался, а его уже пишут; сторож тикает;
/// первое, что слышит микрофон, — вдох и «эээ». Разница между разговором и тренажёром в том, что в
/// тренажёре человек имеет право подумать, и отнимать у него это право ради реализма — плохая
/// сделка. Запись начинается ПО НАЖАТИЮ и кончается по нажатию, по тишине или по сторожу.
///
/// ## Почему одна на три места
///
/// Потому что поведение одно (Ч.1.3), а три копии — это три места, где чинить одну опечатку и
/// откуда однажды разъедется смысл: в одном месте кружок начнёт пульсировать «пишу», в другом —
/// «твоя очередь», и человек перестанет понимать, пишут его или ждут.
///
/// Размер и вес — те же, что у кружка воспроизведения на карточке аудирования: «вот слово» и
/// «теперь скажи» — пара, и разные формы сказали бы, что это разные задачи.
class MicButton extends StatefulWidget {
  const MicButton({super.key, required this.state, required this.onTap, this.caption});

  final MicState state;

  /// Тап. В [MicState.waiting] не вызывается — кнопка не нажимается.
  final VoidCallback onTap;

  /// Подпись под кружком. Null — берётся по состоянию ([MicState.yourTurn] → «Твоя очередь ·
  /// нажми и говори»); строку передают, когда карточка называет ход своими словами.
  final String? caption;

  @override
  State<MicButton> createState() => _MicButtonState();
}

class _MicButtonState extends State<MicButton> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: AppMotion.listenPulse,
    lowerBound: 1.0,
    upperBound: 1.06,
  );

  @override
  void didUpdateWidget(MicButton old) {
    super.didUpdateWidget(old);
    if (widget.state == old.state) return;
    unawaited(_syncPulse());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    unawaited(_syncPulse());
  }

  /// Пульсируют ДВА состояния, и по-разному — потому что говорят они разное.
  ///
  /// «Пишет» дышит НЕПРЕРЫВНО: это единственный признак того, что телефон слушает, и он обязан
  /// жить, пока живёт запись.
  ///
  /// «Твоя очередь» дышит ТРИ РАЗА и замирает. Это приглашение, а приглашение повторяют, а не
  /// твердят: кнопка, пульсирующая всё время, пока человек думает над репликой, торопит его —
  /// ровно то, от чего наряд уводит, убирая автооткрытие микрофона. Заодно конечная анимация не
  /// делает экран вечно «незастывшим»: `pumpAndSettle` в виджет-тесте на бесконечной ждёт вечно, и
  /// анимация, которую нельзя досмотреть, — это ещё и весь набор тестов, который нельзя написать.
  static const _inviteBeats = 3;

  Future<void> _syncPulse() async {
    if (widget.state == MicState.waiting || MediaQuery.of(context).disableAnimations) {
      _pulse.stop();
      _pulse.value = 1.0;

      return;
    }
    if (widget.state == MicState.recording) {
      if (!_pulse.isAnimating) _pulse.repeat(reverse: true);

      return;
    }
    _pulse.stop();
    _pulse.value = 1.0;
    for (var i = 0; i < _inviteBeats; i++) {
      if (!mounted || widget.state != MicState.yourTurn) return;
      await _pulse.forward();
      if (!mounted || widget.state != MicState.yourTurn) return;
      await _pulse.reverse();
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final state = widget.state;
    final filled = state == MicState.recording;
    final waiting = state == MicState.waiting;
    final label = switch (state) {
      MicState.waiting => l.sessionSpeakWaitForRole,
      MicState.yourTurn => l.sessionSpeakYourTurn,
      MicState.recording => l.sessionSpeakStop,
    };

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Semantics(
          button: !waiting,
          enabled: !waiting,
          label: label,
          child: GestureDetector(
            onTap: waiting ? null : widget.onTap,
            child: ScaleTransition(
              scale: _pulse,
              child: Container(
                width: 112,
                height: 112,
                decoration: BoxDecoration(
                  color: filled ? AppColors.ink : AppColors.surfaceRaised,
                  shape: BoxShape.circle,
                  border: filled
                      ? null
                      : Border.fromBorderSide(
                          BorderSide(
                            color: waiting ? AppColors.dividerFaint : AppColors.ink,
                            // «Твоя очередь» — толще обычного: это единственное отличие зовущей
                            // кнопки от ждущей, которое видно, не читая подписи.
                            width: state == MicState.yourTurn ? 2.5 : 1.5,
                          ),
                        ),
                  boxShadow: filled ? AppShadows.anchor : AppShadows.card,
                ),
                child: Icon(
                  LucideIcons.mic,
                  color: filled
                      ? AppColors.paper
                      : (waiting ? AppColors.secondary : AppColors.ink),
                  size: 44,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.s12),
        // ПОДПИСЬ СЛОВАМИ, а не одним кружком (Ч.1.1): «твоя очередь» и «пишу» отличаются заливкой,
        // и заливки мало — человек, который не уверен, пишут его или ждут, молчит.
        Text(
          widget.caption ?? label,
          textAlign: TextAlign.center,
          style: AppTextExercise.taskInstruction,
        ),
      ],
    );
  }
}
