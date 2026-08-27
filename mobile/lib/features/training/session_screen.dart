import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/pronouncer.dart';
import '../../data/api_client.dart';
import '../../data/app_settings.dart';
import '../../data/languages.dart';
import '../../data/models.dart';
import '../../data/perf_log.dart';
import '../../data/practice/recognition_replay.dart';
import '../../data/providers.dart';
import '../home/home_providers.dart';
import 'session/intro_card.dart';
import 'session/session_exercise.dart';
import 'session/session_grading.dart';
import 'triage_swipe.dart';

/// One exercise session (кадры 12a–12k): due then new cards from `/study/sessions`, one card per
/// exercise, played offline-capable per card. The paper/ink «Слова» design (A3.8). Behaviour: the
/// client sends the RAW answer + a `client_seq`; the SERVER grades and schedules (the local check
/// is feedback-only and never stricter than the server). A [practice] session introduces no new
/// terms and never schedules — «Свободная тренировка».
class SessionScreen extends ConsumerStatefulWidget {
  const SessionScreen({
    super.key,
    required this.title,
    this.collectionId,
    this.practice = false,
    this.learn = false,
    this.limit = 20,
    this.targetLang,
    this.onlyTermId,
  });

  final String title;
  final String? collectionId;
  final bool practice;

  /// «Тренировать слово» from a word's expanded card (кадр 16e): a practice session whose pool is
  /// this ONE term. Practice-only by construction — a scheduling session's composition is the
  /// server's to fix, and drilling one word must not spend the daily quota on it.
  final String? onlyTermId;

  /// Opened from the «Учить N» CTA (device-batch F8): the caller knew there were learnable words
  /// (triaged-«не знаю», no progress row). If the built session is still empty, the only cause is
  /// the daily new-words quota being spent — so the empty state says «come back tomorrow», not the
  /// misleading «nothing here yet». (The CTA itself is not gated on the quota — that's F13/F17.)
  final bool learn;

  final int limit;

  /// The language to pronounce answers in — the scoped collection's language (F16). Null for a
  /// cross-collection session, which falls back to the profile's target language.
  final String? targetLang;

  /// THE SAME SESSION, ONCE MORE — «Ещё раз» on a practice summary.
  ///
  /// Every field rides along, and [onlyTermId] is the one that has to: «ещё раз» after drilling ONE
  /// word means that word again. Dropping it turned the repeat into a session over the whole
  /// collection — a different session under the same button, and from «Мои слова» (no collection at
  /// all) it would have been a build with no scope left.
  ///
  /// A method rather than a copy at the call site, so «which fields does a repeat carry» is one
  /// answer in one place, and a field added later cannot be forgotten here silently.
  SessionScreen repeat() => SessionScreen(
    title: title,
    collectionId: collectionId,
    practice: practice,
    learn: learn,
    limit: limit,
    targetLang: targetLang,
    onlyTermId: onlyTermId,
  );

  @override
  ConsumerState<SessionScreen> createState() => _SessionScreenState();
}

class _SessionScreenState extends ConsumerState<SessionScreen> {
  // Minted once so `POST /study/sessions` is idempotent — a rebuild reuses the fixed composition.
  final String _sessionId = ApiClient.ulid();

