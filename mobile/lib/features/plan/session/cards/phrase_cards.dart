import 'dart:async';

import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../../data/plan/day_window.dart' show WindowPair;
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

/// A row of filler chips 40: the chosen one is ink, the rest paper (32-8).
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

/// THE MEANINGS AS NEUTRAL PLATES (кадр 32-1, наряд CLIENT-CONV-1a) — «это карточка-урок, здесь
/// ничего не выбирают». The plates say what the changing part can hold; the one standing in the
/// window is outlined, and none of them is a button.
class _MeaningPlates extends StatelessWidget {
  const _MeaningPlates({required this.fillers, required this.shown});

  final List<CardFiller> fillers;
  final int? shown;

  @override
  Widget build(BuildContext context) => Wrap(
    key: const ValueKey('meaning-plates'),
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final f in fillers)
        SessionTile(key: ValueKey('meaning-${f.index}'), text: f.target, height: 40, outlined: true, selected: shown == f.index),
    ],
  );
}

// ── 32-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// FRAME INTRO (кадр 32-1) — A LESSON CARD, NOT A TASK (наряд CLIENT-CONV-1a): «Посмотри и
/// послушай», the frame with a filler always in its slot, and under it the meanings as NEUTRAL
/// PLATES with the caption «эту часть можно менять». Nothing here is chosen: the plates show what
/// the changing part can hold, and the card ends in «Дальше».
///
/// THE THIRD STATE — one meaning, no window: the phrase stands whole, the line of sense says so in
/// as many words, and under it «В разговоре» holds the exchange the phrase is said in. That exchange
/// comes from the day's own dialogue: `phrase_intro` carries no `usage` of its own, so a phrase the
/// day's dialogue does not hold gets no block at all.
///
/// On open the phrase plays by itself once (SESSION-1b′, item 11), then only by «Прослушать».
class PhraseIntroCard extends StatefulWidget {
  const PhraseIntroCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseIntroPayload payload;

  @override
  State<PhraseIntroCard> createState() => _PhraseIntroCardState();
}

class _PhraseIntroCardState extends State<PhraseIntroCard> {
  Timer? _autoplayTimer;

  /// The frame has a slot with more than one meaning — the plates stand under it. One meaning (or a
  /// frame without a slot) is the canvas' third state: the phrase whole, no plates.
  bool get _changeable => widget.payload.frame.hasSlot && widget.payload.frame.fillers.length > 1;

  /// What stands in the slot — the filler the dialogue says, otherwise the first one (the one the
  /// dialogue says may be missing from the card, SESSION-1e).
  CardFiller? get _shown {
    final frame = widget.payload.frame;
    if (!_changeable) return frame.filler(widget.payload.said.fillerIndex);
    return frame.filler(widget.payload.said.fillerIndex) ?? frame.fillers.first;
  }

  @override
  void initState() {
    super.initState();
    final said = widget.payload.said;
    _autoplayTimer = autoplayOnce(this, widget.env, said.audio, said.textTarget, 'intro-phrase');
  }

  @override
  void dispose() {
    _autoplayTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final env = widget.env;
    final p = widget.payload;
    final frame = p.frame;
    final shown = _shown;
    final pair = _changeable ? null : env.exchangeOf?.call(p.said.textTarget);
    return CardLayout(
      bodyGap: 12,
      fadeStop: 0.34,
      // With «В разговоре» under the sheet the field is taller than the screen, and its last bubble
      // must not sit under the button: the dock stands below the field, not over it.
      overlayDock: pair == null,
      task: SessionTask(
        l.planSessionTaskLookListen,
        // THE LINE OF SENSE. Only the one-meaning state has words the client may write: «эту фразу
        // говорят целиком» is true of every such phrase. What makes a CHANGING frame what it is
        // («так говорят, где болит») is about this phrase alone, and the contract sends no such line
        // — so none is drawn (отчёт §5).
        companion: _changeable ? null : l.planSessionFrameWhole,
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _PhraseSheet(
            plate: _PhrasePlate(
              height: 288,
              listen: CardListen(env: env, audio: p.said.audio, fallback: p.said.textTarget, playKey: 'intro-phrase'),
              child: _changeable && shown != null
                  ? SessionFrameText.frame(frame, style: AppTextSession.frame, slot: shown.target, look: SlotLook.filled)
                  : SessionFrameText.plain(frame.hasSlot ? p.said.textTarget : frame.frameTarget, style: AppTextSession.frame),
            ),
            footer: _PhraseFooter(reading: _pronunciation(frame, shown), native: _native(frame, shown)),
          ),
          if (_changeable) ...[
            const SizedBox(height: 20),
            Text(l.planSessionChangeable, key: const ValueKey('changeable-caption'), style: AppTextSession.meta),
            const SizedBox(height: 10),
            _MeaningPlates(fillers: frame.fillers, shown: shown?.index),
          ] else if (pair != null) ...[
            const SizedBox(height: 24),
            SessionEyebrow(l.planSessionInTalk),
            const SizedBox(height: 14),
            _InTalk(pair: pair, env: env),
          ],
        ],
      ),
      bottom: SessionDockButton(
        label: l.planSessionNext,
        busy: env.advancing,
        onTap: () {
          env.submit(const SessionAnswer(result: SessionResult.passed, attempts: 1));
          unawaited(env.next());
        },
      ),
    );
  }
}

