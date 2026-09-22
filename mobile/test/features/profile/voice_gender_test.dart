import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/entry/voice_gender_sheet.dart';
import 'package:eng_std/features/profile/build_stamp.dart';
import 'package:eng_std/features/profile/profile_screen.dart';

import '../../support/plan_goldens.dart';

/// ГОЛОС СВОИХ РЕПЛИК (кадр 38-1, наряд FIX-3 §6) — спрашивается ОДИН РАЗ, пока пол в профиле не сказан, и меняется
/// в профиле. Лист — два варианта и одна кнопка: выбор уходит на сервер (`PUT /profile` `gender`), и больше вопроса
/// нет.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget app(Widget home, {String? gender, List<Map<String, dynamic>>? sent}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);

        return db;
      }),
      authControllerProvider.overrideWith(() => _Auth(gender: gender, sent: sent ?? [])),
      statsProvider.overrideWith((ref) => const Stream.empty()),
      clientBuildProvider.overrideWithValue((sha: '', at: '')),
      backendCommitProvider.overrideWith((ref) async => 'def5678'),
    ],
    child: planGoldenShell(home),
  );

  // ПРАВИЛО (кадр 38-1): лист — заголовок вопросом, две плашки 132 («Мужской · ниже и спокойнее», «Женский · выше и
  // мягче»), строка «Можно поменять в профиле» и одна кнопка «Дальше». Выбранная плашка — шалфей с галкой; по
  // умолчанию выбран мужской (сервер до ответа озвучивает мужским).
  // ЛОВИТ: лист без «Можно поменять в профиле» (страх ошибки), два выбранных варианта и кнопку, уходящую без выбора.
  testWidgets('38-1: две плашки, выбранная — шалфей с галкой, «Дальше» отдаёт выбор', (tester) async {
    String? chosen;
    await tester.pumpWidget(app(Builder(
      builder: (context) => Center(
        child: TextButton(
          onPressed: () async => chosen = await showVoiceGenderSheet(context),
          child: const Text('open'),
        ),
      ),
    )));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('voice-gender-sheet')), findsOneWidget);
    expect(find.text('Каким голосом озвучивать твои реплики?'), findsOneWidget);
    expect(find.text('Мужской'), findsOneWidget);
    expect(find.text('ниже и спокойнее'), findsOneWidget);
    expect(find.text('Женский'), findsOneWidget);
    expect(find.text('выше и мягче'), findsOneWidget);
    expect(find.text('Можно поменять в профиле'), findsOneWidget);
    expect(tester.getSize(find.byKey(const ValueKey('voice-$kVoiceMale'))).height, 132);
    expect(find.descendant(of: find.byKey(const ValueKey('voice-$kVoiceMale')), matching: find.byKey(const ValueKey('voice-chosen'))),
        findsOneWidget, reason: 'по умолчанию — мужской, как звучит сервер до ответа');
    expect(find.byKey(const ValueKey('voice-chosen')), findsOneWidget, reason: 'выбран ровно один');

    await tester.tap(find.byKey(const ValueKey('voice-$kVoiceFemale')));
    await tester.pump();
    expect(find.descendant(of: find.byKey(const ValueKey('voice-$kVoiceFemale')), matching: find.byKey(const ValueKey('voice-chosen'))),
        findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('voice-gender-next')));
    await tester.pumpAndSettle();
    expect(chosen, kVoiceFemale);
  });

  // ПРАВИЛО (наряд FIX-3 §6): лист, закрытый мимо «Дальше», НИЧЕГО не сохраняет — сервер продолжает озвучивать
  // мужским, и вопрос встанет в следующий раз.
  // ЛОВИТ: смахнутый лист, записавший мужской как ответ ученика.
  testWidgets('лист закрыт мимо «Дальше» — ответа нет', (tester) async {
    String? chosen = 'untouched';
    await tester.pumpWidget(app(Builder(
      builder: (context) => Center(
        child: TextButton(
          onPressed: () async => chosen = await showVoiceGenderSheet(context),
          child: const Text('open'),
        ),
      ),
    )));
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    Navigator.of(tester.element(find.byKey(const ValueKey('voice-gender-sheet')))).pop();
    await tester.pumpAndSettle();
    expect(chosen, isNull);
  });

  // ПРАВИЛО (наряд FIX-3 §6): в профиле стоит ряд «Голос своих реплик» — «не выбран», пока сервер не знает пола, и
  // имя голоса, когда знает; тап открывает ТОТ ЖЕ лист, и выбор уходит на сервер полем `gender`.
  // ЛОВИТ: вопрос, который больше негде поменять, и ряд, печатающий пол сырым словом сервера.
  testWidgets('профиль: ряд голоса — «не выбран», лист меняет его полем gender', (tester) async {
    final sent = <Map<String, dynamic>>[];
    await tester.pumpWidget(app(const ProfileScreen(pushed: true), sent: sent));
    await tester.pumpAndSettle();

    expect(find.text('Голос своих реплик'), findsOneWidget);
    expect(find.text('не выбран'), findsOneWidget);

    await tester.tap(find.text('Голос своих реплик'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('voice-$kVoiceFemale')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('voice-gender-next')));
    await tester.pumpAndSettle();

    expect(sent, [
      {'gender': kVoiceFemale},
    ]);
    expect(find.text('Женский'), findsOneWidget, reason: 'ряд говорит выбранный голос словом словаря');
  });

  // ПРАВИЛО (наряд FIX-3 §8, зал: «Выйти» в профиле оставляло чёрный экран): без пользователя вытолкнутый профиль
  // уходит сам — под ним экран входа, а не пустой непрозрачный маршрут.
  // ЛОВИТ: чёрный экран после «Выйти».
  testWidgets('вышел из аккаунта — профиль уходит сам, чёрного экрана нет', (tester) async {
    await tester.pumpWidget(app(const _PushHome()));
    await tester.tap(find.text('профиль'));
    await tester.pumpAndSettle();
    expect(find.byType(ProfileScreen), findsOneWidget);

    final ref = ProviderScope.containerOf(tester.element(find.byType(ProfileScreen)));
    await ref.read(authControllerProvider.notifier).signOut();
    await tester.pumpAndSettle();

    expect(find.byType(ProfileScreen), findsNothing, reason: 'маршрут ушёл сам');
    expect(find.text('профиль'), findsOneWidget, reason: 'под ним — экран, с которого пришли');
  });
}

/// The screen the profile is pushed from — a button, as the tab's avatar is.
class _PushHome extends StatelessWidget {
  const _PushHome();

  @override
  Widget build(BuildContext context) => Center(
    child: TextButton(
      onPressed: () => Navigator.of(context).push<void>(
        MaterialPageRoute(builder: (_) => const ProfileScreen(pushed: true)),
      ),
      child: const Text('профиль'),
    ),
  );
}

class _Auth extends AuthController {
  _Auth({required this.gender, required this.sent});

  final String? gender;
  final List<Map<String, dynamic>> sent;

  @override
  Future<AppUser?> build() async => _user(gender);

  @override
  Future<void> updateProfile(Map<String, dynamic> changes) async {
    sent.add(changes);
    state = AsyncData(_user(changes['gender'] as String?));
  }

  @override
  Future<void> signOut() async {
    state = const AsyncData(null);
  }

  AppUser _user(String? gender) => AppUser(
    id: 'u1',
    name: 'Денис',
    email: 'haveystar95@gmail.com',
    profile: Profile(nativeLanguage: 'ru', targetLanguage: 'en', cefrLevel: 'B1', dailyGoal: 20, gender: gender),
  );
}
