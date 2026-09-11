import 'package:flutter/material.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// ЛЕНТА ОТВЕТОВ над вопросом (кадры 22-2 … 22-4): «Цель · Иду к врачу… Изм.» — rows of 40 between
/// hairlines, label and «Изм.» tertiary 14, the answer ink 14 in one line. «Изм.» reopens the step
/// with the answer kept.
class EntryTape extends StatelessWidget {
  const EntryTape({super.key, required this.rows});

  final List<EntryTapeRow> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();
    final l = AppLocalizations.of(context);
    const muted = TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.tertiary);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < rows.length; i++)
          Container(
            height: 40,
            decoration: BoxDecoration(
              border: Border(
                top: const BorderSide(color: AppColors.dividerFaint),
                bottom: i == rows.length - 1 ? const BorderSide(color: AppColors.dividerFaint) : BorderSide.none,
              ),
            ),
            child: Row(
              children: [
                Text(rows[i].label, style: muted),
                const Text(' · ', style: muted),
                Expanded(
                  child: Text(
                    rows[i].value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 14, color: AppColors.ink),
                  ),
                ),
                const SizedBox(width: 8),
                Semantics(
                  button: true,
                  child: InkWell(
                    onTap: () {
                      AppHaptics.light();
                      rows[i].onEdit();
                    },
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                      child: Text(l.planEntryTapeEdit, style: muted),
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class EntryTapeRow {
  const EntryTapeRow({required this.label, required this.value, required this.onEdit});

  final String label;
  final String value;
  final VoidCallback onEdit;
}
