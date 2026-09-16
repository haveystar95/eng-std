import 'package:flutter/material.dart';

import '../../../../data/plan/session/session_models.dart';
import 'card_kit.dart';
import 'phrase_cards.dart';
import 'word_cards.dart';

/// CARD BY KIND — the 15 screens of 1b (words 6, phrases 9). Kinds of stages without screens never get here: entry
/// to their stages is blocked; just in case, they render empty.
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
    // Dialogue, listening, speech — no screens in 1b.
    _ => const SizedBox.shrink(),
  };
}
