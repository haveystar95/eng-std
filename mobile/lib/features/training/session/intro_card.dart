import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/local/cached_image_provider.dart';
import '../../../data/models.dart';
import '../../../data/providers.dart';
import '../../../data/speech/speech_recognizer.dart';
import 'session_exercise.dart';
import 'session_grading.dart';

/// The zeroth rung of the acquisition ladder: the word is SHOWN, not asked (кадр 16b).
///
/// A separate widget from [SessionExerciseCard] because it is not an exercise. It has no options,
/// no input, no verdict and no colour — the single exit is «Понятно →», and the only thing it
/// produces is the fact that the learner saw the word. Threading that through the exercise card's
/// answering→feedback state machine would mean an "answer" with no answer in it, which is exactly
/// the shape the review log must never be handed.
///
/// The order is the mock's: TERM first, so typography meets the reader before anything else; then
/// the translation; then the example with the term set in bold inside it; then the photo, which
/// CONFIRMS the meaning rather than announcing it; then «также:»; and the «новое слово» badge last,
/// just above «Понятно» — it says what kind of card this is, which is a footnote and not an opening
/// line. The badge is an outline, not a fill — nothing here is a verdict, so nothing here is coloured.
class SessionIntroCard extends ConsumerStatefulWidget {
  const SessionIntroCard({
    super.key,
    required this.card,
    required this.onSpeak,
    this.photoUrl,
    this.photoResolved = false,
    this.autoPronounce = true,
    this.speechLocaleId = 'en_US',
    this.isCurrent = _alwaysCurrent,
  });

  static bool _alwaysCurrent() => true;

  final SessionCard card;

  /// The shell's TTS. The intro speaks the TERM — the thing being learned — not the example.
  final Future<void> Function(String text, {bool slow}) onSpeak;

  final String? photoUrl;
  final bool photoResolved;
  final bool autoPronounce;

  /// Recognition locale for the echo — the language being learned.
  final String speechLocaleId;

  /// Still the on-screen card? A fast «Понятно» must cancel a deferred pronounce rather than fire
  /// it over the next card — the same rule the exercise card follows (F20).
  final bool Function() isCurrent;

  @override
  ConsumerState<SessionIntroCard> createState() => _SessionIntroCardState();
}

/// How the optional echo on an intro card is going. There is no verdict here on purpose — see
/// [_EchoRow] — so «heard» and «again» are the only two things it can ever say.
enum _Echo { idle, listening, heard, again }

class _SessionIntroCardState extends ConsumerState<SessionIntroCard> {
  Timer? _speakTimer;

  /// The echo's state. Starts [idle] and, if the learner never taps, stays there forever: the echo
  /// is entirely optional and the intro's «Понятно →» is reachable without it.
  _Echo _echo = _Echo.idle;

  /// What the recogniser transcribed on the last attempt, printed back under the button.
  ///
  /// Showing it is not a step toward grading it (QA-21): a bare «Услышал тебя» left the learner
  /// unable to tell a good attempt from a mangled one, or even whether the microphone had heard
  /// THEM rather than the room. The text answers that and nothing else — there is still no verdict
  /// here, and there is not going to be one. Cleared at the start of every new attempt, so the
  /// screen never shows a previous try's words beside a fresh recording.
  String _heard = '';

  /// Resolved once, so `dispose` can close a microphone left open without reaching for `ref` on a
  /// widget that is already coming down.
  late final SpeechRecognizer _recognizer = ref.read(speechRecognizerProvider);

  /// Set once an async, non-prompting OS permission check (below) confirms it — a brand-new word's
  /// intro card is very often the FIRST speech-touching card in a fresh app run, and its echo is
  /// never the thing that calls [SpeechRecognizer.prepare] (see [_speechReady]'s doc), so
  /// [SpeechRecognizer.isReady] alone stayed false there even with the mic already permitted from a
  /// past run or iOS Settings (QA-21).
  bool _osPermitted = false;

