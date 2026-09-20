import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/dialogue_feed.dart';
import '../../../../data/plan/session/live_line.dart';
import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_match.dart';
import '../parts/session_bits.dart';
import '../parts/session_bubbles.dart';
import '../parts/session_choice.dart';
import '../parts/session_tiles.dart';
import '../session_texts.dart';
import 'card_kit.dart';
import 'word_cards.dart' show autoplayOnce;

/// DIALOGUE — canvas series 33 (work order SESSION-1c, section 2): one exchange per card, in the order of the visit,
/// with the conversation so far standing above it ([CardEnv.feed], 33-7) and growing from the bottom.

/// The conversation above the card and the gap to the card's own bubbles — [CardLayout.above], the one part of the
/// field that may scroll (FIX-1 §1); null — nothing has been said yet.
Widget? _feed(CardEnv env) => env.feed.isEmpty
    ? null
    : Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: SessionFeedView(
          lines: env.feed,
          listen: (line) =>
              CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: 'feed-${line.ref}', size: 28, brass: true),
        ),
      );

/// The partner's bubble with its text and «listen» 28.
Widget _partnerRow(CardEnv env, CardLine line, {required Object playKey, double rate = 1.0}) => SessionPartnerRow(
  bubble: SessionBubble(own: false, text: line.textTarget, translation: line.textNative),
  listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: playKey, size: 28, brass: true, rate: rate),
);

/// The pronunciation key inside [text] — case-insensitive; not found — no underline.
TextRange? _keyIn(String text, String? key) {
  if (key == null || key.trim().isEmpty) return null;
  final at = text.toLowerCase().indexOf(key.trim().toLowerCase());
  return at < 0 ? null : TextRange(start: at, end: at + key.trim().length);
}

// ── 33-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// UNDERSTAND THE PARTNER (33-1): the partner's line sounds once when the card opens; its bubble holds a wave, the
/// text is closed; four paraphrases in the native language (template 30-9). Correct — the text opens in the bubble,
/// auto-advance after 600 ms; wrong — an outline on the chosen one, sage on the correct one, «Next».
///
/// The server's `question_native` is the QUESTION OF THE CARD and stands where the thing it asks about is — right
/// above the closed bubble, in the card's question type, with «Answer the question» small over it
/// ([SessionCheckQuestion], FIX-1 доработка). The screen has no task line of its own any more.
class DialoguePartnerCard extends StatefulWidget {
  const DialoguePartnerCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final DialoguePartnerPayload payload;

  @override
  State<DialoguePartnerCard> createState() => _DialoguePartnerCardState();
}

class _DialoguePartnerCardState extends State<DialoguePartnerCard> with ChoiceCardState<DialoguePartnerCard> {
  static const _key = 'partner-line';

  Timer? _autoplay;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  void initState() {
    super.initState();
    final line = widget.payload.partnerLine;
    _autoplay = autoplayOnce(this, env, line.audio, line.textTarget, _key);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.payload;
    final line = p.partnerLine;
    final open = answeredCorrectly;
    return CardLayout(
      feed: true,
      bodyGap: 16,
      // THE QUESTION STANDS WITH THE BUBBLE IT ASKS ABOUT (FIX-1 доработка) — see [SessionCheckQuestion]; the grey
      // task line at the top edge of the screen is gone from this card.
      task: null,
      above: _feed(env),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionCheckQuestion(task: AppLocalizations.of(context).planSessionTaskAnswerQuestion, question: p.questionNative),
          const SizedBox(height: 12),
          SessionPartnerRow(
            bubble: ValueListenableBuilder<Object?>(
              valueListenable: env.voice.playing,
              builder: (_, playing, _) => SessionBubble(
                own: false,
                translation: open ? line.textNative : null,
                child: AnimatedSwitcher(
                  duration: AppMotion.sessionTextReveal,
                  switchInCurve: AppMotion.sessionEaseOut,
                  child: open
                      ? Text(line.textTarget, key: const ValueKey('partner-text'), style: SessionBubble.lineStyle(own: false))
                      : SessionWave(key: const ValueKey('partner-wave'), heights: SessionWave.five, width: 80, playing: playing == _key),
                ),
              ),
            ),
            listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _key, size: 28, brass: true),
          ),
        ],
      ),
      bottom: optionsDock(context),
    );
  }
}

