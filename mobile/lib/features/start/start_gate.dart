import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/brand/wordmark.dart';
import 'package:eng_std/ui/brand/wordmark_metrics.dart';

import '../../data/app_settings.dart';
import '../../data/config.dart';
import '../../data/models.dart';
import '../../data/providers.dart';
import '../../data/start/account_device_store.dart';
import '../home/home_screen.dart';
import 'intro/intro_screen.dart';
import 'splash_choreography.dart';
import 'start_screen.dart';

/// WHAT THE APP SHOWS FROM THE FIRST FRAME ON (work order CLIENT-START §§1–3) — the splash, the sign-in on it, the five
/// «why» sheets once per account on this phone, the app; and back to the sign-in when the account leaves.
///
/// - No session: the splash plays whole (41-1 a → b → c) and becomes the sign-in (41-4). Signed in → the sheets (first
///   time this account signs in on this phone) or the app.
/// - A session: the splash plays a → b and waits for the server's first answer — [StartMotion.serverCap] at most, no
///   indicator — then dissolves into the app, which is already drawn underneath. A 401 on that answer means the session
///   is gone: the sign-in.
/// - «Выйти», a deleted account, a session refused later: the sign-in (41-4a) at once.
class StartGate extends ConsumerStatefulWidget {
  const StartGate({super.key, this.app = _home});

  /// The app under the start — the tab shell; a test puts a stand-in here (the shell starts syncs and queues).
  final Widget Function(AppUser user, int initialTab) app;

  static Widget _home(AppUser user, int initialTab) => HomeScreen(key: ValueKey('home-${user.id}'), initialTab: initialTab);

  @override
  ConsumerState<StartGate> createState() => _StartGateState();
}

enum _Stage { splash, sheets, app }

class _StartGateState extends ConsumerState<StartGate> {
  /// Null until the stored session has been read.
  SplashMode? _mode;
  _Stage _stage = _Stage.splash;

  /// The layer on top is dissolving away; the next one is already under it.
  bool _leaving = false;

  /// The account the sheets are shown to.
  AppUser? _sheetsUser;
  bool _sheetsLeaving = false;

  /// The tab the app opens on: «План» after the sheets (41-2: «оба → вкладка «План»»).
  int _homeTab = 0;

  @override
  Widget build(BuildContext context) {
    // Start the on-disk image cache. `read`, not `watch`: nothing on screen depends on it — until it is ready images
    // load from the network as before — and subscribing would rebuild the whole tree when a disk scan finishes.
    ref.read(imageDiskCacheProvider);
    // «Звуки в сессии» из профиля — в единственный сервис звука; ниже никто не решает сам.
    ref.watch(soundsEnabledProvider);

    ref.listen(authControllerProvider, (previous, next) => _onAuth(next));
    final auth = ref.watch(authControllerProvider);
    _mode ??= _modeFor(auth);
    final user = auth.value;
    final mode = _mode;
    final splash = _stage == _Stage.splash;
    final sheetsUser = _sheetsUser;
    // The layers, bottom to top. The one that is about to show is built under the one dissolving away, so the dissolve
    // reveals a screen already drawn. A session's app is built under its splash from the start: the splash waits for
    // the server anyway, and the tabs start their own reads meanwhile.
    final showHome = user != null &&
        (_stage == _Stage.app || (splash && sheetsUser == null && (_leaving || mode == SplashMode.repeat)));

    final layers = <Widget>[
      if (showHome) widget.app(user, _homeTab),
      if (sheetsUser != null)
        IgnorePointer(
          ignoring: _sheetsLeaving || splash,
          child: AnimatedOpacity(
            opacity: _sheetsLeaving ? 0 : 1,
            duration: StartMotion.sheetsOut,
            child: IntroScreen(key: ValueKey('sheets-${sheetsUser.id}'), onDone: () => _sheetsDone(sheetsUser)),
          ),
        ),
      if (splash)
        IgnorePointer(
          ignoring: _leaving,
          child: mode == null
              ? const _LaunchLookalike()
              : StartScreen(
                  mode: mode,
                  leaving: _leaving,
                  onPlayed: mode == SplashMode.repeat ? _repeatPlayed : null,
                  onSignedIn: _signedIn,
                  onTerms: () => _openDocument(AppConfig.termsUrl),
                  onPrivacy: () => _openDocument(AppConfig.privacyUrl),
                ),
        ),
    ];
    return ColoredBox(color: AppColors.ground, child: Stack(fit: StackFit.expand, children: layers));
  }