  /// Is the recogniser already permitted? The echo button is HIDDEN until it is, so an intro card
  /// never raises a microphone prompt on its own — the learner meets that question on the first
  /// speaking card, where saying something is the actual task, and not on a card that only asks
  /// them to read.
  ///
  /// [SpeechRecognizer.isReady] alone answers "has *this process* already prepared" — true once
  /// some OTHER card has called [SpeechRecognizer.prepare], but false for as long as this intro
  /// card is the first thing in the run to ask, even when the OS would say yes right now.
  /// [_osPermitted] (started in [initState], never prompts — see its own doc) covers exactly that
  /// gap without this card ever being the one that calls [SpeechRecognizer.prepare] itself.
  bool get _speechReady => _recognizer.isReady || _osPermitted;

  @override
  void initState() {
    super.initState();
    if (widget.autoPronounce) {
      // After the slide, like every other deferred effect on this screen — a channel call on the
      // transition's first frame is what F20 measured as a stall.
      _speakTimer = Timer(AppMotion.nextTaskEnter + const Duration(milliseconds: 60), () {
        if (mounted && widget.isCurrent()) widget.onSpeak(widget.card.answerText);
      });
    }
    // Skip the round trip when [SpeechRecognizer.isReady] already answers yes (the common case once
    // any card has prepared this run). [SpeechRecognizer.hasPermission] itself never prompts — see
    // its doc — so this cannot be the thing that raises iOS's permission dialog.
    if (!_recognizer.isReady) {
      unawaited(
        _recognizer.hasPermission.then((granted) {
          if (mounted && granted) setState(() => _osPermitted = true);
        }),
      );
    }
  }

  @override
  void dispose() {
    _speakTimer?.cancel();
    if (_echo == _Echo.listening) unawaited(_recognizer.cancel());
    super.dispose();
  }

  /// «Повторить вслух»: listen once, say something kind either way, and write NOTHING.
  ///
  /// No grade, no review, no exposure of its own, no effect on the ladder — the intro card's whole
  /// contract is that it asks for nothing, and an echo that could be failed would quietly turn it
  /// into the app's first exercise. What it is for is the mouth: hearing the word and then making
  /// it is how a word stops being a shape on a page, and doing that once, unwatched, is worth more
  /// here than any score would be.
  /// A second tap WHILE listening settles the attempt on whatever has been heard — the same
  /// «Готово» the speaking card offers. Without it the only way to end a recording was to go quiet
  /// and wait out the pause window, which on a card with no progress of any kind read as a hang
  /// (QA-21). Tapping again AFTER a result simply starts a fresh attempt, replacing the old text.
  Future<void> _echoBack() async {
    if (_echo == _Echo.listening) {
      await _recognizer.stop();

      return;
    }
    AppHaptics.light();
    setState(() {
      _echo = _Echo.listening;
      _heard = '';
    });

    final term = widget.card.answerText;
    final attempt = await _recognizer.listenOnce(
      expected: [term],
      localeId: widget.speechLocaleId,
      // Same window the speaking word form picks for a term of this length — a phrase-shaped
      // term needs the sentence-sized one (QA-21).
      timeout: SpokenAnswer.windowFor(asksForExample: false, term: term).listenFor,
      pauseFor: SpokenAnswer.windowFor(asksForExample: false, term: term).pauseFor,
      // The same vocabulary hint the speaking word form sends (QA-20): the term whole, plus its
      // individual words. Nothing here grades against it — it only helps the recogniser print
      // back what was actually said.
      contextualStrings: _contextualStrings(term),
      onPartial: (text) {
        if (mounted && _echo == _Echo.listening) setState(() => _heard = text);
      },
    );

    if (!mounted) return;
    // «Услышал тебя» means exactly that — the microphone worked. It is deliberately NOT a check
    // against the word: telling someone their first attempt at a new word was wrong is the fastest
    // way to make them stop trying it out loud.
    setState(() {
      _echo = attempt.isHeard ? _Echo.heard : _Echo.again;
      _heard = attempt.isHeard ? attempt.text.trim() : '';
    });
  }

