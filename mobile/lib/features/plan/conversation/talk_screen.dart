import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../data/api_client.dart' show problemCodeOf;
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/heard_words.dart';
import '../../../data/plan/session/live_line.dart';
import '../../../data/speech/speech_turn.dart';
import '../session/cards/card_kit.dart' show CardLayout;
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import '../session/session_mic.dart';
import '../session/session_voice.dart';
import 'conversation_controller.dart';
import 'talk_constructions.dart';
import 'talk_ribbon.dart';

/// THE TALK (кадры 37-6…37-11) — the ribbon, the microphone and the three ways a move can fail.
///
/// THE MICROPHONE OPENS ONLY ON A TAP. No state of this screen starts a recording by itself: not the
/// end of the role's line, not the chip, not a failure (DECISIONS п. 299 — «Запись начинает
/// человек»). A tap WHILE the role is speaking cuts the line off and starts listening at once
/// (кадр 37-9) — that is still a tap.
class TalkView extends StatefulWidget {
  const TalkView({
    super.key,
    required this.controller,
    required this.scene,
    required this.voice,
    required this.makeMic,
    required this.onSummary,
    required this.onClose,
    this.openSettings,
    this.sceneById,
  });

  final ConversationController controller;

  /// The day's scene — the strip's photo when the talk's own scene is not found in the plan.
  final PlanScene? scene;

  /// The plan's scene by id — the photo of the scene the talk is in NOW ([talkStripScene]).
  final PlanScene? Function(String sceneId)? sceneById;
  final SessionVoice voice;

  /// The talk's own microphone: free speech, the target language's locale.
  final SessionMic Function() makeMic;

  /// «Итог» on the end sheet (37-11).
  final VoidCallback onSummary;
  final VoidCallback onClose;

  /// iOS Settings — the only way left once the system will not ask for the microphone again.
  final Future<void> Function()? openSettings;

  @override
  State<TalkView> createState() => _TalkViewState();
}

class _TalkViewState extends State<TalkView> {
  late final SessionMic _mic;

  /// The role's lines the learner opened with «текст» under «Без подсказок» — by talk and turn, since a replay numbers its
  /// turns from one again.
  final Set<String> _opened = {};

  ConversationController get _talk => widget.controller;

  @override
  void initState() {
    super.initState();
    _mic = widget.makeMic()..onTurn = _onTurn;
    _mic.addListener(_onMic);
    _talk.addListener(_onTalk);
  }

  @override
  void dispose() {
    _mic.removeListener(_onMic);
    _mic.dispose();
    _talk.removeListener(_onTalk);
    super.dispose();
  }

  void _onMic() {
    if (_mic.isListening) _talk.recordingStarted();
    if (mounted) setState(() {});
  }

  void _onTalk() {
    if (mounted) setState(() {});
  }

  /// A recording closed. Silence is not sent — it is «не расслышал», and it costs nothing.
  void _onTurn(MicTurn turn) {
    final heard = turn.outcome == SpeechTurnOutcome.heard ? turn.transcript.trim() : '';
    _mic.reset();
    unawaited(_talk.say(heard));
  }

  /// A tap on the microphone: while the role speaks it cuts the line off AND starts listening; the
  /// rest of the time it is the ordinary tap of every microphone in the app.
  Future<void> _tapMic() async {
    if (_talk.phase == TalkPhase.agentSpeaking) {
      await _talk.interrupt();
      if (!mounted) return;
    }
    await _mic.tap();
  }

  /// «Разрешить» under a microphone that cannot record: ask the system again; once it will not ask any
  /// more, Settings are the only way — the same two steps as «Нужен микрофон» of the cards (30-3).
  Future<void> _allowMic() async {
    final ok = await _mic.askAgain();
    if (!ok && _mic.blockedInSettings) await widget.openSettings?.call();
  }

  /// Every construction the talk is for, as one reference line — the live line paints the words of the plan that have
  /// already sounded in sage (37-7 «слушаю»). A frame is read with the lesson's own value in its window: that is the
  /// whole of what the server wrote for it.
  String get _expected {
    final targets = _talk.talk?.targets ?? const <TalkTarget>[];
    return targets.map((t) => t.saidWith(t.exampleTarget)).join(' ');
  }

