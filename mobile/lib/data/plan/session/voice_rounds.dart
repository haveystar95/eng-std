/// ROUNDS OF A VOICE CARD (polish pass SESSION-1b′, item 12) — `phrase_repeat` is a series of rounds in one card.
/// Round 1 is the card's own filler (`filler_index`, `expected_text`); round 2 — the frame's next visible filler by
/// `index`, cyclically. Round 2 says `frame_target` with that filler; its native sentence is `frame_native` with the
/// filler's native, and its sample is the filler's own audio. A frame without such a second filler — one round, as
/// before. The card is passed when every round is covered; one answer goes to the server at the end.
///
/// `phrase_other_slot` had rounds too until SESSION-2b: on 32-7 the learner now CHOOSES the meaning with a chip, and
/// a second forced round would take that choice back.
library;

import 'package:flutter/foundation.dart';

import 'session_models.dart';

/// One round: what is said in it.
@immutable
class VoiceRound {
  const VoiceRound({required this.expectedText, this.filler, this.fillerIndex, this.slotExpected, this.audio, this.native});

  /// The whole phrase to say.
  final String expectedText;

  /// The round's filler; null — a frame without a slot.
  final CardFiller? filler;

  /// The round's `filler_index` (round 1 — the card's own).
  final int? fillerIndex;

  /// `phrase_other_slot`: the words of the slot, all of them must be heard.
  final String? slotExpected;

  /// `phrase_repeat`: the sample.
  final CardAudio? audio;

  /// The round's native sentence — `frame_native` with the filler's native; null in round 1, which keeps what the card
  /// showed before rounds.
  final String? native;
}

abstract final class VoiceRounds {
  static List<VoiceRound> ofRepeat(PhraseRepeatPayload p) {
    final first = VoiceRound(
      expectedText: p.expectedText,
      filler: p.frame.filler(p.fillerIndex),
      fillerIndex: p.fillerIndex,
      audio: p.audio,
    );
    final second = next(p.frame, p.fillerIndex);
    if (second == null) return [first];
    return [
      first,
      VoiceRound(
        expectedText: p.frame.filledWith(second.target),
        filler: second,
        fillerIndex: second.index,
        audio: second.audio,
        native: nativeOf(p.frame, second),
      ),
    ];
  }

  /// The frame's next visible filler after [index] by `index`, cyclically; null — the frame has no slot, the card
  /// has no filler, or there is no other filler.
  static CardFiller? next(CardFrame frame, int? index) {
    if (!frame.hasSlot || index == null) return null;
    final sorted = [...frame.fillers]..sort((a, b) => a.index.compareTo(b.index));
    for (final f in [...sorted.where((f) => f.index > index), ...sorted.where((f) => f.index < index)]) {
      return f;
    }
    return null;
  }

  /// `frame_native` with the filler's native in place of `___`.
  static String nativeOf(CardFrame frame, CardFiller filler) => frame.frameNative.replaceFirst(kSlotMark, filler.native);
}
