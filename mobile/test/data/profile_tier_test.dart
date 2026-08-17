import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';

/// Regression: the premium `tier` must survive the offline cache round-trip (toJson → fromJson).
/// Dropping it made the premium-gated dialog button vanish after every restart/refresh until a
/// re-login.
void main() {
  test('Profile.toJson persists tier so a cached premium user stays premium', () {
    final premium = Profile(
      nativeLanguage: 'ru',
      targetLanguage: 'en',
      cefrLevel: 'B1',
      dailyGoal: 20,
      tier: 'premium',
    );
    final round = Profile.fromJson(premium.toJson());
    expect(round.tier, 'premium');
    expect(round.isPremium, isTrue);
  });

  test('a user JSON round-trip keeps the profile tier (the restore() path)', () {
    final user = AppUser(
      id: '01ABC',
      name: 'D',
      profile: Profile(
        nativeLanguage: 'ru',
        targetLanguage: 'en',
        cefrLevel: 'B1',
        dailyGoal: 20,
        tier: 'premium',
      ),
    );
    final restored = AppUser.fromJson(user.toJson());
    expect(restored.profile?.isPremium, isTrue);
  });

  test('Profile parses and round-trips the F19 timezone, defaulting to UTC', () {
    final parsed = Profile.fromJson({
      'native_language': 'ru',
      'target_language': 'en',
      'cefr_level': 'B1',
      'daily_goal': 20,
      'timezone': 'Europe/Kyiv',
    });
    expect(parsed.timezone, 'Europe/Kyiv');
    expect(Profile.fromJson(parsed.toJson()).timezone, 'Europe/Kyiv');

    // Absent in the payload → UTC fallback (the server applies the same default).
    final noTz = Profile.fromJson({
      'native_language': 'ru',
      'target_language': 'en',
      'cefr_level': 'B1',
      'daily_goal': 20,
    });
    expect(noTz.timezone, 'UTC');
  });
}
