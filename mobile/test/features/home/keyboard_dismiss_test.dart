import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

/// Leaving a tab must put the keyboard away.
///
/// The tabs live in an `IndexedStack`, which is what makes the bug possible: switching away does
/// not dispose the search screen, so its text field keeps focus, and iOS keeps the keyboard up over
/// a tab that has no field to tap out of. On the device it stayed until the app was backgrounded.
///
/// This reproduces the shape rather than mounting the whole HomeScreen (which pulls in sync, the
/// database and five screens): a field inside an IndexedStack, and a switch that must unfocus.
void main() {
  testWidgets('switching away from a tab with a focused field drops the focus', (tester) async {
    var index = 0;
    final node = FocusNode();
    addTearDown(node.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: StatefulBuilder(
          builder: (context, setState) => Scaffold(
            backgroundColor: AppColors.paper,
            body: Column(
              children: [
                Expanded(
                  child: IndexedStack(
                    index: index,
                    children: [
                      TextField(focusNode: node),
                      const Text('another tab'),
                    ],
                  ),
                ),
                FloatingTabBar(
                  items: const [
                    FloatingTabItem(icon: Icons.search, label: 'Search'),
                    FloatingTabItem(icon: Icons.list, label: 'Collections'),
                  ],
                  currentIndex: index,
                  onTap: (i) {
                    // The line under test, verbatim from HomeScreen._select.
                    FocusManager.instance.primaryFocus?.unfocus();
                    setState(() => index = i);
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.byType(TextField));
    await tester.pump();
    expect(
      node.hasFocus,
      isTrue,
      reason: 'the field must be focused for the test to mean anything',
    );

    await tester.tap(find.text('Collections'));
    await tester.pumpAndSettle();

    // Focus is off the FIELD → no keyboard over the tab the learner just opened.
    //
    // Asserted on the field's own node, not on `primaryFocus.hasFocus`: after an unfocus the
    // primary focus falls back to the enclosing scope, which legitimately still «has focus». What
    // raises the keyboard is a TEXT INPUT holding it, and that is what has to be gone.
    expect(node.hasFocus, isFalse);
    expect(FocusManager.instance.primaryFocus, isNot(same(node)));
  });
}
