import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

import 'session_bits.dart';

/// Кусок строки сборки, как его рисовать.
class RowPiece {
  const RowPiece.word(this.text, {this.wrong = false}) : slot = false, look = SlotLook.filled, tappable = true;

  const RowPiece.slot(this.text, {this.look = SlotLook.filled, this.wrong = false}) : slot = true, tappable = true;

  /// Знак конца предложения после собранного — не плитка, не возвращается.
  const RowPiece.tail(this.text) : slot = false, look = SlotLook.filled, wrong = false, tappable = false;

  final String text;
  final bool slot;
  final SlotLook look;

  /// На этом месте собранное разошлось с ожидаемым — контур чернил.
  final bool wrong;

  /// Тап возвращает кусок в лоток.
  final bool tappable;
}

/// Плитка лотка.
class TrayPiece {
  const TrayPiece(this.text, {this.used = false, this.hint = false});

  final String text;

  /// Уже в строке — остаётся в лотке на 28 %.
  final bool used;

  /// Верная плитка после ошибки — подчёркнута латунью (30-5 «ошибка»).
  final bool hint;
}

/// ПЛИТКИ-СБОРКА (кадр 30-5): строка сборки с курсором над хайрлайном и лоток плиток. Тап по плитке лотка
/// переносит её в строку, тап по слову в строке возвращает; лишние плитки не подсвечиваются.
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

  /// Пустой чип окна перед курсором — окно ещё не выбрано (32-2).
  final bool emptySlot;
  final bool caret;

  /// Собрано верно — строка шалфеем.
  final bool sage;

  /// Покачать строку (ошибка).
  final int shake;

  /// От строки до лотка: 24 у 30-5, 20 у 31-6 / 32-2.
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
      // Знак конца не отрывается от последнего куска: одна точка на новой строке читается как поломка.
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

/// Окно в строке сборки: пустой чип 96 × 30 или наполнение в окне.
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
    // Пустое окно — точного размера 96 × 30: контейнер без ребёнка иначе растягивается на всю строку.
    // Наполненное — по тексту (строка 34 выше 30), без своих ограничений: анимация между точным размером
    // и открытой шириной не интерполируется.
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

/// ПЛИТКА 44 — бумага с тенью, Literata 15; в строке — на 28 %; верная после ошибки — подчерк латунью.
class SessionTile extends StatelessWidget {
  const SessionTile({super.key, required this.text, this.used = false, this.hint = false, this.onTap, this.height = 44, this.selected = false, this.trailing});

  final String text;
  final bool used;
  final bool hint;
  final VoidCallback? onTap;

  /// 44 у плиток сборки, 40 у чипов наполнений.
  final double height;

  /// Выбранный чип — чернила с текстом бумаги.
  final bool selected;
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
        boxShadow: selected ? null : kSessionSheetShadow,
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
