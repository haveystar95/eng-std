import 'package:flutter/material.dart';

import '../../../../data/plan/session/session_models.dart';
import 'card_kit.dart';
import 'phrase_cards.dart';
import 'word_cards.dart';

/// КАРТОЧКА ПО ВИДУ — 15 экранов 1b (слова 6, фразы 9). Виды этапов без экранов сюда не доезжают: вход в их
/// этапы заблокирован; на всякий случай у них пусто.
Widget sessionCardFor(CardEnv env) {
  final card = env.card;
  return switch (card.payload) {
    final WordIntroPayload p => WordIntroCard(env: env, payload: p),
    final WordRepeatPayload p => WordRepeatCard(env: env, payload: p),
    final WordChoosePayload p => WordChooseCard(env: env, payload: p),
    final WordListenPayload p => WordListenCard(env: env, payload: p),
    final WordAssemblePayload p => WordAssembleCard(env: env, payload: p),
    final WordInLinePayload p => WordInLineCard(env: env, payload: p),
    final PhraseIntroPayload p => PhraseIntroCard(env: env, payload: p),
    final PhraseAssemblePayload p => PhraseAssembleCard(env: env, payload: p),
    final PhraseChooseBackPayload p => PhraseChooseBackCard(env: env, payload: p),
    final PhraseSlotPayload p => PhraseSlotCard(env: env, payload: p),
    final PhraseSlotListenPayload p => PhraseSlotListenCard(env: env, payload: p),
    final PhraseRepeatPayload p => PhraseRepeatCard(env: env, payload: p),
    final PhraseOtherSlotPayload p => PhraseOtherSlotCard(env: env, payload: p),
    final PhraseCombinePayload p => PhraseCombineCard(env: env, payload: p),
    final PhraseOwnSlotPayload p => PhraseOwnSlotCard(env: env, payload: p),
    // Диалог, слушание, речь — экранов в 1b нет.
    _ => const SizedBox.shrink(),
  };
}
