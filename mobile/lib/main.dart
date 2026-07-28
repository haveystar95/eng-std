import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme.dart';
import 'data/providers.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';
import 'features/onboarding/onboarding_screen.dart';

void main() {
  runApp(const ProviderScope(child: EngStdApp()));
}

class EngStdApp extends StatelessWidget {
  const EngStdApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Eng Std',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
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
