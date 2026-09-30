import 'dart:async';

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart' show AppleLogoPainter;

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../data/auth_repository.dart';
import '../../data/config.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/start/account_device_store.dart';

/// Where the sign-in stands (41-4 a / b / c, and the moment after).
enum SignInPhase {
  /// Both doors open (41-4a).
  idle,

  /// A door was tapped and the round trip is under way: that button holds a spinner, the other is at 40 % (41-4c).
  waiting,

  /// It did not work: a line in ink over the buttons, both buttons live again (41-4b).
  failed,

  /// Signed in: the spinner turned into a sage check for [StartMotion.signedInCheck] — then the screen moves on.
  succeeded,
}

/// THE SIGN-IN ON THE SPLASH (frame 41-4) — two doors, Apple over Google, and the legal line under them.
///
/// It stands at the foot of the start screen, `left 24 · right 24 · bottom 40`, and comes up after the splash: fade +
/// 12 px, [StartMotion.buttonsFade], [StartMotion.buttonsStep] apart (Apple → Google → the line). There is no anonymous
/// start and there will be none («безымянного старта нет»): a door is the only way in.
///
/// The panel talks to the auth controller itself — the dev door must live in exactly this file (see
/// `test/data/dev_login_release_guard_test.dart`) — and reports [onSignedIn] once the check has been shown.
class SignInPanel extends ConsumerStatefulWidget {
  const SignInPanel({
    super.key,
    required this.visible,
    required this.onSignedIn,
    required this.onTerms,
    required this.onPrivacy,
  });

  /// False — nothing is drawn yet (the splash is still playing); true — the panel fades up, once.
  final bool visible;

  /// The check has been shown; the screen may move on.
  final ValueChanged<AppUser> onSignedIn;

  final VoidCallback onTerms;
  final VoidCallback onPrivacy;

  /// The goldens show the panel as the phone's release build draws it — without the debug-only QA door.
  @visibleForTesting
  static bool debugDevDoor = true;

  @override
  ConsumerState<SignInPanel> createState() => _SignInPanelState();
}

class _SignInPanelState extends ConsumerState<SignInPanel> with SingleTickerProviderStateMixin {
  /// Made in [initState], not lazily: a panel that never came up (the account signed in some other way, the gate moved
  /// on) must not first create its ticker in [dispose].
  late final AnimationController _enter;

  SignInPhase _phase = SignInPhase.idle;

  /// The door that was tapped — it holds the spinner, then the check.
  SignInDoor? _pressed;

  /// The dev door (debug builds) waits like the others but has no row of its own to hold a spinner.
  bool _devPressed = false;

  @override
  void initState() {
    super.initState();
    _enter = AnimationController(vsync: this, duration: StartMotion.buttonsFade + StartMotion.buttonsStep * 2);
    if (widget.visible) _enter.forward();
  }

  @override
  void didUpdateWidget(SignInPanel old) {
    super.didUpdateWidget(old);
    if (widget.visible && !old.visible) _enter.forward();
  }

  @override
  void dispose() {
    _enter.dispose();
    super.dispose();
  }

