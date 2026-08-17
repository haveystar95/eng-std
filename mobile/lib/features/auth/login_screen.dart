import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/auth_repository.dart';
import '../../data/providers.dart';

/// Localised copy for a sign-in failure. The data layer throws an [AuthError] code (no
/// `BuildContext` there); the screen resolves it here. A non-auth error falls back to the generic
/// «не удалось войти».
String _authErrorText(AppLocalizations l, Object? error) {
  if (error is! AuthException) return l.authErrorLoginFailed;
  return switch (error.code) {
    AuthError.offline => l.authErrorOffline,
    AuthError.googleUnsupported => l.authErrorGoogleUnsupported,
    AuthError.cancelled => l.authErrorCancelled,
    AuthError.googleFailed => l.authErrorGoogle,
    AuthError.googleNoToken => l.authErrorGoogleToken,
    AuthError.loginFailed => l.authErrorLoginFailed,
    AuthError.appleUnavailable => l.authErrorApple,
    AuthError.appleNoToken => l.authErrorAppleToken,
  };
}

/// Вход (кадр 10a) — только типографика на бумаге. Sign in with Apple (the official button widget)
/// and a guideline Google button; all reads offline-aware. Apple needs a backend `/auth/apple` +
/// the paid-team capability (see auth_repository); until then it surfaces a clear message.
class LoginScreen extends ConsumerWidget {
  const LoginScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    final loading = auth.isLoading;
    final online = ref.watch(connectivityProvider).value ?? true;

    ref.listen(authControllerProvider, (_, next) {
      if (next.hasError) {
        AppHaptics.warning();
        ScaffoldMessenger.of(context)
          ..clearSnackBars()
          ..showSnackBar(SnackBar(content: Text(_authErrorText(l, next.error))));
      }
    });

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: SafeArea(
          child: Stack(
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 30),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(l.appWordmark,
                        style: const TextStyle(
                            fontFamily: AppFonts.literata,
                            fontWeight: FontWeight.w500,
                            fontSize: 56,
                            height: 1,
                            letterSpacing: -1.68,
                            color: AppColors.ink)),
                    const SizedBox(height: 22),
                    const SizedBox(height: 1, child: ColoredBox(color: AppColors.track)),
                    const SizedBox(height: 20),
                    Text(l.authTagline,
                        style: AppText.translation.copyWith(fontSize: 15.5, height: 1.5, color: AppColors.inkBody)),
                  ],
                ),
              ),
              Positioned(
                left: 30,
                right: 30,
                bottom: 44,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (!online) ...[
                      _OfflineHint(text: l.authOfflineHint),
                      const SizedBox(height: 14),
                    ],
                    if (loading)
                      const SizedBox(
                        height: 54,
                        child: Center(
                          child: SizedBox(
                              width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.4, color: AppColors.ink)),
                        ),
                      )
                    else ...[
                      SignInWithAppleButton(
                        height: 54,
                        borderRadius: BorderRadius.circular(AppRadii.field),
                        style: SignInWithAppleButtonStyle.black,
                        onPressed: () => ref.read(authControllerProvider.notifier).signInWithApple(),
                      ),
                      const SizedBox(height: 12),
                      _GoogleButton(
                        label: l.authContinueGoogle,
                        onTap: () => ref.read(authControllerProvider.notifier).signIn(),
                      ),
                    ],
                    const SizedBox(height: 26),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(l.authTerms, style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
                        const SizedBox(width: 8),
                        Container(
                            width: 3, height: 3, decoration: const BoxDecoration(color: AppColors.dashed, shape: BoxShape.circle)),
                        const SizedBox(width: 8),
                        Text(l.authPrivacy, style: AppText.transcription.copyWith(fontSize: 11.5, color: AppColors.tertiary)),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Guideline Google button — white paper, hairline border, the four-colour G mark + label.
class _GoogleButton extends StatelessWidget {
  const _GoogleButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.field,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.field),
        side: const BorderSide(color: AppColors.dashed),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: SizedBox(
          height: 54,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const SizedBox(width: 18, height: 18, child: CustomPaint(painter: _GoogleGPainter())),
              const SizedBox(width: 10),
              Text(label,
                  style: const TextStyle(fontFamily: AppFonts.inter, fontSize: 15.5, fontWeight: FontWeight.w600, color: AppColors.ink)),
            ],
          ),
        ),
      ),
    );
  }
}

/// A compact rendition of the four-colour Google “G”. Brand colours are isolated in [GoogleBrand].
class _GoogleGPainter extends CustomPainter {
  const _GoogleGPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final c = size.center(Offset.zero);
    final r = size.width / 2;
    final stroke = size.width * 0.22;
    final rect = Rect.fromCircle(center: c, radius: r - stroke / 2);
    final p = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.butt;

    // Four arcs, roughly the Google quadrants (angles in radians, 0 = 3 o'clock, CW).
    void arc(double startDeg, double sweepDeg, Color color) {
      p.color = color;
      canvas.drawArc(rect, startDeg * 3.1415926 / 180, sweepDeg * 3.1415926 / 180, false, p);
    }

    arc(-45, -80, GoogleBrand.red); // top-left
    arc(-125, -85, GoogleBrand.yellow); // bottom-left
    arc(150, 80, GoogleBrand.green); // bottom-right
    arc(70, 55, GoogleBrand.blue); // right
    // The blue crossbar of the G.
    final bar = Paint()..color = GoogleBrand.blue;
    canvas.drawRect(Rect.fromLTWH(c.dx, c.dy - stroke / 2, r, stroke), bar);
  }

  @override
  bool shouldRepaint(_GoogleGPainter old) => false;
}

class _OfflineHint extends StatelessWidget {
  const _OfflineHint({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AppRadii.field),
        border: Border.all(color: AppColors.hairline),
      ),
      child: Row(
        children: [
          Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppColors.secondary, width: 1.5)),
          ),
          const SizedBox(width: 9),
          Expanded(child: Text(text, style: AppText.translation.copyWith(fontSize: 12.5, color: AppColors.inkBody))),
        ],
      ),
    );
  }
}
