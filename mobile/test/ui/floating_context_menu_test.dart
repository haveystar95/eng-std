import 'package:eng_std/ui/floating_context_menu.dart';
import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_test/flutter_test.dart';

class _Harness extends StatelessWidget {
  const _Harness({required this.actions});
  final List<ContextMenuAction> actions;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        body: Center(
          child: Builder(
            builder: (anchorCtx) => ElevatedButton(
              onPressed: () => showFloatingContextMenu(
                context: anchorCtx,
                anchorContext: anchorCtx,
                actions: actions,
                barrierLabel: 'Закрыть меню',
              ),
              child: const Text('open'),
            ),
          ),
        ),
      ),
    );
  }
}

void main() {
  testWidgets('opens on trigger and shows every row', (tester) async {
    await tester.pumpWidget(
      _Harness(
        actions: [
          ContextMenuAction(icon: LucideIcons.pencil, label: 'Изменить', onSelected: () {}),
          ContextMenuAction(icon: LucideIcons.copy, label: 'Дублировать', onSelected: () {}),
          ContextMenuAction(
            icon: LucideIcons.trash2,
            label: 'Удалить',
            destructive: true,
            onSelected: () {},
          ),
        ],
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Изменить'), findsOneWidget);
    expect(find.text('Дублировать'), findsOneWidget);
    expect(find.text('Удалить'), findsOneWidget);
  });

  testWidgets('destructive row is always last (§4в)', (tester) async {
    await tester.pumpWidget(
      _Harness(
        actions: [
          // destructive passed FIRST — component must still place it last
          ContextMenuAction(
            icon: LucideIcons.trash2,
            label: 'Удалить',
            destructive: true,
            onSelected: () {},
          ),
          ContextMenuAction(icon: LucideIcons.pencil, label: 'Изменить', onSelected: () {}),
        ],
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    final edit = tester.getTopLeft(find.text('Изменить')).dy;
    final del = tester.getTopLeft(find.text('Удалить')).dy;
    expect(del, greaterThan(edit));
  });

  testWidgets('tapping a row closes the menu and fires the action', (tester) async {
    var fired = false;
    await tester.pumpWidget(
      _Harness(
        actions: [
          ContextMenuAction(
            icon: LucideIcons.pencil,
            label: 'Изменить',
            onSelected: () => fired = true,
          ),
        ],
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Изменить'));
    await tester.pumpAndSettle();

    expect(fired, isTrue);
    expect(find.text('Изменить'), findsNothing); // closed
  });

  testWidgets('tapping the scrim closes the menu (no Close button)', (tester) async {
    await tester.pumpWidget(
      _Harness(
        actions: [
          ContextMenuAction(icon: LucideIcons.pencil, label: 'Изменить', onSelected: () {}),
        ],
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    await tester.tapAt(const Offset(5, 5)); // outside the card
    await tester.pumpAndSettle();

    expect(find.text('Изменить'), findsNothing);
  });
}
