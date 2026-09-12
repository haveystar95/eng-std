import 'package:flutter/material.dart';

import 'package:eng_std/theme/theme.dart';

/// МЕТКА ЗОНЫ ВХОДА — 11/700/.14em caps tertiary («ЯЗЫК», «УРОВЕНЬ», «ДАТА», «ТАК ПИШУТ ДРУГИЕ»).
class EntrySectionLabel extends StatelessWidget {
  const EntrySectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: const TextStyle(
      fontFamily: AppFonts.inter,
      fontSize: 11,
      fontWeight: FontWeight.w700,
      letterSpacing: 1.54,
      color: AppColors.tertiary,
    ),
  );
}
