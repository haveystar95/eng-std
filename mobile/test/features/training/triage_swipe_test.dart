import 'dart:math' as math;

import 'package:eng_std/data/models.dart';
import 'package:eng_std/features/training/triage_swipe.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';

const _v0 = Velocity(pixelsPerSecond: Offset.zero);
Velocity _fling(Offset v) => Velocity(pixelsPerSecond: v);

void main() {
  group('TriageSwipe.direction', () {
    test('deadzone → null', () {
      expect(TriageSwipe.direction(Offset.zero), isNull);
      expect(TriageSwipe.direction(const Offset(8, 4)), isNull); // < 12
    });
    test('right = known, left = unknown', () {
      expect(TriageSwipe.direction(const Offset(40, 3)), TriageVerdict.known);
      expect(TriageSwipe.direction(const Offset(-40, 3)), TriageVerdict.unknown);
    });
    test('up = unsure, down = null', () {
      expect(TriageSwipe.direction(const Offset(5, -40)), TriageVerdict.unsure);
      expect(TriageSwipe.direction(const Offset(5, 40)), isNull); // down has no verdict
    });
    test('dominant axis wins', () {
      expect(TriageSwipe.direction(const Offset(50, -20)), TriageVerdict.known); // horiz dominant
      expect(TriageSwipe.direction(const Offset(20, -50)), TriageVerdict.unsure); // vert dominant
    });
  });

  group('TriageSwipe.progress', () {
    test('no direction → 0', () {
      expect(TriageSwipe.progress(Offset.zero, 100), 0);
    });
    test('proportional, clamped to 1', () {
      expect(TriageSwipe.progress(const Offset(50, 0), 100), closeTo(0.5, 1e-9));
      expect(TriageSwipe.progress(const Offset(150, 0), 100), 1.0);
    });
    test('uses vertical magnitude for unsure', () {
      expect(TriageSwipe.progress(const Offset(0, -60), 120), closeTo(0.5, 1e-9));
    });
  });

  group('TriageSwipe.tiltRadians', () {
    test('capped at ±6°', () {
      final maxRad = AppMotion.swipeTilt * math.pi / 180.0;
      expect(TriageSwipe.tiltRadians(const Offset(9999, 0), 100), closeTo(maxRad, 1e-9));
      expect(TriageSwipe.tiltRadians(const Offset(-9999, 0), 100), closeTo(-maxRad, 1e-9));
    });
    test('proportional below the cap, sign follows drag', () {
      final full = AppMotion.swipeTilt * math.pi / 180.0;
      expect(TriageSwipe.tiltRadians(const Offset(50, 0), 100), closeTo(full * 0.5, 1e-9));
    });
    test('zero threshold → no tilt', () {
      expect(TriageSwipe.tiltRadians(const Offset(50, 0), 0), 0);
    });
  });

  group('TriageSwipe.signSide — sign stays on the on-screen side', () {
    test('known slides right → sign left', () {
      expect(TriageSwipe.signSide(TriageVerdict.known), VerdictSignSide.left);
    });
    test('unknown slides left → sign right', () {
      expect(TriageSwipe.signSide(TriageVerdict.unknown), VerdictSignSide.right);
    });
    test('unsure slides up → sign bottom', () {
      expect(TriageSwipe.signSide(TriageVerdict.unsure), VerdictSignSide.bottom);
    });
  });

  group('TriageSwipe.shouldCommit', () {
    test('past the 32% threshold commits', () {
      expect(
        TriageSwipe.shouldCommit(drag: const Offset(120, 0), threshold: 100, velocity: _v0),
        isTrue,
      );
    });
    test('below threshold and slow → spring back', () {
      expect(
        TriageSwipe.shouldCommit(drag: const Offset(40, 0), threshold: 100, velocity: _v0),
        isFalse,
      );
    });
    test('a fling over 600 px/s commits even below threshold', () {
      expect(
        TriageSwipe.shouldCommit(
          drag: const Offset(40, 0),
          threshold: 100,
          velocity: _fling(const Offset(900, 0)),
        ),
        isTrue,
      );
    });
    test('no direction never commits, however fast', () {
      expect(
        TriageSwipe.shouldCommit(
          drag: Offset.zero,
          threshold: 100,
          velocity: _fling(const Offset(0, 900)),
        ),
        isFalse,
      );
    });
  });

  group('SessionSegments', () {
    testWidgets('renders `total` segments, first `done` filled with ink', (tester) async {
      await tester.pumpWidget(
        const Directionality(
          textDirection: TextDirection.ltr,
          // Center gives loose constraints so the bar's SizedBox(height: 3) is
          // honoured (pumpWidget's root constraints are tight to the surface).
          child: Center(child: SizedBox(width: 300, child: SessionSegments(done: 4, total: 10))),
        ),
      );
      final boxes = tester.widgetList<ColoredBox>(find.byType(ColoredBox)).toList();
      expect(boxes.length, 10);
      expect(boxes.take(4).every((b) => b.color == AppColors.ink), isTrue);
      expect(boxes.skip(4).every((b) => b.color == AppColors.track), isTrue);
      // Segments must actually have height (regression: a center-aligned Row
      // collapsed the zero-child ColoredBoxes to height 0 → invisible bar).
      expect(tester.getSize(find.byType(ColoredBox).first).height, 3);
    });

    testWidgets('zero total renders nothing to divide by', (tester) async {
      await tester.pumpWidget(
        const Directionality(
          textDirection: TextDirection.ltr,
          child: SizedBox(width: 300, child: SessionSegments(done: 0, total: 0)),
        ),
      );
      expect(find.byType(ColoredBox), findsNothing);
    });
  });
}
