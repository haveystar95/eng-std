import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../../../../data/plan/session/speech_coverage.dart';
import '../../../../data/plan/session/voice_rounds.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_tiles.dart';
import '../session_texts.dart';
import 'card_kit.dart';
import 'word_cards.dart' show autoplayOnce, kAutoplayDelay, kRepeatRate;

/// PHRASES — canvas series 32: a frame with a slot, one widget per kind.

/// Reading of the frame with a filler: `___` in the reading is replaced by the filler's reading.
String? _pronunciation(CardFrame frame, CardFiller? filler) {
  final base = frame.framePronunciationNative;
  if (base == null) return null;
  final fp = filler?.pronunciationNative;
  return fp == null ? base : base.replaceFirst(kSlotMark, fp);
}

/// Translation of the frame with a filler — the native phrase (`native_line`), otherwise the native frame.
String _native(CardFrame frame, CardFiller? filler) {
  if (filler == null) return frame.frameNative;
  return filler.nativeLine ?? frame.frameNative.replaceFirst(kSlotMark, filler.native);
}

void _autoplay(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {double rate = 1.0}) {
  Timer(kAutoplayDelay, () {
    if (state.mounted) unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: key));
  });
}

/// PHRASE PLATE (32-1, 32-3…32-9) — `#EFEBE3` across the sheet's whole field, the phrase in Literata 30
/// left-aligned, «Listen» 44 in the bottom-right corner.
class _PhrasePlate extends StatelessWidget {
  const _PhrasePlate({required this.child, this.listen, this.height = 208, this.topLeft});

  final Widget child;
  final Widget? listen;
  final double height;
  final Widget? topLeft;

  /// The plate's height is at least [height]; a long phrase grows it — the text is not clipped.
  @override
  Widget build(BuildContext context) => ColoredBox(
    color: AppColors.ground,
    child: Stack(
      children: [
        Container(
          constraints: BoxConstraints(minHeight: height),
          padding: EdgeInsets.fromLTRB(20, topLeft == null ? 20 : 52, 20, listen == null ? 20 : 64),
          alignment: Alignment.centerLeft,
          child: child,
        ),
        if (topLeft != null) Positioned(left: 20, top: 20, child: topLeft!),
        if (listen != null) Positioned(right: 16, bottom: 16, child: listen!),
      ],
    ),
  );
}

/// The text part of the phrase sheet — eyebrow, reading, translation. A sheet may carry no eyebrow at all (32-1,
/// 32-7): the frame with its filler in the slot already says what the card is about.
class _PhraseFooter extends StatelessWidget {
  const _PhraseFooter({this.eyebrow, this.reading, this.native, this.nativeStyle});

  final String? eyebrow;
  final String? reading;
  final String? native;
  final TextStyle? nativeStyle;

  @override
  Widget build(BuildContext context) {
    final rows = <Widget>[
      if (eyebrow case final text?) SessionEyebrow(text),
      if (reading case final text?) Text(text, style: AppTextSession.meta),
      if (native case final text?) Text(text, style: nativeStyle ?? AppTextSession.body),
    ];
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (i, row) in rows.indexed) ...[if (i > 0) const SizedBox(height: 4), row],
        ],
      ),
    );
  }
}

/// The phrase sheet: the plate on top and the text part; no text part (32-4) — the plate alone.
class _PhraseSheet extends StatelessWidget {
  const _PhraseSheet({required this.plate, this.footer});

  final Widget plate;
  final Widget? footer;

  @override
  Widget build(BuildContext context) => SessionSheet(
    padding: EdgeInsets.zero,
    child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [plate, ?footer]),
  );
}

/// A row of filler chips 40: the chosen one is ink, the rest paper (32-1, 32-8).
class _FillerChips extends StatelessWidget {
  const _FillerChips({required this.fillers, required this.selected, required this.onTap});

  final List<CardFiller> fillers;
  final int? selected;
  final ValueChanged<CardFiller>? onTap;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final f in fillers)
        SessionTile(
          key: ValueKey('chip-${f.index}'),
          text: f.target,
          height: 40,
          selected: selected == f.index,
          onTap: onTap == null ? null : () => onTap!(f),
        ),
    ],
  );
}

// ── 32-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// FRAME INTRO (32-1) — a lesson card, not a task: «Look and listen»; the frame with a filler ALWAYS in its slot —
/// the one the dialogue says, until a chip is tapped. The chosen chip is ink and the same word stands in the brass
/// slot; the reading and the translation change with it. A tap voices the phrase with that filler. A frame with one
/// meaning (or none) is the third state of the canvas: the phrase whole, no slot and no chips. On open the phrase
/// plays by itself once (SESSION-1b′, item 11), then only by «Listen» and the chips. «Got it» → `passed`, no
/// reaction sound.
class PhraseIntroCard extends StatefulWidget {
  const PhraseIntroCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseIntroPayload payload;

  @override
  State<PhraseIntroCard> createState() => _PhraseIntroCardState();
}

