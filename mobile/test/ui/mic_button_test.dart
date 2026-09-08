import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/mic_button.dart';

/// ОДНА КНОПКА МИКРОФОНА НА ТРИ КАРТОЧКИ — наряд SPEECH-2, Ч.1.
void main() {
  Widget host(MicState state, {VoidCallback? onTap}) => MaterialApp(
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru'), Locale('en')],
    home: Scaffold(body: Center(child: MicButton(state: state, onTap: onTap ?? () {}))),
  );

  // ПРАВИЛО: Ч.1.1 — состояние кнопки названо СЛОВАМИ, не только заливкой.
  // ЛОВИТ: кружок, который отличается от соседнего только цветом. Человек, не уверенный, пишут его
  // или ждут, молчит — а молчание тренажёр читает как «не вспомнил».
  testWidgets('называет своё состояние словами', (tester) async {
    for (final (state, caption) in [
      (MicState.waiting, 'Собеседник говорит'),
      (MicState.yourTurn, 'Твоя очередь — нажми и говори'),
      (MicState.recording, 'Готово'),
    ]) {
      await tester.pumpWidget(host(state));
      await tester.pump();

      expect(find.text(caption), findsOneWidget, reason: state.name);
    }
  });

  // ПРАВИЛО: Ч.1.1 — пока говорит собеседник, кнопка не нажимается.
  // ЛОВИТ: тап посреди чужой реплики. Он записал бы динамик: эхо-замок это выбросит, но попытка
  // уже потрачена, и человек не поймёт, почему.
  testWidgets('в ожидании собеседника не нажимается, в свою очередь — нажимается', (tester) async {
    var taps = 0;
    await tester.pumpWidget(host(MicState.waiting, onTap: () => taps++));
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    expect(taps, 0);

    await tester.pumpWidget(host(MicState.yourTurn, onTap: () => taps++));
    await tester.pump();
    await tester.tap(find.byType(MicButton));
    expect(taps, 1);
  });

  // ПРАВИЛО: Ч.1.2 — «твоя очередь» это состояние БЕЗ ТАЙМАУТА.
  // ЛОВИТ: бесконечную анимацию приглашения. Две вещи сразу: кнопка, пульсирующая всё время, пока
  // человек думает над репликой, торопит его — ровно то, от чего наряд уводит, убирая
  // автооткрытие; и экран, который никогда не «застывает», нельзя дождаться в виджет-тесте, то
  // есть нельзя проверить вообще ничем.
  testWidgets('приглашение конечно — экран застывает и ждёт человека', (tester) async {
    await tester.pumpWidget(host(MicState.yourTurn));

    await tester.pumpAndSettle();

    expect(find.byType(MicButton), findsOneWidget);
  });
}
