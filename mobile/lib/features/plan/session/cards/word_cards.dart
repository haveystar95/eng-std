import 'dart:async';

import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../../../data/plan/session/session_models.dart';
import '../../../../data/plan/session/session_outcomes.dart';
import '../../../../data/plan/session/session_rules.dart';
import '../parts/session_bits.dart';
import '../parts/session_choice.dart';
import '../parts/session_tiles.dart';
import '../session_texts.dart';
import 'card_kit.dart';

/// WORDS — canvas series 31: one widget per kind.

/// The sample sound of «Repeat» — 0.85× (the server voice is always at normal tempo, DECISIONS item 318).
const double kRepeatRate = 0.85;

/// Pause before autoplay — after a card change, so that the audio channel does not cause jank in the transition.
const Duration kAutoplayDelay = Duration(milliseconds: 280);

void _autoplay(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {double rate = 1.0}) {
  Timer(kAutoplayDelay, () {
    if (state.mounted) unawaited(env.voice.play(audio, fallback: fallback, rate: rate, key: key));
  });
}

/// A lesson card's sound ONCE on open (polish pass SESSION-1b′, item 11): the server file, without a file — the
/// phone. The returned timer is cancelled when the card goes away; nothing plays if [skip] says the learner has
/// already chosen what to hear, or if a sound is already playing.
Timer autoplayOnce(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {bool Function()? skip}) =>
    Timer(kAutoplayDelay, () {
      if (!state.mounted || (skip?.call() ?? false) || env.voice.playing.value != null) return;
      unawaited(env.voice.play(audio, fallback: fallback, key: key));
    });

/// THE LINE SOUNDS BEFORE THE CARD ASKS (кадры 33-1, 33-5 серии 38; наряд FIX-3 §1) — the same single autoplay, with
/// [then] run once the sound is over: the question and its options come up after the line, never over it. A line that
/// cannot sound (no file and no voice) ends at once, and the card asks straight away rather than waiting on silence.
Timer autoplayThen(State state, CardEnv env, CardAudio? audio, String fallback, Object key, {required VoidCallback then}) =>
    Timer(kAutoplayDelay, () async {
      if (!state.mounted) return;
      await env.voice.play(audio, fallback: fallback, key: key);
      if (state.mounted) then();
    });

// ── 31-1 ──────────────────────────────────────────────────────────────────────────────────────────

/// WORD INTRO (31-1): photo, word, reading, translation, definition; «In the conversation» — the day's line with
/// the word underlined in brass, and «Listen». The word plays by itself once when it appears ([autoplayOnce]), then
/// only by «Listen». «Got it» → `passed`.
class WordIntroCard extends StatefulWidget {
  const WordIntroCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordIntroPayload payload;

  @override
  State<WordIntroCard> createState() => _WordIntroCardState();
}

class _WordIntroCardState extends State<WordIntroCard> {
  static const _termKey = 'intro-term';
  static const _lineKey = 'intro-line';

  Timer? _autoplayTimer;

  @override
  void initState() {
    super.initState();
    final p = widget.payload;
    _autoplayTimer = autoplayOnce(this, widget.env, p.termAudio, p.term.textTarget, _termKey);
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
    final term = p.term;
    final usedIn = p.usedIn;
    return CardLayout(
      taskGap: 20,
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskRememberWord),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (term.image != null) ...[SessionPhoto(image: term.image, height: 150), const SizedBox(height: 20)],
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(term.textTarget, style: AppTextSession.term),
                          if (term.pronunciationNative != null) ...[
                            const SizedBox(height: 4),
                            Text(term.pronunciationNative!, style: AppTextSession.meta),
                          ],
                          const SizedBox(height: 4),
                          Text(context.nativeText(term.textNative), style: AppTextSession.body),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    CardListen(env: env, audio: p.termAudio, fallback: term.textTarget, playKey: _termKey),
                  ],
                ),
                if (term.definitionTarget != null) ...[
                  const SizedBox(height: 14),
                  Text(term.definitionTarget!, style: AppTextSession.text15),
                ],
              ],
            ),
          ),
          if (usedIn != null) ...[
            const SizedBox(height: 16),
            SessionEyebrow(l.planWindowSheetTalk),
            const SizedBox(height: 14),
            SessionSheet(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _UnderlinedLine(text: usedIn.textTarget, span: usedIn.termSpan),
                        const SizedBox(height: 4),
                        Text(context.nativeText(usedIn.textNative), style: AppTextSession.body),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  CardListen(env: env, audio: p.lineAudio, fallback: usedIn.textTarget, playKey: _lineKey),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: DockButton(
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

/// The «In the conversation» line — Literata 22, the word underlined in brass 2 px by `term_span`.
class _UnderlinedLine extends StatelessWidget {
  const _UnderlinedLine({required this.text, required this.span});

  final String text;
  final CardSpan? span;

  @override
  Widget build(BuildContext context) {
    final s = span;
    const style = AppTextSession.target22;
    if (s == null || s.start < 0 || s.end > text.length || s.start >= s.end) return Text(text, style: style);
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(text: text.substring(0, s.start), style: style),
          TextSpan(
            text: text.substring(s.start, s.end),
            style: style.copyWith(
              decoration: TextDecoration.underline,
              decorationColor: AppColors.brassInk,
              decorationThickness: 2,
            ),
          ),
          TextSpan(text: text.substring(s.end), style: style),
        ],
      ),
    );
  }
}