class _PhraseIntroCardState extends State<PhraseIntroCard> {
  /// The filler the learner put in themselves; null — the one said in the dialogue.
  CardFiller? _filler;

  Timer? _autoplayTimer;

  /// The card offers a choice: a slot with more than one meaning. One meaning (or a frame without a slot) is the
  /// canvas' third state — the phrase whole, no slot and no chips.
  bool get _choosable => widget.payload.frame.hasSlot && widget.payload.frame.fillers.length > 1;

  /// What stands in the slot — always something while there is a choice: the learner's chip, the filler the dialogue
  /// says, otherwise the first one (the one the dialogue says may be missing from the card, SESSION-1e).
  CardFiller? get _shown {
    final frame = widget.payload.frame;
    if (!_choosable) return frame.filler(widget.payload.said.fillerIndex);
    return _filler ?? frame.filler(widget.payload.said.fillerIndex) ?? frame.fillers.first;
  }

  @override
  void initState() {
    super.initState();
    final said = widget.payload.said;
    // The phrase as the dialogue says it — the same sound «Listen» plays before a chip is chosen.
    _autoplayTimer = autoplayOnce(this, widget.env, said.audio, said.textTarget, 'intro-phrase', skip: () => _filler != null);
  }

  @override
  void dispose() {
    _autoplayTimer?.cancel();
    super.dispose();
  }

  void _pick(CardFiller f) {
    final frame = widget.payload.frame;
    setState(() => _filler = f);
    unawaited(widget.env.voice.play(f.audio, fallback: frame.filledWith(f.target), key: 'chip-${f.index}'));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final p = widget.payload;
    final frame = p.frame;
    final shown = _shown;
    final listenAudio = _filler?.audio ?? p.said.audio;
    final listenText = _filler == null ? p.said.textTarget : frame.filledWith(_filler!.target);
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskLookListen),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _PhraseSheet(
            plate: _PhrasePlate(
              height: 288,
              listen: CardListen(env: env, audio: listenAudio, fallback: listenText, playKey: 'intro-phrase'),
              child: _choosable && shown != null
                  ? SessionFrameText.frame(frame, style: AppTextSession.frame, slot: shown.target, look: SlotLook.filled)
                  : SessionFrameText.plain(frame.hasSlot ? p.said.textTarget : frame.frameTarget, style: AppTextSession.frame),
            ),
            footer: _PhraseFooter(reading: _pronunciation(frame, shown), native: _native(frame, shown)),
          ),
          if (_choosable) ...[
            const SizedBox(height: 24),
            _FillerChips(fillers: frame.fillers, selected: shown?.index, onTap: _pick),
          ],
        ],
      ),
      bottom: SessionDockButton(
        label: l.planSessionUnderstood,
        busy: env.advancing,
        onTap: () {
          env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
          unawaited(env.next());
        },
      ),
    );
  }
}

// ── 32-2 ──────────────────────────────────────────────────────────────────────────────────────────

/// TRANSLATION → ASSEMBLY (32-2): the frame's tiles and the filler chips in one tray; the slot in the row is an
/// empty chip until a filler is chosen; «Check»; pass — words = `expected.words`, the slot at `slot_at`, filler =
/// `expected.filler_index`. Tiles are lower-case — the client capitalizes the first word of the assembled row.
class PhraseAssembleCard extends StatefulWidget {
  const PhraseAssembleCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseAssemblePayload payload;

  @override
  State<PhraseAssembleCard> createState() => _PhraseAssembleCardState();
}

class _PhraseAssembleCardState extends State<PhraseAssembleCard> {
  final List<AssemblyPiece> _pieces = [];
  bool? _correct;
  int _shake = 0;

  PhraseAssemblePayload get p => widget.payload;

  SlotPiece? get _slot {
    for (final x in _pieces) {
      if (x is SlotPiece) return x;
    }
    return null;
  }

  void _tapTray(int i) {
    final tiles = p.tiles.length;
    setState(() {
      if (i < tiles) {
        _pieces.add(TilePiece(i, p.tiles[i]));
        return;
      }
      final filler = p.chips[i - tiles];
      final at = _pieces.indexWhere((x) => x is SlotPiece);
      if (at >= 0) {
        _pieces[at] = SlotPiece(filler);
      } else {
        _pieces.add(SlotPiece(filler));
      }
    });
    if (i >= tiles) {
      final f = p.chips[i - tiles];
      unawaited(widget.env.voice.play(f.audio, fallback: p.frame.filledWith(f.target), key: 'chip-${f.index}'));
    }
  }