// ── 33-2 · 33-3 · 33-4 · 33-5 ──────────────────────────────────────────────────────────────────────

/// ANSWER THE PARTNER / ASK (33-2 chips · 33-3 voice with the line · 33-4 blind voice; 33-5 — the learner speaks first):
/// the own bubble is ink. The mode is the plan's level and «No hints» ([SessionRules.dialogueMode]):
/// * chips — the frame with an empty slot, `modes.chips` under the bubble; any chip is right → `passed`
///   (`mode = chips`, `filler_index`), «Next»;
/// * voice with the line — `modes.voice_hint` in the bubble, the key underlined in brass; the frame's own words
///   covered → `passed` (`mode = voice_hint`);
/// * blind voice — the frame with an empty slot; the frame covered, any slot → `passed` (`mode = voice_blind`).
///
/// Two misses — `skipped`. The live line stands in the own bubble, not over the button; an `answer` leaves by itself
/// after a voice pass.
///
/// AN `ask` (33-5) IS THE WHOLE EXCHANGE ON ONE CARD (work order SESSION-2b §2, contract BACK-TAILS-1 §1.5): after the
/// pass the partner's reply appears and sounds with its TEXT CLOSED — a wave and «listen» 44 — and the card asks the
/// check the payload carries (`question_native`, four options, `correct`): the question stands over the closed reply
/// in the card's question type and the options in the dock, and the screen's own task line steps aside for it
/// (FIX-1 доработка). A choice opens the reply's text in the same bubble and marks the right option;
/// correct — away by itself, wrong — «Next», as in every other check. The choice is graded on the phone and sent
/// nowhere: the card's own result is the voice one, already recorded. A card without the check (the day could not
/// build it) opens the text at once, as before.
class DialogueAnswerCard extends StatefulWidget {
  const DialogueAnswerCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final DialogueAnswerPayload payload;

  @override
  State<DialogueAnswerCard> createState() => _DialogueAnswerCardState();
}

class _DialogueAnswerCardState extends State<DialogueAnswerCard> with VoiceCardState<DialogueAnswerCard> {
  static const _replyKey = 'partner-reply';

  late final DialogueMode _mode = SessionRules.dialogueMode(widget.payload, level: env.level, noHints: env.noHints);

  /// The chip chosen (chips mode) — the answer; the chips lock.
  CardFiller? _chip;

  /// What went into the slot on a blind voice pass — a known filler heard whole, otherwise the words beyond the frame.
  ({String target, String? native})? _heardSlot;

  /// `dialogue_ask`: the partner's reply has appeared.
  bool _reply = false;

  /// The option chosen in the exchange's check (33-5); null — not answered yet.
  String? _chosen;
  int _shake = 0;

  /// The voice answer of an ask with a check, waiting for the choice: both fly as ONE answer (BACK-TAILS-1 §1,
  /// доработка — a wrong choice brings the exchange back, and the server reads it beside `result`).
  SessionAnswer? _held;

  DialogueAnswerPayload get p => widget.payload;

  bool get _ask => env.card.kind == SessionKind.dialogueAsk;

  /// The check of this exchange — only an `ask` carries one, and only when the day could build it.
  CardCheck? get _check => _ask ? p.check : null;

  /// The check is on screen: the reply has come and there is something to ask.
  bool get _checking => _reply && _check != null;

  bool get _checkedRight => _chosen != null && _chosen == _check?.correct;

  String get _framePart => SessionRules.framePart(p.frame.frameTarget);

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => SessionRules.dialogueExpected(p, _mode);

