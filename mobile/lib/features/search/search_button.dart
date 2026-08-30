import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import 'search_screen.dart';

/// THE MAGNIFIER — search, where it belongs: in the header of the screen you are already on.
///
/// Search used to be a tab, and a tab is a PLACE. Search is not a place; it is something you do
/// about the words in front of you, which is why кадры 08–11 draw it as an icon in the headers of
/// Главная and Коллекции and give the fifth tab slot to the plan. What it opens is unchanged — the
/// same screen, with a back chevron because it is pushed now rather than mounted.
///
/// One widget rather than two copies of the same `InkResponse`, because the two headers must not
/// come to differ in what a magnifier means.
class SearchIconButton extends StatelessWidget {
  const SearchIconButton({super.key, this.size = 20});

  final double size;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Semantics(
      button: true,
      label: l.searchOpen,
      child: InkResponse(
        radius: 24,
        onTap: () {
          AppHaptics.light();
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const SearchScreen(pushed: true)),
          );
        },
        child: SizedBox(
          width: AppSpacing.minTap,
          height: AppSpacing.minTap,
          child: Icon(LucideIcons.search, size: size, color: AppColors.secondary),
        ),
      ),
    );
  }
}
