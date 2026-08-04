import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/design.dart';
import '../../core/glass.dart';
import '../../core/pronouncer.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// One training session — a specific collection's words, or the global due queue.
class SessionScreen extends ConsumerWidget {
  const SessionScreen({super.key, required this.title, this.collectionId, this.shuffle = false});

  final String title;
  final String? collectionId;
  final bool shuffle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final args = (collectionId: collectionId, shuffle: shuffle);
    final cards = ref.watch(sessionCardsProvider(args));

    final loaded = cards.value ?? const <ReviewCard>[];
    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (loaded.isNotEmpty)
            IconButton(
              tooltip: 'Список слов',
              icon: const Icon(Icons.format_list_bulleted_rounded),
              onPressed: () {
                AppFeedback.tap();
                _showWordList(context, loaded);
              },
            ),
        ],
      ),
      body: AmbientBackground(
        child: SafeArea(
          child: cards.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('Ошибка: $e', style: const TextStyle(color: AppColors.textSecondary))),
            data: (list) => list.isEmpty ? const _EmptySession() : _Deck(cards: list, args: args),
          ),
        ),
      ),
    );
  }

  /// A quick overview of everything the session will drill, so the user can see the set
  /// before/while studying.
  void _showWordList(BuildContext context, List<ReviewCard> cards) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => Container(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.78),
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadii.lg)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 10),
            Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(2))),
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.md, 12, AppSpacing.md, 8),
              child: Row(
                children: [
                  const Icon(Icons.format_list_bulleted_rounded, color: AppColors.primary, size: 20),
                  const SizedBox(width: 8),
                  Text('${cards.length} ${cards.length == 1 ? 'слово' : 'слов'} в занятии',
                      style: const TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w800)),
                ],
              ),
            ),
            Flexible(
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(AppSpacing.md, 0, AppSpacing.md, AppSpacing.lg),
                itemCount: cards.length,
                separatorBuilder: (_, _) => Divider(height: 1, color: Colors.white.withValues(alpha: 0.06)),
                itemBuilder: (_, i) => _WordListRow(word: cards[i].word),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WordListRow extends StatelessWidget {
  const _WordListRow({required this.word});
  final Word word;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    Flexible(
                      child: Text(word.term,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: AppColors.textPrimary, fontSize: 16, fontWeight: FontWeight.w700)),
                    ),
                    if (word.transcription != null && word.transcription!.isNotEmpty) ...[
                      const SizedBox(width: 8),
                      Text('/${word.transcription}/', style: const TextStyle(color: AppColors.textMuted, fontSize: 13)),
                    ],
                  ],
                ),
                const SizedBox(height: 2),
                Text(word.translation, style: const TextStyle(color: AppColors.primary, fontSize: 14)),
              ],
            ),
          ),
          if (word.isPhrase) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(AppRadii.sm)),
              child: const Text('фраза', style: TextStyle(color: AppColors.textSecondary, fontSize: 11, fontWeight: FontWeight.w700)),
            ),
          ],
        ],
      ),
    );
  }
}

class _Deck extends ConsumerStatefulWidget {
  const _Deck({required this.cards, required this.args});
  final List<ReviewCard> cards;
  final SessionArgs args;

  @override
  ConsumerState<_Deck> createState() => _DeckState();
}

class _DeckState extends ConsumerState<_Deck> with SingleTickerProviderStateMixin {
  final _pronouncer = Pronouncer();
  int _pos = 0;
  bool _revealed = false;
  int _know = 0, _review = 0, _dontKnow = 0;
  bool _finished = false;
  DateTime _shownAt = DateTime.now(); // when the current card first appeared (for latency)

