import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/perf_log.dart';
import '../../data/providers.dart';
import 'triage_swipe.dart';
import '../../data/local/cached_image_provider.dart';

/// The triage vertical slice: swipe a collection's new terms → знаю / не знаю /
/// не уверен. Its job is to exercise the contract — self-contained queue,
/// client ULIDs, client-measured latency, on-disk queue, offline flush.
///
/// A3.1 reskin: paper/ink «Слова» design (flip on [PaperCard] + tokens, swipe
/// visuals per §4д, [VerdictButton]s). The behaviour layer is untouched —
/// `revealed`, first-event latency, the durable queue and `client_seq` all work
/// exactly as before; only the UI changed. All copy goes through
/// [AppLocalizations].
class TriageScreen extends ConsumerStatefulWidget {
  const TriageScreen({super.key, required this.collectionId, required this.title});

  final String collectionId;
  final String title;

  @override
  ConsumerState<TriageScreen> createState() => _TriageScreenState();
}

class _TriageScreenState extends ConsumerState<TriageScreen> {
  @override
  void initState() {
    super.initState();
    // Rebuild the deck from the local DB on EVERY entry. The deck excludes terms already triaged
    // (locally marked), which a cached deck would keep showing — so a completed deck would re-show
    // its swiped cards. Riverpod's auto-dispose timing isn't guaranteed on every navigation path
    // back into this screen (observed: some re-entries re-read, one served a stale deck), so
    // invalidating here — before the first build watches it — makes the re-read deterministic.
    // The read is now local (no network), so this works identically offline; nothing is fetched.
    ref.invalidate(triageDeckProvider(widget.collectionId));
    // Stall monitor: which screen a hitch belongs to. This screen is the reference point — it has
    // no TTS and no keyboard, so "smooth here, janky in the trainer" localises a regression fast
    // (that comparison is what found F20-r).
    PerfLog.instance.screen = 'triage';
  }

  @override
  void dispose() {
    PerfLog.instance.screen = 'app';
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final deck = ref.watch(triageDeckProvider(widget.collectionId));
    // Dark status-bar glyphs on the paper background (overrides the dark theme default).
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: deck.when(
            loading: () => const Center(child: CircularProgressIndicator(color: AppColors.ink)),
            error: (e, _) => _CenteredState(
              title: widget.title,
              heading: l.triageLoadError(e.toString()),
            ),
            data: (deck) => deck.cards.isEmpty
                ? _AllTriaged(title: widget.title, remaining: deck.remaining)
                : _Deck(
                    cards: deck.cards,
                    remaining: deck.remaining,
                    collectionId: widget.collectionId,
                    title: widget.title,
                  ),
          ),
        ),
      ),
    );
  }
}

/// The 20-px circular back affordance (кадр 2.2 header).
class _BackChip extends StatelessWidget {
  const _BackChip();

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: InkResponse(
        onTap: () => Navigator.of(context).maybePop(),
        radius: 22,
        child: Container(
          width: AppSpacing.minTap,
          height: AppSpacing.minTap,
          alignment: Alignment.center,
          child: Container(
            width: 20,
            height: 20,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: AppColors.hairline),
            ),
            child: const Icon(LucideIcons.arrowLeft, size: 14, color: AppColors.ink),
          ),
        ),
      ),
    );
  }
}

/// Header: back chip · collection name (Literata) · «N из M» · session segments.
class _TriageHeader extends StatelessWidget {
  const _TriageHeader({required this.title, required this.current, required this.total});

  final String title;
  final int current;
  final int total;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            const _BackChip(),
            const SizedBox(width: AppSpacing.s8),
            Expanded(
              child: Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppText.collectionNameCard.copyWith(fontSize: 14.5),
              ),
            ),
            const SizedBox(width: AppSpacing.s8),
            Text(l.triageCounter(current, total), style: AppTextExercise.sessionHeader),
          ],
        ),
        const SizedBox(height: 10),
        // «Пройдено» = current card position (matches the frame: counter == filled).
        SessionSegments(done: current, total: total),
      ],
    );
  }
}

class _Swiped {
  const _Swiped(this.card, this.verdict);
  final TriageCard card;
  final TriageVerdict verdict;
}

class _Deck extends ConsumerStatefulWidget {
  const _Deck({
    required this.cards,
    required this.remaining,
    required this.collectionId,
    required this.title,
  });
  final List<TriageCard> cards;
  final int remaining; // eligible terms left server-side beyond this page
  final String collectionId;
  final String title;