/// «В РАЗГОВОРЕ» (кадр 32-1, третье состояние) — the exchange the phrase is said in, as the two
/// bubbles the whole product draws a conversation with.
class _InTalk extends StatelessWidget {
  const _InTalk({required this.pair, required this.env});

  final WindowPair pair;
  final CardEnv env;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final partner = pair.partner;
    final learner = pair.learner;
    final rows = <Widget>[
      if (partner != null)
        SessionPartnerRow(
          key: const ValueKey('in-talk-partner'),
          bubble: SessionBubble(own: false, text: partner.text, translation: partner.translation),
          listen: SessionListenButton(
            size: 28,
            brass: true,
            label: l.planWindowListen,
            onTap: () => unawaited(env.voice.play(
              partner.audioUrl == null ? null : CardAudio(ref: 'in-talk-partner', url: partner.audioUrl, voice: 'partner'),
              fallback: partner.text,
              key: 'in-talk-partner',
            )),
          ),
        ),
      if (learner != null)
        SessionOwnRow(key: const ValueKey('in-talk-learner'), bubble: SessionBubble(own: true, text: learner.text, translation: learner.translation)),
    ];
    final ordered = pair.learnerFirst ? rows.reversed.toList() : rows;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [for (final (i, row) in ordered.indexed) ...[if (i > 0) const SizedBox(height: 8), row]],
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
      // «ПРОСЛУШАТЬ» СНЯТО (кадр 32-4, наряд CLIENT-CONV-1a): заданием здесь стоит перевод над
      // карточкой, и кружок у каждого варианта предлагал прослушать ответ до того, как его выбрали.
      bottom: optionsDock(context, target: true),
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

/// REPEAT ALOUD (32-6): the sample at 0.85× on opening, the key underlined in brass; microphone; pass — the line on
/// the screen said as it stands (`speech_mode: repeat`); two attempts without a pass — `skipped`.
///
/// ONE ROUND since work order FIX-2 §5: the card is dealt only to a frame WITHOUT a window, which has no second
/// value to say it with, and as the stand-in when «Скажи целиком» could not be built. The second round the phone
/// used to invent out of the frame's fillers went with the rounds becoming the server's.
class PhraseRepeatCard extends StatefulWidget {
  const PhraseRepeatCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseRepeatPayload payload;

  @override
  State<PhraseRepeatCard> createState() => _PhraseRepeatCardState();
}

class _PhraseRepeatCardState extends State<PhraseRepeatCard> with VoiceCardState<PhraseRepeatCard> {
  static const _key = 'phrase-repeat';

  @override
  CardEnv get env => widget.env;

  @override
  int? fillerIndexOfRound(int round) => widget.payload.fillerIndex;

  @override
  String get expectedSpeech => widget.payload.expectedText;

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(widget.payload, heard, env.speech);

  @override
  void initState() {
    super.initState();
    initVoice();
    _playSample();
  }

