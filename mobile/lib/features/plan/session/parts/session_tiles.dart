import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'session_bits.dart';

/// A piece of the assembly row and how to draw it.
class RowPiece {
  const RowPiece.word(this.text, {this.wrong = false}) : slot = false, look = SlotLook.filled, tappable = true;

  const RowPiece.slot(this.text, {this.look = SlotLook.filled, this.wrong = false}) : slot = true, tappable = true;

  /// The end-of-sentence mark after the assembled part — not a tile, does not go back.
  const RowPiece.tail(this.text) : slot = false, look = SlotLook.filled, wrong = false, tappable = false;

  final String text;
  final bool slot;
  final SlotLook look;

  /// At this position the assembled text diverged from the expected one — ink outline.
  final bool wrong;

  /// A tap returns the piece to the tray.
  final bool tappable;
}

/// A tray tile.
class TrayPiece {
  const TrayPiece(this.text, {this.used = false, this.hint = false});

  final String text;

  /// Already in the row — stays in the tray at 28 %.
  final bool used;

  /// The correct tile after a mistake — underlined in brass (30-5 «mistake»).
  final bool hint;
}

/// TILE ASSEMBLY (canvas 30-5): the assembly row with a caret above the hairline and the tile tray. A tap on a tray
/// tile moves it into the row, a tap on a word in the row returns it; extra tiles are not highlighted.
class SessionAssembly extends StatelessWidget {
  const SessionAssembly({
    super.key,
    required this.row,
    required this.tray,
    required this.onTray,
    required this.onRow,
    this.emptySlot = false,
    this.caret = true,
    this.sage = false,
    this.shake = 0,
    this.trayGap = 24,
  });

  final List<RowPiece> row;
  final List<TrayPiece> tray;
  final ValueChanged<int>? onTray;
  final ValueChanged<int>? onRow;

  /// An empty slot chip before the caret — the slot has not been chosen yet (32-2).
  final bool emptySlot;
  final bool caret;

  /// Assembled correctly — the row in sage.
  final bool sage;

  /// Shake the row (mistake).
  final int shake;

  /// From the row to the tray: 24 in 30-5, 20 in 31-6 / 32-2.
  final double trayGap;

  @override
  Widget build(BuildContext context) {
    final rowStyle = AppTextSession.target22.copyWith(color: sage ? AppColors.verdictKnown : AppColors.ink);
    final pieces = <Widget>[];
    for (var i = 0; i < row.length; i++) {
      final p = row[i];
      final Widget view;
      if (p.slot) {
        view = _SlotChip(text: p.text, look: p.wrong ? SlotLook.wrong : (sage ? SlotLook.sage : p.look));
      } else if (p.wrong) {
        view = Container(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(6), border: Border.all(color: AppColors.ink, width: 1.5)),
          child: Text(p.text, style: rowStyle.copyWith(height: 30 / 22)),
        );
      } else {
        view = Text(p.text, style: rowStyle);
      }
      // The end mark is not torn off the last piece: a lone period on a new line reads as a breakage.
      if (!p.slot && !p.tappable && pieces.isNotEmpty) {
        final last = pieces.removeLast();
        pieces.add(Row(mainAxisSize: MainAxisSize.min, children: [last, const SizedBox(width: 8), view]));
        continue;
      }
      final tappable = onRow != null && p.tappable;
      pieces.add(tappable
          ? GestureDetector(behavior: HitTestBehavior.opaque, onTap: () => onRow!(i), child: view)
          : view);
    }
    if (emptySlot) pieces.add(const _SlotChip(text: null, look: SlotLook.empty));
    if (caret) {
      pieces.add(const Padding(padding: EdgeInsets.only(top: 22), child: SessionCaret()));
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        SessionShake(
          trigger: shake,
          child: Container(
            constraints: const BoxConstraints(minHeight: 44),
            padding: const EdgeInsets.only(bottom: 10),
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.markerOutline, width: 1.5))),
            child: Wrap(
              spacing: 8,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: pieces,
            ),
          ),
        ),
        SizedBox(height: trayGap),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (var i = 0; i < tray.length; i++)
              SessionTile(
                key: ValueKey('tray-$i'),
                text: tray[i].text,
                used: tray[i].used,
                hint: tray[i].hint,
                onTap: onTray == null || tray[i].used ? null : () => onTray!(i),
              ),
          ],
        ),
      ],
    );
  }
}

/// The slot in the assembly row: an empty 96 × 30 chip or the filler in the slot.
class _SlotChip extends StatelessWidget {
  const _SlotChip({required this.text, required this.look});

  final String? text;
  final SlotLook look;

  @override
  Widget build(BuildContext context) {
    final (border, fill, color) = switch (look) {
      SlotLook.sage => (AppColors.verdictKnown, AppColors.sessionSageWash, AppColors.verdictKnown),
      SlotLook.wrong => (AppColors.ink, Colors.transparent, AppColors.ink),
      _ => (AppColors.brassInk, AppColors.sessionWindowFill, AppColors.ink),
    };
    // An empty slot has the exact size 96 × 30: otherwise a container without a child stretches across the whole
    // line. A filled one sizes to its text (a 34 line is taller than 30), with no constraints of its own: the
    // animation between an exact size and an open width does not interpolate.
    return AnimatedContainer(
      duration: AppMotion.sessionChipToSlot,
      width: text == null ? kSessionEmptyWindow.width : null,
      height: text == null ? kSessionEmptyWindow.height : null,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(color: fill, borderRadius: BorderRadius.circular(6), border: Border.all(color: border, width: 1.5)),
      child: text == null ? null : Text(text!, style: AppTextSession.target22.copyWith(color: color, height: 34 / 22)),
    );
  }
}

/// TILE 44 — paper with a shadow, Literata 15; in the row — at 28 %; correct after a mistake — brass underline.
class SessionTile extends StatelessWidget {
  const SessionTile({
    super.key,
    required this.text,
    this.used = false,
    this.hint = false,
    this.onTap,
    this.height = 44,
    this.selected = false,
    this.outlined = false,
    this.trailing,
  });

  final String text;
  final bool used;
  final bool hint;
  final VoidCallback? onTap;

  /// 44 for assembly tiles, 40 for filler chips.
  final double height;

  /// A selected chip — ink with paper text.
  final bool selected;

  /// A neutral chip — paper with an outline instead of a shadow (32-1, 32-9).
  final bool outlined;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final tile = AnimatedContainer(
      duration: AppMotion.sessionChipSelect,
      height: height,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: selected ? AppColors.ink : AppColors.paper,
        borderRadius: BorderRadius.circular(12),
        border: outlined && !selected ? Border.all(color: AppColors.markerOutline) : null,
        boxShadow: selected || outlined ? null : kSessionSheetShadow,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(text, style: AppTextSession.tile.copyWith(color: selected ? AppColors.paper : AppColors.ink)),
          if (trailing != null) ...[const SizedBox(width: 8), trailing!],
        ],
      ),
    );
    return Semantics(
      button: onTap != null,
      label: text,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: onTap,
        child: AnimatedOpacity(
          duration: AppMotion.sessionTileMove,
          opacity: used ? .28 : 1,
          child: hint
              ? Container(
                  decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.brassInk, width: 1.5))),
                  child: tile,
                )
              : tile,
        ),
      ),
    );
  }
}
