import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/design.dart';
import '../../core/glass.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// The triage vertical slice: swipe a collection's new terms → знаю / не знаю /
/// не уверен. Its job is to exercise the contract — self-contained queue,
/// client ULIDs, client-measured latency, on-disk queue, offline flush.
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
    // Re-fetch the queue on EVERY entry. The server excludes terms already triaged (uploaded),
    // which a cached deck would keep showing — so a completed deck would re-show its swiped
    // cards. The deck provider only re-runs on Riverpod's auto-dispose timing, which is not
    // guaranteed on every navigation path back into this screen (observed: some re-entries
    // re-fetched, one served a stale deck). Invalidating here, before the first build watches
    // it, makes the refetch deterministic regardless of disposal timing. Offline-only swipes
    // are still excluded locally by triageDeckProvider (pendingTermIds); a full re-fetch while
    // offline surfaces the load error, which is honest — nothing is lost, the queue is intact.
    ref.invalidate(triageDeckProvider(widget.collectionId));
  }

  @override
  Widget build(BuildContext context) {
    final deck = ref.watch(triageDeckProvider(widget.collectionId));
    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(title: Text('Разбор · ${widget.title}')),
      body: AmbientBackground(
        child: SafeArea(
          child: deck.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(
              child: Text('Не удалось загрузить: $e',
                  style: const TextStyle(color: AppColors.textSecondary)),
            ),
            data: (deck) => deck.cards.isEmpty
                ? _AllTriaged(remaining: deck.remaining)
                : _Deck(cards: deck.cards, remaining: deck.remaining, collectionId: widget.collectionId),
          ),
        ),
      ),
    );
  }
}

class _AllTriaged extends StatelessWidget {
  const _AllTriaged({required this.remaining});

  final int remaining;

  @override
  Widget build(BuildContext context) {
    // "Всё разобрано" only when there is genuinely nothing left server-side. If the page came
    // back empty but the server still has eligible terms (e.g. all of this page is locally
    // pending), say so honestly rather than claiming the set is done.
    final done = remaining == 0;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(done ? Icons.done_all_rounded : Icons.cloud_sync_rounded,
                color: AppColors.know, size: 48),
            const SizedBox(height: AppSpacing.md),
            Text(done ? 'Всё разобрано' : 'На сейчас всё',
                style: const TextStyle(color: AppColors.textPrimary, fontSize: 20, fontWeight: FontWeight.w700)),
            const SizedBox(height: AppSpacing.sm),
            Text(
              done
                  ? 'В этом наборе не осталось новых слов для разбора.'
                  : 'Ещё $remaining после синхронизации — зайдите снова, когда будет сеть.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 14),
            ),
            const SizedBox(height: AppSpacing.lg),
            GlassSecondaryButton(label: 'Готово', onTap: () => Navigator.of(context).maybePop()),
          ],
        ),
      ),
    );
  }
}

class _Swiped {
  const _Swiped(this.card, this.verdict);
  final TriageCard card;
  final TriageVerdict verdict;
}

class _Deck extends ConsumerStatefulWidget {
  const _Deck({required this.cards, required this.remaining, required this.collectionId});
  final List<TriageCard> cards;
  final int remaining; // eligible terms left server-side beyond this page
  final String collectionId;

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