  /// THE CONSTRUCTIONS OVER THE MICROPHONE (наряд FIX-3 §3, кадры 37-7…37-11) — the plates off the latest answer's
  /// `targets[]`, filled by the SERVER's `said`; a talk whose server sent none has no row. A tap opens the
  /// construction's sheet (37-8d), and the sheet reads the list again while it is open.
  Widget? _constructions(PlanConversation talk) => talk.targets.isEmpty
      ? null
      : TalkConstructionChips(
          targets: talk.targets,
          onTap: (target) => unawaited(
            showTalkConstructionSheet(
              context,
              talk: _talk,
              target: () => _targetOf(target.sceneId, target.ref),
            ),
          ),
        );

  /// The construction by its pair, as the talk has it NOW — null once it is no longer in the list.
  TalkTarget? _targetOf(String sceneId, String ref) {
    for (final t in _talk.talk?.targets ?? const <TalkTarget>[]) {
      if (t.sceneId == sceneId && t.ref == ref) return t;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final talk = _talk.talk;
    if (talk == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(kSessionGutter),
          child: _talk.phase == TalkPhase.openFailed
              ? Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_openFailure(l), textAlign: TextAlign.center, style: AppTextSession.body),
                    const SizedBox(height: 18),
                    SessionDockButton(label: l.planTabRetry, onTap: () => unawaited(_talk.open())),
                    const SizedBox(height: 8),
                    TextButton(onPressed: widget.onClose, child: Text(l.planWindowBack, style: AppTextSession.skip)),
                  ],
                )
              : const CircularProgressIndicator(color: AppColors.ink),
        ),
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _header(l, talk),
        // The scene the talk is in NOW — on the rehearsal it moves from scene to scene with the role.
        SessionSceneStrip(
          scene: talkStripScene(talk, sceneById: widget.sceneById) ?? widget.scene,
          title: talk.partner.sceneNative,
          role: talk.partner.roleNative,
        ),
        Expanded(
          child: CardLayout(
            feed: true,
            bodyGap: 16,
            fadeStop: 0.30,
            task: null,
            body: _ribbon(talk),
            bottom: _talk.phase == TalkPhase.ended ? _endSheet(l, talk) : _dock(l, talk),
          ),
        ),
      ],
    );
  }

  String _openFailure(AppLocalizations l) => switch (problemCodeOf(_talk.openError)) {
    'plan_conversation_not_in_day' => l.planTalkNotInDay,
    // «Ещё раз» of a replay (BACK-TAILS-2): the day's replays are spent for today.
    'plan_conversation_replay_limit' => l.planWindowTalkReplayLimit,
    _ => l.planTalkOpenFailed,
  };

  /// The talk's header: the cross, the stage's name and the talk's own minutes. No bar and no beads —
  /// the talk has no cards, and a bar would be a number the phone made up.
  Widget _header(AppLocalizations l, PlanConversation talk) => Padding(
    padding: const EdgeInsets.fromLTRB(kSessionGutter - kSessionCloseInset, 4, kSessionGutter, 0),
    child: SizedBox(
      height: 24,
      child: Row(
        children: [
          SessionCloseButton(onTap: widget.onClose, label: l.planSessionClose),
          const SizedBox(width: 2),
          Text(l.planPlateStageTalk, style: AppTextSession.headerStage),
          const Spacer(),
          Text(
            l.planSessionApproxMinutes(talk.minutesEstimate),
            key: const ValueKey('talk-minutes'),
            style: AppTextSession.meta.copyWith(height: 1),
          ),
        ],
      ),
    ),
  );

  /// THE RIBBON — the server's `turns[]`, oldest first, and the three dots while a move is in flight.
  Widget _ribbon(PlanConversation talk) {
    final rows = <Widget>[];
    TalkTurn? previous;
    for (final turn in talk.turns) {
      final row = _turnRow(talk, turn);
      if (row == null) continue;
      if (rows.isNotEmpty) {
        // Inside one exchange the lines stand 8 apart, between exchanges 16 — an exchange starts
        // with the role's line.
        rows.add(SizedBox(height: !turn.isOwn && previous != null && previous.isOwn ? 16 : 8));
      }
      rows.add(row);
      previous = turn;
    }
    // The move the server has not answered: what the learner said stays on screen while the role
    // «thinks» and after a failure (кадры 37-8, 37-10) — the phone's own words, until the server's
    // copy of them arrives with the answer.
    if (_pendingRow() case final row?) {
      if (rows.isNotEmpty) rows.add(const SizedBox(height: 8));
      rows.add(row);
    }
    if (_talk.phase == TalkPhase.sending) {
      if (rows.isNotEmpty) rows.add(const SizedBox(height: 16));
      rows.add(const TalkThinking());
    }

    return Column(
      key: const ValueKey('talk-ribbon'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: rows,
    );
  }

  Widget? _turnRow(PlanConversation talk, TalkTurn turn) {
    if (turn.isOwn) {
      final text = turn.textTarget;
      // A skipped turn has no words of its own — the ribbon simply goes on. A rescue has the SERVER'S words for it —
      // «Sorry?» in the language of the talk (CONV-2 п. 4а) — and stands as the learner's own ink bubble, with no
      // translation under it and no underline in it (кадр 37-7 «после „Не понял"»).
      if (text == null || text.trim().isEmpty) return null;
      if (turn.kind == TalkTurnKind.rescue) return TalkOwnBubble(key: ValueKey('turn-${turn.index}'), text: text);
      return TalkOwnBubble(key: ValueKey('turn-${turn.index}'), text: text, marks: _marksOf(turn));
    }
    // THE ROLE'S TEXT IS OPEN WITH ITS VOICE (правка прохода 21.09, наряд CLIENT-CONV-1b): the words stand from the
    // first sound, «прослушать» in the corner, no «текст» to tap. Under «Без подсказок» each line stays closed until the
    // learner taps its «текст» (наряд CLIENT-CONV-1c §5, приёмка 22.09) — then that line alone opens.
    final line = '${talk.id}:${turn.index}';
    final open = talk.hints.enabled || _opened.contains(line);
    return ValueListenableBuilder<Object?>(
      key: ValueKey('turn-${turn.index}'),
      valueListenable: widget.voice.playing,
      builder: (_, playing, _) => TalkPartnerBubble(
        text: turn.textTarget ?? '',
        translation: turn.textNative,
        open: open,
        onOpenText: open ? null : () => setState(() => _opened.add(line)),
        playing: playing == 'talk-${turn.index}',
        interrupted: _talk.interruptedAt(turn.index),
        speaking: _talk.sounding(turn.index),
        onListen: () => unawaited(widget.voice.play(turn.audio, fallback: turn.textTarget ?? '', key: 'talk-${turn.index}')),
      ),
    );
  }

  /// The pending move as a row: what was said, without an underline — the server has not matched it yet. A rescue and a
  /// skip wait for the server: the words of a rescue are the server's (its pack's «Sorry?»), and the phone does not
  /// write them in its place.
  Widget? _pendingRow() {
    final move = _talk.pendingMove;
    if (move == null) return null;
    return switch (move.kind) {
      'said' when (move.heard ?? '').trim().isNotEmpty => TalkOwnBubble(key: const ValueKey('talk-pending'), text: move.heard),
      _ => null,
    };
  }

  /// The ranges of the learner's line the SERVER matched to constructions of the day — WHICH construction it heard is
  /// the server's (`phrases_used`, a pair since FIX-3 §6), and the words of that construction are read from `targets[]`
  /// as it said them: the frame with what went into its window. A pair the list does not hold is not marked — the phone
  /// does not guess the words behind a ref.
  List<({int start, int end})> _marksOf(TalkTurn turn) {
    final text = turn.textTarget ?? '';
    final marks = <({int start, int end})>[];
    for (final used in turn.phrasesUsed) {
      final target = _targetOf(used.sceneId, used.ref);
      if (target == null) continue;
      marks.addAll(HeardWords.matched(text, target.saidWith(target.valueTarget)));
    }
    marks.sort((a, b) => a.start.compareTo(b.start));
    return marks;
  }

  Widget _dock(AppLocalizations l, PlanConversation talk) {
    final phase = _talk.phase;
    final listening = _mic.isListening;
    final yourTurn = phase == TalkPhase.yourTurn;
    // A microphone that cannot record (no permission, no recognition on the device) is SAID, not left
    // looking ready: «тап — говорить» over a button that does nothing is the one thing this dock must
    // not print (caught live on a simulator without recognition assets).
    final noMic = _mic.state == MicState.unavailable;
    final look = switch (phase) {
      TalkPhase.agentSpeaking => TalkMicLook.dimmed,
      TalkPhase.sending || TalkPhase.opening || TalkPhase.openFailed || TalkPhase.ended => TalkMicLook.busy,
      TalkPhase.yourTurn when noMic => TalkMicLook.busy,
      TalkPhase.yourTurn => listening ? TalkMicLook.listening : TalkMicLook.waiting,
    };
    final trouble = _talk.trouble;
    // «Не расслышал» is the move's again (37-10): «тап — говорить» over the button, the brass ring of
    // «твоя очередь» around it, and the reason under it.
    final caption = switch (phase) {
      // «слушай» over the dimmed button while the role speaks and while it «thinks» (37-6, 37-8).
      TalkPhase.agentSpeaking || TalkPhase.sending => l.planSessionListenCue,
      TalkPhase.yourTurn when noMic => null,
      TalkPhase.yourTurn => listening ? l.planSessionMicListening : l.planSessionMicTap,
      _ => null,
    };
    final subCaption = listening ? l.planTalkSilenceEnds : (trouble == TalkTrouble.unheard ? l.planTalkUnheard : null);

    return TalkDock(
      debugMic: yourTurn ? _mic : null,
      constructions: _constructions(talk),
      notice: switch (trouble) {
        TalkTrouble.offline => TalkNotice(text: l.planTalkOffline, onAction: () => unawaited(_talk.retry())),
        TalkTrouble.agentSilent => TalkNotice(text: l.planTalkSilent, onAction: () => unawaited(_talk.retry())),
        _ when yourTurn && noMic => TalkNotice(
          text: l.planTalkNoMic,
          actionLabel: l.planSessionNoMicAllow,
          actionKey: const ValueKey('talk-allow-mic'),
          onAction: () => unawaited(_allowMic()),
        ),
        _ => null,
      },
      // The intention comes as a clause (CONV-2 п. 11) and goes into «Скажи, что …» as it came.
      chip: _talk.chipShown && _talk.hintNative != null ? TalkHintChip(text: l.planTalkHintChip(_talk.hintNative!)) : null,
      liveLine: listening && _mic.partial.trim().isNotEmpty
          ? _LiveLine(words: LiveLine.of(_mic.partial, _expected, listening: !_mic.closed))
          : null,
      caption: caption,
      subCaption: subCaption,
      // «Не понял» stands in EVERY state of the learner's move: a rescue is not a hint, and it costs
      // no turn of the scene.
      left: yourTurn
          ? TalkPill(
              key: const ValueKey('talk-rescue'),
              label: l.planTalkRescueAction,
              onTap: () => unawaited(_talk.rescue()),
            )
          : null,
      // While it listens the dock holds «Не понял» and the button alone (37-7 «слушаю»): a hint is asked for
      // before speaking, not in the middle of it.
      right: _talk.hintButtonShown && !listening
          ? TalkPill(key: const ValueKey('talk-hint'), label: l.planSessionHintAction, brass: true, onTap: _talk.showHint)
          : null,
      mic: TalkMicButton(
        look: look,
        label: l.planSessionMicTap,
        onTap: phase == TalkPhase.agentSpeaking || (yourTurn && !noMic) ? () => unawaited(_tapMic()) : null,
      ),
    );
  }

  /// THE END (кадр 37-11): the role's goodbye stays in the ribbon and a sheet rises over it —
  /// «Разговор окончен · N минут» and one button; the constructions stay over it as the talk left them.
  Widget _endSheet(AppLocalizations l, PlanConversation talk) {
    final minutes = talk.summary?.minutes;
    final constructions = _constructions(talk);
    return Column(
      key: const ValueKey('talk-end-sheet'),
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (constructions != null) ...[constructions, const SizedBox(height: 14)],
        SessionSheet(
          child: Row(
            children: [
              Expanded(child: Text(l.planTalkEnded, style: AppTextSession.text15)),
              if (minutes != null) Text(l.planMinutesCount(minutes), style: AppTextSession.meta),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SessionDockButton(key: const ValueKey('talk-summary-action'), label: l.planTalkSummaryAction, onTap: widget.onSummary),
      ],
    );
  }
}

/// THE PLAN'S SCENE THE TALK IS IN NOW — the photo of the strip (наряд CLIENT-CONV-1b). The document names the
/// scene by its title (`scene.title_native`, the same as its `scenes[]` entry) and marks it `current`; an ended talk
/// marks none, and then its last scene is the one named. Null — no such scene in the plan.
PlanScene? talkStripScene(PlanConversation talk, {PlanScene? Function(String sceneId)? sceneById}) {
  if (sceneById == null || talk.scenes.isEmpty) return null;
  final named = talk.scenes.where((s) => s.titleNative == talk.partner.sceneNative).firstOrNull;
  final current = talk.scenes.where((s) => s.state == TalkSceneState.current).firstOrNull;
  final scene = named ?? current ?? talk.scenes.last;
  return sceneById(scene.sceneId);
}

/// The live line of a talk — Literata 22 centred, the words of the plan's phrases in sage.
class _LiveLine extends StatelessWidget {
  const _LiveLine({required this.words});

  final List<LiveWord> words;

  @override
  Widget build(BuildContext context) => Text.rich(
    key: const ValueKey('talk-live-line'),
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
  );
}
