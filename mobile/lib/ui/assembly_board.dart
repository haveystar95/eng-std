import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'answer_option.dart';
import 'verdict_marker.dart';

/// ПЛИТКА СБОРКИ (4л): Literata, высота 44, radius 14, gap 10 — одинаковые в «Базе» и в плане.
///
/// Три состояния: доступная — слоёная бумага с тенью карточки; собранная — заливка ink, текст
/// paper, без тени; использованная (осталась в ряду после того, как улетела в строку) — фон
/// `rgba(46,38,32,.06)`, текст tertiary, без тени (12b).
enum TileState { available, placed, used }

class WordTile extends StatefulWidget {
  const WordTile({super.key, required this.text, required this.state, this.onTap, this.shake = false, this.marked = false});

  final String text;
  final TileState state;
  final VoidCallback? onTap;

  /// Вздрогнуть (лишняя плитка в неверной сборке, 23-7h).
  final bool shake;

  /// Слово, которого нет в ответе, названо волнистой терракотой — собранная строка остаётся своей,
  /// а не исправленной (тренажёры коллекций; «База» 12b).
  final bool marked;

  @override
  State<WordTile> createState() => _WordTileState();
}

class _WordTileState extends State<WordTile> with TickerProviderStateMixin {
  late final AnimationController _shake = AnimationController(vsync: this, duration: AppMotion.answerWrong);
  late final AnimationController _land = AnimationController(vsync: this, duration: AppMotion.chipToLine);

  @override
  void didUpdateWidget(WordTile old) {
    super.didUpdateWidget(old);
    final reduce = MediaQuery.of(context).disableAnimations;
    if (widget.shake && !old.shake && !reduce) _shake.forward(from: 0);
    // Плитка встала в строку: масштаб 1 → 1.04 → 1 (4е «Чип в строку сборки»).
    if (widget.state == TileState.placed && old.state != TileState.placed && !reduce) {
      _land.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _shake.dispose();
    _land.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final (Color bg, Color fg, List<BoxShadow> shadow) = switch (widget.state) {
      TileState.available => (AppColors.surfaceRaised, AppColors.ink, AppShadows.card),
      TileState.placed => (AppColors.ink, AppColors.paper, const <BoxShadow>[]),
      TileState.used => (AppColors.faintInk, AppColors.tertiary, const <BoxShadow>[]),
    };

    return AnimatedBuilder(
      animation: Listenable.merge([_shake, _land]),
      builder: (context, child) {
        final t = _shake.value;
        final dx = _shake.isAnimating ? math.sin(t * math.pi * 3) * 3 * (1 - t) : 0.0;
        final s = _land.isAnimating ? 1 + 0.04 * math.sin(_land.value * math.pi) : 1.0;
        return Transform.translate(
          offset: Offset(dx, 0),
          child: Transform.scale(scale: s, child: child),
        );
      },
      child: Semantics(
        button: widget.onTap != null,
        label: widget.text,
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(14),
            child: Container(
              height: 44,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: bg,
                borderRadius: BorderRadius.circular(14),
                boxShadow: shadow,
              ),
              // По ширине слова: `alignment` у Container растянул бы плитку на весь ряд Wrap.
              child: Center(
                widthFactor: 1,
                child: Text(
                widget.text,
                style: AppTextDay.tile.copyWith(
                  color: fg,
                  decoration: widget.marked ? TextDecoration.underline : null,
                  decorationStyle: widget.marked ? TextDecorationStyle.wavy : null,
                  decorationColor: widget.marked ? AppColors.destructiveText : null,
                ),
              ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// ПОДЛОЖКА СБОРКИ (4л): `rgba(46,38,32,.06)`, radius 18, padding 10 12, gap 10; при вердикте
/// получает ту же тонировку, что вариант, а маркер 22 встаёт СЛЕВА от подложки (23-7g/h).
///
/// Пустая — держит высоту [minHeight], чтобы экран не прыгал, когда первая плитка встаёт.
class AssemblyBoard extends StatelessWidget {
  const AssemblyBoard({
    super.key,
    required this.placed,
    required this.verdict,
    this.onTapPlaced,
    this.minHeight = 60,
    this.shakeIndex,
    this.marked = const {},
  });

  final List<String> placed;
  final AnswerOptionVerdict verdict;
  final ValueChanged<int>? onTapPlaced;
  final double minHeight;

  /// Индекс собранной плитки, которая вздрагивает при ошибке (лишняя), или null.
  final int? shakeIndex;

  /// Индексы собранных плиток, названных чужими (волнистая терракота под словом).
  final Set<int> marked;

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations;
    final tint = switch (verdict) {
      AnswerOptionVerdict.correct => AppColors.sageTint,
      AnswerOptionVerdict.wrong => AppColors.terracottaTint,
      _ => AppColors.faintInk,
    };
    final marker = switch (verdict) {
      AnswerOptionVerdict.correct || AnswerOptionVerdict.correctQuiet => MarkerState.passed,
      AnswerOptionVerdict.wrong => MarkerState.failed,
      _ => null,
    };

    final board = AnimatedContainer(
      duration: reduce ? Duration.zero : AppMotion.feedbackReveal,
      curve: AppMotion.easeOut,
      constraints: BoxConstraints(minHeight: minHeight),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(AppRadii.field)),
      alignment: Alignment.centerLeft,
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (var i = 0; i < placed.length; i++)
            WordTile(
              key: ValueKey('placed-$i-${placed[i]}'),
              text: placed[i],
              state: TileState.placed,
              shake: shakeIndex == i,
              marked: marked.contains(i),
              onTap: onTapPlaced == null ? null : () => onTapPlaced!(i),
            ),
        ],
      ),
    );

    if (marker == null) return board;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(padding: const EdgeInsets.only(top: 21), child: VerdictMarker(state: marker)),
        const SizedBox(width: 12),
        Expanded(child: board),
      ],
    );
  }
}

/// РЯД ДОСТУПНЫХ ПЛИТОК под подложкой — gap 10, перенос; собранная плитка оставляет выцветшую копию.
class TileTray extends StatelessWidget {
  const TileTray({super.key, required this.tiles, required this.used, this.onTap});

  final List<String> tiles;
  final Set<int> used;
  final ValueChanged<int>? onTap;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 10,
    runSpacing: 10,
    children: [
      for (var i = 0; i < tiles.length; i++)
        WordTile(
          key: ValueKey('tray-$i'),
          text: tiles[i],
          state: used.contains(i) ? TileState.used : TileState.available,
          onTap: onTap == null || used.contains(i) ? null : () => onTap!(i),
        ),
    ],
  );
}
