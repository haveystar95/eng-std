import 'package:eng_std/ui/progress_line.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ProgressLine.fillWidth — min-width 8 (§4б)', () {
    test('zero value → no fill', () {
      expect(ProgressLine.fillWidth(0, 200), 0);
    });

    test('tiny value still fills at least 8px', () {
      expect(ProgressLine.fillWidth(0.01, 200), 8);
    });

    test('proportional above the floor', () {
      expect(ProgressLine.fillWidth(0.5, 200), 100);
      expect(ProgressLine.fillWidth(1, 200), 200);
    });

    test('value is clamped to 0..1', () {
      expect(ProgressLine.fillWidth(2, 200), 200);
      expect(ProgressLine.fillWidth(-1, 200), 0);
    });
  });
}
