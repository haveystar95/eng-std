import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../data/api_client.dart' show problemCodeOf;
import '../../../data/plan/conversation/conversation_models.dart';
import '../../../data/plan/plan_models.dart';
import '../../../data/plan/session/heard_words.dart';
import '../../../data/plan/session/live_line.dart';
import '../../../data/plan/session/speech_match.dart' show SpeechRules;
import '../../../data/speech/speech_turn.dart';
import '../session/cards/card_kit.dart' show CardLayout;
import '../session/parts/session_bits.dart';
import '../session/parts/session_chrome.dart';
import '../session/session_mic.dart';
import '../session/session_texts.dart';
import '../session/session_voice.dart';
import 'conversation_controller.dart';
import 'talk_constructions.dart';
import 'talk_ribbon.dart';

/// THE TALK (кадры 37-6…37-11, 39-1) — the ribbon, the microphone, the three ways a move can fail and, on the rehearsal
/// and a review, the walk from scene to scene.
///
/// THE MICROPHONE OPENS ONLY ON A TAP. No state of this screen starts a recording by itself: not the
/// end of the role's line, not the hint, not a failure (DECISIONS п. 299 — «Запись начинает
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
    this.speech = SpeechRules.none,
    this.openSettings,
    this.sceneById,
  });

  final ConversationController controller;

  /// The day's scene — the strip's photo when the talk's own scene is not found in the plan.
  final PlanScene? scene;

  /// The plan's scene by id — the photo of the scene the talk is in NOW and of the next one on its transition card.
  final PlanScene? Function(String sceneId)? sceneById;
  final SessionVoice voice;

  /// The talk's own microphone: free speech, the target language's locale.
  final SessionMic Function() makeMic;

  /// «Итог» on the end sheet (37-11).
  final VoidCallback onSummary;
  final VoidCallback onClose;

  /// The day's speech rules — the same foldings the cards grade by; they say what «39» and «thirty nine», «p.m.» and
  /// «pm» are when the own bubble's underline reads the construction's words.
  final SpeechRules speech;

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
    if (_talk.canInterrupt) {
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
    final talk = _talk.talk;
    final targets = talk?.targetsOf(talk.currentSceneId) ?? const <TalkTarget>[];
    return targets.map((t) => t.lessonLine).join(' ');
  }

  /// THE CONSTRUCTIONS OVER THE MICROPHONE (наряд CLIENT-FIX-4 §2, кадры 37-7…37-11) — the ones of the scene the talk
  /// is in that are still to say, as the SERVER's judge left them; a talk whose server sent none has no row. A tap on the
  /// row opens the sheet of the scene (37-8d), which reads the talk again while it is open. The row is keyed by its
  /// scene: the next scene's row is a new one, and the plates of the scene left behind do not ride out of it.
  Widget? _constructions(PlanConversation talk) {
    final scene = talk.currentSceneId;
    final targets = talk.targetsOf(scene);
    if (targets.isEmpty) return null;
    return TalkConstructionChips(
      key: ValueKey('talk-constructions-of-$scene'),
      targets: targets,
      onTap: () => unawaited(
        showTalkConstructionSheet(
          context,
          talk: _talk,
          targets: () {
            final now = _talk.talk;
            return now == null ? const [] : now.targetsOf(now.currentSceneId);
          },
        ),
      ),
    );
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
                    DockButton(label: l.planTabRetry, onTap: () => unawaited(_talk.open())),
                    const SizedBox(height: 8),
                    TextButton(onPressed: widget.onClose, child: Text(l.planWindowBack, style: AppTextSession.skip)),
                  ],
                )
              : const CircularProgressIndicator(color: AppColors.ink),
        ),
      );
    }
    final ended = _talk.endSheetUp;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _header(l, talk),
        _strip(talk),
        Expanded(
          child: CardLayout(
            feed: true,
            bodyGap: 16,
            fadeStop: 0.30,
            task: null,
            body: _ribbon(l, talk),
            // THE END SHEET (37-11) takes the dock's place and runs the screen's width, gutter to gutter, on the ground.
            bottom: ended ? null : _dock(l, talk),
          ),
        ),
        if (ended) TalkEndSheet(minutes: talk.summary?.minutes, onSummary: widget.onSummary),
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

  /// THE SCENE THE TALK IS IN (30-2b) — on the rehearsal it moves from scene to scene with the role: while the
  /// transition card stands it still names the scene that has just said goodbye, and on «Продолжить» it turns to the
  /// next one with a 200 ms fade (39-1).
  Widget _strip(PlanConversation talk) {
    final id = _talk.stripSceneId;
    final named = talk.sceneOf(id);
    final strip = SessionSceneStrip(
      key: ValueKey('talk-strip-${id ?? '-'}'),
      scene: talkStripScene(talk, sceneId: id, sceneById: widget.sceneById) ?? widget.scene,
      title: named?.titleNative ?? talk.partner.sceneNative,
      role: named?.roleNative ?? talk.partner.roleNative,
    );
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return strip;
    return AnimatedSwitcher(
      duration: AppMotion.talkSceneStripFade,
      layoutBuilder: (current, previous) => Stack(alignment: Alignment.topLeft, children: [...previous, ?current]),
      child: strip,
    );
  }

  /// THE RIBBON — the server's `turns[]`, oldest first: every earlier boundary of scenes as its divider, the next
  /// scene's greeting held back behind the transition card until «Продолжить» (39-1), the hint plate under the role's
  /// last line while the move is the learner's (37-7), the judge's «Почти — скажи целиком» under the learner's line it
  /// was about (37-8e), and the three dots while a move is in flight.
  Widget _ribbon(AppLocalizations l, PlanConversation talk) {
    final rows = <Widget>[];
    TalkTurn? previous;
    final held = _talk.heldStart;
    final hint = _talk.hint;
    final lastPartner = talk.lastPartnerTurn;
    final lastOwn = talk.lastOwnTurn;

    void gap(double height) {
      if (rows.isNotEmpty) rows.add(SizedBox(height: height));
    }

    for (final (position, turn) in talk.turns.indexed) {
      if (turn.index == held) break;
      if (talk.opensScene(position)) {
        gap(16);
        rows.add(_boundary(l, talk, turn.sceneId, cardFor: null));
        previous = null;
      }
      final row = _turnRow(talk, turn);
      if (row == null) continue;
      // Inside one exchange the lines stand 8 apart, between exchanges 16 — an exchange starts with the role's line.
      gap(previous == null ? 16 : (!turn.isOwn && previous.isOwn ? 16 : 8));
      rows.add(row);
      previous = turn;
      if (turn.isOwn && turn.index == _talk.almostAt && turn.index == lastOwn?.index) {
        rows
          ..add(const SizedBox(height: 8))
          ..add(const TalkJudgeLine());
      }
      if (!turn.isOwn && turn.index == lastPartner?.index && hint != null) {
        rows
          ..add(const SizedBox(height: 8))
          ..add(
            TalkHintPlate(
              sentence: hint.sentence,
              line: hint.line,
              open: hint.open,
              onTap: hint.line == null ? null : _talk.toggleHint,
            ),
          );
      }
    }
    // The boundary the ribbon stops at: the transition card while it stands, the divider it folds into after
    // «Продолжить» while the next role draws breath.
    if (held != null) {
      final greeting = talk.turns.where((t) => t.index == held).firstOrNull;
      gap(16);
      rows.add(_boundary(l, talk, greeting?.sceneId, cardFor: _talk.phase == TalkPhase.sceneChange ? greeting : null));
    }
    // The move the server has not answered: what the learner said stays on screen while the role
    // «thinks» and after a failure (кадры 37-8, 37-10) — the phone's own words, until the server's
    // copy of them arrives with the answer.
    if (_pendingRow() case final row?) {
      gap(8);
      rows.add(row);
    }
    if (_talk.phase == TalkPhase.sending) {
      gap(16);
      rows.add(const TalkThinking());
    }

    return Column(
      key: const ValueKey('talk-ribbon'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: rows,
    );
  }

  /// A BOUNDARY OF SCENES: the transition card of [cardFor]'s scene while it stands (39-1), otherwise the divider
  /// «Сцена N · название · роль» (39-1b). The card folds into the divider in 220 ms on «Продолжить».
  Widget _boundary(AppLocalizations l, PlanConversation talk, String? sceneId, {required TalkTurn? cardFor}) {
    final scene = talk.sceneOf(sceneId);
    final number = talk.sceneNumberOf(sceneId);
    // The scene's name and its role are the server's, in the learner's language (CLIENT-22-1 §2).
    final title = context.nativeText(scene?.titleNative.trim() ?? '');
    final role = SessionTexts.roleInline(context.nativeText(scene?.roleNative.trim() ?? ''));
    final Widget child = cardFor == null
        ? TalkSceneDivider(key: ValueKey('talk-scene-divider-$sceneId'), label: talkSceneLabel(l, number: number, title: title, role: role))
        : TalkSceneCard(
            key: ValueKey('talk-scene-card-$sceneId'),
            number: number ?? 1,
            total: talk.scenes.length,
            title: title,
            role: role,
            targets: talk.targetsOf(sceneId),
            photo: sceneId == null ? null : widget.sceneById?.call(sceneId)?.image,
            onContinue: () => unawaited(_talk.continueScene()),
          );
    // Under «Уменьшение движения» the card is simply replaced by its divider.
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return child;
    return AnimatedSize(
      duration: AppMotion.talkSceneCardFold,
      curve: AppMotion.sessionEaseOut,
      alignment: Alignment.topCenter,
      child: AnimatedSwitcher(
        duration: AppMotion.talkSceneCardFold,
        switchInCurve: AppMotion.sessionEaseOut,
        switchOutCurve: AppMotion.sessionEaseOut,
        layoutBuilder: (current, previous) => Stack(alignment: Alignment.topCenter, children: [...previous, ?current]),
        child: child,
      ),
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
      return TalkOwnBubble(key: ValueKey('turn-${turn.index}'), text: text, marks: _marksOf(talk, turn));
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

  /// The ranges of the learner's line the SERVER matched to constructions of the talk — WHICH construction it heard is
  /// the server's (`phrases_used` for the targets, `extra_said` for «Ещё вспомнил», pairs since FIX-3 §6 / FIX-4 §2), and
  /// its words are read from the talk's lists: THE IMMOVABLE PART OF THE FRAME AND THE VALUE the learner put in its
  /// window, both the server's fields (приёмка окна 2, п. 1). Letter case aside and through the day's own foldings, so
  /// «can I take» of a line is the «Can I take ___ onboard?» of the lesson. A pair the lists do not hold is not marked —
  /// the phone does not guess the words behind a ref.
  List<({int start, int end})> _marksOf(PlanConversation talk, TalkTurn turn) {
    final text = turn.textTarget ?? '';
    // Одно слово — одна линия: две конструкции одного хода часто делят слова («I»), и метка у них общая.
    final marks = <({int start, int end})>{};
    for (final used in [...turn.phrasesUsed, ...turn.extraSaid]) {
      final target = talk.constructionOf(used.sceneId, used.ref);
      if (target == null) continue;
      marks.addAll(HeardWords.wordsIn(text, [target.frameFixed, target.valueTarget], widget.speech));
    }

    return marks.toList()..sort((a, b) => a.start.compareTo(b.start));
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
      TalkPhase.sending || TalkPhase.opening || TalkPhase.openFailed || TalkPhase.sceneChange || TalkPhase.ended => TalkMicLook.busy,
      TalkPhase.yourTurn when noMic => TalkMicLook.busy,
      TalkPhase.yourTurn => listening ? TalkMicLook.listening : TalkMicLook.waiting,
    };
    final trouble = _talk.trouble;
    // «Не расслышал» is the move's again (37-10): «тап — говорить» over the button, the brass ring of
    // «твоя очередь» around it, and the reason under it.
    final caption = switch (phase) {
      // «слушай» over the dimmed button while the role speaks and while it «thinks» (37-6, 37-8) — the last scene's
      // goodbye too, before the end sheet rises.
      TalkPhase.agentSpeaking || TalkPhase.sending || TalkPhase.ended => l.planSessionListenCue,
      TalkPhase.yourTurn when noMic => null,
      TalkPhase.yourTurn => listening ? l.planSessionMicListening : l.planSessionMicTap,
      // The transition card is the one thing to do (39-1): no caption over the dimmed button.
      _ => null,
    };
    final subCaption = listening ? l.planTalkSilenceEnds : (trouble == TalkTrouble.unheard ? l.planTalkUnheard : null);

    return TalkDock(
      debugMic: yourTurn ? _mic : null,
      // While the transition card stands the row hides — the next scene's constructions are on the card (39-1).
      constructions: phase == TalkPhase.sceneChange ? null : _constructions(talk),
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
      // «Подсказать» — only under «Без подсказок» (37-7c); with hints on the plate already stands under the role's line.
      // While it listens the dock holds «Не понял» and the button alone (37-7 «слушаю»): a hint is asked for before
      // speaking, not in the middle of it.
      right: _talk.hintButtonShown && !listening
          ? TalkPill(key: const ValueKey('talk-hint'), label: l.planSessionHintAction, brass: true, onTap: _talk.showHint)
          : null,
      mic: TalkMicButton(
        look: look,
        label: l.planSessionMicTap,
        onTap: _talk.canInterrupt || (yourTurn && !noMic) ? () => unawaited(_tapMic()) : null,
      ),
    );
  }
}

/// THE PLAN'S SCENE THE STRIP NAMES — its photo (наряды CLIENT-CONV-1b, CLIENT-FIX-4). [sceneId] — the scene the
/// screen names now ([ConversationController.stripSceneId]); without it the document's own scene, found by its title
/// among `scenes[]`, or the one marked `current`, or the last. Null — no such scene in the plan.
PlanScene? talkStripScene(PlanConversation talk, {String? sceneId, PlanScene? Function(String sceneId)? sceneById}) {
  if (sceneById == null) return null;
  if (sceneId != null) return sceneById(sceneId);
  if (talk.scenes.isEmpty) return null;
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