// ── 31-2 ──────────────────────────────────────────────────────────────────────────────────────────

/// REPEAT THE WORD (31-2): the sample plays on opening at 0.85×, replay — «Listen»; microphone; pass — coverage of
/// `expected_text` by `coverage_min`; two attempts without a pass — `skipped`. Heard — an echo under the sheet.
class WordRepeatCard extends StatefulWidget {
  const WordRepeatCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordRepeatPayload payload;

  @override
  State<WordRepeatCard> createState() => _WordRepeatCardState();
}

class _WordRepeatCardState extends State<WordRepeatCard> with VoiceCardState<WordRepeatCard> {
  static const _key = 'repeat-term';

  @override
  CardEnv get env => widget.env;

  @override
  String get expectedSpeech => widget.payload.expectedText;

  @override
  bool accepts(String heard) => SessionRules.voiceAccepted(widget.payload, heard, env.speech);

  @override
  void initState() {
    super.initState();
    initVoice();
    final p = widget.payload;
    _autoplay(this, env, p.termAudio, p.term.textTarget, _key, rate: kRepeatRate);
  }

  @override
  void dispose() {
    disposeVoice();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final noMic = noMicBody((s) => SessionTexts.stage(AppLocalizations.of(context), s));
    if (noMic != null) return noMic;
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final term = p.term;
    final heardOk = done && !skippedAfterMisses;
    return CardLayout(
      taskGap: 20,
      centerBody: true,
      fadeStop: 0.30,
      task: SessionTask(
        l.planSessionTaskSayWord,
        companion: env.role.isEmpty ? null : l.planSessionCompanionSayWord(SessionTexts.roleInline(env.role)),
      ),
      body: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (term.image != null) ...[SessionPhoto(image: term.image, height: 170), const SizedBox(height: 20)],
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(term.textTarget, style: AppTextSession.term),
                          if (term.pronunciationNative != null) ...[
                            const SizedBox(height: 4),
                            Text(term.pronunciationNative!, style: AppTextSession.meta),
                          ],
                          const SizedBox(height: 4),
                          Text(context.nativeText(term.textNative), style: AppTextSession.body),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    CardListen(env: env, audio: p.termAudio, fallback: term.textTarget, playKey: _key, rate: kRepeatRate),
                  ],
                ),
              ],
            ),
          ),
          if (heardOk) ...[
            const SizedBox(height: 20),
            SessionSheet(
              key: const ValueKey('word-repeat-echo'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const SessionCheckBadge(),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(term.textTarget, style: AppTextSession.target22.copyWith(color: AppColors.verdictKnown)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(l.planSessionEcho, style: AppTextSession.meta),
                ],
              ),
            ),
          ],
        ],
      ),
      bottom: voiceDock(context, showHeardLine: false),
    );
  }
}

// ── 31-3 / 31-4 ───────────────────────────────────────────────────────────────────────────────────

/// CHOICE OF FOUR (template 30-9): `term_to_native` (31-3) — photo, word and sound on the question (it plays by itself
/// once when the card opens, SESSION-2a §2), options in the native language; `native_to_term` (31-4) — photo and
/// translation without sound, options — target words with their own sound.
class WordChooseCard extends StatefulWidget {
  const WordChooseCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordChoosePayload payload;

  @override
  State<WordChooseCard> createState() => _WordChooseCardState();
}

