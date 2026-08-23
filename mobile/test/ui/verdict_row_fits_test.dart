import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/verdict_button.dart';

/// The triage row of three, at real phone widths, in BOTH locales (QA-OBS-3).
///
/// «Don't know» was drawn as «Don't k…»: the row gives each button a third of the screen, the label
/// was pinned to one line, and the longest label in this app is English — «Не знаю» fitted with room
/// to spare, which is exactly why the Russian-first eye never caught it. What is pinned here is not
/// a pixel width but the only thing that matters: no label is ever cut.
/// A one-character string from a font's private use area — i.e. an [Icon], not a label.
bool _isIconGlyph(String text) =>
    text.runes.isNotEmpty && text.runes.every((r) => r >= 0xE000 && r <= 0xF8FF);

void main() {
  // The REAL font, not the test harness's default. flutter_test draws in Ahem, where every glyph is
  // a square em — «уверен» measures 6 × 13.5pt there and nothing short of a redesign fits. Inter is
  // bundled in this app precisely so type is not a guess, so the test loads it and measures what the
  // phone measures.
  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    final loader = FontLoader(AppFonts.inter);
    for (final file in ['Inter-Regular.ttf', 'Inter-SemiBold.ttf', 'Inter-Bold.ttf']) {
      loader.addFont(
          File('assets/fonts/$file').readAsBytes().then((b) => ByteData.sublistView(b)));
    }
    await loader.load();
  });

  /// The row as triage_screen.dart lays it out: three equal columns, 8pt apart, inside the screen's
  /// horizontal padding.
  Widget row(Locale locale) => MaterialApp(
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          body: Builder(builder: (context) {
            final l = AppLocalizations.of(context);
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
              child: Row(
                children: [
                  Expanded(
                    child: VerdictButton(
                        kind: VerdictKind.unknown, label: l.triageVerdictUnknown, minHeight: 56),
                  ),
                  const SizedBox(width: AppSpacing.s8),
                  Expanded(
                    child: VerdictButton(
                        kind: VerdictKind.unsure, label: l.triageVerdictUnsure, minHeight: 56),
                  ),
                  const SizedBox(width: AppSpacing.s8),
                  Expanded(
                    child: VerdictButton(
                        kind: VerdictKind.known, label: l.triageVerdictKnown, minHeight: 56),
                  ),
                ],
              ),
            );
          }),
        ),
      );

  /// Every label in the row, with the flag that says the text did not fit the lines it was given.
  List<({String text, bool cut})> labels(WidgetTester tester) => [
        for (final element in find
            .descendant(of: find.byType(VerdictButton), matching: find.byType(RichText))
            .evaluate())
          // The icon is a RichText too — one glyph from the icon font's private use area.
          if (!_isIconGlyph((element.widget as RichText).text.toPlainText()))
            (
              text: (element.widget as RichText).text.toPlainText(),
              cut: (element.renderObject as RenderParagraph).didExceedMaxLines,
            ),
      ];

  for (final (name, size) in [
    ('iPhone 17 Pro (402pt)', const Size(402, 874)),
    ('iPhone 16 (393pt)', const Size(393, 852)),
    ('the narrow one (375pt)', const Size(375, 667)),
  ]) {
    for (final locale in const [Locale('en'), Locale('ru')]) {
      testWidgets('$name / ${locale.languageCode}: every verdict fits whole', (tester) async {
        tester.view.physicalSize = size * tester.view.devicePixelRatio;
        tester.view.devicePixelRatio = tester.view.devicePixelRatio;
        addTearDown(tester.view.reset);

        await tester.pumpWidget(row(locale));
        await tester.pumpAndSettle();

        final found = labels(tester);
        expect(found, hasLength(3));
        for (final label in found) {
          expect(label.cut, isFalse, reason: '«${label.text}» is cut off at $name');
        }
        expect(tester.takeException(), isNull, reason: 'and nothing overflowed the row');
      });
    }
  }
}
