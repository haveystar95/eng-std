/// The «Слова» design system — paper/ink, monochrome (see
/// `backend2/docs/design/tokens.html`, the source of truth).
///
/// Import this barrel for all design tokens:
/// ```dart
/// import 'package:eng_std/theme/theme.dart';
/// ```
///
/// Never write a raw hex colour outside `lib/theme/` — the
/// `test/theme/no_hex_outside_theme_test.dart` guard fails the build if you do.
library;

import 'package:flutter/material.dart';

import 'colors.dart';
import 'geometry.dart';
import 'typography.dart';

export 'brand_palette.dart';
export 'colors.dart';
export 'feedback.dart';
export 'flag_palette.dart';
export 'geometry.dart';
export 'haptics.dart';
export 'ink_density.dart';
export 'motion.dart';
export 'shadows.dart';
export 'typography.dart';

/// Baseline Material theme for the paper/ink system. Screens lean on the token
/// classes and `lib/ui/` components directly; this provides sane defaults
/// (background, default text = Inter/ink, transparent app bar) underneath them.
ThemeData buildAppTheme() {
  const scheme = ColorScheme(
    brightness: Brightness.light,
    primary: AppColors.ink,
    onPrimary: AppColors.paper,
    secondary: AppColors.secondary,
    onSecondary: AppColors.paper,
    error: AppColors.destructiveText,
    onError: AppColors.paper,
    surface: AppColors.surfaceRaised,
    onSurface: AppColors.ink,
  );

  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    colorScheme: scheme,
    scaffoldBackgroundColor: AppColors.paper,
    canvasColor: AppColors.paper,
    fontFamily: AppFonts.inter,
    splashFactory: InkRipple.splashFactory,
    textSelectionTheme: const TextSelectionThemeData(
      cursorColor: AppColors.ink,
      selectionColor: AppColors.track,
      selectionHandleColor: AppColors.ink,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      centerTitle: false,
      foregroundColor: AppColors.ink,
      titleTextStyle: AppText.screenTitle,
    ),
    dividerTheme: const DividerThemeData(color: AppColors.hairline, thickness: 1, space: 1),
    dialogTheme: DialogThemeData(
      backgroundColor: AppColors.alertSurface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(AppRadii.alert)),
    ),
  );
}
