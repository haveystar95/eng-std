import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_tts/flutter_tts.dart';

import '../../core/design.dart';
import '../../data/models.dart';
import '../../data/providers.dart';

/// One training session — mixed (shuffled due) or a specific collection.
class SessionScreen extends ConsumerWidget {
  const SessionScreen({super.key, required this.title, this.collectionId, this.shuffle = false});

  final String title;
  final String? collectionId;
  final bool shuffle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final args = (collectionId: collectionId, shuffle: shuffle);
    final cards = ref.watch(sessionCardsProvider(args));

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        top: false,
        child: cards.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('Ошибка: $e', style: const TextStyle(color: AppColors.textSecondary))),
          data: (list) => list.isEmpty
              ? const _EmptySession()
              : _Deck(cards: list, args: args),
        ),
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
  final _tts = FlutterTts();
  int _pos = 0;
  bool _revealed = false;
  int _know = 0, _review = 0, _dontKnow = 0;
  bool _finished = false;

  // Swipe state
  late final AnimationController _anim =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 240));
  Offset _drag = Offset.zero;
  Offset _from = Offset.zero, _to = Offset.zero;
  Rating? _pending;
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
          // Zero the tween BEFORE reset so the listener can't snap the card
          // back to the swipe's mid-point.
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
    await _tts.setLanguage('en-US');
    await _tts.setSpeechRate(0.45);
    await _tts.speak(_word.term);
  }

  void _answer(Rating rating) {
    final wordId = _word.id;
    final isLast = _pos + 1 >= widget.cards.length;

    // Advance the UI immediately; reset any swipe offset for the next card.
    setState(() {
      if (rating == Rating.easy) {
        _know++;
      } else if (rating == Rating.hard) {
        _review++;
      } else {
        _dontKnow++;
      }
      _drag = Offset.zero;
      _from = Offset.zero;
      _to = Offset.zero;
      if (isLast) {
        _finished = true;
      } else {
        _pos++;
        _revealed = false;
      }
    });

    // Persist in the background so the card flow stays snappy.
    ref.read(apiClientProvider).answer(wordId, rating).whenComplete(() {
      ref.invalidate(statsProvider);
      ref.invalidate(dueCardsProvider);
    });
  }

  /// Which rating a given drag maps to (null = not past threshold).
  Rating? _ratingFor(Offset d) {
    if (d.dy < -_threshold && d.dy.abs() > d.dx.abs()) return Rating.hard; // up = повторить
    if (d.dx > _threshold) return Rating.easy; // right = знаю
    if (d.dx < -_threshold) return Rating.again; // left = не знаю
    return null;
  }

  void _onPanUpdate(DragUpdateDetails d) {
    if (!_revealed || _anim.isAnimating) return;
    setState(() => _drag += d.delta);
  }

  void _onPanEnd(DragEndDetails _) {
    if (!_revealed) return;
    final r = _ratingFor(_drag);
    _from = _drag;
    _pending = r;
    if (r == Rating.hard) {
      _to = const Offset(0, -1200);
    } else if (r == Rating.easy) {
      _to = Offset(1200, _drag.dy);
    } else if (r == Rating.again) {
      _to = Offset(-1200, _drag.dy);
    } else {
      _to = Offset.zero; // snap back
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
                        onTap: () => setState(() => _revealed = !_revealed),
                        onSpeak: _speak,
                      ),
                    ),
                  ),
                  if (_revealed && _drag.distance > 24) _SwipeHint(rating: hint),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          if (_revealed)
            _Answers(onAnswer: _answer)
          else
            FilledButton.icon(
              onPressed: () => setState(() => _revealed = true),
              icon: const Icon(Icons.visibility_outlined),
              label: const Text('Показать перевод'),
            ),
        ],
      ),
    );
  }
}