  late final AnimationController _anim =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 240));
  Offset _drag = Offset.zero;
  Offset _from = Offset.zero, _to = Offset.zero;
  Rating? _pending;
  Rating? _lastHint;
  static const _threshold = 90.0;

  @override
  void initState() {
    super.initState();
    _anim
      ..addListener(() {
        setState(() => _drag = Offset.lerp(_from, _to, Curves.easeOut.transform(_anim.value))!);
      })
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) {
          final r = _pending;
          _pending = null;
          _from = Offset.zero;
          _to = Offset.zero;
          _drag = Offset.zero;
          _anim.reset();
          if (r != null) {
            _answer(r);
          } else {
            setState(() {});
          }
        }
      });
  }

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  Word get _word => widget.cards[_pos].word;

  Future<void> _speak() async {
    AppFeedback.tap();
    final target = ref.read(authControllerProvider).value?.profile?.targetLanguage ?? 'en';
    await _pronouncer.speak(_word, targetLang: target);
  }

  void _feedbackFor(Rating r) => switch (r) {
        Rating.good || Rating.easy => AppFeedback.success(),
        Rating.hard => AppFeedback.select(),
        Rating.again => AppFeedback.warn(),
      };

  void _answer(Rating rating) {
    _feedbackFor(rating);
    final wordId = _word.id;
    final latencyMs = DateTime.now().difference(_shownAt).inMilliseconds;
    final isLast = _pos + 1 >= widget.cards.length;

    setState(() {
      switch (rating) {
        case Rating.good || Rating.easy:
          _know++;
        case Rating.hard:
          _review++;
        case Rating.again:
          _dontKnow++;
      }
      _drag = Offset.zero;
      _from = Offset.zero;
      _to = Offset.zero;
      _lastHint = null;
      if (isLast) {
        _finished = true;
      } else {
        _pos++;
        _revealed = false;
        _shownAt = DateTime.now();
      }
    });

    // Offline-first: record locally (survives no network), flush as a batch.
    ref.read(reviewSyncProvider).record(rating, wordId, latencyMs: latencyMs);
  }

  // Two-swipe model: right = Знаю (good), left = Не знаю (again). The finer grades
  // (Трудно / Легко) are button-only, so swiping stays fast and unambiguous.
  Rating? _ratingFor(Offset d) {
    if (d.dx > _threshold) return Rating.good; // right = знаю
    if (d.dx < -_threshold) return Rating.again; // left = не знаю
    return null;
  }

  void _onPanUpdate(DragUpdateDetails d) {
    // Swiping works on both sides of the card: a confident learner can answer the front
    // (Знаю / Не знаю) without flipping it first. Only the horizontal axis carries meaning,
    // so damp the vertical so the card slides sideways instead of drifting onto the button.
    if (_anim.isAnimating) return;
    setState(() => _drag += Offset(d.delta.dx, d.delta.dy * 0.2));
    // A subtle tick each time the drag crosses into a new answer zone.
    final r = _ratingFor(_drag);
    if (r != _lastHint) {
      _lastHint = r;
      if (r != null) AppFeedback.select();
    }
  }

  void _onPanEnd(DragEndDetails _) {
    final r = _ratingFor(_drag);
    _from = _drag;
    _pending = r;
    if (r == Rating.good) {
      _to = Offset(1200, _drag.dy);
    } else if (r == Rating.again) {
      _to = Offset(-1200, _drag.dy);
    } else {
      _to = Offset.zero;
      _lastHint = null;
    }
    _anim.forward(from: 0);
  }

  @override
  Widget build(BuildContext context) {
    if (_finished) {
      return _Summary(know: _know, review: _review, dontKnow: _dontKnow);
    }

    final total = widget.cards.length;
    final hint = _ratingFor(_drag);

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, AppSpacing.lg),
      child: Column(
        children: [
          _Monitor(position: _pos, total: total, know: _know, review: _review, dontKnow: _dontKnow),
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
                      child: _Flashcard(
                        word: _word,
                        revealed: _revealed,
                        onTap: () {
                          AppFeedback.select();
                          setState(() => _revealed = !_revealed);
                        },
                        onSpeak: _speak,
                      ),
                    ),
                  ),
                  if (_drag.distance > 24) _SwipeHint(rating: hint),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          const _SwipeLegend(),
          const SizedBox(height: 10),
          if (_revealed)
            _Answers(onAnswer: _answer)
          else
            GlassButton(
              label: 'Показать перевод',
              icon: Icons.visibility_outlined,
              onTap: () {
                AppFeedback.select();
                setState(() => _revealed = true);
              },
            ),
        ],
      ),
    );
  }
}

class _SwipeHint extends StatelessWidget {
  const _SwipeHint({required this.rating});
  final Rating? rating;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (rating) {
      Rating.good => ('Знаю', AppColors.know),
      Rating.again => ('Не знаю', AppColors.dontKnow),
      _ => ('', AppColors.textMuted),
    };
    if (label.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 11),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        boxShadow: AppShadows.glow(color),
      ),
      child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
    ).animate().scale(begin: const Offset(0.8, 0.8), end: const Offset(1, 1), duration: 140.ms, curve: Curves.easeOutBack);
  }
}

