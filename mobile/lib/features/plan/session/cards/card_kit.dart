import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/plan_models.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_coverage.dart';
import '../../../../data/speech/speech_turn.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_mic_panel.dart';
import '../session_mic.dart';
import '../session_voice.dart';

/// ЧТО КАРТОЧКА ЗНАЕТ СВЕРХ СВОЕГО PAYLOAD — один объект на все 15 видов 1b.
///
/// Карточка сама судит ответ (выбор, плитки, голос — [SessionRules]) и отдаёт итог в [submit]; дальше —
/// [next] (ждёт, пока ответ уйдёт на сервер). Судейский вид спрашивает [judge]. Ничего из сессии, кроме
/// этого, карточке не видно.
class CardEnv {
  const CardEnv({
    required this.card,
    required this.voice,
    required this.targetLang,
    required this.localeId,
    required this.role,
    required this.submit,
    required this.next,
    required this.judge,
    required this.makeMic,
    required this.reportNoMic,
    required this.openSettings,
    this.outcome,
    this.advancing = false,
  });

  final SessionCard card;
  final SessionVoice voice;
  final String targetLang;

  /// Локаль распознавания — `en_US`.
  final String localeId;

  /// Роль собеседника в именительном, как отдал сервер («Регистратор»); пустая — роли нет.
  final String role;
  final void Function(SessionAnswer answer) submit;
  final Future<void> Function() next;
  final Future<SessionJudgeOutcome> Function(String heard) judge;

  /// Микрофон карточки: что должно прозвучать и слова-подсказки распознавателю.
  final SessionMic Function(String expected, List<String> contextual) makeMic;

  /// Микрофона нет — экран показывает «Нужен микрофон» вместо шапки этапа.
  final ValueChanged<bool> reportNoMic;

  /// «Разрешить», когда разрешение отказано насовсем, — настройки телефона.
  final Future<void> Function() openSettings;

  /// Ответ сервера по этой карточке, когда он пришёл (`unit.returns_tomorrow`).
  final SessionAnswerOutcome? outcome;

  /// «Дальше» ждёт, пока уйдёт ответ.
  final bool advancing;

  Set<String> get articles => SpeechCoverage.articlesFor(targetLang);

  /// После второго провала единица вернётся завтра — сервер сказал это в ответе.
  bool get returnsTomorrow => outcome?.unit.returnsTomorrow ?? false;
}

/// ОБЩАЯ РАСКЛАДКА КАРТОЧКИ: задание сверху, лист материала (прижат к заданию или по центру свободного
/// поля), зона ответа прижата к низу доком. Док лежит ПОВЕРХ поля, как в канве (`position:absolute`,
/// градиент сверху): поле заходит под прозрачный верхний отступ дока, а что не влезло — уходит под его
/// сплошную часть, а не режется краем прокрутки.
class CardLayout extends StatelessWidget {
  const CardLayout({
    super.key,
    required this.task,
    required this.body,
    this.bottom,
    this.centerBody = false,
    this.taskInBody = false,
    this.taskGap = 8,
    this.bodyGap = 12,
    this.fadeStop = 0.22,
  });

  final Widget task;
  final Widget body;
  final Widget? bottom;

  /// Лист по центру свободного поля (голосовые карточки, плитки), а не под заданием.
  final bool centerBody;

  /// Верх листа текстом (30-9 «вопрос · верх текстом»): задание и лист — одна группа по центру свободного
  /// поля.
  final bool taskInBody;

  /// От полосы сцены до задания.
  final double taskGap;

  /// От задания до листа.
  final double bodyGap;
  final double fadeStop;

  @override
  Widget build(BuildContext context) {
    final dock = bottom;
    final field = CustomScrollView(
      // Под доком поле не режется: низ, который не влез, закрывает сам док.
      clipBehavior: dock == null ? Clip.hardEdge : Clip.none,
      slivers: [
        if (!taskInBody)
          SliverPadding(
            padding: EdgeInsets.fromLTRB(kSessionGutter, taskGap, kSessionGutter, 0),
            sliver: SliverToBoxAdapter(child: task),
          ),
        SliverFillRemaining(
          hasScrollBody: false,
          child: Padding(
            padding: EdgeInsets.fromLTRB(kSessionGutter, taskInBody ? taskGap : bodyGap, kSessionGutter, dock == null ? 16 : 0),
            child: switch ((taskInBody, centerBody)) {
              (true, _) => Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [task, SizedBox(height: bodyGap), body],
                ),
              ),
              (false, true) => Center(child: body),
              (false, false) => Align(alignment: Alignment.topCenter, child: body),
            },
          ),
        ),
      ],
    );
    if (dock == null) return field;
    return ClipRect(
      child: CustomMultiChildLayout(
        delegate: _DockOverField(),
        children: [
          LayoutId(id: _CardSlot.field, child: field),
          LayoutId(id: _CardSlot.dock, child: SessionDock(fadeStop: fadeStop, child: dock)),
        ],
      ),
    );
  }
}

