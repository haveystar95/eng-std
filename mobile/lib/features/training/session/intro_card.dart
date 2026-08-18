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

  /// Resolved once, so `dispose` can close a microphone left open without reaching for `ref` on a
  /// widget that is already coming down.
  late final SpeechRecognizer _recognizer = ref.read(speechRecognizerProvider);

  /// Is the recogniser already permitted? The echo button is HIDDEN until it is, so an intro card
  /// never raises a microphone prompt on its own — the learner meets that question on the first
  /// speaking card, where saying something is the actual task, and not on a card that only asks
  /// them to read.
  bool get _speechReady => _recognizer.isReady;

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
  Future<void> _echoBack() async {
    if (_echo == _Echo.listening) return;
    AppHaptics.light();
    setState(() => _echo = _Echo.listening);

    final attempt = await _recognizer.listenOnce(
          expected: [widget.card.answerText],
          localeId: widget.speechLocaleId,
        );

    if (!mounted) return;
    // «Услышал тебя» means exactly that — the microphone worked. It is deliberately NOT a check
    // against the word: telling someone their first attempt at a new word was wrong is the fastest
    // way to make them stop trying it out loud.
    setState(() => _echo = attempt.isHeard ? _Echo.heard : _Echo.again);
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
                _EchoRow(state: _echo, onTap: _echoBack),
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
class _EchoRow extends StatelessWidget {
  const _EchoRow({required this.state, required this.onTap});

  final _Echo state;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final note = switch (state) {
      _Echo.idle => null,
      _Echo.listening => l.sessionSpeakListening,
      _Echo.heard => l.sessionEchoHeard,
      _Echo.again => l.sessionEchoAgain,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        QuietButton(
          label: l.sessionEchoTry,
          icon: LucideIcons.mic,
          onPressed: state == _Echo.listening ? null : onTap,
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
