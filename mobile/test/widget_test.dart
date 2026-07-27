import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/main.dart';

void main() {
  testWidgets('App boots into the auth gate without throwing', (tester) async {
    await tester.pumpWidget(const ProviderScope(child: EngStdApp()));
    await tester.pump(); // let the first frame settle

    // The app shell is present (login screen or loader — both are valid here).
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
