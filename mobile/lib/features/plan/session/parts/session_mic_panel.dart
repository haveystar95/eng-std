import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/live_line.dart';
import '../session_mic.dart';
import 'session_bits.dart';

/// МИКРОФОН (кадр 30-3) — нижняя зона голосовой карточки: подпись, живая строка, волна, «Пропустить» и
/// кнопка 72. Запись только по нажатию; «Пропустить» — только здесь.
///
/// Состояния — по кадру: покой («тап — говорить») · слушаю, пусто (курсор, «говори, я слушаю») · слушаю,
/// текст идёт (живая строка: совпавшее шалфеем, последнее слово серым) · услышал (кнопка шалфеем с
/// галкой) · не расслышал («ещё раз»). В debug-сборке под кнопкой — поле «что услышал».
class SessionMicPanel extends StatelessWidget {
  const SessionMicPanel({
    super.key,
    required this.mic,
    required this.expected,
    required this.onSkip,
    this.missedCaption,
    this.showHeardLine = true,
    this.heardText,
  });

  final SessionMic mic;

  /// Эталон живой строки.
  final String expected;

  /// «Пропустить»; null — не показывать.
  final VoidCallback? onSkip;

  /// Подпись вместо «не расслышал, ещё раз» (причина отказа судьи, 32-9).
  final String? missedCaption;

  /// Строка услышанного над «услышал» (у 31-2 её место занимает эхо в листе).
  final bool showHeardLine;

  /// Что писать в строке «услышал» — по умолчанию сам транскрипт.
  final String? heardText;

  @override
  Widget build(BuildContext context) => ListenableBuilder(
    listenable: mic,
    builder: (context, _) {
      final l = AppLocalizations.of(context);
      final state = mic.state;
      final children = <Widget>[];
      void gap() => children.add(const SizedBox(height: 14));

      switch (state) {
        case MicState.idle || MicState.unavailable:
          children.add(Text(l.planSessionMicTap, style: AppTextSession.meta));
          if (onSkip != null) {
            gap();
            children.add(_Skip(onTap: onSkip!));
          }
        case MicState.listening:
          final words = LiveLine.of(mic.partial, expected, listening: !mic.closed);
          children.add(_LiveLineText(words: words));
          gap();
          if (mic.partial.trim().isEmpty) {
            children.add(Text(l.planSessionMicListening, style: AppTextSession.meta));
            gap();
          }
          children.add(SessionWave(heights: SessionWave.five, playing: !mic.closed));
          if (onSkip != null) {
            gap();
            children.add(_Skip(onTap: mic.closed ? null : onSkip!));
          }
        case MicState.heard:
          if (showHeardLine) {
            children.add(Text(
              heardText ?? mic.partial,
              textAlign: TextAlign.center,
              style: AppTextSession.target22.copyWith(color: AppColors.verdictKnown),
            ));
            gap();
          }
          children.add(Text(l.planSessionMicHeard, style: AppTextSession.meta));
        case MicState.missed:
          children.add(Text(
            missedCaption ?? l.planSessionMicMissed,
            textAlign: TextAlign.center,
            style: AppTextSession.meta,
          ));
          if (onSkip != null) {
            gap();
            children.add(_Skip(onTap: onSkip!));
          }
      }
      gap();
      children.add(_MicButton(mic: mic));
      if (kDebugMode && state != MicState.heard) {
        children.add(const SizedBox(height: 10));
        children.add(_DebugHeardField(mic: mic));
      }
      return Column(mainAxisSize: MainAxisSize.min, children: children);
    },
  );
}

class _Skip extends StatelessWidget {
  const _Skip({required this.onTap});

  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    child: GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Text(AppLocalizations.of(context).planSessionSkip, style: AppTextSession.skip),
      ),
    ),
  );
}

/// ЖИВАЯ СТРОКА — Literata 22: совпавшее шалфеем, последнее слово серым, пустая — курсор.
class _LiveLineText extends StatelessWidget {
  const _LiveLineText({required this.words});

  final List<LiveWord> words;

  @override
  Widget build(BuildContext context) {
    if (words.isEmpty) {
      return const SizedBox(height: 28, child: Center(child: SessionCaret(width: 16)));
    }
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 28),
      child: Text.rich(
        TextSpan(
          children: [
            for (var i = 0; i < words.length; i++)
              TextSpan(
                text: i == 0 ? words[i].text : ' ${words[i].text}',
                style: AppTextSession.target22.copyWith(
                  color: switch (words[i].tone) {
                    LiveTone.matched => AppColors.verdictKnown,
                    LiveTone.pending => AppColors.tertiary,
                    LiveTone.plain => AppColors.ink,
                  },
                ),
              ),
          ],
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}

/// КНОПКА МИКРОФОНА 72: покой — угольная с микрофоном; слушаю — «стоп» и кольцо шалфея 30 % с пульсом
/// 1,2 с; услышал — шалфей с галкой; не расслышал — «повторить».
class _MicButton extends StatefulWidget {
  const _MicButton({required this.mic});

  final SessionMic mic;

  @override
  State<_MicButton> createState() => _MicButtonState();
}

class _MicButtonState extends State<_MicButton> with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(vsync: this, duration: AppMotion.sessionListenPulse);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _syncPulse();
  }

  @override
  void didUpdateWidget(_MicButton old) {
    super.didUpdateWidget(old);
    _syncPulse();
  }

