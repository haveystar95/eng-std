import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import 'entry_preview_data.dart';

/// ВХОД В ПЛАН, без сервера и без логина — серия «Вход v4» целиком, кадры V4·01…06б.
///
/// Третий харнесс рядом с `preview.dart` и `ladder_preview.dart`, и по той же причине: экраны
/// входа стоят за авторизацией, а посмотреть на них надо каждому, кто их правит. Данные здесь —
/// НЕ выдумка: три реплики разогрева, каркас и вводки сцен взяты из живого прогона наряда ENTRY-2
/// (`../backend2/docs/research/entry-2-run.md`), поэтому скрин пути показывает то, что модель
/// действительно написала.
///
///     flutter run -d <simulator-udid> --debug --target tool/entry_preview.dart
///
/// Задержки сохранены настоящие: разогрев «думает» полторы секунды, сборка — четыре, чтобы кадр
/// V4·05 было видно живьём, а не только на бумаге.
void main() {
  runApp(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(_PreviewApi()),
        authControllerProvider.overrideWith(_PreviewAuth.new),
      ],
      child: const _EntryPreviewApp(),
    ),
  );
}

class _PreviewAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
    id: '01CCCCCCCCCCCCCCCCCCCCCCCC1',
    name: 'Denis',
    email: 'you@example.com',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20),
  );
}

/// The live run's own answers, played back with the waits that make the frames real.
class _PreviewApi extends ApiClient {
  _PreviewApi() : super(TokenStore());

  LearningPlan get _plan =>
      LearningPlan.fromJson(jsonDecode(kEntryPreviewPlanJson) as Map<String, dynamic>);

  @override
  Future<ListenWarmup> listenWarmup({
    required String goalText,
    String targetLang = '',
    required String level,
  }) async {
    // Продолжения приходят быстро (их ждут на паузе набора), реплики — как настоящий вызов.
    await Future<void>.delayed(
      targetLang.isEmpty ? const Duration(milliseconds: 600) : const Duration(milliseconds: 1500),
    );

    return ListenWarmup(
      lines: targetLang.isEmpty ? const [] : kEntryPreviewLines,
      continuations: kEntryPreviewContinuations,
    );
  }

  @override
  Future<LearningPlan> createPlan({
    required String goalText,
    required String targetLang,
    required String level,
    required String? eventDate,
    required int minutesPerDay,
    List<ListenAnswer> listening = const [],
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 600));

    return _plan;
  }

  @override
  Future<LearningPlan> buildPlanOutline(String planId) async {
    await Future<void>.delayed(const Duration(seconds: 4));

    return _plan;
  }

  @override
  Future<LearningPlan> reschedulePlan(
    String planId, {
    int? minutesPerDay,
    String? eventDate,
    int? dropDayIndex,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 400));
    final raw = jsonDecode(kEntryPreviewPlanJson) as Map<String, dynamic>;
    if (eventDate != null) raw['event_date'] = eventDate;

    return LearningPlan.fromJson(raw);
  }
}

class _EntryPreviewApp extends StatelessWidget {
  const _EntryPreviewApp();

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    home: const PlanEntryScreen(),
  );
}