  void _check() {
    final ok = SessionRules.phraseAssembled(p, _pieces);
    setState(() {
      _correct = ok;
      if (!ok) _shake++;
    });
    if (ok) {
      AppHaptics.success();
    } else {
      AppHaptics.warning();
    }
    SessionSounds.verdict(correct: ok);
    widget.env.submit(SessionAnswer(
      result: ok ? SessionResult.passed : SessionResult.failed,
      attempts: 1,
      response: SessionResponse(mode: 'tiles', fillerIndex: _slot?.filler.index),
    ));
    if (ok) {
      unawaited(Future<void>.delayed(AppMotion.sessionAutoAdvance, () {
        if (mounted) unawaited(widget.env.next());
      }));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final answered = _correct != null;
    final wrongAt = _correct == false ? SessionRules.phraseMismatch(p, _pieces) : -1;
    final expectedAt = wrongAt >= 0 ? SessionRules.phraseExpectedAt(p, wrongAt) : null;
    final full = _pieces.length >= p.expectedWords.length + 1;
    final tail = p.frame.parts.after.trim();
    final row = <RowPiece>[
      for (var k = 0; k < _pieces.length; k++)
        switch (_pieces[k]) {
          TilePiece(:final text) => RowPiece.word(k == 0 ? SessionRules.capitalized(text) : text, wrong: k == wrongAt),
          SlotPiece(:final filler) => RowPiece.slot(filler.target, wrong: k == wrongAt),
        },
      if (full && tail.isNotEmpty && !tail.contains(' ')) RowPiece.tail(tail),
    ];
    final usedTiles = {for (final x in _pieces) if (x is TilePiece) x.index};
    final slotFiller = _slot?.filler.index;
    int? hint;
    if (expectedAt != null) {
      if (expectedAt.word != null) {
        for (var i = 0; i < p.tiles.length; i++) {
          if (p.tiles[i] == expectedAt.word && !usedTiles.contains(i)) {
            hint = i;
            break;
          }
        }
      } else if (expectedAt.fillerIndex != null) {
        final c = p.chips.indexWhere((f) => f.index == expectedAt.fillerIndex);
        if (c >= 0) hint = p.tiles.length + c;
      }
    }
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      centerBody: true,
      task: SessionTask(l.planSessionTaskAssemblePhrase),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionQuestionSheet(
            eyebrow: l.planSessionBrowTranslation,
            text: Text(p.targetNative, style: AppTextSession.question),
            translation: null,
          ),
          const SizedBox(height: 24),
          SessionAssembly(
            trayGap: 20,
            row: row,
            tray: [
              for (var i = 0; i < p.tiles.length; i++)
                TrayPiece(p.tiles[i], used: usedTiles.contains(i), hint: hint == i),
              for (var c = 0; c < p.chips.length; c++)
                TrayPiece(p.chips[c].target, used: slotFiller == p.chips[c].index, hint: hint == p.tiles.length + c),
            ],
            emptySlot: _slot == null && p.frame.hasSlot && !answered,
            caret: !answered && !full,
            sage: _correct == true,
            shake: _shake,
            onTray: answered ? null : _tapTray,
            onRow: answered ? null : (k) {
              if (k < _pieces.length) setState(() => _pieces.removeAt(k));
            },
          ),
        ],
      ),
      bottom: _correct == false
          ? SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()))
          : SessionDockButton(
              label: l.planSessionCheck,
              enabled: !answered && _pieces.isNotEmpty && (_slot != null || !p.frame.hasSlot),
              onTap: _check,
            ),
    );
  }
}

// ── 32-3 ──────────────────────────────────────────────────────────────────────────────────────────

/// BACK TRANSLATION (32-3, template 30-9): the target phrase with sound and reading — four native options. The phrase
/// plays by itself once when the card opens, like every phrase trainer with a sound (SESSION-2a §2).
class PhraseChooseBackCard extends StatefulWidget {
  const PhraseChooseBackCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseChooseBackPayload payload;

  @override
  State<PhraseChooseBackCard> createState() => _PhraseChooseBackCardState();
}

class _PhraseChooseBackCardState extends State<PhraseChooseBackCard> with ChoiceCardState<PhraseChooseBackCard> {
  static const _key = 'choose-back';

  Timer? _autoplay;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  void initState() {
    super.initState();
    _autoplay = autoplayOnce(this, env, widget.payload.audio, widget.payload.textTarget, _key);
  }

  @override
  void dispose() {
    _autoplay?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      task: SessionTask(l.planSessionTaskChooseTranslation),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          listen: CardListen(env: env, audio: p.audio, fallback: p.textTarget, playKey: _key),
          child: SessionFrameText.plain(p.textTarget, style: AppTextSession.frame),
        ),
        footer: _PhraseFooter(eyebrow: l.planSessionBrowPhrase, reading: p.pronunciationNative),
      ),
      bottom: optionsDock(context),
    );
  }
}

// ── 32-4 ──────────────────────────────────────────────────────────────────────────────────────────

/// SLOT · INSERT THE FILLER (32-4): the native sentence stands OVER the card in Literata 26 — it is the task
/// itself, and the sheet below is the frame with an empty slot; four fillers in Literata 22, each with its own
/// «Listen». Correct — the slot in sage.
class PhraseSlotCard extends StatefulWidget {
  const PhraseSlotCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseSlotPayload payload;

  @override
  State<PhraseSlotCard> createState() => _PhraseSlotCardState();
}