  /// The term whole plus its individual words, deduplicated, no empties — the word form's own
  /// contextualStrings shape (see `SessionExerciseCard`).
  List<String> _contextualStrings(String term) {
    final strings = <String>{};
    void add(String text) {
      final trimmed = text.trim();
      if (trimmed.isNotEmpty) strings.add(trimmed);
    }

    add(term);
    for (final word in term.trim().split(RegExp(r'\s+'))) {
      add(word);
    }

    return strings.take(50).toList();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final card = widget.card;
    final example = card.example;
    final variants = card.acceptedVariants;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        PaperCard(
          clipContent: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Flexible(child: Text(card.answerText, style: AppTextExercise.introTerm)),
                  const SizedBox(width: AppSpacing.s8),
                  _SpeakButton(onTap: () => widget.onSpeak(card.answerText)),
                ],
              ),
              if ((card.transcription ?? '').isNotEmpty) ...[
                const SizedBox(height: 2),
                Text('/${card.transcription}/', style: AppText.transcription),
              ],
              const SizedBox(height: AppSpacing.s4),
              Text(card.prompt ?? '', style: AppTextExercise.introTranslation),
              if (example != null && example.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.s16),
                _ExampleLine(example: example, term: card.answerText),
                const SizedBox(height: AppSpacing.s12),
                _PromptPhotoPlate(
                  termId: card.termId,
                  url: widget.photoUrl,
                  resolved: widget.photoResolved,
                ),
              ] else ...[
                const SizedBox(height: AppSpacing.s12),
                _PromptPhotoPlate(
                  termId: card.termId,
                  url: widget.photoUrl,
                  resolved: widget.photoResolved,
                ),
              ],
              if (variants.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.s12),
                Text(
                  '${l.sessionIntroAlso} ${variants.join(' · ')}',
                  style: AppTextExercise.introAlso,
                ),
              ],
              // The echo. Absent entirely until the microphone has been permitted elsewhere, so
              // this card never asks for anything — including a permission.
              if (_speechReady) ...[
                const SizedBox(height: AppSpacing.s16),
                _EchoRow(state: _echo, heard: _heard, onTap: _echoBack),
              ],
              // The badge closes the card rather than opening it (кадр 16b): it is a footnote about
              // what KIND of card this is, and at the top it was the first thing read — a label
              // where the word itself should have met the reader. It stays the last line even with
              // the echo above it: the echo is something to DO, the badge only says what this is.
              const SizedBox(height: AppSpacing.s16),
              _IntroBadge(label: l.sessionIntroBadge),
            ],
          ),
        ),
      ],
    );
  }
}

/// «Повторить вслух» plus its one-line reaction.
///
/// Quiet by construction — a [QuietButton] and a grey line, no colour, no icon, no verdict. The
/// intro card has nothing on it that is a judgement, and the moment this row got a green tick it
/// would become one. «Услышал тебя» is a statement about the microphone; «Попробуй ещё» is an
/// invitation, not a fail.
class _EchoRow extends StatefulWidget {
  const _EchoRow({required this.state, required this.heard, required this.onTap});

  final _Echo state;

  /// The transcript to print back, or '' when there is none (idle, or nothing was made out).
  final String heard;

  final VoidCallback onTap;

  @override
  State<_EchoRow> createState() => _EchoRowState();
}