  /// Пульс кольца — только пока идёт запись; под «уменьшением движения» кольцо стоит.
  void _syncPulse() {
    final mic = widget.mic;
    final listening = mic.state == MicState.listening && !mic.closed;
    final reduce = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (listening && !reduce && !_pulse.isAnimating) {
      unawaited(_pulse.repeat());
    } else if ((!listening || reduce) && _pulse.isAnimating) {
      _pulse.stop();
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
    final mic = widget.mic;
    final state = mic.state;
    final listening = state == MicState.listening && !mic.closed;
    final heard = state == MicState.heard;
    final icon = switch (state) {
      MicState.listening => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(color: AppColors.paper, borderRadius: BorderRadius.circular(4)),
      ),
      MicState.heard => const Icon(LucideIcons.check, size: 30, color: AppColors.paper),
      MicState.missed => const Icon(LucideIcons.rotateCw, size: 28, color: AppColors.paper),
      MicState.idle || MicState.unavailable => const Icon(LucideIcons.mic, size: 30, color: AppColors.paper),
    };
    return Semantics(
      button: true,
      label: l.planSessionMicTap,
      child: GestureDetector(
        onTap: heard ? null : () => unawaited(mic.tap()),
        child: AnimatedBuilder(
          animation: _pulse,
          builder: (_, child) {
            // om-pulse: кольцо 8 → 12 → 8, прозрачность .30 → .14 → .30.
            final t = listening ? (0.5 - 0.5 * math.cos(_pulse.value * 2 * math.pi)) : 0.0;
            return Container(
              width: 72,
              height: 72,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: heard ? AppColors.verdictKnown : AppColors.windowInk,
                boxShadow: [
                  const BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8)),
                  if (state == MicState.listening)
                    BoxShadow(
                      color: Color.lerp(AppColors.sessionListenRing, AppColors.sessionListenRing.withValues(alpha: .14), t)!,
                      spreadRadius: 8 + 4 * t,
                    ),
                ],
              ),
              child: child,
            );
          },
          child: icon,
        ),
      ),
    );
  }
}

/// ПОЛЕ «ЧТО УСЛЫШАЛ» — только debug-сборка: текст уходит как распознанный (симулятор без микрофона).
class _DebugHeardField extends StatefulWidget {
  const _DebugHeardField({required this.mic});

  final SessionMic mic;

  @override
  State<_DebugHeardField> createState() => _DebugHeardFieldState();
}

class _DebugHeardFieldState extends State<_DebugHeardField> {
  final _text = TextEditingController();

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  void _submit() {
    final value = _text.text;
    if (value.trim().isEmpty) return;
    _text.clear();
    FocusScope.of(context).unfocus();
    widget.mic.submitDebug(value);
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 36,
    child: TextField(
      key: const ValueKey('session-debug-heard'),
      controller: _text,
      style: AppTextSession.meta.copyWith(color: AppColors.ink),
      textInputAction: TextInputAction.done,
      onSubmitted: (_) => _submit(),
      decoration: InputDecoration(
        isDense: true,
        hintText: AppLocalizations.of(context).planSessionDebugHeard,
        hintStyle: AppTextSession.meta,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        filled: true,
        fillColor: AppColors.paper,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.markerOutline)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.markerOutline)),
        suffixIcon: IconButton(
          icon: const Icon(LucideIcons.arrowUp, size: 16, color: AppColors.ink),
          onPressed: _submit,
        ),
      ),
    ),
  );
}

/// «НУЖЕН МИКРОФОН» (кадр 30-3, «нет разрешения · экран»): заголовок, текст, лист двух этапов, которые без
/// микрофона не пройти, кнопка микрофона; внизу «Пропустить» и «Разрешить».
class SessionNoMicView extends StatelessWidget {
  const SessionNoMicView({super.key, required this.onAllow, required this.onSkip, required this.stageName});

  final VoidCallback onAllow;
  final VoidCallback onSkip;
  final String Function(PlanStage stage) stageName;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(kSessionGutter, 50, kSessionGutter, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(l.planSessionNoMicTitle, style: AppTextSession.stageTitle),
                const SizedBox(height: 14),
                Text(l.planSessionNoMicBody, style: AppTextSession.body),
                const SizedBox(height: 32),
                SessionSheet(
                  child: Column(
                    children: [
                      for (final s in const [PlanStage.listen, PlanStage.speak]) ...[
                        if (s == PlanStage.speak) const SizedBox(height: 8),
                        SizedBox(
                          height: 20,
                          child: Row(
                            children: [
                              _StageGlyph(stage: s),
                              const SizedBox(width: 12),
                              Expanded(child: Text(stageName(s), style: AppTextSession.text15)),
                              Text(l.planSessionStateAhead, style: AppTextSession.meta),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 110),
                Center(
                  child: Container(
                    width: 72,
                    height: 72,
                    alignment: Alignment.center,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: AppColors.windowInk,
                      boxShadow: [BoxShadow(color: AppColors.sessionMicShadow, blurRadius: 24, offset: Offset(0, 8))],
                    ),
                    child: const Icon(LucideIcons.mic, size: 30, color: AppColors.paper),
                  ),
                ),
              ],
            ),
          ),
        ),
        SessionDock(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(child: _Skip(onTap: onSkip)),
              const SizedBox(height: 14),
              SessionDockButton(label: l.planSessionNoMicAllow, onTap: onAllow),
            ],
          ),
        ),
      ],
    );
  }
}

class _StageGlyph extends StatelessWidget {
  const _StageGlyph({required this.stage});

  final PlanStage stage;

  @override
  Widget build(BuildContext context) => sessionStageGlyph(stage, AppColors.tertiary);
}