class _PhraseSlotCardState extends State<PhraseSlotCard> with ChoiceCardState<PhraseSlotCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    return CardLayout(
      bodyGap: 40,
      centerBody: true,
      task: SessionTask(l.planSessionTaskInsert),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 68),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(p.promptNative, key: const ValueKey('slot-native'), style: AppTextSession.question),
            ),
          ),
          const SizedBox(height: 20),
          _PhraseSheet(
            plate: _PhrasePlate(
              child: SessionFrameText.frame(
                p.frame,
                style: AppTextSession.frame,
                slot: answered ? p.correctOption?.text : null,
                look: answered ? SlotLook.sage : SlotLook.empty,
              ),
            ),
          ),
        ],
      ),
      bottom: optionsDock(
        context,
        target: true,
        listen: (o) => CardListen(env: env, audio: o.audio, fallback: p.frame.filledWith(o.text), playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}

// ── 32-5 ──────────────────────────────────────────────────────────────────────────────────────────

/// SLOT BY EAR (32-5): a wave instead of the phrase, one filler plays (on opening and on a tap on the wave), the
/// frame with an empty slot as text; the options are silent.
class PhraseSlotListenCard extends StatefulWidget {
  const PhraseSlotListenCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseSlotListenPayload payload;

  @override
  State<PhraseSlotListenCard> createState() => _PhraseSlotListenCardState();
}

class _PhraseSlotListenCardState extends State<PhraseSlotListenCard> with ChoiceCardState<PhraseSlotListenCard> {
  static const _key = 'slot-listen';

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  String get _fallback {
    final p = widget.payload;
    final f = p.frame.filler(p.fillerIndex);
    return p.frame.filledWith(f?.target ?? p.correctOption?.text ?? '');
  }

  @override
  void initState() {
    super.initState();
    _autoplay(this, env, widget.payload.audio, _fallback, _key);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final said = p.frame.filler(p.fillerIndex);
    return CardLayout(
      bodyGap: 4,
      task: SessionTask(l.planSessionTaskChooseHeard),
      body: SessionQuestionSheet(
        media: ValueListenableBuilder<Object?>(
          valueListenable: env.voice.playing,
          builder: (_, playing, _) => SessionWavePlate(
            playing: playing == _key,
            heights: SessionWave.five,
            label: l.planWindowListen,
            onTap: () => unawaited(env.voice.play(p.audio, fallback: _fallback, key: _key)),
          ),
        ),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: l.planSessionBrowByEar,
        eyebrowTrailing: eyebrowTrailing(l),
        text: SessionFrameText.frame(
          p.frame,
          style: AppTextSession.question,
          slot: answered ? p.correctOption?.text : null,
          look: answered ? SlotLook.sage : SlotLook.empty,
        ),
        translation: answered ? _native(p.frame, said) : p.frame.frameNative,
      ),
      bottom: optionsDock(context, target: true),
    );
  }
}

// ── 32-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// REPEAT ALOUD (32-6): the sample at 0.85× on opening, the key underlined in brass; microphone; pass — coverage of
/// `expected_text` by `coverage_min`; two attempts without a pass — `skipped`. Rounds (item 12, [VoiceRounds]):
/// round 2 — the frame with the next filler, its own sample at 0.85×, «1 of 2 · 2 of 2» above the sheet.
class PhraseRepeatCard extends StatefulWidget {
  const PhraseRepeatCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseRepeatPayload payload;

  @override
  State<PhraseRepeatCard> createState() => _PhraseRepeatCardState();
}

class _PhraseRepeatCardState extends State<PhraseRepeatCard> with VoiceCardState<PhraseRepeatCard> {
  static const _key = 'phrase-repeat';

  late final List<VoiceRound> _rounds = VoiceRounds.ofRepeat(widget.payload);

  VoiceRound get _current => _rounds[round];

  @override
  CardEnv get env => widget.env;

  @override
  int get roundCount => _rounds.length;

  @override
  int? fillerIndexOfRound(int round) => _rounds[round].fillerIndex;

  @override
  String get expectedSpeech => _current.expectedText;

  @override
  bool accepts(String heard) => SessionRules.roundAccepted(widget.payload, _current, heard, env.articles);

  @override
  void initState() {
    super.initState();
    initVoice();
    _playSample();
  }

  @override
  void onRoundStarted(int round) => _playSample();

  void _playSample() => _autoplay(this, env, _current.audio, _current.expectedText, _key, rate: kRepeatRate);

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  /// The key in the line — case-insensitive; not found — no underline.
  TextRange? get _keyRange {
    final key = widget.payload.key;
    if (key == null) return null;
    final text = _current.expectedText;
    final at = text.toLowerCase().indexOf(key.toLowerCase());
    return at < 0 ? null : TextRange(start: at, end: at + key.length);
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final r = _current;
    final filler = r.filler;
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskSayPhrase),
      body: _Rounds(
        round: round,
        count: roundCount,
        child: _PhraseSheet(
          plate: _PhrasePlate(
            listen: CardListen(env: env, audio: r.audio, fallback: r.expectedText, playKey: _key, rate: kRepeatRate),
            child: SessionFrameText.plain(r.expectedText, style: AppTextSession.frame, underline: _keyRange),
          ),
          footer: _PhraseFooter(
            eyebrow: l.planSessionBrowPhrase,
            reading: _pronunciation(p.frame, filler),
            native: r.native ?? _native(p.frame, filler),
          ),
        ),
      ),
      bottom: voiceDock(context),
    );
  }
}