  // F20: pre-warm the iOS keyboard while the session is still loading (behind the spinner), so the
  // ~600 ms first-keyboard-init doesn't freeze the first typing/cloze card. We briefly focus a hidden
  // field to spin the keyboard process up, then unfocus before any card needs it.
  final FocusNode _kbWarm = FocusNode(skipTraversal: true, canRequestFocus: true);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _kbWarm.requestFocus();
      // Drop focus next frame — the keyboard engine stays warm after this, but nothing is shown.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _kbWarm.unfocus();
      });
    });
  }

  @override
  void dispose() {
    _kbWarm.dispose();
    super.dispose();
  }

  /// «Ещё раз» on a practice summary: start a brand-new practice session immediately (a fresh
  /// SessionScreen mints a new id → the pool is reshuffled). Replaces the route so the back stack
  /// doesn't fill up with finished sessions. What «the same session» means is [SessionScreen.repeat].
  void _again() {
    Navigator.of(
      context,
    ).pushReplacement(MaterialPageRoute(builder: (_) => widget.repeat()));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final args = (
      sessionId: _sessionId,
      collectionId: widget.collectionId,
      practice: widget.practice,
      limit: widget.limit,
      onlyTermId: widget.onlyTermId,
    );
    final session = ref.watch(studySessionProvider(args));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          bottom: false,
          child: Stack(
            children: [
              session.when(
                loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
                // Sessions are still built server-side, so no network means no session. Say that
                // in words instead of printing a DioException at the user; the detail goes to the
                // log. Retry re-runs the provider — the session id is minted once, and the build
                // is idempotent under it, so a retry returns the same composition.
                error: (e, st) {
                  debugPrint('[session] build failed: $e\n$st');
                  return _CenteredMessage(
                    text: isOffline(e) ? l.sessionOffline : l.sessionLoadFailed,
                    icon: isOffline(e) ? LucideIcons.cloudOff : LucideIcons.triangleAlert,
                    // Not the green of a right answer: this screen is a failure, and the warning
                    // triangle was drawn in the success colour (QA-OBS-30).
                    iconColor: AppColors.destructiveText,
                    actionLabel: l.generationRetry,
                    onAction: () => ref.invalidate(studySessionProvider(args)),
                  );
                },
                data: (s) => s.cards.isEmpty
                    ? _CenteredMessage(
                        text: widget.learn ? l.sessionDailyNewLimit : l.sessionEmpty,
                        icon: widget.learn ? LucideIcons.clock : LucideIcons.check,
                      )
                    : _SessionShell(
                        session: s,
                        practice: widget.practice,
                        targetLang: widget.targetLang,
                        onAgain: _again,
                      ),
              ),
              // Invisible keyboard-warmup field (F20). 1×1, transparent, non-interactive.
              Positioned(
                left: 0,
                top: 0,
                width: 1,
                height: 1,
                child: Opacity(
                  opacity: 0,
                  child: IgnorePointer(child: TextField(focusNode: _kbWarm, autofocus: false)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SessionShell extends ConsumerStatefulWidget {
  const _SessionShell({
    required this.session,
    required this.practice,
    required this.onAgain,
    this.targetLang,
  });

  final StudySession session;
  final bool practice;
  final String? targetLang;

  /// Start another practice session (used by the practice summary's «Ещё раз»).
  final VoidCallback onAgain;

  @override
  ConsumerState<_SessionShell> createState() => _SessionShellState();
}

class _SessionShellState extends ConsumerState<_SessionShell> {
  final _pronouncer = Pronouncer();
  final _scroll = ScrollController();
  int _pos = 0;
  bool _finished = false;
  bool _answered = false; // current card answered → the pinned «Дальше» bar shows
  bool _bannerDismissed = false;
  final List<({SessionCard card, LocalCheck verdict})> _results = [];

  List<SessionCard> get _cards => widget.session.cards;

  /// A recognition slot is played at the pair's CURRENT rung, so a failed rung 1 is replayed rather
  /// than followed by rung 2 (QA-9). Resolved at DISPLAY time, which is the only moment that knows
  /// how the earlier cards went — the session itself was dealt before any of them were answered.
  late final RecognitionReplay _replay = RecognitionReplay(_cards, enabled: !widget.practice);

  /// The index actually being played at the current position — [_pos] unless the slot is replaying
  /// a rung the learner has not passed yet.
  int get _playing => _replay.resolve(_pos);
  SessionCard get _card => _cards[_playing];

  @override
  void initState() {
    super.initState();
    PerfLog.instance.screen = 'session'; // stall monitor: which screen a hitch belongs to
    // Raise the iOS audio session and prime the synthesizer ONCE, behind the loading spinner —
    // never on the first listening card, whose whole content is the sound (F20-r).
    // The pairs are not resolved yet, so this primes the engine with the session's fallback; every
    // utterance sets the language of the card it belongs to before speaking.
    unawaited(_pronouncer.warmUp(targetLang: _sessionLang));
    // F20: warm the first few cards' photos up front so opening photo cards aren't cold network
    // loads (the lag the user saw was photo cards fetching + decoding late).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _prepareCard(0);
      _prepareCard(1);
      _prepareCard(2);
    });
    unawaited(_resolvePairs());
  }

  /// WHICH PAIR each card belongs to — «EN→RU» over the card, resolved from the local mirror.
  ///
  /// A study session is MIXED by design (DECISIONS п. 128): the pool holds words from folders of
  /// different languages and deals them in one stream. Without a label a Polish word arriving in
  /// what the learner took to be an English session reads as a bug in the app.
  ///
  /// WHERE it is drawn depends on the session: when every card shares one pair — a collection-scoped
  /// session, and most of them — it is stated ONCE in the header, beside the phase, because
  /// repeating it over twenty cards is noise about something that is not changing. As soon as two
  /// pairs are in the same session it moves ONTO the card, where it changes with the card and can be
  /// read against the word it belongs to.
  Map<String, ({String learned, String support})> _pairs = const {};

  /// True when this session spans more than one pair — the case the per-card badge exists for.
  bool get _mixedPairs => _pairs.values.map((p) => '${p.learned}:${p.support}').toSet().length > 1;

  ({String learned, String support})? get _cardPair => _pairs[_card.termId];

  Future<void> _resolvePairs() async {
    final pairs = await ref
        .read(appDatabaseProvider)
        .pairByTerms(_cards.map((c) => c.termId).toList(growable: false));
    if (mounted) setState(() => _pairs = pairs);
  }

  /// The resolved photo url per card index. A present KEY means the lookup finished, which is what
  /// lets the card size its banner on the first frame instead of reserving 150 px and collapsing a
  /// moment later — that collapse was a 164 px jump right after the slide (F20-r).
  final Map<int, String?> _photoUrl = {};

  /// Indices already prepared. Each card used to be prepared TWICE (once as `+1`, once as `+2`),
  /// so a 20-card session paid 40 lookups and 40 decodes.
  final Set<int> _prepared = {};

  /// Only `multiple_choice` renders the banner in its prompt, so only those are worth decoding up
  /// front. Everything else pays a ~2.7 MB decode for a picture it never shows.
  bool _showsPhoto(int index) =>
      _photoUrl[index] != null && _cards[index].mode == ExerciseMode.multipleChoice;

  /// Resolve the card's photo (always — the layout needs to know) and warm it (only when the card
  /// actually shows one). No-op for an out-of-range or already-prepared index.
  void _prepareCard(int index) {
    if (index < 0 || index >= _cards.length || !mounted) return;
    if (!_prepared.add(index)) return;
    final card = _cards[index];
    ref.read(appDatabaseProvider).termById(card.termId).then((term) async {
      if (!mounted) return;
      final raw = term?.imageUrl ?? '';
      final url = raw.isEmpty ? null : raw;
      // Only the on-screen card needs a rebuild; a card resolved ahead of time is simply recorded.
      if (index <= _pos) {
        setState(() => _photoUrl[index] = url);
      } else {
        _photoUrl[index] = url;
      }
      if (url != null && card.mode == ExerciseMode.multipleChoice) {
        await precacheSessionImage(context, url);
      }
    });
  }

  @override
  void dispose() {
    PerfLog.instance.screen = 'app';
    // Hands the iOS audio session back (and un-ducks other audio) exactly once, here — not after
    // every spoken word, which is what froze the trainer for ~600 ms per utterance (F20-r).
    unawaited(_pronouncer.release());
    _scroll.dispose();
    super.dispose();
  }

  /// The session's FALLBACK language: the scoped collection's (F16), else the profile's. It is what
  /// a card is spoken in only while its own pair is unknown — the warm-up, which runs before
  /// [_resolvePairs] has answered, and a card whose collection is not in the local mirror.
  String get _sessionLang =>
      widget.targetLang ?? ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';

  /// The language a CARD is in — read off that card's own pair, the way the pair badge is.
  ///
  /// A study session is mixed by design (DECISIONS п. 128), so «what language is this» is a property
  /// of the card, never of the session or of the profile. Pinned to the profile it was the bug from
  /// the owner's phone: an Italian card read out by an English voice, in a session that also held
  /// English cards, with listening and dictation — whose whole content IS the sound — asking one
  /// language and pronouncing another.
  String _langOfCard(SessionCard card) => _pairs[card.termId]?.learned ?? _sessionLang;

  Future<void> _speak(String lang, String text, {bool slow = false}) async {
    // Reuse the Pronouncer, which speaks a Word — wrap the raw target text. [slow] backs the
    // listening card's «замедленно» replay.
    await _pronouncer.speak(
      Word(termId: '', term: text, translation: '', type: 'word'),
      targetLang: lang,
      slow: slow,
    );
  }

  void _onAnswered(SessionAnswer a) {
    // Read BEFORE the ladder moves: this is the card that was actually on screen, and after
    // [RecognitionReplay.record] the same slot may resolve elsewhere.
    final played = _card;
    // F20: while the user reads the feedback, warm the next TWO cards' photos so an upcoming photo
    // card meets a ready image instead of a cold network load (precache is the main image fix).
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    // A wrong answer shows the photo in the feedback whatever the mode, and only `multiple_choice`
    // was warmed up front — so warm this one now. It lands on a static screen, never on a slide.
    if (a.verdict == LocalCheck.wrong && !_showsPhoto(_pos)) {
      final url = _photoUrl[_pos];
      if (url != null) precacheSessionImage(context, url);
    }
    _results.add((card: played, verdict: a.verdict));
    ref
        .read(reviewSyncProvider)
        .record(
          termId: played.termId,
          exerciseMode: played.mode.wire,
          response: a.response,
          usedHint: a.usedHint,
          isPractice: widget.practice,
          latencyMs: a.latencyMs,
          sessionId: widget.session.sessionId,
          // Echo the rung the card was dealt at — the rung of the card SHOWN, which after a replay
          // is not the one the slot was planned at. The server needs it to know a rung-1 answer is a
          // TAP graded by identity; without it the tapped term id is graded as text and a correct
          // tap becomes a lapse. It is also what makes the review log's rung column true.
          ladderStep: played.ladderStep,
        );
    // Move the session's own view of the ladder. A failed recognition leaves the pair where it is,
    // which is what makes the term's next slot replay this rung instead of dealing the next one.
    _replay.record(played, accepted: a.verdict.isAccepted);
    // Reveal the pinned «Дальше» bar. It lives OUTSIDE the scroll view, so it stays reachable no
    // matter how tall the feedback grows (the photo loads async and kept pushing an in-scroll
    // button below the fold — device-batch F9).
    setState(() => _answered = true);
  }

  /// A speaking card the MICROPHONE lost — «Пропустить» after a few failed attempts.
  ///
  /// The whole behaviour is what it does NOT do: no `reviewSync.record`, so no review row and no
  /// grade; no `_results` entry, so no tick or cross in the summary; no ladder movement, so the
  /// pair stands exactly where it did. The word simply comes back on its own schedule, as if this
  /// card had never been dealt — which is the honest reading of «the room was too noisy».
  ///
  /// It is a session slot spent, and only that: the counter moves so a card cannot trap the learner.
  void _skipCard() {
    PerfLog.instance.tapHandled('skip');
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    _next();
  }

  /// «Понятно» on an intro card. Nothing is graded and nothing reaches the review queue: the card
  /// asked for nothing, so there is no retrieval to log. What it produces is an EXPOSURE — durable,
  /// idempotent on the pair — plus the local ladder step, so the word's recognition cards later in
  /// this same session know it has been met even with the network off from start to summary.
  Future<void> _acknowledgeIntro() async {
    final termId = _card.termId;
    // Deliberately NOT added to [_results]: the summary lists answers, and an intro is not one —
    // a tick beside it would claim the word was got right. The word still reaches the summary,
    // through the recognition cards it comes back as later in this same session.
    await ref
        .read(exposureSyncProvider)
        .record(termId: termId, sessionId: widget.session.sessionId);
    if (!mounted) return;
    _prepareCard(_pos + 1);
    _prepareCard(_pos + 2);
    _next();
  }

  void _next() {
    PerfLog.instance.tapHandled('next');
    // The verdict's auto-pronounce is fired on a timer AFTER the feedback settles, so a «Дальше»
    // tapped while it is still speaking used to carry the previous card's word over the slide and
    // finish it on top of the next card (QA-21). Cutting it here covers every way forward — the
    // «Дальше» bar, a microphone skip, an intro's «Понятно» — because all of them funnel through
    // this one method, including the last card's jump to the summary.
    unawaited(_pronouncer.stop());
    if (_pos + 1 >= _cards.length) {
      setState(() => _finished = true);
    } else {
      setState(() {
        _pos++;
        _answered = false;
      });
      // Release the photo of a card three back: it is off-screen, out of the outgoing animation,
      // and nothing can navigate to it again. Keeps the session's decoded-image footprint flat
      // instead of climbing pass after pass within one app launch (F20-r).
      unawaited(evictSessionImage(context, _photoUrl[_pos - 3]));
      // New card starts at the top (the previous one may have been scrolled to its feedback).
      if (_scroll.hasClients) _scroll.jumpTo(0);
    }
  }

  Future<bool> _confirmExit() async {
    // Silence the current utterance as the dialog opens, not after the pop: `dispose`'s `release`
    // does stop the engine, but it only runs once the screen is actually gone, so the word carried
    // on over the confirm dialog (QA-21). Staying (a cancelled dialog) simply leaves it quiet,
    // which is the right outcome for a word the learner has already read.
    unawaited(_pronouncer.stop());
    final l = AppLocalizations.of(context);
    final leave = await showCenterAlert(
      context: context,
      title: l.sessionExitTitle,
      message: l.sessionExitBody,
      confirmLabel: l.sessionExitConfirm, // «Выйти» — destructive
      cancelLabel: l.sessionExitCancel, // «Продолжить» — default
    );
    return leave ?? false;
  }

  /// The rung names are captions first («узнавание», under a dot) and a header second. One string
  /// in the deck rather than two, capitalised where the layout calls for it — two entries would be
  /// two entries to keep in step, and this is exactly the drift Ч.4 exists to undo.
  static String _capitalized(String s) =>
      s.isEmpty ? s : s[0].toUpperCase() + s.substring(1);

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    if (_finished) {
      return _SessionSummary(
        results: _results,
        practice: widget.practice,
        onAgain: widget.onAgain,
        sessionId: widget.session.sessionId,
        // Counted from the session's own cards, not from the answers: an intro produces no answer,
        // so it is not in [_results] at all — and the summary is reached only by playing every card,
        // which is what makes «dealt an intro» and «met the word» the same fact here.
        newWords: newWordCount(widget.session.cards),
      );
    }

    final total = _cards.length;
    final phaseLabel = widget.practice
        ? l.sessionPhasePractice
        // The RUNG's own name wherever the card has one — the same five words the word card, the
        // pool row and the ladder strip use (Ч.4). Capitalised here because it is a header; the
        // ladder strip sets the identical words in lower case as captions.
        : switch (sessionHeaderFor(mode: _card.mode, ladderStep: _card.ladderStep)) {
            SessionHeader.rungMeeting => _capitalized(l.ladderStep0),
            SessionHeader.rungRecognition => _capitalized(l.ladderStep1),
            SessionHeader.rungAssembly => _capitalized(l.ladderStep3),
            SessionHeader.rungWriting => _capitalized(l.ladderStep4),
            SessionHeader.rungDictation => _capitalized(l.ladderStep5),
            SessionHeader.phaseIntro => l.sessionPhaseIntro,
            SessionHeader.phaseAssemble => l.sessionPhaseAssemble,
            SessionHeader.phaseReview => l.sessionPhaseReview,
          };

    final autoPronounce = ref.watch(appSettingsProvider).value?.autoPronounce ?? true;

    final builtAt = _pos; // the index this card is built for — used to cancel its deferred effects
    final isIntro = _card.mode == ExerciseMode.intro;
    // Bound to THIS CARD rather than to «the current position»: a verdict's auto-pronounce fires
    // after [RecognitionReplay.record], by which point the slot may resolve to a different term —
    // and the word being spoken is still the one the learner just answered.
    //
    // The LOOKUP, though, happens at speak time, not here: [_resolvePairs] answers asynchronously,
    // and a card built before it landed would otherwise carry the fallback language for as long as
    // it is on screen — which is precisely the first card of the session.
    final played = _card;
    final cardLang = _langOfCard(played);
    Future<void> speakCard(String text, {bool slow = false}) =>
        _speak(_langOfCard(played), text, slow: slow);
    // The intro is not an exercise, so it is not the exercise widget. It has no options, no input
    // and no verdict — giving it its own widget is what keeps an "answer" with no answer in it out
    // of the card that owns answering.
    final card = isIntro
        ? SessionIntroCard(
            key: ValueKey(_pos),
            card: _card,
            autoPronounce: autoPronounce,
            onSpeak: speakCard,
            photoUrl: _photoUrl[_pos],
            photoResolved: _photoUrl.containsKey(_pos),
            speechLocaleId: sttLocaleFor(cardLang),
            isCurrent: () => mounted && _pos == builtAt,
          )
        : SessionExerciseCard(
            key: ValueKey(_pos),
            card: _card,
            autoPronounce: autoPronounce,
            onAnswered: _onAnswered,
            onSpeak: speakCard,
            onSkipped: _skipCard,
            // The learner is speaking the language being LEARNED, whatever the app's own language
            // is — the same value the pronouncer speaks in, off the same card's pair.
            speechLocaleId: sttLocaleFor(cardLang),
            // And TYPING it in the same language. One value, three consumers (voice out, voice in,
            // keyboard + the layout guard) — a mixed session must not judge an Italian answer by
            // English's alphabet any more than it may read it out in an English voice.
            answerLang: cardLang,
            photoUrl: _photoUrl[_pos],
            photoResolved: _photoUrl.containsKey(_pos),
            showDue: !widget.practice,
            // F20: still the on-screen card? A fast «Дальше» moves _pos on, so the outgoing card's
            // deferred speak/focus is cancelled instead of firing on the next card.
            isCurrent: () => mounted && _pos == builtAt,
          );

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
      },
      // Stamps every touch so a handler can report how long the tap waited (see [PerfLog]).
      child: Listener(
        onPointerDown: (_) => PerfLog.instance.pointerDown(),
        child: Column(
          children: [
            if (widget.practice && !_bannerDismissed)
              _PracticeBanner(onClose: () => setState(() => _bannerDismissed = true)),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 14, AppSpacing.screenH, 0),
              child: _SessionHeader(
                phaseLabel: phaseLabel,
                // One pair for the whole session: say it once, here, beside the phase. Mixed:
                // null, and the badge rides each card instead — see [_pairs].
                pair: _mixedPairs ? null : _pairs.values.firstOrNull,
                current: _pos + 1,
                total: total,
                onClose: () async {
                  if (await _confirmExit() && context.mounted) Navigator.of(context).pop();
                },
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                controller: _scroll,
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.screenH,
                  18,
                  AppSpacing.screenH,
                  AppSpacing.s26,
                ),
                child: _SlideSwitcher(
                  index: _pos,
                  child: (_mixedPairs && _cardPair != null)
                      ? Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // Top-right, above the card and outside it: the corner the eye reaches
                            // last, so it answers «which language» without competing with the word.
                            Padding(
                              padding: const EdgeInsets.only(bottom: 6),
                              child: Align(
                                alignment: Alignment.centerRight,
                                child: PairBadge(
                                  learned: _cardPair!.learned,
                                  support: _cardPair!.support,
                                ),
                              ),
                            ),
                            card,
                          ],
                        )
                      : card,
                ),
              ),
            ),
            // «Дальше» pinned below the scroll view so a tall feedback (async photo) can't push it
            // off-screen (device-batch F9). Appears only once the card is answered.
            //
            // The intro's «Понятно →» sits in the same bar and is there from the start: there is
            // nothing to answer first, so nothing to wait for. One exit, no verdict.
            if (isIntro)
              _NextBar(onNext: _acknowledgeIntro, label: l.sessionIntroGot)
            else if (_answered)
              _NextBar(onNext: _next),
          ],
        ),
      ),
    );
  }
}