  void _playSample() =>
      _autoplay(this, env, widget.payload.audio, widget.payload.expectedText, _key, rate: kRepeatRate);

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  /// The key in the line — case-insensitive; not found — no underline.
  TextRange? get _keyRange {
    final key = widget.payload.key;
    if (key == null) return null;
    final text = widget.payload.expectedText;
    final at = text.toLowerCase().indexOf(key.toLowerCase());
    return at < 0 ? null : TextRange(start: at, end: at + key.length);
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final filler = p.fillerIndex == null ? null : p.frame.filler(p.fillerIndex!);
    return CardLayout(
      bodyGap: 12,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(l.planSessionTaskSayPhrase),
      body: _PhraseSheet(
        plate: _PhrasePlate(
          listen: CardListen(env: env, audio: p.audio, fallback: p.expectedText, playKey: _key, rate: kRepeatRate),
          child: SessionFrameText.plain(p.expectedText, style: AppTextSession.frame, underline: _keyRange),
        ),
        footer: _PhraseFooter(
          eyebrow: l.planSessionBrowPhrase,
          reading: _pronunciation(p.frame, filler),
          native: _native(p.frame, filler),
        ),
      ),
      bottom: voiceDock(context),
    );
  }
}

// ── 32-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// SAY IT WHOLE (32-7, work orders FIX-1 §6 and FIX-2 §5) — THE ONE TRAINER OF A FRAME WITH A WINDOW, at either
/// level. A meaning stands in the window, the learner says the WHOLE phrase, a pass moves the next meaning in, and
/// the last round is «and now with your own word» — the former «my own slot» (32-9), which has no screen of its own.
///
/// THE ROUNDS ARE THE SERVER'S (`payload.rounds`, FIX-2 §5). The phone used to work them out of the frame's fillers,
/// which meant the device decided how much of the day a learner got and the two levels differed in the TRAINER they
/// were given; now they differ in this list's length and in nothing else, and what is said on a card is the day's.
///
/// The chips are not a choice but a STATE: said · now · ahead. Nothing on this card is tapped except the microphone.
///
/// THE OWN-WORD ROUND IS THE JUDGE'S AND IS PRACTICE. Its verdict comes from `…/judge` and does not close the card
/// (the server keeps `result` null for this kind); a miss or a skip there deals no copy and returns no unit, so an
/// answer that would go out as `skipped` after every value round has passed goes out as `passed` instead.
class PhraseSayWholeCard extends StatefulWidget {
  const PhraseSayWholeCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final PhraseOtherSlotPayload payload;

  @override
  State<PhraseSayWholeCard> createState() => _PhraseSayWholeCardState();
}

class _PhraseSayWholeCardState extends State<PhraseSayWholeCard> with VoiceCardState<PhraseSayWholeCard> {
  /// How the last attempt of this round went — the frame and the window separately (the sage of a pass).
  ({bool frame, bool slot})? _parts;

  /// What went into the window on a pass — the heard words beyond the frame, or the judge's value.
  String? _heardSlot;

  /// Why the judge did not accept the own word; null — nothing was rejected.
  String? _reason;

  CardFrame get _frame => widget.payload.frame;

  List<CardSayWholeRound> get _values => widget.payload.rounds;

  /// The own-word round, or null when the stage's ceiling cut it (DECISIONS п. 354) — the card is then its values.
  CardOwnRound? get _ownRound => widget.payload.ownRound;

  /// The value round being said; null on the last round — «and now with your own word».
  CardSayWholeRound? get _value => round < _values.length ? _values[round] : null;

  bool get _own => _value == null;

  /// Every value round is through: what is left is the own word, and it costs the learner nothing.
  bool get _valuesDone => round >= _values.length;

  String get _framePart => SessionRules.framePart(_frame.frameTarget);

  /// The window's chip of this round — the filler the server named.
  CardFiller? get _filler {
    final at = _value?.fillerIndex;
    return at == null ? null : _frame.filler(at);
  }

  @override
  CardEnv get env => widget.env;

  @override
  int get roundCount => _values.length + (_ownRound == null ? 0 : 1);

  @override
  int? fillerIndexOfRound(int round) => round < _values.length ? _values[round].fillerIndex : null;

  @override
  String get expectedSpeech => _value?.expectedText ?? _framePart;

  @override
  List<String> get contextual => [
    expectedSpeech,
    _framePart,
    for (final f in _frame.fillers) f.target,
    ...?_ownRound?.examples,
  ];

  /// A VALUE ROUND is the phrase on the screen said as it stands (`speech_mode: repeat`): the value is part of that
  /// text, so there is nothing to count apart. The OWN round asks what the SERVER asks before it calls the model —
  /// the frame's own words, in `free` — and nothing else: whether the window holds anything, and what, is the
  /// judge's to say. The phone used to refuse the call when it heard nothing beyond the frame, which made it
  /// stricter than the server and cost the learner the judge's own reason; a client check may never be stricter
  /// (work order FIX-2 §2).
  ///
  /// What it does still read off the attempt is the WINDOW'S TEXT, for the screen: the words heard beyond the frame
  /// stand in the window while the judge thinks, and the verdict replaces them with its own `slot_value`.
  @override
  bool accepts(String heard) {
    final value = _value;
    final said = value != null
        ? SessionRules.roundAccepted(widget.payload, value, heard, env.speech)
        : SpeechMatch.said(heard, _framePart, _ownRound?.speechMode ?? SpeechMode.free, env.speech);
    final slot = _slotWordsOf(heard, _frame, '');
    setState(() {
      _parts = (frame: said, slot: said);
      _heardSlot = slot.isEmpty ? null : slot;
    });
    return said;
  }