class _WordChooseCardState extends State<WordChooseCard> with ChoiceCardState<WordChooseCard> {
  static const _promptKey = 'choose-prompt';

  Timer? _autoplay;

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  void initState() {
    super.initState();
    final p = widget.payload;
    if (p.termToNative) _autoplay = autoplayOnce(this, env, p.promptAudio, p.promptTextTarget ?? '', _promptKey);
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
    final forward = p.termToNative;
    final text = (forward ? p.promptTextTarget : p.promptTextNative) ?? '';
    return CardLayout(
      bodyGap: 4,
      taskInBody: p.promptImage == null,
      task: SessionTask(forward ? l.planSessionTaskChooseTranslation : l.planSessionTaskChooseWord),
      body: SessionQuestionSheet(
        media: p.promptImage == null ? null : SessionPhoto(image: p.promptImage, radius: 0),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: forward ? l.planSessionBrowWord : l.planSessionBrowTranslation,
        eyebrowTrailing: eyebrowTrailing(l),
        // The word as it came; its translation (the other direction) is the learner's language — set by its typography.
        text: Text(forward ? text : context.nativeText(text), style: AppTextSession.question),
        listen: forward ? CardListen(env: env, audio: p.promptAudio, fallback: text, playKey: _promptKey) : null,
      ),
      bottom: optionsDock(
        context,
        target: !forward,
        listen: forward
            ? null
            : (o) => CardListen(env: env, audio: o.audio, fallback: o.text, playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}

// ── 31-5 ──────────────────────────────────────────────────────────────────────────────────────────

/// BY EAR (31-5): a wave instead of the photo, sound only on the question (plays on opening), the options are
/// silent — translations in the native language drawn like those of 31-3 (contract SESSION-1e), or target
/// spellings on a day dealt before it.
class WordListenCard extends StatefulWidget {
  const WordListenCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordListenPayload payload;

  @override
  State<WordListenCard> createState() => _WordListenCardState();
}

class _WordListenCardState extends State<WordListenCard> with ChoiceCardState<WordListenCard> {
  static const _key = 'listen-audio';

  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  /// Without a file the phone reads the word: spellings — the correct option; translations — the day's word by the
  /// card's unit, never the correct option (that would read the answer aloud). Not found — silence.
  String get _fallback {
    final p = widget.payload;
    if (!p.nativeOptions) return p.correctOption?.text ?? '';
    return env.termText?.call(env.card.unit.ref) ?? '';
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
    return CardLayout(
      bodyGap: 4,
      task: SessionTask(l.planSessionTaskChooseHeard),
      body: SessionQuestionSheet(
        media: ValueListenableBuilder<Object?>(
          valueListenable: env.voice.playing,
          builder: (_, playing, _) => SessionWavePlate(
            playing: playing == _key,
            label: l.planWindowListen,
            onTap: () => unawaited(env.voice.play(p.audio, fallback: _fallback, key: _key)),
          ),
        ),
        mediaHeight: answeredWrong ? 160 : 208,
        eyebrow: l.planSessionBrowByEar,
        eyebrowTrailing: eyebrowTrailing(l),
        text: Text(l.planSessionWhatHeard, style: AppTextSession.question),
        listen: CardListen(env: env, audio: p.audio, fallback: _fallback, playKey: _key),
      ),
      bottom: optionsDock(context, target: !p.nativeOptions),
    );
  }
}

// ── 31-6 ──────────────────────────────────────────────────────────────────────────────────────────

/// BUILD FROM TILES (31-6): photo and translation carry the meaning, tiles 30-5; «Check»; pass — the assembled =
/// `expected` in order. Wrong — a shake, an outline at the mistake's place, the correct tile underlined in brass.
class WordAssembleCard extends StatefulWidget {
  const WordAssembleCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordAssemblePayload payload;

  @override
  State<WordAssembleCard> createState() => _WordAssembleCardState();
}

class _WordAssembleCardState extends State<WordAssembleCard> {
  /// Positions of the tray tiles in the order they were placed.
  final List<int> _placed = [];
  bool? _correct;
  int _shake = 0;

  WordAssemblePayload get p => widget.payload;

  List<String> get _placedText => [for (final i in _placed) p.tiles[i]];

  int get _mismatch {
    final placed = _placedText;
    for (var i = 0; i < placed.length; i++) {
      if (i >= p.expected.length || placed[i] != p.expected[i]) return i;
    }
    return placed.length < p.expected.length ? placed.length : -1;
  }

  void _check() {
    final ok = SessionRules.wordAssembled(_placedText, p.expected);
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
      response: const SessionResponse(mode: 'tiles'),
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
    final wrongAt = _correct == false ? _mismatch : -1;
    // The correct tile for the mistake's place — the first unused one with this text.
    int? hintTray;
    if (wrongAt >= 0 && wrongAt < p.expected.length) {
      final want = p.expected[wrongAt];
      for (var i = 0; i < p.tiles.length; i++) {
        if (p.tiles[i] == want && !_placed.contains(i)) {
          hintTray = i;
          break;
        }
      }
    }
    return CardLayout(
      taskGap: 20,
      bodyGap: 16,
      fadeStop: 0.34,
      task: SessionTask(l.planSessionTaskAssembleParts),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SessionSheet(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (p.term.image != null) ...[SessionPhoto(image: p.term.image, height: 226), const SizedBox(height: 16)],
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Expanded(child: Text(context.nativeText(p.term.textNative), style: AppTextSession.text15)),
                    const SizedBox(width: 8),
                    Text(l.planSessionByParts, style: AppTextSession.meta),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          SessionAssembly(
            trayGap: 20,
            row: [
              for (var k = 0; k < _placed.length; k++) RowPiece.word(p.tiles[_placed[k]], wrong: k == wrongAt),
            ],
            tray: [
              for (var i = 0; i < p.tiles.length; i++) TrayPiece(p.tiles[i], used: _placed.contains(i), hint: i == hintTray),
            ],
            caret: !answered,
            sage: _correct == true,
            shake: _shake,
            onTray: answered ? null : (i) => setState(() => _placed.add(i)),
            onRow: answered ? null : (k) => setState(() => _placed.removeAt(k)),
          ),
        ],
      ),
      bottom: _correct == false
          ? DockButton(label: l.planSessionNext, busy: env.advancing, onTap: () => unawaited(env.next()))
          : DockButton(label: l.planSessionCheck, enabled: _placed.isNotEmpty && !answered, onTap: _check),
    );
  }
}

// ── 31-7 ──────────────────────────────────────────────────────────────────────────────────────────

/// WORD IN THE SLOT (31-7): the line with a brass slot in place of the word, the full translation `text_native`
/// with the word, four terms with their own sound. The card has no photo (the payload does not carry one) —
/// text-only sheet top.
class WordInLineCard extends StatefulWidget {
  const WordInLineCard({super.key, required this.env, required this.payload});

  final CardEnv env;
  final WordInLinePayload payload;

  @override
  State<WordInLineCard> createState() => _WordInLineCardState();
}

class _WordInLineCardState extends State<WordInLineCard> with ChoiceCardState<WordInLineCard> {
  @override
  CardEnv get env => widget.env;

  @override
  ChoicePayload get choice => widget.payload;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = widget.payload;
    final parts = splitAtSlot(p.line.textTarget);
    final correct = p.correctOption?.text;
    return CardLayout(
      bodyGap: 4,
      taskInBody: true,
      task: SessionTask(l.planSessionTaskInsertWord),
      body: SessionQuestionSheet(
        eyebrow: l.planSessionBrowSlot,
        eyebrowTrailing: eyebrowTrailing(l),
        text: SessionFrameText(
          before: parts.before,
          after: parts.after,
          style: AppTextSession.question,
          window: p.line.textTarget.contains(kSlotMark),
          slot: answered ? correct : null,
          look: answered ? SlotLook.sage : SlotLook.empty,
          emptyWindow: const Size(56, 28),
        ),
        listen: CardListen(
          env: env,
          audio: p.line.audio,
          fallback: correct == null ? p.line.textTarget : p.line.textTarget.replaceFirst(kSlotMark, correct),
          playKey: 'in-line',
        ),
        // The full translation with the word, not `text_native_gapped`: the line's slot accepts several words of
        // the day, and only the translation makes the answer unique (owner's clarification to 31-7; the canvas is
        // wrong here).
        translation: context.nativeText(p.line.textNative),
      ),
      bottom: optionsDock(
        context,
        target: true,
        listen: (o) => CardListen(env: env, audio: o.audio, fallback: o.text, playKey: 'option-${o.id}', size: 28),
      ),
    );
  }
}
