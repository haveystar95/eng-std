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
import '../../data/models.dart';
import '../../data/perf_log.dart';
import '../../data/providers.dart';
import '../progress/activity.dart';
import '../progress/progress_providers.dart';
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
  });

  final String title;
  final String? collectionId;
  final bool practice;

  /// Opened from the «Учить N» CTA (device-batch F8): the caller knew there were learnable words
  /// (triaged-«не знаю», no progress row). If the built session is still empty, the only cause is
  /// the daily new-words quota being spent — so the empty state says «come back tomorrow», not the
  /// misleading «nothing here yet». (The CTA itself is not gated on the quota — that's F13/F17.)
  final bool learn;

  final int limit;

  /// The language to pronounce answers in — the scoped collection's language (F16). Null for a
  /// cross-collection session, which falls back to the profile's target language.
  final String? targetLang;

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
  /// SessionScreen mints a new id → the server reshuffles the whole-collection pool). Replaces the
  /// route so the back stack doesn't fill up with finished sessions.
  void _again() {
    Navigator.of(context).pushReplacement(MaterialPageRoute(
      builder: (_) => SessionScreen(
        title: widget.title,
        collectionId: widget.collectionId,
        practice: widget.practice,
        learn: widget.learn,
        limit: widget.limit,
        targetLang: widget.targetLang,
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final args = (
      sessionId: _sessionId,
      collectionId: widget.collectionId,
      practice: widget.practice,
      limit: widget.limit,
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
                error: (e, _) => _CenteredMessage(text: l.sessionLoadError(e.toString())),
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
                  child: IgnorePointer(
                    child: TextField(focusNode: _kbWarm, autofocus: false),
                  ),
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
  SessionCard get _card => _cards[_pos];

  @override
  void initState() {
    super.initState();
    PerfLog.instance.screen = 'session'; // stall monitor: which screen a hitch belongs to
    // Raise the iOS audio session and prime the synthesizer ONCE, behind the loading spinner —
    // never on the first listening card, whose whole content is the sound (F20-r).
    unawaited(_pronouncer.warmUp(targetLang: _targetLang));
    // F20: warm the first few cards' photos up front so opening photo cards aren't cold network
    // loads (the lag the user saw was photo cards fetching + decoding late).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _prepareCard(0);
      _prepareCard(1);
      _prepareCard(2);
    });
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

  /// The scoped collection's language wins (F16); a cross-collection session has none and falls
  /// back to the profile's target language.
  String get _targetLang =>
      widget.targetLang ?? ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';

  Future<void> _speak(String text, {bool slow = false}) async {
    // Reuse the Pronouncer, which speaks a Word — wrap the raw target text. [slow] backs the
    // listening card's «замедленно» replay.
    await _pronouncer.speak(
      Word(termId: '', term: text, translation: '', type: 'word'),
      targetLang: _targetLang,
      slow: slow,
    );
  }

  void _onAnswered(SessionAnswer a) {
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
    _results.add((card: _card, verdict: a.verdict));
    ref.read(reviewSyncProvider).record(
          termId: _card.termId,
          exerciseMode: _card.mode.wire,
          response: a.response,
          usedHint: a.usedHint,
          isPractice: widget.practice,
          latencyMs: a.latencyMs,
          sessionId: widget.session.sessionId,
        );
    // Reveal the pinned «Дальше» bar. It lives OUTSIDE the scroll view, so it stays reachable no
    // matter how tall the feedback grows (the photo loads async and kept pushing an in-scroll
    // button below the fold — device-batch F9).
    setState(() => _answered = true);
  }

  void _next() {
    PerfLog.instance.tapHandled('next');
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

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    if (_finished) {
      return _SessionSummary(results: _results, practice: widget.practice, onAgain: widget.onAgain);
    }

    final total = _cards.length;
    final phaseLabel = widget.practice
        ? l.sessionPhasePractice
        : switch (phaseFor(_card.mode)) {
            SessionPhase.intro => l.sessionPhaseIntro,
            SessionPhase.assemble => l.sessionPhaseAssemble,
            SessionPhase.review => l.sessionPhaseReview,
          };

    final autoPronounce = ref.watch(appSettingsProvider).value?.autoPronounce ?? true;

    final builtAt = _pos; // the index this card is built for — used to cancel its deferred effects
    final card = SessionExerciseCard(
      key: ValueKey(_pos),
      card: _card,
      autoPronounce: autoPronounce,
      onAnswered: _onAnswered,
      onSpeak: _speak,
      photoUrl: _photoUrl[_pos],
      photoResolved: _photoUrl.containsKey(_pos),
      showDue: !widget.practice,
      // F20: still the on-screen card? A fast «Дальше» moves _pos on, so the outgoing card's deferred
      // speak/focus is cancelled instead of firing on the next card.
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
              padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 18, AppSpacing.screenH, AppSpacing.s26),
              child: _SlideSwitcher(index: _pos, child: card),
            ),
          ),
          // «Дальше» pinned below the scroll view so a tall feedback (async photo) can't push it
          // off-screen (device-batch F9). Appears only once the card is answered.
          if (_answered) _NextBar(onNext: _next),
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
  const _NextBar({required this.onNext});
  final VoidCallback onNext;

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
      child: PrimaryButton(label: l.sessionNext, trailingIcon: LucideIcons.arrowRight, onPressed: onNext),
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
  });

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
              child: Text(phaseLabel, textAlign: TextAlign.center, style: AppTextExercise.sessionHeader),
            ),
            SizedBox(
              width: AppSpacing.minTap,
              child: Text(
                l.triageCounter(current, total),
                textAlign: TextAlign.right,
                style: AppTextExercise.sessionHeader,
              ),
            ),
          ],
        ),
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
        decoration: BoxDecoration(color: AppColors.faintInk, borderRadius: BorderRadius.circular(14)),
        child: Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(color: AppColors.tertiary, shape: BoxShape.circle),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(l.sessionPracticeBanner, style: AppText.translation.copyWith(color: AppColors.inkBody)),
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
    if (MediaQuery.of(context).disableAnimations) return KeyedSubtree(key: ValueKey(index), child: child);
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
        return FadeTransition(opacity: anim, child: SlideTransition(position: offset, child: child));
      },
      layoutBuilder: (current, previous) => Stack(
        alignment: Alignment.topCenter,
        children: [...previous, ?current],
      ),
      child: KeyedSubtree(key: ValueKey(index), child: child),
    );
  }
}