  SplashMode? _modeFor(AsyncValue<AppUser?> auth) {
    if (auth.isLoading && !auth.hasValue) return null;
    return auth.value == null ? SplashMode.first : SplashMode.repeat;
  }

  void _onAuth(AsyncValue<AppUser?> next) {
    if (!mounted) return;
    // The session was read — the splash knows which way to play.
    if (_mode == null) {
      final mode = _modeFor(next);
      if (mode != null) setState(() => _mode = mode);
      return;
    }
    // The account left — «Выйти», deleted, or the server refused the stored session. The sign-in, at once. A sign-in in
    // flight (loading, or its own error) is the panel's business and changes nothing here.
    if (next.isLoading || next.hasError) return;
    if (next.value == null && (_stage != _Stage.splash || _mode == SplashMode.repeat)) {
      setState(() {
        _stage = _Stage.splash;
        _mode = SplashMode.signedOut;
        _leaving = false;
        _sheetsUser = null;
        _sheetsLeaving = false;
        _homeTab = 0;
      });
    }
  }

  /// A session's splash has played a → b: into the app once the server has answered — at [StartMotion.serverCap] from
  /// the start at the latest (the splash has already taken [StartMotion.repeatLaunch] of it), and then the app opens on
  /// what it saved («офлайн-состояние»).
  Future<void> _repeatPlayed() async {
    await Future.any([
      ref.read(authControllerProvider.notifier).firstAnswer,
      Future<void>.delayed(StartMotion.serverCap - StartMotion.repeatLaunch),
    ]);
    if (!mounted || _stage != _Stage.splash || _mode != SplashMode.repeat) return;
    if (ref.read(authControllerProvider).value == null) return; // refused — [_onAuth] already brought the sign-in
    _dissolveInto(_Stage.app);
  }

  /// The sign-in's check has been shown (41-4): the sheets for an account new on this phone, else the app.
  Future<void> _signedIn(AppUser user) async {
    final seen = await ref.read(accountDeviceStoreProvider).introSeen(user.id);
    if (!mounted) return;
    if (seen) {
      _dissolveInto(_Stage.app);
      return;
    }
    setState(() => _sheetsUser = user);
    _dissolveInto(_Stage.sheets);
  }

  void _dissolveInto(_Stage next) {
    setState(() => _leaving = true);
    Future<void>.delayed(StartMotion.dissolve, () {
      if (!mounted) return;
      setState(() {
        _leaving = false;
        _stage = next;
      });
    });
  }

  /// «Пропустить» / «Начать» (41-2) → the Plan tab: the sheet dissolves, the app shows under it.
  Future<void> _sheetsDone(AppUser user) async {
    await ref.read(accountDeviceStoreProvider).markIntroSeen(user.id);
    if (!mounted) return;
    setState(() {
      _homeTab = kPlanTabIndex;
      _stage = _Stage.app;
      _sheetsLeaving = true;
    });
    Future<void>.delayed(StartMotion.sheetsOut, () {
      if (!mounted) return;
      setState(() {
        _sheetsUser = null;
        _sheetsLeaving = false;
      });
    });
  }

  Future<void> _openDocument(String url) async {
    if (url.isEmpty) return;
    await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
  }
}

/// The first frame before the stored session is read — exactly what the launch screen shows, so there is nothing to
/// see change.
class _LaunchLookalike extends StatelessWidget {
  const _LaunchLookalike();

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, box) => Stack(
      children: [
        Positioned(
          left: (box.maxWidth - kRMarkImageWidth) / 2,
          top: (box.maxHeight - WordmarkGeometry.columnBottomPad - kWordmarkHeight) / 2,
          child: const WordmarkR(),
        ),
      ],
    ),
  );
}