/// The sheet of a card with rounds (item 12) — «1 of 2 · 2 of 2» small and grey above it; one round — the sheet alone.
class _Rounds extends StatelessWidget {
  const _Rounds({required this.round, required this.count, required this.child});

  final int round;
  final int count;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (count < 2) return child;
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(AppLocalizations.of(context).planSessionRound(round + 1, count), key: const ValueKey('voice-round'), style: AppTextSession.meta),
        const SizedBox(height: 8),
        child,
      ],
    );
  }
}

// ── 32-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// SAY IT WHOLE (32-7, work order FIX-1 §6) — THE ONE TRAINER OF A FRAME WITH A WINDOW. The window shows a meaning,
/// the learner says the WHOLE phrase, a pass moves the next meaning into the window, and so on through every meaning
/// the card carries (2–3); the last round is «and now with your own word» — the former «my own slot» (32-9), which
/// has no screen of its own any more.
///
/// The chips are not a choice but a STATE: said · now · ahead. Nothing on this card is tapped except the microphone.
///
/// The two payloads it is dealt differ in ONE thing, and the contract decides it: who may pass the own word.
/// `phrase_other_slot` is a voice kind and the phone grades it (the frame's own words covered, and something said in
/// the window); `phrase_own_slot` is a judged kind whose pass only the server may write (`…/judge`), so that round
/// asks the judge and the client sends no answer of its own on a pass.
class PhraseSayWholeCard extends StatefulWidget {
  /// `phrase_other_slot` — the phone grades every round.
  PhraseSayWholeCard.other({super.key, required this.env, required PhraseOtherSlotPayload payload})
    : frame = payload.frame,
      coverageMin = payload.coverageMin,
      phraseKey = payload.key,
      judged = false,
      hints = const [],
      expectedText = payload.expectedText,
      slotExpected = payload.slotExpected,
      dealtFiller = payload.fillerIndex;

  /// `phrase_own_slot` — the own word goes to the slot judge.
  PhraseSayWholeCard.own({super.key, required this.env, required PhraseOwnSlotPayload payload})
    : frame = payload.frame,
      coverageMin = payload.coverageMin,
      phraseKey = payload.key,
      judged = true,
      hints = payload.examples,
      expectedText = null,
      slotExpected = null,
      dealtFiller = null;

  final CardEnv env;
  final CardFrame frame;
  final double coverageMin;

  /// The phrase's key words — underlined in the frame.
  final String? phraseKey;

  /// The own word is the judge's to pass (`phrase_own_slot`).
  final bool judged;

  /// Words the recognizer should expect beyond the frame (`examples`).
  final List<String> hints;

  /// The server's strings for the filler the card was dealt with; null — the payload carries none.
  final String? expectedText;
  final String? slotExpected;
  final int? dealtFiller;

  @override
  State<PhraseSayWholeCard> createState() => _PhraseSayWholeCardState();
}

class _PhraseSayWholeCardState extends State<PhraseSayWholeCard> with VoiceCardState<PhraseSayWholeCard> {
  /// The rounds: every meaning of the window, then the learner's own word (null). A frame without a window has the
  /// one round of saying it as it stands.
  late final List<CardFiller?> _rounds = _roundsOf(widget.frame);

  static List<CardFiller?> _roundsOf(CardFrame frame) {
    final rounds = <CardFiller?>[...frame.fillers, if (frame.hasSlot) null];
    return rounds.isEmpty ? const [null] : rounds;
  }

  /// How the last attempt of this round went — the frame and the window separately (the sage of a pass).
  ({bool frame, bool slot})? _parts;

  /// What went into the window on a pass — the heard words beyond the frame, or the judge's value.
  String? _heardSlot;

  /// Why the judge did not accept the own word; null — nothing was rejected.
  String? _reason;

  CardFiller? get _filler => _rounds[round];

  /// The last round — «and now with your own word».
  bool get _own => _filler == null;

  String get _framePart => SessionRules.framePart(widget.frame.frameTarget);

  /// The card was dealt with this meaning, so the server's own strings describe it best.
  bool get _dealt => _filler != null && _filler!.index == widget.dealtFiller;

  @override
  CardEnv get env => widget.env;

  @override
  int get roundCount => _rounds.length;

  @override
  int? fillerIndexOfRound(int round) => _rounds[round]?.index;

  @override
  String get expectedSpeech {
    final filler = _filler;
    if (filler == null) return _framePart;
    return _dealt ? widget.expectedText ?? widget.frame.filledWith(filler.target) : widget.frame.filledWith(filler.target);
  }

  /// All of these words must be heard — the meaning in the window; the own round has none of its own.
  String get _slotExpected => _dealt ? widget.slotExpected ?? _filler!.target : _filler?.target ?? '';

