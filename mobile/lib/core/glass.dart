import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'design.dart';

/// Haptic + sound feedback, iOS-style. Keep taps feeling physical.
class AppFeedback {
  static void select() {
    HapticFeedback.selectionClick();
    SystemSound.play(SystemSoundType.click);
  }

  static void tap() => HapticFeedback.lightImpact();

  static void success() => HapticFeedback.mediumImpact();

  static void warn() => HapticFeedback.heavyImpact();
}

/// Ambient backdrop: deep base with soft colored light blobs. Glass surfaces on
/// top refract these, which is what sells the "liquid glass" look.
class AmbientBackground extends StatelessWidget {
  const AmbientBackground({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(child: ColoredBox(color: AppColors.bg)),
        Positioned.fill(
          child: ImageFiltered(
            imageFilter: ImageFilter.blur(sigmaX: 70, sigmaY: 70),
            child: Stack(
              children: [
                _blob(const Alignment(-1.1, -1.0), const Color(0xFFFF5C7A), 320),
                _blob(const Alignment(1.2, -0.6), const Color(0xFF7C5CFC), 300),
                _blob(const Alignment(-0.8, 1.1), const Color(0xFF2AD0CA), 300),
                _blob(const Alignment(1.0, 1.2), const Color(0xFFFF9145), 260),
              ],
            ),
          ),
        ),
        child,
      ],
    );
  }

  Widget _blob(Alignment a, Color c, double size) => Align(
        alignment: a,
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: RadialGradient(colors: [c.withValues(alpha: 0.55), c.withValues(alpha: 0.0)]),
          ),
        ),
      );
}

/// A frosted-glass surface: real backdrop blur + translucent fill + hairline
/// border + a faint top sheen. The building block for the whole UI.
class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.md),
    this.radius = 22,
    this.onTap,
    this.blur = 22,
    this.tint,
  });

  final Widget child;
  final EdgeInsets padding;
  final double radius;
  final VoidCallback? onTap;
  final double blur;
  final Color? tint;

  @override
  Widget build(BuildContext context) {
    final content = ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(radius),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                (tint ?? Colors.white).withValues(alpha: tint != null ? 0.28 : 0.12),
                (tint ?? Colors.white).withValues(alpha: tint != null ? 0.10 : 0.04),
              ],
            ),
            border: Border.all(color: Colors.white.withValues(alpha: 0.14), width: 1),
          ),
          child: child,
        ),
      ),
    );

    if (onTap == null) return content;
    return SpringTap(onTap: onTap!, child: content);
  }
}

/// Scale-and-settle press with feedback — the spring every tappable thing gets.
class SpringTap extends StatefulWidget {
  const SpringTap({super.key, required this.child, required this.onTap, this.scale = 0.96});
  final Widget child;
  final VoidCallback onTap;
  final double scale;

  @override
  State<SpringTap> createState() => _SpringTapState();
}

class _SpringTapState extends State<SpringTap> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _down = true),
      onTapCancel: () => setState(() => _down = false),
      onTapUp: (_) => setState(() => _down = false),
      onTap: () {
        AppFeedback.select();
        widget.onTap();
      },
      child: AnimatedScale(
        scale: _down ? widget.scale : 1,
        duration: const Duration(milliseconds: 140),
        curve: Curves.easeOutBack,
        child: widget.child,
      ),
    );
  }
}

/// A selectable pill (language / level chip) with a glass look.
class GlassChip extends StatelessWidget {
  const GlassChip({super.key, required this.label, required this.selected, required this.onTap, this.leading});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final String? leading;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.pill),
          gradient: selected ? AppGradients.brand : null,
          color: selected ? null : Colors.white.withValues(alpha: 0.06),
          border: Border.all(
            color: selected ? Colors.transparent : Colors.white.withValues(alpha: 0.16),
            width: 1,
          ),
          boxShadow: selected ? AppShadows.glow(AppColors.primary) : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (leading != null) ...[Text(leading!, style: const TextStyle(fontSize: 18)), const SizedBox(width: 8)],
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : AppColors.textSecondary,
                fontWeight: FontWeight.w700,
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Full-width gradient CTA with press spring + feedback.
class GlassButton extends StatelessWidget {
  const GlassButton({super.key, required this.label, required this.onTap, this.icon, this.busy = false});
  final String label;
  final VoidCallback onTap;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return SpringTap(
      onTap: busy ? () {} : onTap,
      child: Container(
        height: 56,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: AppGradients.brand,
          borderRadius: BorderRadius.circular(AppRadii.md),
          boxShadow: AppShadows.glow(AppColors.primary),
        ),
        child: busy
            ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
            : Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[Icon(icon, color: Colors.white, size: 20), const SizedBox(width: 10)],
                  Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                ],
              ),
      ),
    );
  }
}