  @override
  List<String> get contextual => [
    p.ownLine.textTarget,
    _framePart,
    for (final f in p.frame.fillers) f.target,
    ...p.ownLine.textTarget.split(RegExp(r'\s+')).where((w) => w.trim().isNotEmpty),
  ];

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(p, heard, env.speech);

  @override
  String? get responseMode => _mode.wire;

  @override
  bool get autoAdvanceOnPass => !_ask;

  @override
  void initState() {
    super.initState();
    initVoice();
  }

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  /// A voice attempt passed (a «Skip» also closes the card, but is not a pass).
  bool _voicePassed = false;

  /// «Skip» was tapped — the learner is leaving the exchange, so nothing of it is asked afterwards.
  bool _left = false;

  @override
  void onAccepted(String heard) {
    _voicePassed = true;
    _heardSlot = _slotOf(heard);
    if (_ask) unawaited(_showReply());
  }

  /// BOTH ATTEMPTS SPENT — THE EXCHANGE STILL ANSWERS (FIX-1 §1). The reply comes in the same closed bubble and
  /// sounds, and the check is asked as after a pass: the answer of the card (`skipped`) waits for the choice and
  /// both fly together. Without it the learner met the reply only in the next card's conversation — open text that
  /// never sounded (the live pass of 18.09, exchange x5).
  @override
  void onMissedOut(String heard) {
    if (_ask) unawaited(_showReply());
  }

  @override
  void skip({bool noMic = false}) {
    _left = true;
    super.skip(noMic: noMic);
  }

  /// The slot as heard: a filler of the frame said whole, otherwise the words beyond the frame; none — null.
  ({String target, String? native})? _slotOf(String heard) {
    if (!p.frame.hasSlot) return null;
    for (final f in p.frame.fillers) {
      if (SpeechMatch.containsSequence(heard, f.target, env.speech)) return (target: f.target, native: f.nativeLine);
    }
    final frameWords = SpeechMatch.words(_framePart).toSet();
    final words = heard
        .split(RegExp(r'\s+'))
        .where((w) {
          final tokens = SpeechMatch.words(w);
          return tokens.isNotEmpty && !tokens.every(frameWords.contains);
        })
        .join(' ')
        .replaceAll(RegExp(r'[.!?,;:]+$'), '');
    return words.isEmpty ? null : (target: words, native: null);
  }

  Future<void> _showReply() async {
    final line = p.partnerLine;
    if (line == null || !mounted) return;
    setState(() => _reply = true);
    await env.voice.play(line.audio, fallback: line.textTarget, key: _replyKey);
  }

  /// THE ANSWER OF AN ASK WITH A CHECK WAITS FOR THE CHOICE: the voice result is known when the learner speaks or
  /// runs out of attempts, the choice when they answer the question, and the server takes both in one answer
  /// (`result` + `choice`). A card without a check, one whose reply the day did not deal, and a «Skip» (the learner
  /// left — the check never comes up) fly at once.
  @override
  void submitAnswer(SessionAnswer answer) {
    if (_check == null || p.partnerLine == null || _left) {
      env.submit(answer);
      return;
    }
    _held = answer;
  }

