import 'package:flutter/material.dart';

import '../../../../data/plan/session/session_models.dart';
import 'card_kit.dart';
import 'dialogue_cards.dart';
import 'listen_cards.dart';
import 'phrase_cards.dart';
import 'speak_cards.dart';
import 'word_cards.dart';

/// CARD BY KIND — every dealt kind has its screen: words 6, phrases 9 (SESSION-1b), dialogue 4, listen and answer 6,
/// speak myself 3 (SESSION-1c). The switch is over the sealed payload, so a new kind cannot compile without a screen;
/// `dialogue_answer` and `dialogue_ask` share one payload and one widget.
Widget sessionCardFor(CardEnv env) => switch (env.card.payload) {
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
  // Both slot kinds are ONE trainer since FIX-1 §6 — «Say it whole», rounds through the meanings and the learner's
  // own word last; they differ only in who passes that last round (the phone, or the server's slot judge).
  final PhraseOtherSlotPayload p => PhraseSayWholeCard(env: env, payload: p),
  final PhraseCombinePayload p => PhraseCombineCard(env: env, payload: p),
  final DialoguePartnerPayload p => DialoguePartnerCard(env: env, payload: p),
  final DialogueAnswerPayload p => DialogueAnswerCard(env: env, payload: p),
  final DialogueRescuePayload p => DialogueRescueCard(env: env, payload: p),
  final ListenDialoguePayload p => ListenDialogueCard(env: env, payload: p),
  final ListenQuestionPayload p => ListenQuestionCard(env: env, payload: p),
  final ListenReviewPayload p => ListenReviewCard(env: env, payload: p),
  final ListenPredictPayload p => ListenPredictCard(env: env, payload: p),
  final ListenPacePayload p => ListenPaceCard(env: env, payload: p),
  final ListenNumberPayload p => ListenNumberCard(env: env, payload: p),
  final SpeakAnswerPayload p => SpeakAnswerCard(env: env, payload: p),
  final SpeakEchoPayload p => SpeakEchoCard(env: env, payload: p),
  final SpeakRetellPayload p => SpeakRetellCard(env: env, payload: p),
  // The choice mixin is a subtype of the sealed payload only for the exhaustiveness check: every class that carries it
  // is one of the cases above.
  ChoicePayload() => throw StateError('a choice payload of no known kind'),
};