  @override
  List<String> get contextual => [
    expectedSpeech,
    _framePart,
    for (final f in widget.frame.fillers) f.target,
    ...widget.hints,
  ];

  @override
  bool accepts(String heard) {
    final frameSaid = SpeechCoverage.covers(heard, expectedSpeech, widget.coverageMin, env.articles);
    final slot = _slotWordsOf(heard, widget.frame, '');
    // The window: a known meaning is heard whole; the own word is whatever was said beyond the frame — that there
    // WAS something is the phone's whole claim about it (the meaning is the judge's, where there is one).
    final slotSaid = _own ? slot.isNotEmpty : SpeechCoverage.covers(heard, _slotExpected, SpeechCoverage.all, env.articles);
    setState(() {
      _parts = (frame: frameSaid, slot: slotSaid);
      _heardSlot = slot.isEmpty ? null : slot;
    });
    return frameSaid && slotSaid;
  }

  /// The own word of a judged card is the JUDGE's to pass: the microphone stays closed until the verdict, and a
  /// rejection stands under the microphone in the judge's own words.
  @override
  Future<bool> grade(String heard) async {
    if (!_own || !widget.judged) return accepts(heard);
    if (!accepts(heard)) return false;
    try {
      final outcome = await env.judge(heard);
      if (!mounted) return false;
      setState(() {
        _reason = outcome.accepted ? null : (outcome.reasonNative ?? '');
        if (outcome.accepted && outcome.slotValue != null) _heardSlot = outcome.slotValue;
      });
      return outcome.accepted;
    } catch (e) {
      if (mounted) setState(() => _reason = AppLocalizations.of(context).planSessionOffline);
      return false;
    }
  }

  /// A JUDGED PASS IS THE SERVER'S: the judge wrote the card's answer when it accepted the own word, and this client
  /// may not write `passed` on a judged kind at all (`CardKind::allows`). A skip is the client's, as always.
  @override
  void submitAnswer(SessionAnswer answer) {
    if (widget.judged && answer.result == SessionResult.passed) return;
    env.submit(answer);
  }

  @override
  void initState() {
    super.initState();
    initVoice();
  }

  @override
  void onRoundStarted(int round) {
    _parts = null;
    _heardSlot = null;
    _reason = null;
  }

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  /// The key in the frame — case-insensitive; not found — no underline.
  TextRange? get _keyRange {
    final key = widget.phraseKey;
    if (key == null) return null;
    final at = widget.frame.parts.before.toLowerCase().indexOf(key.toLowerCase());
    return at < 0 ? null : TextRange(start: at, end: at + key.length);
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final passed = _parts?.frame == true && _parts?.slot == true && (done || roundPassed);
    final listening = mic.isListening;
    final filler = _filler;
    // The own word fills the window as it is said, and stays there while the judge thinks; a known meaning stands in
    // the window from the start of its round.
    final live = _own && listening && !mic.closed ? _slotWordsOf(mic.partial, widget.frame, '') : null;
    final shown = passed
        ? (_heardSlot ?? _slotExpected)
        : _own
        ? (live != null ? (live.isEmpty ? null : live) : _heardSlot)
        : filler?.target;
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.30,
      // The dock grows and shrinks across the states of a recording, and the chips are the card's own progress:
      // nothing of the field may end up under it (the same reason 32-9 had before the two were merged).
      overlayDock: false,
      task: SessionTask(l.planSessionTaskSayEachMeaning, companion: _own ? l.planSessionOwnWordNow : null),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _PhraseSheet(
            plate: _PhrasePlate(
              child: SessionFrameText(
                before: widget.frame.parts.before,
                after: widget.frame.parts.after,
                style: AppTextSession.frame,
                window: widget.frame.hasSlot,
                frameColor: passed && _parts?.frame == true ? AppColors.verdictKnown : null,
                slot: shown,
                look: passed ? SlotLook.sage : (shown == null ? SlotLook.empty : SlotLook.filled),
                caret: _own && listening && !mic.closed,
                underline: _keyRange,
              ),
            ),
            // No reading line on this sheet (as on 32-7 before the merge): the card carries the row of meanings under
            // it, and the two have to stand above the microphone on an 844 pt phone.
            footer: _PhraseFooter(
              native: _own ? widget.frame.frameNative : _native(widget.frame, filler),
              nativeStyle: AppTextSession.body,
            ),
          ),
          if (widget.frame.hasSlot) ...[
            const SizedBox(height: 20),
            _RoundChips(fillers: widget.frame.fillers, round: round, rounds: _rounds),
          ],
        ],
      ),
      bottom: voiceDock(context, showIdleCaption: false, missedCaption: _reason),
    );
  }
}

/// THE CHIPS OF «SAY IT WHOLE» — a STATE, not a choice (FIX-1 §6): said (a sage check), now (ink), ahead (an
/// outline). The own-word round has no chip of its own — the row is then all checks, and the task line says what
/// is left to do.
class _RoundChips extends StatelessWidget {
  const _RoundChips({required this.fillers, required this.round, required this.rounds});