/// Floating badge that previews the answer the current swipe would give.
class _SwipeHint extends StatelessWidget {
  const _SwipeHint({required this.rating});
  final Rating? rating;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (rating) {
      Rating.easy => ('Знаю', AppColors.know),
      Rating.hard => ('Повторить', AppColors.review),
      Rating.again => ('Не знаю', AppColors.dontKnow),
      _ => ('', AppColors.textMuted),
    };
    if (label.isEmpty) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        boxShadow: AppShadows.glow(color),
      ),
      child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
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
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: AppColors.textSecondary, fontWeight: FontWeight.w700,
                    )),
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
          child: LinearProgressIndicator(
            value: progress,
            minHeight: 8,
            backgroundColor: AppColors.surfaceAlt,
            valueColor: const AlwaysStoppedAnimation(AppColors.primary),
          ),
        ),
      ],
    );
  }

  Widget _tally(Color c, int n) {
    return Row(children: [
      Container(width: 9, height: 9, decoration: BoxDecoration(color: c, shape: BoxShape.circle)),
      const SizedBox(width: 5),
      Text('$n', style: TextStyle(color: c, fontWeight: FontWeight.w700, fontSize: 14)),
    ]);
  }
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
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.xl),
          boxShadow: AppShadows.card,
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: [
            Container(height: 6, decoration: const BoxDecoration(gradient: AppGradients.brand)),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (word.type == 'phrase')
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: AppColors.surfaceAlt, borderRadius: BorderRadius.circular(AppRadii.sm)),
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
                      duration: const Duration(milliseconds: 200),
                      crossFadeState: revealed ? CrossFadeState.showSecond : CrossFadeState.showFirst,
                      firstChild: Text('Нажми, чтобы увидеть перевод',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(color: AppColors.textMuted)),
                      secondChild: Column(children: [
                        const Divider(height: 1),
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
            ),
          ],
        ),
      ),
    ).animate(key: ValueKey(word.id)).fadeIn(duration: 200.ms).slideY(begin: 0.05, end: 0);
  }
}

class _SpeakButton extends StatelessWidget {
  const _SpeakButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surfaceAlt,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: const Padding(
          padding: EdgeInsets.all(14),
          child: Icon(Icons.volume_up_rounded, color: AppColors.primary, size: 26),
        ),
      ),
    );
  }
}

class _Answers extends StatelessWidget {
  const _Answers({required this.onAnswer});
  final ValueChanged<Rating> onAnswer;

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      _btn('Не знаю', Icons.close_rounded, AppColors.dontKnow, Rating.again),
      const SizedBox(width: 10),
      _btn('Повторить', Icons.autorenew_rounded, AppColors.review, Rating.hard),
      const SizedBox(width: 10),
      _btn('Знаю', Icons.check_rounded, AppColors.know, Rating.easy),
    ]).animate().fadeIn(duration: 180.ms).slideY(begin: 0.15, end: 0);
  }

  Widget _btn(String label, IconData icon, Color color, Rating rating) {
    return Expanded(
      child: GestureDetector(
        onTap: () => onAnswer(rating),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 16),
          decoration: BoxDecoration(
            color: color.withValues(alpha: 0.16),
            borderRadius: BorderRadius.circular(AppRadii.md),
            border: Border.all(color: color.withValues(alpha: 0.5), width: 1),
          ),
          child: Column(children: [
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 6),
            Text(label, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w700)),
          ]),
        ),
      ),
    );
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.know, required this.review, required this.dontKnow});
  final int know, review, dontKnow;

  @override
  Widget build(BuildContext context) {
    final total = know + review + dontKnow;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 96, height: 96,
              decoration: const BoxDecoration(gradient: AppGradients.brand, shape: BoxShape.circle),
              child: const Icon(Icons.emoji_events_rounded, color: Colors.white, size: 50),
            ).animate().scale(duration: 400.ms, curve: Curves.easeOutBack),
            const SizedBox(height: AppSpacing.lg),
            Text('Сессия завершена',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text('$total карточек повторено',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: AppColors.textSecondary)),
            const SizedBox(height: AppSpacing.lg),
            Row(children: [
              _stat('Знаю', know, AppColors.know),
              const SizedBox(width: 10),
              _stat('Повторить', review, AppColors.review),
              const SizedBox(width: 10),
              _stat('Не знаю', dontKnow, AppColors.dontKnow),
            ]),
            const SizedBox(height: AppSpacing.xl),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Готово'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _stat(String label, int value, Color c) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(AppRadii.md)),
        child: Column(children: [
          Text('$value', style: TextStyle(color: c, fontSize: 24, fontWeight: FontWeight.w800)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
        ]),
      ),
    );
  }
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
