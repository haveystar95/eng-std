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
}