  final List<CardFiller> fillers;
  final int round;
  final List<CardFiller?> rounds;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final f in fillers)
        if (rounds.indexWhere((r) => r?.index == f.index) case final at when at >= 0)
          SessionTile(
            key: ValueKey('chip-${f.index}'),
            text: f.target,
            height: 40,
            outlined: true,
            selected: at == round,
            trailing: at < round
                ? const Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown)
                : null,
          ),
    ],
  );
}

/// The heard words beyond the frame [frame] in speech order — what goes into the slot; empty — [fallback].
String _slotWordsOf(String heard, CardFrame frame, String fallback) {
  final frameWords = SpeechCoverage.words('${frame.parts.before} ${frame.parts.after}').toSet();
  final slot = heard
      .split(RegExp(r'\s+'))
      .where((w) {
        final tokens = SpeechCoverage.words(w);
        return tokens.isNotEmpty && !tokens.every(frameWords.contains);
      })
      .join(' ')
      .replaceAll(RegExp(r'[.!?,;:]+$'), '');
  return slot.isEmpty ? fallback : slot;
}

// ── 32-8 ──────────────────────────────────────────────────────────────────────────────────────────

/// COMBINATION (32-8, polish pass SESSION-1b′, item 1): «What will you answer?»; the partner's line plays, its
/// text is shown. Step 1 — three options as WHOLE phrases (`frames[].said`, no `___`), each with «listen» 28:
/// wrong — ink outline, sage on the correct one, `failed`, «Next»; correct — sage, and after 600 ms the slot opens
/// in the sheet. Step 2 — the slot is empty in brass, the filler chips under the sheet; a tap on a chip
/// puts in and voices the filler, `passed` (`mode = chips`, `filler_index`), «Next» is active.
class PhraseCombineCard extends StatefulWidget {
  const PhraseCombineCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseCombinePayload payload;

  @override
  State<PhraseCombineCard> createState() => _PhraseCombineCardState();
}

class _PhraseCombineCardState extends State<PhraseCombineCard> {
  static const _partnerKey = 'combine-partner';

  String? _frameRef;

  /// The correct phrase is chosen and the slot is open (step 2).
  bool _opened = false;
  CardFiller? _filler;
  int _shake = 0;
  Timer? _openTimer;

  PhraseCombinePayload get p => widget.payload;

  @override
  void initState() {
    super.initState();
    final line = p.partnerLine;
    if (line != null) _autoplay(this, widget.env, line.audio, line.textTarget, _partnerKey);
  }

  @override
  void dispose() {
    _openTimer?.cancel();
    super.dispose();
  }

  bool get _frameWrong => _frameRef != null && !SessionRules.combineCorrect(p, _frameRef!);

  CardFrameText? get _correctFrame {
    for (final f in p.frames) {
      if (f.ref == p.correctFrame) return f;
    }
    return null;
  }

  /// An option as a whole phrase: `frames[].said`, otherwise the day's frame with the filler said in the dialogue,
  /// otherwise for the correct frame — the filler from the dialogue / the first of the chips. Null — the frame has
  /// no whole phrase (neither the contract nor the day allows that): the option is not drawn rather than shown with
  /// `___` or an ellipsis.
  String? _sentenceOf(CardFrameText f) {
    final said = f.said?.textTarget.trim();
    if (said != null && said.isNotEmpty) return said;
    final fromDay = widget.env.frameSentence?.call(f.ref)?.trim();
    if (fromDay != null && fromDay.isNotEmpty) return fromDay;
    if (f.ref == p.correctFrame && p.chips.isNotEmpty) {
      final chip = p.chips.where((c) => c.inDialogue).firstOrNull ?? p.chips.first;
      return f.frameTarget.replaceFirst(kSlotMark, chip.target);
    }
    return null;
  }

  void _pickFrame(CardFrameText frame) {
    if (_frameRef != null) return;
    final ok = SessionRules.combineCorrect(p, frame.ref);
    setState(() {
      _frameRef = frame.ref;
      if (!ok) _shake++;
    });
    if (!ok) {
      AppHaptics.warning();
      SessionSounds.verdict(correct: false);
      widget.env.submit(const SessionAnswer(result: SessionResult.failed, attempts: 1, response: SessionResponse(mode: 'chips')));
      return;
    }
    SessionSounds.verdict(correct: true);
    _openTimer = Timer(AppMotion.sessionAutoAdvance, () {
      if (mounted) setState(() => _opened = true);
    });
  }

