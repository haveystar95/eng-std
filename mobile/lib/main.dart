import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'theme/theme.dart';
import 'data/app_identity.dart';
import 'data/deep_links.dart';
import 'data/locale_controller.dart';
import 'data/providers.dart';
import 'features/plan/plan_providers.dart';
import 'features/profile/qa_report_button.dart';
import 'features/start/start_gate.dart';
import 'l10n/app_localizations.dart';
import 'ui/native_text.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  unawaited(DeepLinks.init());
  // Which build on which phone — asked once, before the first request needs it (наряд CLIENT-FIX-4 §6).
  unawaited(AppIdentity.load());
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
    // THE NATIVE OF THE SERVER'S TEXTS (наряд CLIENT-22-1 §2): the held plan's — a plan keeps the native it was made in
    // when the profile's changes — else the profile's. Read without waking the tab (as [heldPlan] does).
    final planNative = ref.exists(planTabProvider)
        ? ref.watch(planTabProvider.select((s) => s.value?.plan?.nativeLang))
        : null;
    return MaterialApp(
      title: 'Ritora',
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
      builder: (context, child) => NativeLanguageScope(
        language: (planNative ?? '').isNotEmpty ? planNative! : (supportLang ?? 'ru'),
        child: QaReportOverlay(child: child ?? const SizedBox.shrink()),
      ),
      home: const StartGate(),
    );
  }
}
