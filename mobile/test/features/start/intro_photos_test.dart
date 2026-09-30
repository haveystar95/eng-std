import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/start/intro/intro_pages.dart';
import 'package:eng_std/features/start/intro/intro_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// THE SHEETS' PHOTOS AT THE SIZE THEY ARE DRAWN (доработка CLIENT-START): the photos of a, c and d are 1536 × 2048 —
/// each is decoded by the side its box meets first under `BoxFit.cover`, and [IntroScreen] warms up exactly the images
/// the sheets draw.
void main() {
  Widget shell(Widget home) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    supportedLocales: const [Locale('ru'), Locale('en')],
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    home: home,
  );

  // ЛОВИТ: фото, декодированное целиком (12 МБ памяти на карточку в 160 pt), и прогрев мимо кэша — другим ключом, чем
  // у картинки листа: тогда фото листа всё равно приходит кадром позже.
  testWidgets('каждая картинка листов — по размеру показа на @3×; прогрев — теми же ключами', (tester) async {
    // The owner's phone: 390 × 844 @3×, a 47 status bar (under the canvas's 52 — the picture does not move down).
    tester.view
      ..devicePixelRatio = 3
      ..physicalSize = const Size(390, 844) * 3
      ..padding = const FakeViewPadding(top: 47 * 3, bottom: 34 * 3);
    addTearDown(tester.view.reset);

    late List<ImageProvider> warmed;
    await tester.pumpWidget(shell(Builder(builder: (context) {
      warmed = IntroPages.images(context);
      return const SizedBox();
    })));
    expect({
      for (final r in warmed.cast<ResizeImage>()) (r.imageProvider as AssetImage).assetName: (r.width, r.height),
    }, {
      for (final scene in ['airport', 'bank', 'interview', 'rent', 'doctor']) 'assets/intro/scene-$scene.jpg': (480, null),
      'assets/intro/doctor-office.jpg': (null, 1680),
      'assets/intro/cafe.jpg': (null, 1680),
      for (final cover in ['city', 'health', 'work']) 'assets/intro/cover-$cover.jpg': (336, null),
    });

    for (final page in [0, 2, 3, 4]) {
      await tester.pumpWidget(shell(IntroScreen(key: ValueKey(page), onDone: () {}, initialPage: page, still: true)));
      await tester.pump(const Duration(milliseconds: 100));
      final drawn = [for (final image in tester.widgetList<Image>(find.byType(Image))) image.image];
      expect(drawn, isNotEmpty, reason: 'sheet ${page + 1}');
      for (final image in drawn) {
        expect(warmed, contains(image), reason: 'sheet ${page + 1}: $image');
      }
    }
  });
}