  void _pickFiller(CardFiller f) {
    if (_filler != null) return;
    final frame = _correctFrame;
    setState(() => _filler = f);
    // No reaction sound here: the chip voices the assembled phrase at once; the reaction sounded on the phrase choice.
    AppHaptics.success();
    widget.env.submit(SessionAnswer(
      result: SessionResult.passed,
      attempts: 1,
      response: SessionResponse(mode: 'chips', fillerIndex: f.index),
    ));
    unawaited(widget.env.voice.play(
      f.audio,
      fallback: frame == null ? f.target : frame.frameTarget.replaceFirst(kSlotMark, f.target),
      key: 'combine-filler',
    ));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final line = p.partnerLine;
    final frame = _correctFrame;
    final framePicked = _opened && frame != null;

    if (!framePicked) {
      // Step 1 — the line and three phrases.
      return CardLayout(
        bodyGap: 12,
        centerBody: true,
        task: SessionTask(l.planSessionTaskWhatAnswer),
        body: line == null
            ? const SizedBox.shrink()
            : _PhraseSheet(
                plate: ValueListenableBuilder<Object?>(
                  valueListenable: env.voice.playing,
                  builder: (_, playing, _) => _PhrasePlate(
                    topLeft: SessionWave(heights: SessionWave.five, width: 80, playing: playing == _partnerKey),
                    listen: CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _partnerKey),
                    child: Text(line.textTarget, style: AppTextSession.frame),
                  ),
                ),
                footer: _PhraseFooter(
                  eyebrow: env.role.isEmpty ? l.planSessionBrowFrame : l.planSessionBrowPartnerAsks(env.role),
                  native: line.textNative,
                ),
              ),
        bottom: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final (i, (f, sentence)) in [
              for (final f in p.frames)
                if (_sentenceOf(f) case final sentence?) (f, sentence),
            ].indexed) ...[
              if (i > 0) const SizedBox(height: 8),
              SessionOption(
                key: ValueKey('frame-${f.ref}'),
                text: sentence,
                target: true,
                // «Listen» 28 as in the canvas: the whole phrase (`said.audio`); a day dealt before SESSION-1e has no
                // file — the phone reads the phrase.
                listen: CardListen(env: env, audio: f.said?.audio, fallback: sentence, playKey: 'frame-${f.ref}', size: 28),
                look: _frameRef == null
                    ? OptionLook.idle
                    : f.ref == p.correctFrame
                    ? (env.returnsTomorrow ? OptionLook.returns : OptionLook.correct)
                    : f.ref == _frameRef
                    ? OptionLook.wrong
                    : OptionLook.settled,
                shake: f.ref == _frameRef ? _shake : 0,
                onTap: _frameRef == null ? () => _pickFrame(f) : null,
              ),
            ],
            if (_frameWrong) ...[
              const SizedBox(height: 8),
              SessionDockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next())),
            ],
          ],
        ),
      );
    }

    // Step 2 — the slot: the filler chips of the correct frame.
    final parts = splitAtSlot(frame.frameTarget);
    final filled = _filler;
    return CardLayout(
      bodyGap: 0,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskWhatAnswer),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (line != null)
            // The partner's line wraps in full — nothing on a session card is cut (the owner's rule of 16.09); the
            // canvas height 48 is only the minimum.
            ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 48),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      '${line.textTarget} · ${line.textNative}',
                      key: const ValueKey('combine-partner-line'),
                      style: AppTextSession.sceneLine,
                    ),
                  ),
                  const SizedBox(width: 12),
                  CardListen(env: env, audio: line.audio, fallback: line.textTarget, playKey: _partnerKey, size: 28),
                ],
              ),
            ),
          const SizedBox(height: 48),
          _PhraseSheet(
            plate: _PhrasePlate(
              listen: filled == null
                  ? null
                  : CardListen(
                      env: env,
                      audio: filled.audio,
                      fallback: frame.frameTarget.replaceFirst(kSlotMark, filled.target),
                      playKey: 'combine-filler',
                    ),
              child: SessionFrameText(
                before: parts.before,
                after: parts.after,
                style: AppTextSession.frame,
                frameColor: filled == null ? null : AppColors.verdictKnown,
                slot: filled?.target,
                look: filled == null ? SlotLook.empty : SlotLook.sage,
              ),
            ),
            footer: _PhraseFooter(
              eyebrow: filled == null ? l.planSessionBrowFrame : l.planSessionBrowAssembled,
              native: filled == null ? frame.frameNative : (filled.nativeLine ?? frame.frameNative.replaceFirst(kSlotMark, filled.native)),
            ),
          ),
        ],
      ),
      bottom: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (filled == null)
            _FillerChips(fillers: p.chips, selected: null, onTap: _pickFiller)
          else
            ValueListenableBuilder<Object?>(
              valueListenable: env.voice.playing,
              builder: (_, playing, _) => Column(
                children: [
                  SessionWave(heights: SessionWave.five, width: 80, playing: playing == 'combine-filler'),
                  const SizedBox(height: 14),
                  // «playing» — only while the assembled phrase plays (32-8 «assembled · playing»); the space is kept.
                  Opacity(
                    opacity: playing == 'combine-filler' ? 1 : 0,
                    child: Text(l.planSessionPlaying, style: AppTextSession.meta),
                  ),
                ],
              ),
            ),
          const SizedBox(height: 14),
          SessionDockButton(
            label: l.planSessionNext,
            enabled: filled != null,
            busy: env.advancing,
            onTap: filled == null ? null : () => unawaited(env.next()),
          ),
        ],
      ),
    );
  }
}