/// A quiet one-line legend teaching the swipe directions, mirroring the button sides.
class _SwipeLegend extends StatelessWidget {
  const _SwipeLegend();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        _hint(Icons.west_rounded, 'Не знаю', AppColors.dontKnow, iconFirst: true),
        _hint(Icons.east_rounded, 'Знаю', AppColors.know, iconFirst: false),
      ],
    );
  }

  Widget _hint(IconData arrow, String label, Color color, {required bool iconFirst}) {
    final tint = color.withValues(alpha: 0.7);
    final text = Text(label, style: TextStyle(color: tint, fontSize: 12, fontWeight: FontWeight.w600));
    final icon = Icon(arrow, color: tint, size: 15);
    return Row(
      children: iconFirst ? [icon, const SizedBox(width: 5), text] : [text, const SizedBox(width: 5), icon],
    );
  }
}

class _Monitor extends StatelessWidget {
  const _Monitor({required this.position, required this.total, required this.know, required this.review, required this.dontKnow});
  final int position, total, know, review, dontKnow;

  @override
  Widget build(BuildContext context) {
    final progress = total == 0 ? 0.0 : position / total;
    return Column(
      children: [
        Row(
          children: [
            Text('${position + 1} / $total',
                style: const TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w700, fontSize: 14)),
            const Spacer(),
            _tally(AppColors.know, know),
            const SizedBox(width: 10),
            _tally(AppColors.review, review),
            const SizedBox(width: 10),
            _tally(AppColors.dontKnow, dontKnow),
          ],
        ),
        const SizedBox(height: 10),
        ClipRRect(
          borderRadius: BorderRadius.circular(AppRadii.pill),
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: progress),
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOut,
            builder: (_, v, _) => LinearProgressIndicator(
              value: v,
              minHeight: 8,
              backgroundColor: Colors.white.withValues(alpha: 0.08),
              valueColor: const AlwaysStoppedAnimation(AppColors.primary),
            ),
          ),
        ),
      ],
    );
  }

  Widget _tally(Color c, int n) => Row(children: [
        Container(width: 9, height: 9, decoration: BoxDecoration(color: c, shape: BoxShape.circle)),
        const SizedBox(width: 5),
        Text('$n', style: TextStyle(color: c, fontWeight: FontWeight.w700, fontSize: 14)),
      ]);
}

class _Flashcard extends StatelessWidget {
  const _Flashcard({required this.word, required this.revealed, required this.onTap, required this.onSpeak});
  final Word word;
  final bool revealed;
  final VoidCallback onTap, onSpeak;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: double.infinity,
        child: GlassCard(
          padding: EdgeInsets.zero,
          radius: 28,
          blur: 26,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(height: 6, decoration: const BoxDecoration(gradient: AppGradients.brand)),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (word.isPhrase)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(AppRadii.sm),
                        ),
                        child: const Text('фраза',
                            style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w700, fontSize: 12)),
                      ),
                    const SizedBox(height: AppSpacing.md),
                    Text(word.term,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w800, height: 1.1)),
                    if (word.transcription != null) ...[
                      const SizedBox(height: 8),
                      Text('/${word.transcription}/',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(color: AppColors.textMuted)),
                    ],
                    const SizedBox(height: AppSpacing.md),
                    _SpeakButton(onTap: onSpeak),
                    const SizedBox(height: AppSpacing.lg),
                    AnimatedCrossFade(
                      duration: const Duration(milliseconds: 220),
                      crossFadeState: revealed ? CrossFadeState.showSecond : CrossFadeState.showFirst,
                      firstChild: Text('Нажми, чтобы увидеть перевод',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
                      secondChild: Column(children: [
                        Divider(height: 1, color: Colors.white.withValues(alpha: 0.10)),
                        const SizedBox(height: AppSpacing.md),
                        ShaderMask(
                          shaderCallback: (r) => AppGradients.brand.createShader(r),
                          child: Text(word.translation,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                    fontWeight: FontWeight.w800, color: Colors.white,
                                  )),
                        ),
                        if (word.example != null) ...[
                          const SizedBox(height: 12),
                          Text('“${word.example}”',
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    fontStyle: FontStyle.italic, color: AppColors.textSecondary, height: 1.4,
                                  )),
                        ],
                      ]),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ).animate(key: ValueKey(word.id)).fadeIn(duration: 220.ms).scale(begin: const Offset(0.96, 0.96), end: const Offset(1, 1), curve: Curves.easeOutBack);
  }
}

