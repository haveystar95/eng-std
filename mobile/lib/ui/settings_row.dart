import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';

import 'paper_switch.dart';

/// What stands at the right end of a [SettingsRow].
enum SettingsRowEnd {
  /// Nothing — a row that only states (the plan on 42-1a, «Восстановить покупки»).
  none,

  /// A chevron — opens a sheet or a screen.
  chevron,

  /// An arrow out — leaves the app (App Store).
  external,

  /// A switch — the row toggles.
  toggle,

  /// A check — the chosen option of a sheet's list.
  check,
}

/// A ROW OF A SETTINGS LIST (frames 42-1…42-4): 48 tall, the label 15/20 ink, a value 15/20 grey, then the row's end;
/// rows under one another share a hairline at .22. One component for the profile and its sheets — the chooser rows
/// (interface language, native language) are the same row with a check.
class SettingsRow extends StatelessWidget {
  const SettingsRow({
    super.key,
    required this.label,
    this.value,
    this.end = SettingsRowEnd.chevron,
    this.on = false,
    this.onTap,
    this.first = false,
    this.leading,
    this.busy = false,
  });

  final String label;
  final String? value;
  final SettingsRowEnd end;

  /// The switch's state, or whether the check is shown.
  final bool on;
  final VoidCallback? onTap;

  /// The first row of a group has no hairline over it.
  final bool first;

  /// A mark before the label (a language's flag in a chooser).
  final Widget? leading;

  /// The row's action is on its way (a request with no screen to go to).
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final trailing = switch (end) {
      SettingsRowEnd.none => null,
      SettingsRowEnd.chevron => const Icon(LucideIcons.chevronRight, size: 20, color: AppColors.tertiary),
      SettingsRowEnd.external => const Icon(LucideIcons.arrowUpRight, size: 20, color: AppColors.tertiary),
      SettingsRowEnd.toggle => PaperSwitch(value: on),
      SettingsRowEnd.check => on ? const Icon(LucideIcons.check, size: 20, color: AppColors.ink) : null,
    };
    final row = Container(
      height: 48,
      decoration: first ? null : const BoxDecoration(border: Border(top: BorderSide(color: AppColors.markerOutline))),
      child: Row(
        children: [
          if (leading != null) ...[leading!, const SizedBox(width: 12)],
          Expanded(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTextStart.rowLabel)),
          if (value != null) ...[
            const SizedBox(width: 12),
            Text(value!, maxLines: 1, style: AppTextStart.rowValue),
          ],
          if (busy) ...[
            const SizedBox(width: 12),
            const SizedBox.square(dimension: 16, child: CircularProgressIndicator(strokeWidth: 1.5, color: AppColors.tertiary)),
          ] else if (trailing != null) ...[
            const SizedBox(width: 12),
            trailing,
          ],
        ],
      ),
    );
    if (onTap == null) return Semantics(label: label, value: value, child: row);
    return Semantics(
      button: end != SettingsRowEnd.toggle,
      toggled: end == SettingsRowEnd.toggle ? on : null,
      label: label,
      value: value,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: busy
            ? null
            : () {
                AppHaptics.light();
                onTap!();
              },
        child: row,
      ),
    );
  }
}

/// A GROUP OF ROWS under its caps label (42-1: «ПОДПИСКА», «ОБУЧЕНИЕ»…) — the label 11/14 600 .08em grey, 14 to the
/// rows.
class SettingsGroup extends StatelessWidget {
  const SettingsGroup({super.key, required this.label, required this.rows});

  final String label;
  final List<Widget> rows;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(label.toUpperCase(), style: AppTextStart.groupLabel),
      const SizedBox(height: 14),
      ...rows,
    ],
  );
}