  /// The own word is the JUDGE's to rule on: the microphone stays closed until the verdict, and a rejection stands
  /// under the microphone in the judge's own words.
  @override
  Future<bool> grade(String heard) async {
    if (!_own) return accepts(heard);
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

  /// THE CARD'S RESULT IS ITS VALUE ROUNDS' (FIX-2 §5). The own word is practice: once the values are through, a
  /// `skipped` — two misses on the own round, or «Skip» on it — goes out as `passed`, so the frame is not sent back
  /// tomorrow over a word the learner was invited to invent. Before the values are through a skip is a skip, and the
  /// frame lapses as it always did (DECISIONS п. 327).
  @override
  void submitAnswer(SessionAnswer answer) {
    if (answer.result == SessionResult.skipped && _valuesDone) {
      env.submit(SessionAnswer(result: SessionResult.passed, attempts: answer.attempts, response: answer.response));
      return;
    }
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
    final key = widget.payload.key;
    if (key == null) return null;
    final at = _frame.parts.before.toLowerCase().indexOf(key.toLowerCase());
    return at < 0 ? null : TextRange(start: at, end: at + key.length);
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final passed = _parts?.frame == true && _parts?.slot == true && (done || roundPassed);
    final listening = mic.isListening;
    final value = _value;
    // The own word fills the window as it is said, and stays there while the judge thinks; a known meaning stands in
    // the window from the start of its round.
    final live = _own && listening && !mic.closed ? _slotWordsOf(mic.partial, _frame, '') : null;
    final shown = passed
        ? (_heardSlot ?? _filler?.target)
        : _own
        ? (live != null ? (live.isEmpty ? null : live) : _heardSlot)
        : _filler?.target;
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
                before: _frame.parts.before,
                after: _frame.parts.after,
                style: AppTextSession.frame,
                window: _frame.hasSlot,
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
              native: value?.taskNative ?? _ownRound?.taskNative ?? '',
              nativeStyle: AppTextSession.body,
            ),
          ),
          if (_frame.hasSlot) ...[
            const SizedBox(height: 20),
            _RoundChips(fillers: _frame.fillers, round: round, rounds: _values, ownRound: _ownRound != null),
          ],
        ],
      ),
      bottom: voiceDock(context, showIdleCaption: false, missedCaption: _reason),
    );
  }
}

/// THE PLATES OF «SAY IT WHOLE» — a STATE, not a choice (FIX-1 §6, кадр 32-7): said (a sage check),
/// now (ink), ahead (an outline). THE OWN WORD IS THE LAST PLATE OF THE ROW (наряд CLIENT-CONV-1a):
/// it is a round like the others, and a row that ended in checks left the last round with nothing
/// showing where it stood.
class _RoundChips extends StatelessWidget {
  const _RoundChips({required this.fillers, required this.round, required this.rounds, required this.ownRound});

  final List<CardFiller> fillers;
  final int round;
  final List<CardSayWholeRound> rounds;

  /// The card ends in the learner's own word; false — the stage's ceiling cut that round.
  final bool ownRound;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final f in fillers)
          if (rounds.indexWhere((r) => r.fillerIndex == f.index) case final at when at >= 0)
            SessionTile(
              key: ValueKey('chip-${f.index}'),
              text: f.target,
              height: 40,
              outlined: true,
              selected: at == round,
              trailing: at < round ? const Icon(LucideIcons.check, size: 16, color: AppColors.verdictKnown) : null,
            ),
        if (ownRound)
          SessionTile(
            key: const ValueKey('chip-own'),
            text: l.planSessionOwnWordChip,
            height: 40,
            outlined: true,
            selected: round >= rounds.length,
          ),
      ],
    );
  }
}

/// The heard words beyond the frame [frame] in speech order — what goes into the slot; empty — [fallback].
String _slotWordsOf(String heard, CardFrame frame, String fallback) {
  final frameWords = SpeechMatch.words('${frame.parts.before} ${frame.parts.after}').toSet();
  final slot = heard
      .split(RegExp(r'\s+'))
      .where((w) {
        final tokens = SpeechMatch.words(w);
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
