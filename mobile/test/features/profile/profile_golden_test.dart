import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/profile/build_stamp.dart';
import 'package:eng_std/features/profile/profile_screen.dart';

import '../../support/plan_goldens.dart';

/// ВЕРСИЯ СБОРКИ НА ПРОФИЛЕ — снимок низа экрана (наряд PLAN-UI, §8).
///
/// Строка стоит внизу профиля ВСЕГДА, и приёмка начинается с неё: «ту ли сборку я смотрю».
/// Снимается двумя состояниями, потому что у неё их ровно два и второе — не поломка:
///
/// - сборка со скрипта: `--dart-define=BUILD_SHA=… BUILD_AT=…` → «клиент abc1234 · 10.09 23:40»;
/// - сборка руками (`flutter run`): констант нет → «клиент без метки». Честное «не знаю» вместо
///   пустоты или выдуманного SHA — иначе строке, ради которой всё и заведено, нельзя верить.
///
/// Серверная половина живая (`GET /health`) и в снимке подменена ответом; ошибку и ожидание той же
/// половины держат тесты `build_stamp_test.dart` — там это дешевле, чем кадром.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget profile({required ({String sha, String at}) client}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);

        return db;
      }),
      authControllerProvider.overrideWith(_ProfileAuth.new),
      statsProvider.overrideWith(
        (ref) => Stream.value(
          Stats(
            totalWords: 146,
            learned: 60,
            mastered: 82,
            dueToday: 0,
            reviewsTotal: 1240,
            streakDays: 12,
          ),
        ),
      ),
      clientBuildProvider.overrideWithValue(client),
      backendCommitProvider.overrideWith((ref) async => 'def5678'),
    ],
    child: planGoldenShell(const ProfileScreen(pushed: true)),
  );

  /// Низ профиля: строка версии — последняя на экране, и до неё надо доскроллить.
  Future<void> toBottom(WidgetTester tester) async {
    await tester.pumpAndSettle();
    await tester.dragUntilVisible(
      find.byType(BuildStampLine),
      find.byType(Scrollable).first,
      const Offset(0, -300),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('сборка со скрипта — хеш клиента, дата и хеш сервера', (tester) async {
    await expectPlanGolden(
      tester,
      profile(client: (sha: 'a1b2c3d', at: '10.09 23:40')),
      'profile/build-stamp-stamped',
      prime: toBottom,
    );
  });

  testWidgets('сборка мимо скрипта — «без метки», а не пустота', (tester) async {
    await expectPlanGolden(
      tester,
      profile(client: (sha: '', at: '')),
      'profile/build-stamp-unstamped',
      prime: toBottom,
    );
  });
}

class _ProfileAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(
    id: 'u1',
    name: 'Денис',
    email: 'haveystar95@gmail.com',
    profile: Profile(
      nativeLanguage: 'ru',
      targetLanguage: 'en',
      cefrLevel: 'B1',
      dailyGoal: 20,
    ),
  );
}