enum _CardSlot { field, dock }

/// Док прижат к низу; поле — от верха до верха дока плюс его прозрачный верхний отступ.
class _DockOverField extends MultiChildLayoutDelegate {
  @override
  void performLayout(Size size) {
    final dock = layoutChild(_CardSlot.dock, BoxConstraints(minWidth: size.width, maxWidth: size.width, maxHeight: size.height));
    positionChild(_CardSlot.dock, Offset(0, size.height - dock.height));
    final field = (size.height - dock.height + SessionDock.topInset).clamp(0.0, size.height);
    layoutChild(_CardSlot.field, BoxConstraints.tight(Size(size.width, field)));
    positionChild(_CardSlot.field, Offset.zero);
  }

  @override
  bool shouldRelayout(_DockOverField oldDelegate) => false;
}

/// ГОЛОСОВАЯ КАРТОЧКА — общий ход «запись → зачёт → две попытки → пропуск» для `word_repeat`,
/// `phrase_repeat`, `phrase_other_slot` (кадры 31-2, 32-6, 32-7).
///
/// Голос никогда не пишет `failed`: зачёт — `passed` и автопереход через 600 мс; вторая попытка без
/// зачёта — `skipped` и «Дальше» вручную; «Пропустить» — `skipped` сразу. Микрофона нет — «Нужен
/// микрофон», «Пропустить» там пишет `skipped` с `no_mic`.
mixin VoiceCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;

  late final SessionMic mic;
  int _attempts = 0;
  bool _done = false;
  String _heard = '';

  /// Карточка закрыта пропуском после второй попытки — «Дальше» вручную.
  bool skippedAfterMisses = false;

  bool get done => _done;
  int get attempts => _attempts;
  String get heard => _heard;

  /// Что должно прозвучать.
  String get expectedSpeech;

  /// Слова-подсказки распознавателю — по умолчанию слова ожидаемого текста.
  List<String> get contextual => [
    expectedSpeech,
    ...expectedSpeech.split(RegExp(r'\s+')).where((w) => w.trim().isNotEmpty),
  ];

  /// Зачёт услышанного — правило вида.
  bool accepts(String heard);

  /// Зачтено — карточке показать своё «услышал».
  void onAccepted(String heard) {}

  void initVoice() {
    mic = env.makeMic(expectedSpeech, contextual)..onTurn = _onTurn;
    mic.addListener(_onMic);
  }

  void disposeVoice() {
    mic.removeListener(_onMic);
    mic.dispose();
  }

  bool _noMicReported = false;

  void _onMic() {
    final noMic = mic.state == MicState.unavailable;
    if (noMic != _noMicReported) {
      _noMicReported = noMic;
      env.reportNoMic(noMic);
    }
    if (mounted) setState(() {});
  }

  void _onTurn(MicTurn turn) {
    if (_done || !mounted) return;
    _heard = turn.transcript;
    _attempts++;
    final ok = turn.outcome == SpeechTurnOutcome.heard && accepts(turn.transcript);
    if (ok) {
      _done = true;
      mic.settle(accepted: true);
      onAccepted(turn.transcript);
      env.submit(SessionAnswer(result: SessionResult.passed, attempts: _attempts, response: SessionResponse(heard: turn.transcript)));
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
      setState(() {});
      return;
    }
    mic.settle(accepted: false);
    if (_attempts >= SessionRules.voiceAttempts) {
      _done = true;
      skippedAfterMisses = true;
      env.submit(SessionAnswer(
        result: SessionResult.skipped,
        attempts: _attempts,
        response: SessionResponse(heard: turn.transcript.isEmpty ? null : turn.transcript),
      ));
    }
    setState(() {});
  }

  /// «Пропустить» у микрофона.
  void skip({bool noMic = false}) {
    if (_done) return;
    _done = true;
    unawaited(env.voice.stop());
    env.submit(SessionAnswer(
      result: SessionResult.skipped,
      attempts: _attempts < 1 ? 1 : _attempts,
      response: SessionResponse(heard: _heard.isEmpty ? null : _heard, noMic: noMic ? true : null),
    ));
    env.reportNoMic(false);
    unawaited(env.next());
  }

  /// «Разрешить» на экране «Нужен микрофон».
  Future<void> allowMic() async {
    final ok = await mic.askAgain();
    if (!ok && mic.blockedInSettings) await env.openSettings();
  }

  /// Док голосовой карточки: микрофон, или «Дальше» после второй попытки.
  Widget voiceDock(BuildContext context, {bool showHeardLine = true, String? heardText}) {
    final l = AppLocalizations.of(context);
    // Вторая попытка без зачёта: «ещё раз» уже не предлагается — только «Дальше».
    if (skippedAfterMisses) {
      return SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()));
    }
    return SessionMicPanel(
      mic: mic,
      expected: expectedSpeech,
      onSkip: _done ? null : () => skip(),
      showHeardLine: showHeardLine,
      heardText: heardText,
    );
  }

  /// Тело карточки без микрофона — «Нужен микрофон» (30-3).
  Widget? noMicBody(String Function(PlanStage) stageName) => mic.state == MicState.unavailable
      ? SessionNoMicView(onAllow: () => unawaited(allowMic()), onSkip: () => skip(noMic: true), stageName: stageName)
      : null;
}

