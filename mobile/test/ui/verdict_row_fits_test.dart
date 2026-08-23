import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// Rows whose labels have to fit, at real phone widths, in BOTH locales.
///
/// It started with the triage row of three (QA-OBS-3): «Don't know» was drawn as «Don't k…» —
/// the row gives each button a third of the screen, the label was pinned to one line, and the
/// longest label in this app is English, which is exactly why the Russian-first eye never caught
/// it. The QAB-1 pass found the same class of bug in four more places, so the file grew into the
/// guard for all of them: the home pair (QA-OBS-10), the cloze aids (QA-OBS-29), the ladder's five
/// captions (QA-OBS-27) and the session counter (QA-OBS-28).
///
/// What is pinned is never a pixel width — only the thing that matters: no label is ever cut, and
/// nothing overflows its row.
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

  // ── the rest of the rows found by the QAB-1 pass ────────────────────────────

  Widget host(Locale locale, Widget Function(AppLocalizations l) body) => MaterialApp(
        locale: locale,
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          body: Builder(builder: (context) => body(AppLocalizations.of(context))),
        ),
      );

  /// Sets the view to a phone width for the length of one test.
  void atWidth(WidgetTester tester, Size size) {
    tester.view.physicalSize = size * tester.view.devicePixelRatio;
    addTearDown(tester.view.reset);
  }

  /// Every label drawn under [root] that is not an icon glyph, with the «did not fit» flag.
  List<({String text, bool cut})> labelsUnder(WidgetTester tester, Finder root) => [
        for (final element
            in find.descendant(of: root, matching: find.byType(RichText)).evaluate())
          if (!_isIconGlyph((element.widget as RichText).text.toPlainText()))
            (
              text: (element.widget as RichText).text.toPlainText(),
              cut: (element.renderObject as RenderParagraph).didExceedMaxLines,
            ),
      ];

  for (final locale in const [Locale('en'), Locale('ru')]) {
    final lang = locale.languageCode;

    /// QA-OBS-10 — «Тренировка по теме» ran a RenderFlex overflow stripe across the home screen
    /// while English «Session by topic» fitted. The pair is the row as _PoolEntries lays it out.
    testWidgets('$lang: the home pair fits, and the two buttons stay the same height',
        (tester) async {
      atWidth(tester, const Size(375, 667)); // the narrowest we ship to
      await tester.pumpWidget(host(locale, (l) => Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                      child: QuietButton(
                          label: l.myWordsTitle, icon: LucideIcons.bookMarked, onPressed: () {})),
                  const SizedBox(width: AppSpacing.s8),
                  Expanded(
                      child: QuietButton(
                          label: l.topicSessionAction, icon: LucideIcons.layers, onPressed: () {})),
                ],
              ),
            ),
          )));
      await tester.pumpAndSettle();

      final found = labelsUnder(tester, find.byType(QuietButton));
      expect(found, hasLength(2));
      for (final label in found) {
        expect(label.cut, isFalse, reason: '«${label.text}» is cut off');
      }
      final heights = tester.widgetList<QuietButton>(find.byType(QuietButton)).map(
            (b) => tester.getSize(find.byWidget(b)).height,
          );
      expect(heights.toSet(), hasLength(1), reason: 'one row of equals, not a tall one and a short one');
      expect(tester.takeException(), isNull);
    });

    /// QA-OBS-29 — «Подсказка: первая буква» overflowed its half of the cloze card.
    testWidgets('$lang: the cloze aids fit their half of the card', (tester) async {
      atWidth(tester, const Size(375, 667));
      await tester.pumpWidget(host(locale, (l) => Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(child: QuietButton(label: l.sessionHintFirstLetter, onPressed: () {})),
                  const SizedBox(width: AppSpacing.s12),
                  Expanded(child: QuietButton(label: l.sessionDontRemember, onPressed: () {})),
                ],
              ),
            ),
          )));
      await tester.pumpAndSettle();

      for (final label in labelsUnder(tester, find.byType(QuietButton))) {
        expect(label.cut, isFalse, reason: '«${label.text}» is cut off');
      }
      expect(tester.takeException(), isNull);
    });

    /// QA-OBS-27 — «знакомство» was drawn «знакомст…». The captions live in a fifth of the sheet
    /// each: screen − the card's 22pt margins − the sheet's own 16pt padding.
    testWidgets('$lang: no ladder caption is cut in its fifth of the card', (tester) async {
      atWidth(tester, const Size(375, 667));
      await tester.pumpWidget(host(locale, (l) => Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.s22 + AppSpacing.s16),
            child: LadderTrack(
              step: 3,
              labels: [l.ladderStep0, l.ladderStep1, l.ladderStep3, l.ladderStep4, l.ladderStep5],
            ),
          )));
      await tester.pumpAndSettle();

      final captions = find.descendant(of: find.byType(LadderTrack), matching: find.byType(Text));
      expect(captions, findsNWidgets(5));
      for (final element in captions.evaluate()) {
        final text = (element.widget as Text).data!;
        final drawn = tester.getRect(find.byWidget(element.widget as Text));
        final column = tester.getSize(find.byType(LadderTrack)).width / 5;
        // Drawn width, not natural width — the FittedBox scales a long caption down, and that is
        // the mechanism under test.
        expect(drawn.width, lessThanOrEqualTo(column),
            reason: '«$text» spills out of its column');
        expect((element.renderObject as RenderParagraph).didExceedMaxLines, isFalse,
            reason: '«$text» is cut instead of shrunk');
      }
      expect(tester.takeException(), isNull);
    });

    /// QA-OBS-28 — «1 из 14» / «1 of 12» broke onto a second line the moment the denominator went
    /// double-digit, because the counter sat in a 44pt-wide box.
    testWidgets('$lang: the session counter stays on one line at a two-digit total', (tester) async {
      atWidth(tester, const Size(375, 667));
      await tester.pumpWidget(host(locale, (l) => Padding(
            padding: const EdgeInsets.symmetric(horizontal: AppSpacing.screenH),
            child: Row(
              children: [
                const SizedBox(width: AppSpacing.minTap, height: AppSpacing.minTap),
                Expanded(
                  child: Text(l.sessionPhaseAssemble,
                      textAlign: TextAlign.center, style: AppTextExercise.sessionHeader),
                ),
                ConstrainedBox(
                  constraints: const BoxConstraints(minWidth: AppSpacing.minTap),
                  child: Text(
                    l.triageCounter(1, 14),
                    maxLines: 1,
                    softWrap: false,
                    textAlign: TextAlign.right,
                    style: AppTextExercise.sessionHeader,
                  ),
                ),
              ],
            ),
          )));
      await tester.pumpAndSettle();

      final counter = find.byWidgetPredicate(
          (w) => w is Text && w.data != null && w.data!.contains('14'));
      expect(counter, findsOneWidget);
      final paragraph = tester.renderObject<RenderParagraph>(
          find.descendant(of: counter, matching: find.byType(RichText)));
      expect(paragraph.didExceedMaxLines, isFalse);
      expect(tester.getSize(counter).height, lessThan(AppTextExercise.sessionHeader.fontSize! * 2),
          reason: 'the counter wrapped to a second line');
      expect(tester.takeException(), isNull);
    });
  }
}