// ── summary (кадр 12e) ────────────────────────────────────────────────────────

class _SessionSummary extends ConsumerStatefulWidget {
  const _SessionSummary({required this.results, required this.practice, required this.onAgain});

  final List<({SessionCard card, LocalCheck verdict})> results;
  final bool practice;

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
  }

  int get _total => widget.results.length;
  int get _errors => widget.results.where((r) => r.verdict == LocalCheck.wrong).length;
  // "New" ≈ intro cards (multiple_choice) — the session card carries the mode, not the state, so
  // this is a proxy for freshly-introduced terms (new/relearning), documented in session_grading.
  int get _new => widget.results.where((r) => phaseFor(r.card.mode) == SessionPhase.intro).length;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final struggling = widget.results.where((r) => r.verdict == LocalCheck.wrong).toList();
    final practice = widget.practice;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.screenH, 14, AppSpacing.screenH, AppSpacing.s26),
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
              children: practice
                  ? [
                      _Stat(value: _total, label: l.sessionPracticeStatDone),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors),
                    ]
                  : [
                      _Stat(value: _total, label: l.sessionStatReviewed),
                      const _StatDivider(),
                      _Stat(value: _new, label: l.sessionStatNew),
                      const _StatDivider(),
                      _Stat(value: _errors, label: l.sessionStatErrors),
                    ],
            ),
          ),
          if (!practice) ...[
            const SizedBox(height: 18),
            const _GoalCard(),
          ],
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
            _StrugglingCard(termId: struggling.first.card.termId, term: struggling.first.card.answer),
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
            PrimaryButton(
              label: l.sessionDone,
              onPressed: () => Navigator.of(context).maybePop(),
            ),
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

/// Daily-goal card: today's cumulative reviews vs the profile goal, plus the streak. Filled and
/// labelled «закрыта» once the goal is met (кадр 12e). Reads the same local `daily_activity` the
/// Progress screen does, so the numbers agree.
class _GoalCard extends ConsumerWidget {
  const _GoalCard();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final goal = ref.watch(authControllerProvider).value?.profile?.dailyGoal ?? 20;
    final activity = ref.watch(dailyActivityProvider).value ?? const {};
    final today = todayReviewCount(DateTime.now(), activity);
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
            Text(l.sessionStreak(streak), style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody)),
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
                Text(card.answer, style: AppText.termInList, maxLines: 1, overflow: TextOverflow.ellipsis),
                const SizedBox(height: 2),
                Text(card.prompt ?? '', style: AppText.translation.copyWith(fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
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
        setState(() => _error =
            e.response?.statusCode == 429 ? l.sessionNewExampleExhausted : e.message ?? '');
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
          Text(l.sessionStrugglingTitle(widget.term), style: AppText.sheetButton.copyWith(fontSize: 14)),
          const SizedBox(height: 6),
          Text(l.sessionStrugglingBody, style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody, height: 1.45)),
          const SizedBox(height: 12),
          if (_error != null) ...[
            Text(_error!, style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.destructiveText)),
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
                      Text(l.sessionNewExample, style: AppTextExercise.answerAuxButton.copyWith(color: AppColors.verdictKnown)),
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
  const _CenteredMessage({required this.text, this.icon});
  final String text;
  final IconData? icon;

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
                  Icon(icon, size: 48, color: AppColors.verdictKnown),
                  const SizedBox(height: 12),
                ],
                Text(text, textAlign: TextAlign.center, style: AppText.stepTitle.copyWith(fontSize: 20)),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