/// The pinned bottom action bar — the session's «Дальше», always reachable regardless of how far
/// the feedback content scrolls. Carries the bottom safe-area inset itself (the shell's SafeArea
/// has `bottom: false`).
class _NextBar extends StatelessWidget {
  const _NextBar({required this.onNext, this.label});
  final VoidCallback onNext;

  /// Overridden by the intro card, whose single exit reads «Понятно →» — it acknowledges a word
  /// that was shown rather than advancing past one that was answered.
  final String? label;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paper,
        border: Border(top: BorderSide(color: AppColors.hairline)),
      ),
      padding: EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        12,
        AppSpacing.screenH,
        12 + MediaQuery.of(context).viewPadding.bottom,
      ),
      child: PrimaryButton(
        label: label ?? l.sessionNext,
        trailingIcon: LucideIcons.arrowRight,
        onPressed: onNext,
      ),
    );
  }
}

/// Header: close (×) · phase label · «N из M» · session segments (§2б). The segment bar reuses
/// [SessionSegments] — the same one the triage header uses.
class _SessionHeader extends StatelessWidget {
  const _SessionHeader({
    required this.phaseLabel,
    required this.current,
    required this.total,
    required this.onClose,
    this.pair,
  });

  /// The session's pair when it has just one. Null when the session mixes pairs — the badge then
  /// belongs on the card, which is the only place it can change with the card.
  final ({String learned, String support})? pair;