  /// The check's option (33-5): it goes to the server beside the voice result — a wrong one costs the exchange a
  /// copy and then its return, exactly as the choice card it replaced.
  void _chooseCheck(String optionId) {
    if (_chosen != null) return;
    final correct = optionId == _check?.correct;
    setState(() {
      _chosen = optionId;
      if (!correct) _shake++;
    });
    final held = _held;
    if (held != null) {
      _held = null;
      env.submit(held.withChoice(optionId));
    }
    if (correct) {
      AppHaptics.success();
    } else {
      AppHaptics.warning();
    }
    SessionSounds.verdict(correct: correct);
    if (correct) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(env.next());
      }));
    }
  }

  OptionLook _lookOf(String optionId) {
    if (_chosen == null) return OptionLook.idle;
    if (optionId == _check?.correct) return OptionLook.correct;
    if (optionId == _chosen) return OptionLook.wrong;
    return OptionLook.settled;
  }

  Future<void> _pickChip(CardFiller f) async {
    if (_chip != null) return;
    setState(() => _chip = f);
    AppHaptics.success();
    SessionSounds.verdict(correct: true);
    submitAnswer(SessionAnswer(result: SessionResult.passed, attempts: 1, response: SessionResponse(mode: DialogueMode.chips.wire, fillerIndex: f.index)));
    await env.voice.play(f.audio, fallback: DialogueFeed.filledSentence(p.frame, f), key: 'chip-${f.index}');
    if (_ask && mounted) await _showReply();
  }

  bool get _passed => _chip != null || _voicePassed;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (_mode != DialogueMode.chips) {
      final noMic = noMicBody((s) => SessionTexts.stage(l, s));
      if (noMic != null) return noMic;
    }
    final own = SessionOwnRow(mark: _passed ? FeedMark.passed : FeedMark.none, bubble: _ownBubble());
    // After a rescue of this line the partner's bubble already stands in the feed, before the slow repeat.
    final partner = p.partnerLine == null || (!_ask && DialogueFeed.partnerInFeed(env.feed, p.partnerLine))
        ? null
        : _checking && _chosen == null
        ? _closedReply(p.partnerLine!)
        : _partnerRow(env, p.partnerLine!, playKey: _ask ? _replyKey : 'partner-line');
    final rows = _ask
        ? [own, if (_reply && partner != null) SessionAppear(child: _checking ? _withQuestion(l, partner) : partner)]
        : [?partner, own];
    return CardLayout(
      feed: true,
      bodyGap: 16,
      fadeStop: _mode == DialogueMode.chips ? 0.34 : 0.30,
      // The check's question is not a line at the top of the screen: it stands with the closed reply ([_withQuestion]).
      task: _checking
          ? null
          : SessionTask(switch (_mode) {
              _ when _ask => l.planSessionTaskSayLine,
              DialogueMode.chips => l.planSessionTaskCollectAnswer,
              DialogueMode.voiceHint || DialogueMode.voiceBlind => l.planSessionTaskSayLine,
            }),
      above: _feed(env),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (i, row) in rows.indexed) ...[if (i > 0) const SizedBox(height: 8), row],
        ],
      ),
      bottom: _dock(context),
    );
  }

  /// The own bubble in every state of the mode.
  Widget _ownBubble() {
    final style = SessionBubble.lineStyle(own: true);
    final listening = mic.isListening && mic.partial.trim().isNotEmpty;
    switch (_mode) {
      case DialogueMode.chips:
        final chip = _chip;
        return SessionBubble(
          own: true,
          translation: chip == null ? p.frame.frameNative : (chip.nativeLine ?? p.ownLine.textNative),
          child: _frame(style, slot: chip?.target, look: chip == null ? SlotLook.empty : SlotLook.filled),
        );
      case DialogueMode.voiceHint:
        final line = p.modes.voiceHint;
        return SessionBubble(
          own: true,
          translation: p.ownLine.textNative,
          child: listening && !_passed
              ? SessionHeardLine(text: line, heard: mic.partial)
              : SessionFrameText.plain(line, style: style, underline: _keyIn(line, p.ownLine.key), onInk: true),
        );
      case DialogueMode.voiceBlind:
        if (listening && !_passed) {
          return SessionBubble(own: true, child: SessionInkLiveLine(words: LiveLine.of(mic.partial, _framePart, listening: !mic.closed)));
        }
        if (!_passed) {
          return SessionBubble(own: true, translation: p.frame.frameNative, child: _frame(style, look: SlotLook.empty));
        }
        // Passed: the frame with what was heard in the slot — a known filler with its native line, other words without
        // a translation (nobody wrote one); nothing beyond the frame (a frame without a slot) — the line itself.
        final slot = _heardSlot;
        if (slot == null) return SessionBubble(own: true, text: p.ownLine.textTarget, translation: p.ownLine.textNative);
        return SessionBubble(own: true, translation: slot.native, child: _frame(style, slot: slot.target, look: SlotLook.filled));
    }
  }

  /// THE CHECK AS ONE BLOCK (33-5, FIX-1 доработка): the task line, the question in the card's own question type,
  /// and right under them the reply the question is about — closed while it is being asked, open after the choice.
  Widget _withQuestion(AppLocalizations l, Widget reply) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      SessionCheckQuestion(task: l.planSessionTaskAnswerQuestion, question: _check!.questionNative),
      const SizedBox(height: 12),
      reply,
    ],
  );

  /// The partner's reply with its text CLOSED (33-5): a wave in the bubble and «listen» 44 beside it — the learner
  /// answers the check by ear.
  Widget _closedReply(CardLine line) => SessionPartnerRow(
    bubble: ValueListenableBuilder<Object?>(
      valueListenable: env.voice.playing,
      builder: (_, playing, _) => SessionBubble(
        own: false,
        child: SessionWave(key: const ValueKey('reply-wave'), heights: SessionWave.five, width: 80, playing: playing == _replyKey),
      ),
    ),
    listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _replyKey, size: 44, brass: true),
  );

  /// The frame in the own bubble with its slot; the key — the frame's words before the slot — underlined in brass.
  Widget _frame(TextStyle style, {String? slot, required SlotLook look}) {
    final parts = p.frame.parts;
    return SessionFrameText(
      before: parts.before,
      after: parts.after,
      style: style,
      window: p.frame.hasSlot,
      slot: slot,
      look: look,
      onInk: true,
      underline: _keyIn(parts.before, p.ownLine.key),
    );
  }

  Widget _dock(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (_mode == DialogueMode.chips) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final f in p.modes.chips)
                SessionTile(
                  key: ValueKey('chip-${f.index}'),
                  text: f.target,
                  height: 40,
                  selected: _chip?.index == f.index,
                  onTap: _chip == null ? () => unawaited(_pickChip(f)) : null,
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(l.planSessionAnyChip, style: AppTextSession.meta),
          const SizedBox(height: 14),
          if (_checking) ..._checkDock(l) else ...[
            if (_ask && _reply) ...[_playingLine(l), const SizedBox(height: 14)],
            SessionDockButton(
              label: l.planSessionNext,
              enabled: _chip != null,
              busy: env.advancing,
              onTap: _chip == null ? null : () => unawaited(env.next()),
            ),
          ],
        ],
      );
    }
    if (_checking) {
      return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: _checkDock(l));
    }
    if (_ask && _passed) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _playingLine(l),
          const SizedBox(height: 14),
          SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
        ],
      );
    }
    return voiceDock(context, liveLineInDock: false);
  }

  /// The check's four options (33-5) and, after a wrong one, «Next» — the same dock as every other check.
  List<Widget> _checkDock(AppLocalizations l) {
    final check = _check!;
    return [
      for (final o in check.options) ...[
        if (o != check.options.first) const SizedBox(height: 8),
        SessionOption(
          key: ValueKey('option-${o.id}'),
          text: o.text,
          look: _lookOf(o.id),
          shake: o.id == _chosen ? _shake : 0,
          onTap: _chosen == null ? () => _chooseCheck(o.id) : null,
        ),
      ],
      if (_chosen != null && !_checkedRight) ...[
        const SizedBox(height: 8),
        SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
      ],
    ];
  }

  /// «playing» under the partner's reply — the wave moves only while it sounds.
  Widget _playingLine(AppLocalizations l) => ValueListenableBuilder<Object?>(
    valueListenable: env.voice.playing,
    builder: (_, playing, _) => Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        SessionWave(heights: SessionWave.five, width: 80, playing: playing == _replyKey),
        const SizedBox(width: 12),
        Text(l.planSessionPlaying, style: AppTextSession.meta),
      ],
    ),
  );
}

