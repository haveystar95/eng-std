import 'package:flutter/material.dart';

/// Dark, vibrant design tokens — single source of truth for the app's look.
class AppColors {
  // Surfaces (deep navy-black, like the reference)
  static const bg = Color(0xFF0E1230);
  static const surface = Color(0xFF1A2150);
  static const surfaceAlt = Color(0xFF262E63);
  static const surfaceHi = Color(0xFF313A78);

  // Signature accent (warm coral→pink lives in gradients below)
  static const primary = Color(0xFFFF7A59);
  static const accent = Color(0xFF7C5CFC);

  // Text
  static const textPrimary = Color(0xFFF4F6FF);
  static const textSecondary = Color(0xFFA8AED0);
  static const textMuted = Color(0xFF6D74A0);

  // Answer buttons (3-way: know / review / don't know)
  static const know = Color(0xFF39D98A);
  static const review = Color(0xFFFFB13D);
  static const dontKnow = Color(0xFFFF5C7A);

  static const success = Color(0xFF39D98A);
  static const danger = Color(0xFFFF5C7A);
}

class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
}

class AppRadii {
  static const sm = 10.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 30.0;
  static const pill = 999.0;
}

class AppShadows {
  static List<BoxShadow> card = [
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.28),
      blurRadius: 24,
      offset: const Offset(0, 10),
    ),
  ];

  static List<BoxShadow> glow(Color c) => [
        BoxShadow(color: c.withValues(alpha: 0.40), blurRadius: 26, offset: const Offset(0, 12)),
      ];
}

class AppGradients {
  /// Primary CTA / progress — warm coral → pink.
  static const brand = LinearGradient(
    colors: [Color(0xFFFF9145), Color(0xFFFF4D8D)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  /// Category tile gradients, cycled for variety.
  static const tiles = <LinearGradient>[
    LinearGradient(colors: [Color(0xFFFF9F45), Color(0xFFFF5C5C)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFF2AD0CA), Color(0xFF4C6FFF)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFF7C5CFC), Color(0xFFEB5AA0)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFFFFC93D), Color(0xFFFF7A3D)], begin: Alignment.topLeft, end: Alignment.bottomRight),
    LinearGradient(colors: [Color(0xFF37E0A6), Color(0xFF1FA5C7)], begin: Alignment.topLeft, end: Alignment.bottomRight),
  ];

  static LinearGradient tileFor(int seed) => tiles[seed.abs() % tiles.length];
}
