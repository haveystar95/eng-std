import 'package:eng_std/features/training/session_screen.dart';
import 'package:flutter_test/flutter_test.dart';

/// «ЕЩЁ РАЗ» REPEATS THE SESSION THAT JUST ENDED — INCLUDING ITS SCOPE.
///
/// The summary's «Ещё раз» rebuilt the screen field by field and quietly left [onlyTermId] out, so
/// a drill of ONE word came back as a drill of the whole collection: a different session under a
/// button that promised the same one. From «Мои слова» there is not even a collection to fall back
/// to — the repeat would have been a build with no scope at all.
///
/// [SessionScreen.repeat] is the single answer to «what does a repeat carry», and this is the test
/// that a field added later is not forgotten there.
void main() {
  test('a one-word practice repeats THAT word', () {
    const screen = SessionScreen(title: 'towel', practice: true, onlyTermId: 'T1');

    expect(screen.repeat().onlyTermId, 'T1');
    expect(screen.repeat().practice, isTrue);
  });

  test('every field of the session rides along', () {
    const screen = SessionScreen(
      title: 'Отель',
      collectionId: 'c1',
      practice: true,
      learn: true,
      limit: 7,
      targetLang: 'en',
      onlyTermId: 'T1',
    );

    final again = screen.repeat();

    expect(again.title, screen.title);
    expect(again.collectionId, screen.collectionId);
    expect(again.practice, screen.practice);
    expect(again.learn, screen.learn);
    expect(again.limit, screen.limit);
    expect(again.targetLang, screen.targetLang);
    expect(again.onlyTermId, screen.onlyTermId);
  });

  test('a collection practice is unchanged — no scope is invented for it', () {
    const screen = SessionScreen(title: 'Отель', collectionId: 'c1', practice: true);

    expect(screen.repeat().collectionId, 'c1');
    expect(screen.repeat().onlyTermId, isNull);
  });
}