  @override
  ConsumerState<_Deck> createState() => _DeckState();
}

class _DeckState extends ConsumerState<_Deck>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  int _pos = 0;
  int _known = 0, _unknown = 0, _unsure = 0;
  final List<_Swiped> _history = [];

  /// When the current card was actually painted — the latency clock starts here,
  /// not at the transition animation. Null if we somehow couldn't measure it.
  DateTime? _shownAt;

  /// When the learner first acted on the card — flip OR swipe/button, whichever came first. Latency
  /// is measured to this, not to the verdict, so a flip-then-think delay doesn't inflate it.
  DateTime? _firstEventAt;

  /// Did the learner flip to the back (translation/example) before deciding. A peeked «know» is a
  /// weaker signal — sent to the server as `revealed`.
  bool _revealed = false;

  /// Card-motion controller — drives both the commit fly-off and the
  /// spring-back of an under-threshold swipe. [_leaving] picks which.
  late final AnimationController _anim = AnimationController(vsync: this);
  Offset _drag = Offset.zero;
  Offset _from = Offset.zero, _to = Offset.zero;
  Curve _curve = AppMotion.easeIn;
  bool _leaving = false;
  TriageVerdict? _pending;
  TriageVerdict? _lastDir;

  /// Last laid-out card size — thresholds and fly-off distance derive from it.
  Size _cardSize = Size.zero;

  /// Flush the moment the network returns — the app-lifecycle triggers only fire on
  /// resume/dispose, so a flaky connection recovering while the user sits on this
  /// screen would otherwise leave the queue waiting.
  StreamSubscription<List<ConnectivityResult>>? _connSub;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _connSub = Connectivity().onConnectivityChanged.listen((results) {
      if (results.any((r) => r != ConnectivityResult.none)) {
        ref.read(triageSyncProvider).flush();
      }
    });
    _armLatencyClock();
    _anim
      ..addListener(() {
        setState(() => _drag = Offset.lerp(_from, _to, _curve.transform(_anim.value))!);
      })
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) {
          final v = _pending;
          _pending = null;
          _from = Offset.zero;
          _to = Offset.zero;
          _drag = Offset.zero;
          final wasLeaving = _leaving;
          _leaving = false;
          _anim.reset();
          if (wasLeaving && v != null) {
            _commit(v);
          } else {
            setState(() {});
          }
        }
      });
    // A card queued while offline should go out the moment we land here with a network.
    ref.read(triageSyncProvider).flush();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _connSub?.cancel();
    _anim.dispose();
    ref.read(triageSyncProvider).flush(); // last chance to send on the way out
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(triageSyncProvider).flush(); // network may be back
    }
  }

  /// Reset per-card state and start the latency clock once the new card has rendered.
  void _armLatencyClock() {
    _shownAt = null;
    _firstEventAt = null;
    _revealed = false;
    WidgetsBinding.instance.addPostFrameCallback((_) => _shownAt = DateTime.now());
  }

  /// Stamp the first interaction (flip or swipe/button) — the latency endpoint. Idempotent.
  void _markFirstEvent() => _firstEventAt ??= DateTime.now();

  TriageCard get _card => widget.cards[_pos];

  int? _latencyMs() {
    // Measured from paint to the first event. Missing either → null (never 0), treated neutrally.
    if (_shownAt == null || _firstEventAt == null) return null;
    final ms = _firstEventAt!.difference(_shownAt!).inMilliseconds;
    return ms > 0 ? ms : null;
  }

  /// Apply a verdict for the current card: record it (offline-first) and advance.
  void _commit(TriageVerdict verdict) {
    final card = _card;
    final latency = _latencyMs();

    ref.read(triageSyncProvider).record(
          termId: card.termId,
          verdict: verdict,
          collectionId: widget.collectionId,
          latencyMs: latency,
          revealed: _revealed,
        );

    setState(() {
      _history.add(_Swiped(card, verdict));
      switch (verdict) {
        case TriageVerdict.known:
          _known++;
          AppHaptics.success();
        case TriageVerdict.unknown:
          _unknown++;
          AppHaptics.warning();
        case TriageVerdict.unsure:
          _unsure++;
          AppHaptics.light();
      }
      _drag = Offset.zero;
      _lastDir = null;
      _pos++;
    });
    _armLatencyClock();
  }

  /// Undo the last swipe: drop the still-unsent verdict (or let a re-swipe
  /// override a sent one), roll the tally back, and re-show the card.
  void _undo() {
    if (_history.isEmpty) return;
    AppHaptics.light();
    final last = _history.removeLast();
    ref.read(triageSyncProvider).removePending(last.card.termId);
    setState(() {
      switch (last.verdict) {
        case TriageVerdict.known:
          _known--;
        case TriageVerdict.unknown:
          _unknown--;
        case TriageVerdict.unsure:
          _unsure--;
      }
      _pos--;
      _drag = Offset.zero;
    });
    _armLatencyClock();
  }

  double get _threshold => _cardSize.width * AppMotion.swipeThresholdFraction;

  void _onPanUpdate(DragUpdateDetails d) {
    if (_anim.isAnimating) return;
    _markFirstEvent(); // a drag counts as the first interaction
    setState(() => _drag += d.delta);
    final dir = TriageSwipe.direction(_drag);
    if (dir != _lastDir) {
      _lastDir = dir;
      if (dir != null) AppHaptics.light();
    }
  }

  void _onPanEnd(DragEndDetails d) {
    if (_anim.isAnimating) return;
    final commit = TriageSwipe.shouldCommit(
      drag: _drag,
      threshold: _threshold,
      velocity: d.velocity,
    );
    final dir = TriageSwipe.direction(_drag);
    if (commit && dir != null) {
      _flyOff(dir, from: _drag);
    } else {
      _springBack();
    }
  }

  /// Fire a verdict from a button — animate the card out in that direction.
  void _button(TriageVerdict v) {
    if (_anim.isAnimating) return;
    _markFirstEvent();
    _flyOff(v, from: Offset.zero);
  }

  /// Commit fly-off: card leaves toward the verdict side and fades (§4д).
  void _flyOff(TriageVerdict v, {required Offset from}) {
    final w = _cardSize.width == 0 ? 400.0 : _cardSize.width;
    final h = _cardSize.height == 0 ? 500.0 : _cardSize.height;
    _from = from;
    _pending = v;
    _leaving = true;
    _curve = AppMotion.easeIn;
    _to = switch (v) {
      TriageVerdict.known => Offset(w * 1.4, from.dy),
      TriageVerdict.unknown => Offset(-w * 1.4, from.dy),
      TriageVerdict.unsure => Offset(from.dx, -h * 1.2),
    };
    _anim.duration = AppMotion.verdictLeave;
    _anim.forward(from: 0);
  }

  /// Under-threshold swipe: spring back to centre (§4д, spring(.55)).
  void _springBack() {
    _from = _drag;
    _to = Offset.zero;
    _pending = null;
    _leaving = false;
    _lastDir = null;
    // easeOutBack overshoots slightly then settles — reads as a soft spring.
    _curve = Curves.easeOutBack;
    _anim.duration = AppMotion.swipeReturn;
    _anim.forward(from: 0);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (_pos >= widget.cards.length) {
      return _TriageSummary(
        title: widget.title,
        known: _known,
        unsure: _unsure,
        unknown: _unknown,
        remaining: widget.remaining,
      );
    }

    final total = widget.cards.length;
    final dir = TriageSwipe.direction(_drag);
    final progress = TriageSwipe.progress(_drag, _threshold);
    final leaveOpacity = _leaving ? (1 - _anim.value).clamp(0.0, 1.0) : 1.0;
    final showHint = _pos < 3; // teaching hint on the first cards
    final reduceMotion = MediaQuery.of(context).disableAnimations;

    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH, AppSpacing.s8, AppSpacing.screenH, AppSpacing.s16),
      child: Column(
        children: [
          _TriageHeader(title: widget.title, current: (_pos + 1).clamp(1, total), total: total),
          const SizedBox(height: AppSpacing.s22),
          Expanded(
            child: LayoutBuilder(
              builder: (context, c) {
                _cardSize = Size(c.maxWidth, c.maxHeight);
                final tilt = reduceMotion ? 0.0 : TriageSwipe.tiltRadians(_drag, _threshold);
                return GestureDetector(
                  onPanUpdate: _onPanUpdate,
                  onPanEnd: _onPanEnd,
                  child: Transform.translate(
                    offset: _drag,
                    child: Transform.rotate(
                      angle: tilt,
                      child: Opacity(
                        opacity: leaveOpacity,
                        child: IgnorePointer(
                          ignoring: _anim.isAnimating,
                          child: Stack(
                            children: [
                              Positioned.fill(
                                child: _FlipCard(
                                  key: ValueKey(_card.termId), // fresh flip state per card
                                  card: _card,
                                  showHint: showHint,
                                  onReveal: () {
                                    _markFirstEvent();
                                    setState(() => _revealed = true);
                                  },
                                ),
                              ),
                              if (dir != null)
                                Positioned.fill(
                                  child: _SwipeOverlay(verdict: dir, progress: progress),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: AppSpacing.s16),
          Row(
            children: [
              Expanded(
                child: VerdictButton(
                  kind: VerdictKind.unknown,
                  label: l.triageVerdictUnknown,
                  minHeight: 56,
                  onPressed: () => _button(TriageVerdict.unknown),
                ),
              ),
              const SizedBox(width: AppSpacing.s8),
              Expanded(
                child: VerdictButton(
                  kind: VerdictKind.unsure,
                  label: l.triageVerdictUnsure,
                  minHeight: 56,
                  onPressed: () => _button(TriageVerdict.unsure),
                ),
              ),
              const SizedBox(width: AppSpacing.s8),
              Expanded(
                child: VerdictButton(
                  kind: VerdictKind.known,
                  label: l.triageVerdictKnown,
                  minHeight: 56,
                  onPressed: () => _button(TriageVerdict.known),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.s16),
          _UndoButton(onTap: _history.isEmpty ? null : _undo, label: l.triageUndo),
        ],
      ),
    );
  }
}

/// «Отменить последний» — tertiary text, no fill, disabled when empty (кадр 2.2).
class _UndoButton extends StatelessWidget {
  const _UndoButton({required this.onTap, required this.label});
  final VoidCallback? onTap;
  final String label;

  @override
  Widget build(BuildContext context) {
    final color = onTap == null ? AppColors.track : AppColors.tertiary;
    return Semantics(
      button: true,
      enabled: onTap != null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.field),
        child: Container(
          height: AppSpacing.minTap,
          alignment: Alignment.center,
          child: Text(
            label,
            style: AppText.transcription.copyWith(color: color, fontSize: 12.5),
          ),
        ),
      ),
    );
  }
}

/// The triage card that flips. FRONT shows ONLY the target-language term (recall, not recognition —
/// a translation on the face would let "know" mean "recognised the hint", the false positives that
/// later fail verification). Tap flips to the BACK: image (if any) + translation + example. The
/// first flip calls [onReveal] so the deck can mark the verdict `revealed`.
class _FlipCard extends StatefulWidget {
  const _FlipCard({super.key, required this.card, required this.onReveal, required this.showHint});
  final TriageCard card;
  final VoidCallback onReveal;
  final bool showHint;

  @override
  State<_FlipCard> createState() => _FlipCardState();
}

class _FlipCardState extends State<_FlipCard> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: AppMotion.flip);
  bool _back = false;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  void _flip() {
    AppHaptics.light();
    final goingToBack = !_back;
    setState(() => _back = goingToBack);
    if (MediaQuery.of(context).disableAnimations) {
      _c.value = goingToBack ? 1 : 0; // reduced motion: no rotateY, just swap
    } else {
      goingToBack ? _c.forward() : _c.reverse();
    }
    if (goingToBack) widget.onReveal();
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _flip,
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          final t = AppMotion.easeOut.transform(_c.value); // 0 = front, 1 = back
          final showBack = t > 0.5;
          // Midpoint darkening (§4д «на середине притемнение»): peaks at t=0.5.
          final darken = (1 - (2 * t - 1).abs()) * 0.22;
          final angle = t * 3.1415926;
          final face = showBack
              // Counter-rotate the back so its content isn't mirrored.
              ? Transform(
                  alignment: Alignment.center,
                  transform: Matrix4.identity()..rotateY(3.1415926),
                  child: _backFace(context, darken),
                )
              : _frontFace(context, darken);
          return Transform(
            alignment: Alignment.center,
            transform: Matrix4.identity()
              ..setEntry(3, 2, 0.0012) // perspective
              ..rotateY(angle),
            child: face,
          );
        },
      ),
    );
  }

  /// Wraps a face in the flip-card [PaperCard] with the midpoint darkening overlay.
  Widget _paper({required Widget child, required double darken, EdgeInsets? padding}) {
    return PaperCard(
      radius: AppRadii.card,
      clipContent: true,
      padding: padding ?? const EdgeInsets.all(AppSpacing.s26),
      child: Stack(
        children: [
          Positioned.fill(child: child),
          if (darken > 0)
            Positioned.fill(
              child: IgnorePointer(child: ColoredBox(color: AppColors.ink.withValues(alpha: darken))),
            ),
        ],
      ),
    );
  }

  Widget _frontFace(BuildContext context, double darken) {
    final l = AppLocalizations.of(context);
    return _paper(
      darken: darken,
      child: Stack(
        children: [
          Center(
            child: Text(widget.card.text, textAlign: TextAlign.center, style: AppText.termFlip),
          ),
          if (widget.showHint)
            Positioned(
              left: 0,
              right: 0,
              bottom: 20,
              child: Text(
                l.triageSwipeHint,
                textAlign: TextAlign.center,
                style: AppText.transcription.copyWith(color: AppColors.tertiary, fontSize: 11.5),
              ),
            ),
        ],
      ),
    );
  }

  Widget _backFace(BuildContext context, double darken) {
    final card = widget.card;
    final hasImage = card.imageUrl != null && card.imageUrl!.isNotEmpty;
    return _paper(
      darken: darken,
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (hasImage)
            Image(
              image: CachedNetworkImage(card.imageUrl!),
              height: 212,
              width: double.infinity,
              fit: BoxFit.cover,
              loadingBuilder: (_, child, p) => p == null ? child : const SizedBox(height: 212),
              errorBuilder: (_, _, _) => const SizedBox.shrink(),
            ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(card.text, style: AppTextExercise.feedbackTerm),
                  const SizedBox(height: 7),
                  Row(
                    children: [
                      if (card.transcription != null && card.transcription!.isNotEmpty) ...[
                        Text('/${card.transcription}/', style: AppText.transcription),
                        const SizedBox(width: 9),
                      ],
                      _TypeBadge(type: card.type),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    card.translation,
                    style: AppText.translation.copyWith(fontSize: 16),
                  ),
                  if (card.example != null && card.example!.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    const Divider(color: AppColors.hairline, height: 1, thickness: 1),
                    const SizedBox(height: 12),
                    Text(card.example!, style: AppText.usageExample.copyWith(fontSize: 15.5)),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Type badge (§2 «Бейдж типа»): caps, hairline outline, copy from ARB.
class _TypeBadge extends StatelessWidget {
  const _TypeBadge({required this.type});
  final String type;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // Forward-compat: unknown types fall back to phrase-like (as in Word.isPhrase).
    final label = switch (type) {
      'word' => l.triageTermTypeWord,
      'phrase' => l.triageTermTypePhrase,
      'idiom' => l.triageTermTypeIdiom,
      'phrasal_verb' => l.triageTermTypePhrasalVerb,
      _ => l.triageTermTypePhrase,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.thumb),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Text(label.toUpperCase(), style: AppText.badge),
    );
  }
}

/// Swipe visual (§4д): a verdict tint from the staying side (≤16 %) plus a sign
/// ring whose opacity ∝ displacement. Lives inside the card's rounded clip.
class _SwipeOverlay extends StatelessWidget {
  const _SwipeOverlay({required this.verdict, required this.progress});
  final TriageVerdict verdict;
  final double progress;

  @override
  Widget build(BuildContext context) {
    final color = switch (verdict) {
      TriageVerdict.known => AppColors.verdictKnown,
      TriageVerdict.unknown => AppColors.verdictUnknown,
      TriageVerdict.unsure => AppColors.verdictUnsure,
    };
    final sign = switch (verdict) {
      TriageVerdict.known => LucideIcons.check,
      TriageVerdict.unknown => LucideIcons.x,
      TriageVerdict.unsure => LucideIcons.minus,
    };
    final side = TriageSwipe.signSide(verdict);

    final (begin, end) = switch (side) {
      VerdictSignSide.left => (Alignment.centerLeft, Alignment.centerRight),
      VerdictSignSide.right => (Alignment.centerRight, Alignment.centerLeft),
      VerdictSignSide.bottom => (Alignment.bottomCenter, Alignment.topCenter),
    };
    final align = switch (side) {
      VerdictSignSide.left => const Alignment(-0.82, -0.82),
      VerdictSignSide.right => const Alignment(0.82, -0.82),
      VerdictSignSide.bottom => const Alignment(0, 0.82),
    };

    return IgnorePointer(
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.card),
        child: Stack(
          children: [
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: begin,
                    end: end,
                    colors: [
                      color.withValues(alpha: progress * AppMotion.swipeBgTintMax),
                      color.withValues(alpha: 0),
                    ],
                  ),
                ),
              ),
            ),
            Align(
              alignment: align,
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.s22),
                child: Opacity(
                  opacity: progress.clamp(0.0, 1.0),
                  child: Container(
                    width: 64,
                    height: 64,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: color.withValues(alpha: 0.55), width: 2.5),
                    ),
                    child: Icon(sign, size: 34, color: color),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Empty state: nothing (or nothing yet) to triage.
class _AllTriaged extends StatelessWidget {
  const _AllTriaged({required this.title, required this.remaining});
  final String title;
  final int remaining;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // "Всё разобрано" only when there is genuinely nothing left server-side. If the page came
    // back empty but the server still has eligible terms (e.g. all of this page is locally
    // pending), say so honestly rather than claiming the set is done.
    final done = remaining == 0;
    return _CenteredState(
      title: title,
      heading: done ? l.triageAllDoneTitle : l.triageMoreLaterTitle,
      body: done ? l.triageAllDoneBody : l.triageMoreLaterBody(remaining),
    );
  }
}

/// Shared centred layout for the loading-error / empty / summary states: a back
/// chip pinned top-left, the message centred, a «Готово» primary button.
class _CenteredState extends StatelessWidget {
  const _CenteredState({required this.title, required this.heading, this.body, this.extra});
  final String title;
  final String heading;
  final String? body;
  final Widget? extra;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.screenH, AppSpacing.s8, AppSpacing.screenH, AppSpacing.s16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Align(alignment: Alignment.centerLeft, child: const _BackChip()),
          Expanded(
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(heading, textAlign: TextAlign.center, style: AppText.stepTitle),
                  if (body != null) ...[
                    const SizedBox(height: AppSpacing.s12),
                    Text(body!,
                        textAlign: TextAlign.center,
                        style: AppText.translation.copyWith(color: AppColors.secondary)),
                  ],
                  if (extra != null) ...[
                    const SizedBox(height: AppSpacing.s22),
                    extra!,
                  ],
                  const SizedBox(height: AppSpacing.s26),
                  PrimaryButton(
                    label: l.triageDone,
                    onPressed: () => Navigator.of(context).maybePop(),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// End-of-batch summary (§2б «Итог сессии»): monochrome — Inter numbers on ink,
/// tertiary caps labels, hairline separators. Verdict colours don't appear here.
class _TriageSummary extends StatelessWidget {
  const _TriageSummary({
    required this.title,
    required this.known,
    required this.unsure,
    required this.unknown,
    required this.remaining,
  });
  final String title;
  final int known, unsure, unknown;
  final int remaining; // eligible terms still on the server beyond the page just finished

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return _CenteredState(
      title: title,
      heading: remaining > 0 ? l.triageSummaryBatchTitle : l.triageSummaryDoneTitle,
      body: remaining > 0 ? l.triageRemainingAfterSync(remaining) : null,
      extra: IntrinsicHeight(
        child: Row(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            _Tally(label: l.triageTallyKnown, value: known),
            const _TallyDivider(),
            _Tally(label: l.triageTallyLearning, value: unknown),
            const _TallyDivider(),
            _Tally(label: l.triageTallyUnsure, value: unsure),
          ],
        ),
      ),
    );
  }
}

class _TallyDivider extends StatelessWidget {
  const _TallyDivider();
  @override
  Widget build(BuildContext context) =>
      const VerticalDivider(color: AppColors.hairline, width: 1, thickness: 1, indent: 4, endIndent: 4);
}

class _Tally extends StatelessWidget {
  const _Tally({required this.label, required this.value});
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s16),
      child: Column(
        children: [
          Text('$value', style: AppTextExercise.summaryNumber),
          const SizedBox(height: AppSpacing.s4),
          Text(label.toUpperCase(), style: AppTextExercise.summaryLabel),
        ],
      ),
    );
  }
}