  final String phaseLabel;
  final int current;
  final int total;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      children: [
        Row(
          children: [
            Semantics(
              button: true,
              label: l.sessionClose,
              child: InkResponse(
                onTap: onClose,
                radius: 22,
                child: const SizedBox(
                  width: AppSpacing.minTap,
                  height: AppSpacing.minTap,
                  child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
                ),
              ),
            ),
            Expanded(
              child: Text(
                phaseLabel,
                textAlign: TextAlign.center,
                style: AppTextExercise.sessionHeader,
              ),
            ),
            // A MINIMUM width, not a fixed one: «1 из 14» / «1 of 12» does not fit 44pt and was
            // wrapped to two lines the moment the denominator went double-digit (QA-OBS-28). The
            // 44pt floor is still there to balance the × on the left when the counter is short.
            ConstrainedBox(
              constraints: const BoxConstraints(minWidth: AppSpacing.minTap),
              child: Text(
                l.triageCounter(current, total),
                maxLines: 1,
                softWrap: false,
                textAlign: TextAlign.right,
                style: AppTextExercise.sessionHeader,
              ),
            ),
          ],
        ),
        // Centred UNDER the phase rather than beside it: the header row already carries a × and a
        // counter, and the counter has form — it wrapped to two lines the moment the denominator
        // went double-digit (QA-OBS-28). A third item competing for that row would find the same
        // edge on a narrow phone.
        if (pair case final p?) ...[
          const SizedBox(height: 4),
          Center(child: PairBadge(learned: p.learned, support: p.support)),
        ],
        const SizedBox(height: 10),
        SessionSegments(done: current - 1, total: total),
      ],
    );
  }
}

