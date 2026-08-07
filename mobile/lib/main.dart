import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'theme/theme.dart';
import 'data/locale_controller.dart';
import 'data/providers.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';
import 'features/onboarding/onboarding_screen.dart';
import 'l10n/app_localizations.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
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
    // Locale resolution (A3.0): stored override → device → ru fallback. The
    // override is applied here; device→fallback lives in [resolveLocale].
    final option = ref.watch(localeControllerProvider).asData?.value ?? UiLanguageOption.system;
    return MaterialApp(
      title: 'Eng Std',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      locale: LocaleController.overrideLocale(option),
      supportedLocales: kSupportedLocales,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      localeResolutionCallback: (device, supported) => resolveLocale(device, supported),
      home: const _AuthGate(),
    );
  }
}

/// Routes between the login screen and the app based on auth state.
class _AuthGate extends ConsumerWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
  Widget build(BuildContext context) => const Scaffold(body: Center(child: CircularProgressIndicator()));
}
