import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'theme/theme.dart';
import 'data/app_settings.dart';
import 'data/deep_links.dart';
import 'data/locale_controller.dart';
import 'data/providers.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';
import 'features/onboarding/onboarding_screen.dart';
import 'features/profile/qa_report_button.dart';
import 'l10n/app_localizations.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  unawaited(DeepLinks.init());
  // Paper is a light background, so the status-bar content is dark (rule: the
  // reskinned screens have no AppBar to set this). Dark screens with an AppBar
  // (old tabs) reassert their own light overlay; the collection cover overrides
  // to light via an AnnotatedRegion over its photo.
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark, // Android
      statusBarBrightness: Brightness.light, // iOS: light bg → dark glyphs
    ),
  );
  runApp(const ProviderScope(child: EngStdApp()));
}

class EngStdApp extends ConsumerWidget {
  const EngStdApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Locale resolution: stored override → ЯЗЫК ПОДДЕРЖКИ ПАРЫ → device → ru fallback. The first
    // two are applied here; device→fallback lives in [resolveLocale].
    //
    // The support language sits above the device's because it answers the right question: the app
    // TALKS to the learner in the language of their pair («для ru→en это русский», канон диалога
    // §4), and the phone's language only says how they keep their system. The owner's phone is in
    // English, and that alone printed the whole plan contour in English — «said aloud», «rung A»,
    // «choose what you will say» — with every one of those strings correctly translated in
    // `app_ru.arb` all along (наряд DAY-2-FIX, Ч.1.2).
    final option = ref.watch(localeControllerProvider).asData?.value ?? UiLanguageOption.system;
    final supportLang = ref.watch(authControllerProvider).value?.profile?.nativeLanguage;
    return MaterialApp(
      title: 'Eng Std',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: LocaleController.localeFor(option, supportLang),
      supportedLocales: kSupportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      localeResolutionCallback: (device, supported) => resolveLocale(device, supported),
      // «ЖАЛОБА» ЖИВЁТ НАД ВСЕМ ПРИЛОЖЕНИЕМ (наряд DAY-GATE-1, Ч.0.5), а не на отдельных экранах:
      // нажимают её там, где что-то не так, и заранее известного списка таких мест нет. Обёртка
      // ставится через `builder`, чтобы попасть ВНУТРЬ навигатора — иначе снимок не поймал бы ни
      // одного вытолкнутого экрана. Кнопки нет ни у кого, кроме QA-аккаунта; решает сервер.
      builder: (context, child) => QaReportOverlay(child: child ?? const SizedBox.shrink()),
      home: const _AuthGate(),
    );
  }
}

/// Routes between the login screen and the app based on auth state.
class _AuthGate extends ConsumerWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Start the on-disk image cache. `read`, not `watch`: nothing on screen depends on it — until
    // it is ready images load from the network as before — and subscribing would rebuild the whole
    // tree when a disk scan finishes.
    ref.read(imageDiskCacheProvider);
    // «Звуки» из профиля — в единственный сервис звука и хаптики; ниже никто не решает сам.
    ref.watch(soundsEnabledProvider);
    final auth = ref.watch(authControllerProvider);

    return auth.when(
      loading: () => const _Splash(),
      error: (_, _) => const LoginScreen(),
      data: (user) => user == null ? const LoginScreen() : const _OnboardingGate(),
    );
  }
}

/// For a signed-in user, shows onboarding until it's completed on this device.
class _OnboardingGate extends ConsumerWidget {
  const _OnboardingGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final onboarded = ref.watch(onboardedProvider);
    return onboarded.when(
      loading: () => const _Splash(),
      error: (_, _) => const HomeScreen(),
      data: (done) => done ? const HomeScreen() : const OnboardingScreen(),
    );
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) =>
      const Scaffold(body: Center(child: CircularProgressIndicator()));
}