/// The quiet practice plaque (кадр 12f): 6 % ink, no colour, closes with an × and doesn't
/// return until the next session.
class _PracticeBanner extends StatelessWidget {
  const _PracticeBanner({required this.onClose});
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 10, AppSpacing.screenH, 0),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: AppColors.faintInk,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(color: AppColors.tertiary, shape: BoxShape.circle),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                l.sessionPracticeBanner,
                style: AppText.translation.copyWith(color: AppColors.inkBody),
              ),
            ),
            InkResponse(
              onTap: onClose,
              radius: 18,
              child: const Icon(LucideIcons.x, size: 16, color: AppColors.tertiary),
            ),
          ],
        ),
      ),
    );
  }
}

/// Card-to-card transition (§4е «Переход к следующему заданию»): the outgoing card fades and
/// slides left, the incoming one arrives from the right. Reduce-motion → an instant swap.
class _SlideSwitcher extends StatelessWidget {
  const _SlideSwitcher({required this.index, required this.child});
  final int index;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) {
      return KeyedSubtree(key: ValueKey(index), child: child);
    }
    return AnimatedSwitcher(
      duration: AppMotion.nextTaskEnter,
      switchInCurve: AppMotion.easeOut,
      switchOutCurve: AppMotion.easeIn,
      transitionBuilder: (child, anim) {
        final incoming = child.key == ValueKey(index);
        final offset = Tween<Offset>(
          begin: Offset(incoming ? 0.06 : -0.06, 0),
          end: Offset.zero,
        ).animate(anim);
        return FadeTransition(
          opacity: anim,
          child: SlideTransition(position: offset, child: child),
        );
      },
      layoutBuilder: (current, previous) =>
          Stack(alignment: Alignment.topCenter, children: [...previous, ?current]),
      child: KeyedSubtree(key: ValueKey(index), child: child),
    );
  }
}