class _EchoRowState extends State<_EchoRow> with SingleTickerProviderStateMixin {
  /// The «I am recording» sign. A tap used to change nothing at all on screen (QA-21) — the button
  /// simply went disabled — so there was no way to tell a live microphone from a dead one, and no
  /// way to guess when to stop talking. The same slow breath the speaking card's record circle
  /// uses, so the two read as one behaviour.
  /// Built in [initState], NOT as a `late final` field: this row only touches the controller while
  /// listening, so on the common path (the echo is optional and usually never tapped) a lazy field
  /// would be constructed for the first time inside [dispose] — and an AnimationController reads
  /// TickerMode off the tree it is already leaving, which throws.
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: AppMotion.listenPulse,
      lowerBound: 0.55,
      upperBound: 1.0,
    );
  }

  @override
  void didUpdateWidget(_EchoRow old) {
    super.didUpdateWidget(old);
    if (widget.state == old.state) return;
    if (widget.state == _Echo.listening && !MediaQuery.of(context).disableAnimations) {
      _pulse.repeat(reverse: true);
    } else {
      _pulse.stop();
      _pulse.value = 1.0;
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
    final listening = widget.state == _Echo.listening;
    final heard = widget.heard.trim();

    // What the row says under the button. While listening it is the live partial, if there is one
    // yet — seeing your own words appear is the clearest possible «yes, it hears you».
    final note = switch (widget.state) {
      _Echo.idle => null,
      _Echo.listening => heard.isEmpty ? l.sessionSpeakListening : l.sessionSpeakHeard(heard),
      // Печатаем РАСПОЗНАННОЕ, not a bare «Услышал тебя»: the point of the echo is the mouth, and
      // the learner can only tell how it went by reading what came out. Still not a verdict — the
      // text is shown, never marked.
      _Echo.heard => heard.isEmpty ? l.sessionEchoHeard : l.sessionSpeakHeard(heard),
      _Echo.again => l.sessionEchoAgain,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            // Never disabled, unlike before: while listening the tap becomes «стоп», which is the
            // other half of making the recording's end knowable.
            QuietButton(
              label: listening ? l.sessionSpeakStop : l.sessionEchoTry,
              icon: listening ? LucideIcons.square : LucideIcons.mic,
              onPressed: widget.onTap,
            ),
            if (listening) ...[
              const SizedBox(width: AppSpacing.s8),
              FadeTransition(
                opacity: _pulse,
                child: const Icon(LucideIcons.mic, size: 16, color: AppColors.ink),
              ),
            ],
          ],
        ),
        if (note != null) ...[
          const SizedBox(height: AppSpacing.s4),
          Text(note, style: AppTextExercise.taskInstruction),
        ],
      ],
    );
  }
}

/// The «новое слово» badge — an OUTLINE. Nothing on this card is a verdict, so nothing on it is
/// filled or coloured; the badge marks a kind of card, not an outcome.
class _IntroBadge extends StatelessWidget {
  const _IntroBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.chip),
          border: Border.all(color: AppColors.hairline),
        ),
        child: Text(label.toUpperCase(), style: AppText.badge),
      ),
    );
  }
}

/// The example sentence with the term itself set in bold inside it (кадр 16b). Bold rather than an
/// underline: the underline in this design means «the broken fragment» on pick_correct, and one mark
/// cannot mean both "look here, this is the word" and "look here, this is wrong".
class _ExampleLine extends StatelessWidget {
  const _ExampleLine({required this.example, required this.term});
  final String example;
  final String term;

  @override
  Widget build(BuildContext context) {
    // The term's own trailing punctuation is not part of what to look for: «I have a fever.» is
    // taught by «I have a fever and feel very weak.», where the full stop sits nowhere near it.
    final needle = termSearchForm(term);
    final at = spanPositionIn(example, needle);
    if (at < 0) {
      return Text(example, style: AppTextExercise.introExample);
    }
    return Text.rich(
      TextSpan(
        style: AppTextExercise.introExample,
        children: [
          TextSpan(text: example.substring(0, at)),
          TextSpan(
            text: example.substring(at, at + needle.length),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          TextSpan(text: example.substring(at + needle.length)),
        ],
      ),
    );
  }
}

/// The term's photo under the example — it CONFIRMS the meaning the sentence just gave, which is
/// why it sits below and not above. Absent photo simply collapses; nothing reserves space for it.
class _PromptPhotoPlate extends StatelessWidget {
  const _PromptPhotoPlate({required this.termId, required this.url, required this.resolved});
  final String termId;
  final String? url;
  final bool resolved;

  @override
  Widget build(BuildContext context) {
    if (!resolved || url == null || url!.isEmpty) return const SizedBox.shrink();
    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadii.thumb),
      child: AspectRatio(
        aspectRatio: 16 / 10,
        child: Image(
          image: CachedNetworkImage(url!),
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => const SizedBox.shrink(),
        ),
      ),
    );
  }
}

class _SpeakButton extends StatelessWidget {
  const _SpeakButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: onTap,
        radius: 24,
        child: Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.hairline),
          ),
          child: const Icon(LucideIcons.volume2, size: 15, color: AppColors.ink),
        ),
      ),
    );
  }
}