class _SpeakButton extends StatelessWidget {
  const _SpeakButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      feedback: false,
      onTap: onTap,
      child: Container(
        width: 54,
        height: 54,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: Colors.white.withValues(alpha: 0.08),
          border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
        ),
        child: const Icon(Icons.volume_up_rounded, color: AppColors.primary, size: 26),
      ),
    );
  }
}

class _Answers extends StatelessWidget {
  const _Answers({required this.onAnswer});
  final ValueChanged<Rating> onAnswer;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Primary — mirrors the two swipes.
        Row(children: [
          _primary('Не знаю', Icons.close_rounded, AppColors.dontKnow, Rating.again),
          const SizedBox(width: 12),
          _primary('Знаю', Icons.check_rounded, AppColors.know, Rating.good),
        ]),
        const SizedBox(height: 10),
        // Nuance — optional fine-tuning, visually secondary.
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            _nuance('Трудно', Icons.trending_down_rounded, AppColors.review, Rating.hard),
            const SizedBox(width: 10),
            _nuance('Легко', Icons.bolt_rounded, AppColors.accent, Rating.easy),
          ],
        ),
      ],
    ).animate().fadeIn(duration: 180.ms).slideY(begin: 0.15, end: 0);
  }

  Widget _primary(String label, IconData icon, Color color, Rating rating) {
    return Expanded(
      child: SpringTap(
        feedback: false, // _answer plays a rating-specific sound/haptic
        onTap: () => onAnswer(rating),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 18),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.20),
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: color.withValues(alpha: 0.55), width: 1),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: color, size: 22),
              const SizedBox(width: 8),
              Text(label, style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.w800)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _nuance(String label, IconData icon, Color color, Rating rating) {
    return SpringTap(
      feedback: false,
      onTap: () => onAnswer(rating),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.05),
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(color: color.withValues(alpha: 0.35)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 17),
            const SizedBox(width: 6),
            Text(label, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w700)),
          ],
        ),
      ),
    );
  }
}

class _Summary extends ConsumerStatefulWidget {
  const _Summary({required this.know, required this.review, required this.dontKnow});
  final int know, review, dontKnow;

  @override
  ConsumerState<_Summary> createState() => _SummaryState();
}

class _SummaryState extends ConsumerState<_Summary> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => AppFeedback.success());
    // Push the session's answers now rather than waiting for the next trigger.
    ref.read(reviewSyncProvider).flush();
  }

  @override
  Widget build(BuildContext context) {
    final total = widget.know + widget.review + widget.dontKnow;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                gradient: AppGradients.brand,
                shape: BoxShape.circle,
                boxShadow: AppShadows.glow(AppColors.primary),
              ),
              child: const Icon(Icons.emoji_events_rounded, color: Colors.white, size: 50),
            ).animate().scale(duration: 500.ms, curve: Curves.easeOutBack),
            const SizedBox(height: AppSpacing.lg),
            Text('Сессия завершена',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text('$total карточек повторено',
                style: const TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: AppSpacing.lg),
            Row(children: [
              _stat('Знаю', widget.know, AppColors.know),
              const SizedBox(width: 10),
              _stat('Повторить', widget.review, AppColors.review),
              const SizedBox(width: 10),
              _stat('Не знаю', widget.dontKnow, AppColors.dontKnow),
            ]).animate().fadeIn(delay: 200.ms).slideY(begin: 0.1, end: 0),
            const SizedBox(height: AppSpacing.xl),
            GlassButton(label: 'Готово', icon: Icons.check_rounded, onTap: () => Navigator.of(context).pop()),
          ],
        ),
      ),
    );
  }

  Widget _stat(String label, int value, Color c) => Expanded(
        child: GlassCard(
          padding: const EdgeInsets.symmetric(vertical: 16),
          radius: 18,
          child: Column(children: [
            Text('$value', style: TextStyle(color: c, fontSize: 24, fontWeight: FontWeight.w800)),
            const SizedBox(height: 2),
            Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
          ]),
        ),
      );
}

class _EmptySession extends StatelessWidget {
  const _EmptySession();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.check_circle_outline_rounded, size: 56, color: AppColors.success),
          const SizedBox(height: 12),
          Text('Здесь пока нечего повторять',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