// ── summary (кадр 12e) ────────────────────────────────────────────────────────

class _SessionSummary extends ConsumerStatefulWidget {
  const _SessionSummary({
    required this.results,
    required this.practice,
    required this.onAgain,
    required this.sessionId,
    this.newWords = 0,
  });

  final List<({SessionCard card, LocalCheck verdict})> results;
  final bool practice;

  /// Words INTRODUCED in this run — see [newWordCount]. Practice introduces nothing and never
  /// shows this stat.
  final int newWords;

  /// The run being closed. Reaching this screen IS the definition of «played to the end», which is
  /// what `study_sessions.ended_at` records (QA-12).
  final String sessionId;

  /// «Ещё раз» (practice only): start a fresh practice session right away.
  final VoidCallback onAgain;

  @override
  ConsumerState<_SessionSummary> createState() => _SessionSummaryState();
}

class _SessionSummaryState extends ConsumerState<_SessionSummary> {
  @override
  void initState() {
    super.initState();
    // Push the session's answers now rather than waiting for the next trigger, then a gentle
    // success — no confetti (§4е).
    WidgetsBinding.instance.addPostFrameCallback((_) => AppHaptics.success());
    ref.read(reviewSyncProvider).flush();
    // …and close the run. Recorded in its own durable queue first, so a session finished in
    // airplane mode still reaches `ended_at` when the network returns; the server takes only the
    // time from it and recomputes the rest from the run's own logs (QA-12).
    ref.read(sessionCompletionSyncProvider).record(sessionId: widget.sessionId);
  }