  late final AnimationController _anim =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 220));
  Offset _drag = Offset.zero;
  Offset _from = Offset.zero, _to = Offset.zero;
  TriageVerdict? _pending;
  TriageVerdict? _lastHint;
  static const _threshold = 90.0;

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
        setState(() => _drag = Offset.lerp(_from, _to, Curves.easeOut.transform(_anim.value))!);
      })
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) {
          final v = _pending;
          _pending = null;
          _from = Offset.zero;
          _to = Offset.zero;
          _drag = Offset.zero;
          _anim.reset();
          if (v != null) {
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

  /// Start the latency clock once the new card has actually rendered.
  void _armLatencyClock() {
    _shownAt = null;
    WidgetsBinding.instance.addPostFrameCallback((_) => _shownAt = DateTime.now());
  }

  TriageCard get _card => widget.cards[_pos];

  int? _latencyMs() {
    if (_shownAt == null) return null; // couldn't measure → null, never 0
    final ms = DateTime.now().difference(_shownAt!).inMilliseconds;
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
        );

    setState(() {
      _history.add(_Swiped(card, verdict));
      switch (verdict) {
        case TriageVerdict.known:
          _known++;
          AppFeedback.success();
        case TriageVerdict.unknown:
          _unknown++;
          AppFeedback.warn();
        case TriageVerdict.unsure:
          _unsure++;
          AppFeedback.select();
      }
      _drag = Offset.zero;
      _lastHint = null;
      _pos++;
    });
    _armLatencyClock();
  }

  /// Undo the last swipe: drop the still-unsent verdict (or let a re-swipe
  /// override a sent one), roll the tally back, and re-show the card.
  void _undo() {
    if (_history.isEmpty) return;
    AppFeedback.select();
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

  TriageVerdict? _verdictFor(Offset d) {
    if (d.dy < -_threshold && d.dy.abs() > d.dx.abs()) return TriageVerdict.unsure; // up
    if (d.dx > _threshold) return TriageVerdict.known; // right
    if (d.dx < -_threshold) return TriageVerdict.unknown; // left
    return null;
  }

  void _onPanUpdate(DragUpdateDetails d) {
    if (_anim.isAnimating) return;
    setState(() => _drag += d.delta);
    final v = _verdictFor(_drag);
    if (v != _lastHint) {
      _lastHint = v;
      if (v != null) AppFeedback.select();
    }
  }

  void _onPanEnd(DragEndDetails _) {
    final v = _verdictFor(_drag);
    _from = _drag;
    _pending = v;
    _to = switch (v) {
      TriageVerdict.known => Offset(1400, _drag.dy),
      TriageVerdict.unknown => Offset(-1400, _drag.dy),
      TriageVerdict.unsure => Offset(_drag.dx, -1400),
      null => Offset.zero,
    };
    if (v == null) _lastHint = null;
    _anim.forward(from: 0);
  }

  /// Fire a verdict from a button — animate the card out in that direction.
  void _button(TriageVerdict v) {
    if (_anim.isAnimating) return;
    _from = Offset.zero;
    _pending = v;
    _to = switch (v) {
      TriageVerdict.known => const Offset(1400, 0),
      TriageVerdict.unknown => const Offset(-1400, 0),
      TriageVerdict.unsure => const Offset(0, -1400),
    };
    _anim.forward(from: 0);
  }

  @override
  Widget build(BuildContext context) {
    if (_pos >= widget.cards.length) {
      return _TriageSummary(
          known: _known, unsure: _unsure, unknown: _unknown, remaining: widget.remaining);
    }

    final total = widget.cards.length;
    final hint = _verdictFor(_drag);

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, AppSpacing.lg),
      child: Column(
        children: [
          _Counter(position: _pos, total: total),
          const SizedBox(height: AppSpacing.md),
          Expanded(
            child: GestureDetector(
              onPanUpdate: _onPanUpdate,
              onPanEnd: _onPanEnd,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Transform.translate(
                    offset: _drag,
                    child: Transform.rotate(
                      angle: _drag.dx / 1600,
                      child: _TriageCardView(card: _card),
                    ),
                  ),
                  if (_drag.distance > 24) _SwipeHint(verdict: hint),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          _Buttons(onVerdict: _button),
          const SizedBox(height: 10),
          TextButton.icon(
            onPressed: _history.isEmpty ? null : _undo,
            icon: const Icon(Icons.undo_rounded, size: 18),
            label: const Text('Отменить последний'),
            style: TextButton.styleFrom(foregroundColor: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

class _Counter extends StatelessWidget {
  const _Counter({required this.position, required this.total});
  final int position, total;

  @override
  Widget build(BuildContext context) {
    // `position` is how many are already swiped, so the card on screen is position + 1.
    final current = (position + 1).clamp(1, total);
    return Column(
      children: [
        Align(
          alignment: Alignment.centerRight,
          child: Text('$current из $total',
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 13, fontWeight: FontWeight.w600)),
        ),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: LinearProgressIndicator(
            value: total == 0 ? 0 : position / total,
            minHeight: 6,
            backgroundColor: AppColors.surface,
            valueColor: const AlwaysStoppedAnimation(AppColors.accent),
          ),
        ),
      ],
    );
  }
}

class _TriageCardView extends StatelessWidget {
  const _TriageCardView({required this.card});
  final TriageCard card;

  @override
  Widget build(BuildContext context) {
    return GlassCard(
      padding: const EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(card.text,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textPrimary, fontSize: 30, fontWeight: FontWeight.w800)),
          const SizedBox(height: AppSpacing.sm),
          Text(card.translation,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.textSecondary, fontSize: 18)),
          if (card.isPhrase && card.example != null) ...[
            const SizedBox(height: AppSpacing.lg),
            Text(card.example!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.textMuted, fontSize: 15, fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}

class _SwipeHint extends StatelessWidget {
  const _SwipeHint({required this.verdict});
  final TriageVerdict? verdict;

  @override
  Widget build(BuildContext context) {
    if (verdict == null) return const SizedBox.shrink();
    final (label, color, icon) = switch (verdict!) {
      TriageVerdict.known => ('Знаю', AppColors.know, Icons.check_rounded),
      TriageVerdict.unknown => ('Не знаю', AppColors.dontKnow, Icons.close_rounded),
      TriageVerdict.unsure => ('Не уверен', AppColors.review, Icons.help_outline_rounded),
    };
    return IgnorePointer(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.9),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: Colors.white, size: 20),
            const SizedBox(width: 6),
            Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class _Buttons extends StatelessWidget {
  const _Buttons({required this.onVerdict});
  final void Function(TriageVerdict) onVerdict;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _VerdictButton(
                label: 'Не знаю', color: AppColors.dontKnow, icon: Icons.close_rounded,
                onTap: () => onVerdict(TriageVerdict.unknown),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: _VerdictButton(
                label: 'Знаю', color: AppColors.know, icon: Icons.check_rounded,
                onTap: () => onVerdict(TriageVerdict.known),
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        _VerdictButton(
          label: 'Не уверен', color: AppColors.review, icon: Icons.help_outline_rounded,
          onTap: () => onVerdict(TriageVerdict.unsure),
        ),
      ],
    );
  }
}

class _VerdictButton extends StatelessWidget {
  const _VerdictButton({required this.label, required this.color, required this.icon, required this.onTap});
  final String label;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(AppRadii.lg),
          border: Border.all(color: color.withValues(alpha: 0.5)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 8),
            Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 15)),
          ],
        ),
      ),
    );
  }
}

class _TriageSummary extends ConsumerWidget {
  const _TriageSummary(
      {required this.known, required this.unsure, required this.unknown, required this.remaining});
  final int known, unsure, unknown;
  final int remaining; // eligible terms still on the server beyond the page just finished

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(remaining > 0 ? 'Пачка разобрана' : 'Разбор завершён',
                style: const TextStyle(color: AppColors.textPrimary, fontSize: 22, fontWeight: FontWeight.w800)),
            const SizedBox(height: AppSpacing.lg),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _Tally(label: 'Знакомо', value: known, color: AppColors.know),
                _Tally(label: 'Учим', value: unknown, color: AppColors.dontKnow),
                _Tally(label: 'Не уверены', value: unsure, color: AppColors.review),
              ],
            ),
            // Honest remainder: only claim the set is done when the server has nothing left.
            if (remaining > 0) ...[
              const SizedBox(height: AppSpacing.lg),
              Text('Ещё $remaining после синхронизации',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 14)),
            ],
            const SizedBox(height: AppSpacing.xl),
            GlassSecondaryButton(label: 'Готово', onTap: () => Navigator.of(context).maybePop()),
          ],
        ),
      ),
    );
  }
}

class _Tally extends StatelessWidget {
  const _Tally({required this.label, required this.value, required this.color});
  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      child: Column(
        children: [
          Text('$value', style: TextStyle(color: color, fontSize: 30, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 13)),
        ],
      ),
    );
  }
}