  Future<void> _go(SignInDoor? door, Future<void> Function(AuthController auth) signIn) async {
    if (_phase == SignInPhase.waiting || _phase == SignInPhase.succeeded) return;
    AppHaptics.light();
    setState(() {
      _phase = SignInPhase.waiting;
      _pressed = door;
      _devPressed = door == null;
    });
    final auth = ref.read(authControllerProvider.notifier);
    await signIn(auth);
    if (!mounted) return;
    final result = ref.read(authControllerProvider);
    final user = result.value;
    if (user != null) {
      AppHaptics.success();
      setState(() => _phase = SignInPhase.succeeded);
      await Future<void>.delayed(StartMotion.signedInCheck);
      if (mounted) widget.onSignedIn(user);
      return;
    }
    final error = result.error;
    // Closing Apple's or Google's own sheet is a choice, not a failure: back to the two doors, no line.
    final cancelled = error is AuthException && error.code == AuthError.cancelled;
    if (!cancelled) AppHaptics.warning();
    setState(() {
      _phase = cancelled ? SignInPhase.idle : SignInPhase.failed;
      _pressed = null;
      _devPressed = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    if (!widget.visible) return const SizedBox.shrink();

    final reduced = MediaQuery.disableAnimationsOf(context);
    Widget rise(int step, Widget child) => AnimatedBuilder(
      animation: _enter,
      child: child,
      builder: (context, child) {
        final start = StartMotion.buttonsStep * step;
        final total = _enter.duration!;
        final from = start.inMicroseconds / total.inMicroseconds;
        final to = (start + StartMotion.buttonsFade).inMicroseconds / total.inMicroseconds;
        final t = reduced ? 1.0 : StartMotion.ease.transform(((_enter.value - from) / (to - from)).clamp(0.0, 1.0));
        return Opacity(
          opacity: t,
          child: Transform.translate(offset: Offset(0, (1 - t) * StartMotion.buttonsRise), child: child),
        );
      },
    );

    final waiting = _phase == SignInPhase.waiting || _phase == SignInPhase.succeeded;
    SignInButtonState stateOf(SignInDoor door) {
      if (!waiting) return SignInButtonState.ready;
      if (_pressed != door) return SignInButtonState.dimmed;
      return _phase == SignInPhase.succeeded ? SignInButtonState.done : SignInButtonState.busy;
    }

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (_phase == SignInPhase.failed)
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: Text(l.startSignInFailed, key: const ValueKey('sign-in-failed'), style: AppTextStart.signInError),
          ),
        rise(
          0,
          SignInButton(
            key: const ValueKey('sign-in-apple'),
            door: SignInDoor.apple,
            label: l.startSignInApple,
            state: stateOf(SignInDoor.apple),
            onTap: () => _go(SignInDoor.apple, (auth) => auth.signInWithApple()),
          ),
        ),
        const SizedBox(height: 12),
        rise(
          1,
          SignInButton(
            key: const ValueKey('sign-in-google'),
            door: SignInDoor.google,
            label: l.startSignInGoogle,
            state: stateOf(SignInDoor.google),
            onTap: () => _go(SignInDoor.google, (auth) => auth.signIn()),
          ),
        ),
        // The QA door — DEBUG BUILDS ONLY. `kDevLoginEnabled` is a compile-time `kDebugMode`, so this branch and the
        // widget behind it are folded out of a release build entirely (the canonical device build is `--release`). It
        // is here because neither door above can be completed on a simulator, which is the only place QA can run the
        // app. Deliberately unlocalised: it is not product copy and it must not enter the ARB deck.
        if (kDevLoginEnabled) ...[
          if (SignInPanel.debugDevDoor) ...[
            const SizedBox(height: 12),
            _DevLoginButton(
              email: kDevLoginEmail,
              busy: _devPressed && waiting,
              onTap: () => _go(null, (auth) => auth.signInWithDev(kDevLoginEmail)),
            ),
          ],
        ],
        const SizedBox(height: 16),
        rise(2, _LegalLine(onTerms: widget.onTerms, onPrivacy: widget.onPrivacy)),
      ],
    );
  }
}

/// How a door looks right now (41-4 a / c, and the check after).
enum SignInButtonState { ready, busy, dimmed, done }

/// ONE SIGN-IN DOOR (41-4): 56 tall, radius 16, the provider's mark 20 and the label Inter 17/600, 10 apart.
///
/// Apple is the charcoal button with the paper mark; Google is the paper card with an ink outline and the four-colour
/// «G». While the round trip runs the tapped one presses to 0.98 at 60 % with a spinner in the mark's place and the
/// other steps back to 40 %; signed in — the spinner becomes a sage check.
class SignInButton extends StatelessWidget {
  const SignInButton({
    super.key,
    required this.door,
    required this.label,
    required this.onTap,
    this.state = SignInButtonState.ready,
  });

  final SignInDoor door;
  final String label;
  final VoidCallback onTap;
  final SignInButtonState state;

  static const height = 56.0;
  static const radius = 16.0;
  static const mark = 20.0;