  int get _total => widget.results.length;
  int get _errors => widget.results.where((r) => r.verdict == LocalCheck.wrong).length;
  // Words met for the first time in this run, counted by the shell from the session's own cards
  // (see [newWordCount]) — the answers alone cannot tell, since an intro produces none.
  int get _new => widget.newWords;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final struggling = widget.results.where((r) => r.verdict == LocalCheck.wrong).toList();
    final practice = widget.practice;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.screenH,
        14,
        AppSpacing.screenH,
        AppSpacing.s26,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(l.sessionSummaryTitle, style: AppTextExercise.summaryTitle),
          const SizedBox(height: 18),
          // IntrinsicHeight bounds the row's height so the vertical dividers can stretch to it.
          // Without it, `CrossAxisAlignment.stretch` under the scroll view's unbounded height blew
          // the row up in RELEASE (asserts off), pushing the goal card, word list and Done button
          // off-screen — the whole summary looked like just three counters (device-batch F11).
          //
          // Practice gets a COMPACT two-stat tally («прошёл N, ошибки M») — there's no «New» (it
          // introduces nothing) and no daily-goal block (it moves nothing), per Training Loop v2/F17.
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              // Every label takes its own count: «1 НОВОЕ», not «1 НОВЫХ» (QA-OBS-12). The four
              // counter strings are plural-shaped even where the word doesn't inflect, so a call
              // site can't pass one counter's number with another's label.
              children: practice
                  ? [
                      _Stat(value: _total, label: l.sessionPracticeStatDone(_total)),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors(_errors)),
                    ]
                  : [
                      _Stat(value: _total, label: l.sessionStatReviewed(_total)),
                      const _StatDivider(),
                      _Stat(value: _new, label: l.sessionStatNew(_new)),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors(_errors)),
                    ],
            ),
          ),
          if (!practice) ...[const SizedBox(height: 18), const _GoalCard()],
          const SizedBox(height: 20),
          Text(l.sessionSessionWords.toUpperCase(), style: AppText.sectionLabel),
          const SizedBox(height: 6),
          // Practice never schedules, so a «увидишь через N дней» line would be a lie — hide it.
          for (final r in widget.results)
            _SummaryWordRow(card: r.card, verdict: r.verdict, showDue: !practice),
          // «Проседает → Новый пример» regenerates content, which is about progress-bearing study —
          // omit it in practice (compact итог).
          if (!practice && struggling.isNotEmpty) ...[
            const SizedBox(height: 16),
            _StrugglingCard(
              termId: struggling.first.card.termId,
              term: struggling.first.card.answerText,
            ),
          ],
          const SizedBox(height: 20),
          if (practice) ...[
            // «Ещё раз» → a fresh practice session immediately; «Готово» exits.
            PrimaryButton(
              label: l.sessionPracticeAgain,
              trailingIcon: LucideIcons.rotateCw,
              onPressed: widget.onAgain,
            ),
            const SizedBox(height: 10),
            Center(
              child: QuietButton(
                label: l.sessionDone,
                onPressed: () => Navigator.of(context).maybePop(),
              ),
            ),
          ] else
            PrimaryButton(label: l.sessionDone, onPressed: () => Navigator.of(context).maybePop()),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.value, required this.label});
  final int value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('$value', style: AppTextExercise.summaryNumber),
          const SizedBox(height: 5),
          Text(label.toUpperCase(), style: AppTextExercise.summaryLabel),
        ],
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();
  @override
  Widget build(BuildContext context) => Container(
    width: 1,
    margin: const EdgeInsets.symmetric(horizontal: 16),
    color: AppColors.hairline,
  );
}

/// Daily-goal card: the day's NEW WORDS against the day's goal, plus the streak. Filled and
/// labelled «закрыта» once the goal is met (кадр 12e).
///
/// Reads [dailyGoalProvider] — the same counter the home screen's ring reads, which is the whole
/// point of it existing (QA-BUG-2). This card used to print today's ANSWERS here («8 / 20») while
/// the home screen printed the new words («3 / 20») on the same day, and both called it «Дневная
/// цель». The session's own answer count is still on this screen — as the «повторено» stat above,
/// where it is a fact about the run and nothing is divided by a goal.
class _GoalCard extends ConsumerWidget {
  const _GoalCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final ring = ref.watch(dailyGoalProvider);
    final today = ring.done;
    final goal = ring.goal;
    final streak = ref.watch(statsProvider).value?.streakDays ?? 0;
    final done = today >= goal;

    return PaperCard(
      radius: AppRadii.alert,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  done ? l.sessionGoalClosed : l.sessionDailyGoal,
                  style: AppText.sheetButton.copyWith(fontSize: 14),
                ),
              ),
              Text('$today / $goal', style: AppText.counterHeader.copyWith(fontSize: 13)),
            ],
          ),
          const SizedBox(height: 10),
          ProgressLine(value: goal == 0 ? 1 : today / goal, height: 4),
          if (streak > 0) ...[
            const SizedBox(height: 9),
            Text(
              l.sessionStreak(streak),
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody),
            ),
          ],
        ],
      ),
    );
  }
}

class _SummaryWordRow extends ConsumerWidget {
  const _SummaryWordRow({required this.card, required this.verdict, this.showDue = true});
  final SessionCard card;
  final LocalCheck verdict;