// ── 33-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// «DIDN'T CATCH THAT» (33-6): the line the learner did not catch (`asked_line`) sounds in the partner's bubble, and
/// the bubble holds «Didn't catch that» 44 (a screen reader says the rescue line itself). A tap — the rescue line
/// appears in the own bubble and sounds, then the partner repeats (`partner_repeat`) at `slow_rate` with its text; the
/// wave «slowly» moves while it sounds. No microphone on the frame: «Next» → `passed`.
class DialogueRescueCard extends StatefulWidget {
  const DialogueRescueCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final DialogueRescuePayload payload;

  @override
  State<DialogueRescueCard> createState() => _DialogueRescueCardState();
}

class _DialogueRescueCardState extends State<DialogueRescueCard> {
  static const _askedKey = 'asked-line';
  static const _rescueKey = 'rescue-line';
  static const _repeatKey = 'partner-repeat';

  bool _asked = false;
  bool _repeat = false;
  Timer? _autoplay;

  DialogueRescuePayload get p => widget.payload;

  @override
  void initState() {
    super.initState();
    final asked = p.askedLine;
    if (asked != null) _autoplay = autoplayOnce(this, widget.env, asked.audio, asked.textTarget, _askedKey);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    super.dispose();
  }

  Future<void> _notUnderstood() async {
    if (_asked) return;
    _autoplay?.cancel();
    final env = widget.env;
    setState(() => _asked = true);
    await env.voice.play(p.rescueLine.audio, fallback: p.rescueLine.textTarget, key: _rescueKey);
    if (!mounted) return;
    setState(() => _repeat = true);
    await env.voice.play(p.partnerRepeat.audio, fallback: p.partnerRepeat.textTarget, rate: p.slowRate, slowFallback: true, key: _repeatKey);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final asked = p.askedLine;
    final button = SessionBrassButton(
      key: const ValueKey('rescue-not-understood'),
      label: l.planSessionNotUnderstood,
      semanticsLabel: p.rescueLine.textTarget,
      onTap: _asked ? null : () => unawaited(_notUnderstood()),
    );
    return CardLayout(
      feed: true,
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskRescue),
      above: _feed(env),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (asked != null)
            SessionPartnerRow(
              bubble: SessionBubble(
                own: false,
                text: asked.textTarget,
                translation: asked.textNative,
                footer: _asked ? null : Padding(padding: const EdgeInsets.only(top: 12), child: Align(alignment: Alignment.centerLeft, child: button)),
              ),
              listen: CardListen(env: env, audio: asked.audio, fallback: asked.textTarget, playKey: _askedKey, size: 28, brass: true),
            )
          else if (!_asked)
            Align(alignment: Alignment.centerLeft, child: button),
          if (_asked) ...[
            const SizedBox(height: 8),
            SessionAppear(
              child: SessionOwnRow(
                bubble: SessionBubble(own: true, text: p.rescueLine.textTarget, translation: p.rescueLine.textNative),
              ),
            ),
          ],
          if (_repeat) ...[
            const SizedBox(height: 8),
            SessionAppear(child: _partnerRow(env, p.partnerRepeat, playKey: _repeatKey, rate: p.slowRate)),
          ],
        ],
      ),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_repeat) ...[
            ValueListenableBuilder<Object?>(
              valueListenable: env.voice.playing,
              builder: (_, playing, _) => Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  SessionWave(heights: SessionWave.five, width: 80, playing: playing == _repeatKey),
                  const SizedBox(width: 12),
                  Text(l.planSessionSlowly, style: AppTextSession.meta),
                ],
              ),
            ),
            const SizedBox(height: 14),
          ],
          SessionDockButton(
            label: l.planSessionNext,
            busy: env.advancing,
            onTap: () {
              env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
              unawaited(env.next());
            },
          ),
        ],
      ),
    );
  }
}