  @override
  Widget build(BuildContext context) {
    final dark = door == SignInDoor.apple;
    final ink = dark ? AppColors.paper : AppColors.ink;
    final pressed = state == SignInButtonState.busy || state == SignInButtonState.done;
    final Widget leading = switch (state) {
      SignInButtonState.busy => SizedBox.square(
        dimension: mark,
        child: CircularProgressIndicator(
          key: const ValueKey('sign-in-spinner'),
          strokeWidth: 2,
          color: ink,
          backgroundColor: ink.withValues(alpha: .3),
        ),
      ),
      SignInButtonState.done => Icon(
        LucideIcons.check,
        key: const ValueKey('sign-in-check'),
        size: mark,
        color: dark ? AppColors.sessionSageOnInk : AppColors.verdictKnown,
      ),
      _ => dark
          ? const SizedBox.square(dimension: mark, child: CustomPaint(painter: _AppleMark()))
          : SvgPicture.asset('assets/brand/google_g.svg', width: mark, height: mark),
    };

    return Semantics(
      button: true,
      label: label,
      enabled: state == SignInButtonState.ready,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: state == SignInButtonState.ready ? onTap : null,
        child: AnimatedScale(
          scale: pressed ? StartMotion.buttonPressScale : 1,
          duration: StartMotion.buttonPress,
          child: AnimatedOpacity(
            opacity: switch (state) {
              SignInButtonState.busy => .6,
              SignInButtonState.dimmed => .4,
              _ => 1,
            },
            duration: StartMotion.buttonPress,
            child: Container(
              height: height,
              decoration: BoxDecoration(
                color: dark ? AppColors.windowInk : AppColors.paper,
                borderRadius: BorderRadius.circular(radius),
                border: dark ? null : Border.all(color: AppColors.ink),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  leading,
                  const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTextStart.signInButton.copyWith(color: ink),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Apple's mark in paper — the painter `sign_in_with_apple` ships, so the logo is Apple's own outline.
class _AppleMark extends CustomPainter {
  const _AppleMark();

  static const _logo = AppleLogoPainter(color: AppColors.paper);

  @override
  void paint(Canvas canvas, Size size) {
    // The logo is taller than wide (≈ 0.82 : 1) — fit it in the 20 box the way the canvas's placeholder square sits.
    const aspect = 0.82;
    final h = size.height * .9;
    final w = h * aspect;
    canvas.save();
    canvas.translate((size.width - w) / 2, (size.height - h) / 2 - size.height * .03);
    _logo.paint(canvas, Size(w, h));
    canvas.restore();
  }

  @override
  bool shouldRepaint(_AppleMark old) => false;
}

/// «Продолжая, ты принимаешь Правила и Конфиденциальность» — 13/18 grey, the two documents in brass and tappable.
class _LegalLine extends StatefulWidget {
  const _LegalLine({required this.onTerms, required this.onPrivacy});

  final VoidCallback onTerms;
  final VoidCallback onPrivacy;

  @override
  State<_LegalLine> createState() => _LegalLineState();
}

class _LegalLineState extends State<_LegalLine> {
  late final _terms = TapGestureRecognizer()..onTap = widget.onTerms;
  late final _privacy = TapGestureRecognizer()..onTap = widget.onPrivacy;

  @override
  void dispose() {
    _terms.dispose();
    _privacy.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    // One sentence with two slots, so a language that orders it differently keeps the links where its words are.
    final sentence = l.startLegal('\u0000', '\u0001');
    final spans = <InlineSpan>[];
    var rest = sentence;
    while (rest.isNotEmpty) {
      final t = rest.indexOf('\u0000');
      final p = rest.indexOf('\u0001');
      final next = [t, p].where((i) => i >= 0).fold<int?>(null, (a, b) => a == null || b < a ? b : a);
      if (next == null) {
        spans.add(TextSpan(text: rest));
        break;
      }
      if (next > 0) spans.add(TextSpan(text: rest.substring(0, next)));
      final isTerms = next == t;
      spans.add(TextSpan(
        text: isTerms ? l.startLegalTerms : l.startLegalPrivacy,
        style: AppTextStart.legalLink,
        recognizer: isTerms ? _terms : _privacy,
      ));
      rest = rest.substring(next + 1);
    }
    return Text.rich(
      TextSpan(style: AppTextStart.legal, children: spans),
      key: const ValueKey('sign-in-legal'),
      textAlign: TextAlign.center,
    );
  }
}

/// The QA dev-login button (debug builds only — see [kDevLoginEnabled]).
///
/// Deliberately ugly next to the two real buttons: dashed border, the account spelled out. It should never be mistaken
/// for a shipped surface in a screenshot, and the address is on it so a QA screenshot always says which account the run
/// happened under.
class _DevLoginButton extends StatelessWidget {
  const _DevLoginButton({required this.email, required this.onTap, this.busy = false});
  final String email;
  final VoidCallback onTap;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.ground,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(SignInButton.radius),
        side: const BorderSide(color: AppColors.dashed),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: busy ? null : onTap,
        child: SizedBox(
          height: 44,
          child: Center(
            child: Text(
              busy ? 'Dev login · …' : 'Dev login · $email',
              style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary),
            ),
          ),
        ),
      ),
    );
  }
}