  /// Practice sessions move no schedule → hide the «увидишь через N дней» line (it would be a lie).
  final bool showDue;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final prog = showDue ? ref.watch(termProgressForProvider(card.termId)) : null;
    final due = prog?.value?.dueAt;
    final relative = due == null
        ? null
        : () {
            final days = daysUntil(due.toLocal(), DateTime.now());
            return days == 0
                ? l.sessionDueToday
                : days == 1
                ? l.sessionDueTomorrow
                : l.sessionDueInDays(days);
          }();

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 11),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.hairline)),
      ),
      child: Row(
        children: [
          _VerdictMark(verdict: verdict),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // The reviewed word, as words — a rung-1 card's [answer] is its term id (see
                // SessionCard.answerText), and the mistakes list is exactly where it would show.
                Text(
                  card.answerText,
                  style: AppText.termInList,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                // The TRANSLATION, never the prompt: on a rung-1 card the prompt is the term itself,
                // and printing it under the headline gave «cold / cold» — a word explained by itself
                // (see [SessionCard.translationText]).
                Text(
                  card.translationText,
                  style: AppText.translation.copyWith(fontSize: 12),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          if (relative != null) ...[
            const SizedBox(width: 10),
            Text(relative, style: AppText.counterSmall.copyWith(color: AppColors.tertiary)),
          ],
        ],
      ),
    );
  }
}

class _VerdictMark extends StatelessWidget {
  const _VerdictMark({required this.verdict});
  final LocalCheck verdict;

  @override
  Widget build(BuildContext context) {
    // Correct → sage check; typo → amber dash (accepted but shaky); wrong → terracotta cross.
    return switch (verdict) {
      LocalCheck.correct => const Icon(LucideIcons.check, size: 18, color: AppColors.verdictKnown),
      LocalCheck.typo => Container(width: 18, height: 2, color: AppColors.verdictUnsure),
      LocalCheck.wrong => const Icon(LucideIcons.x, size: 18, color: AppColors.destructiveText),
    };
  }
}

/// «Проседает» block (кадр 12e): for a word missed this session, offer a fresh example
/// (B6 `POST /terms/{id}/regenerate-example`) — sometimes it's the context, not the word. Counts
/// against the daily quota (429 → «лимит исчерпан»).
class _StrugglingCard extends ConsumerStatefulWidget {
  const _StrugglingCard({required this.termId, required this.term});
  final String termId;
  final String term;

  @override
  ConsumerState<_StrugglingCard> createState() => _StrugglingCardState();
}

class _StrugglingCardState extends ConsumerState<_StrugglingCard> {
  bool _busy = false;
  bool _done = false;
  String? _error;

  Future<void> _regenerate() async {
    if (_busy || _done) return;
    final l = AppLocalizations.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(apiClientProvider).regenerateExample(widget.termId);
      // The new example replaces the stored one server-side; it arrives on the next sync/study.
      ref.read(syncServiceProvider).sync();
      if (mounted) setState(() => _done = true);
    } on DioException catch (e) {
      if (mounted) {
        setState(
          () => _error = e.response?.statusCode == 429
              ? l.sessionNewExampleExhausted
              : e.message ?? '',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return PaperCard(
      radius: AppRadii.alert,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l.sessionStrugglingTitle(widget.term),
            style: AppText.sheetButton.copyWith(fontSize: 14),
          ),
          const SizedBox(height: 6),
          Text(
            l.sessionStrugglingBody,
            style: AppText.translation.copyWith(
              fontSize: 12.5,
              color: AppColors.inkBody,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 12),
          if (_error != null) ...[
            Text(
              _error!,
              style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.destructiveText),
            ),
            const SizedBox(height: 10),
          ],
          Align(
            alignment: Alignment.centerLeft,
            child: _done
                ? Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(LucideIcons.check, size: 17, color: AppColors.verdictKnown),
                      const SizedBox(width: 8),
                      Text(
                        l.sessionNewExample,
                        style: AppTextExercise.answerAuxButton.copyWith(
                          color: AppColors.verdictKnown,
                        ),
                      ),
                    ],
                  )
                : QuietButton(
                    label: l.sessionNewExample,
                    icon: _busy ? null : LucideIcons.sparkles,
                    foreground: AppColors.ink,
                    onPressed: _busy ? null : _regenerate,
                  ),
          ),
        ],
      ),
    );
  }
}

class _CenteredMessage extends StatelessWidget {
  const _CenteredMessage({
    required this.text,
    this.icon,
    this.iconColor = AppColors.verdictKnown,
    this.actionLabel,
    this.onAction,
  });
  final String text;
  final IconData? icon;

  /// Green by default — the empty states this screen shows are «всё сделано», not faults. A
  /// failure passes its own colour (QA-OBS-30).
  final Color iconColor;

  /// Optional recovery action — «Повторить» on a failed session build. Absent for the empty
  /// states, where there is nothing to retry.
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned(
          top: 8,
          left: 8,
          child: Semantics(
            button: true,
            label: AppLocalizations.of(context).sessionClose,
            child: InkResponse(
              onTap: () => Navigator.of(context).maybePop(),
              radius: 22,
              child: const SizedBox(
                width: AppSpacing.minTap,
                height: AppSpacing.minTap,
                child: Icon(LucideIcons.x, size: 20, color: AppColors.secondary),
              ),
            ),
          ),
        ),
        Center(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.s26),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 48, color: iconColor),
                  const SizedBox(height: 12),
                ],
                Text(
                  text,
                  textAlign: TextAlign.center,
                  style: AppText.stepTitle.copyWith(fontSize: 20),
                ),
                if (actionLabel != null && onAction != null) ...[
                  const SizedBox(height: AppSpacing.s16),
                  QuietButton(label: actionLabel!, icon: LucideIcons.rotateCw, onPressed: onAction),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}