/// КАРТОЧКА ВЫБОРА — шаблон 30-9: тап по варианту — ответ; верно — подложка шалфея и автопереход через
/// 600 мс; неверно — контур чернил у выбранного, шалфей у верного и «Дальше» вручную; вторая ошибка по
/// единице — у верного точка «вернётся завтра», когда сервер это сказал.
mixin ChoiceCardState<T extends StatefulWidget> on State<T> {
  CardEnv get env;
  ChoicePayload get choice;

  String? _chosen;
  int _shake = 0;

  bool get answered => _chosen != null;
  bool get answeredCorrectly => _chosen != null && _chosen == choice.correct;
  bool get answeredWrong => _chosen != null && _chosen != choice.correct;

  void choose(String optionId) {
    if (_chosen != null) return;
    final correct = SessionRules.choiceCorrect(choice, optionId);
    setState(() {
      _chosen = optionId;
      if (!correct) _shake++;
    });
    if (correct) {
      AppHaptics.success();
    } else {
      AppHaptics.warning();
    }
    env.submit(SessionAnswer(result: correct ? SessionResult.passed : SessionResult.failed, attempts: 1));
    if (correct) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
    }
  }

  OptionLook lookOf(String optionId) {
    if (_chosen == null) return OptionLook.idle;
    if (optionId == choice.correct) return env.returnsTomorrow ? OptionLook.returns : OptionLook.correct;
    if (optionId == _chosen) return OptionLook.wrong;
    return OptionLook.settled;
  }

  int shakeOf(String optionId) => optionId == _chosen ? _shake : 0;

  /// Варианты и, после неверного, «Дальше».
  Widget optionsDock(BuildContext context, {bool target = false, Widget? Function(CardOption option)? listen}) {
    final l = AppLocalizations.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final o in choice.options) ...[
          if (o != choice.options.first) const SizedBox(height: 8),
          SessionOption(
            key: ValueKey('option-${o.id}'),
            text: o.text,
            target: target,
            look: lookOf(o.id),
            shake: shakeOf(o.id),
            listen: listen?.call(o),
            onTap: answered ? null : () => choose(o.id),
          ),
        ],
        if (answeredWrong) ...[
          const SizedBox(height: 8),
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
        ],
      ],
    );
  }

  /// Бровь «вернётся завтра» справа, когда сервер сказал, что единица вернётся.
  String? eyebrowTrailing(AppLocalizations l) => env.returnsTomorrow ? l.planWindowSheetReturnsTomorrow : null;
}

/// «Прослушать» с волной, пока звучит именно этот звук.
class CardListen extends StatelessWidget {
  const CardListen({super.key, required this.env, required this.audio, required this.fallback, required this.playKey, this.size = 44, this.rate = 1.0});

  final CardEnv env;
  final CardAudio? audio;
  final String fallback;
  final Object playKey;
  final double size;
  final double rate;

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (context, playing, _) => SessionListenButton(
      size: size,
      label: AppLocalizations.of(context).planWindowListen,
      playing: playing == playKey,
      onTap: () => unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: playKey)),
    ),
  );
}
