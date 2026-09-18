/// THE CONVERSATION SO FAR (work order SESSION-1c, section 2; canvas 33-7) — the bubbles that stand above a dialogue
/// card: every line said on the cards before it, in the order the stage walked them.
///
/// Nothing is kept on the phone: the feed is read off the stage's answered cards every time a card opens, so a
/// session resumed tomorrow shows the same conversation. A line appears once (by its `ref`: the partner's `x3`, the
/// learner's `x3b`), and a line the current card draws itself is not repeated above it. The learner's line of an
/// answer given with a chip is the frame with THAT filler — what was said, not the line of the lesson.
///
/// Pure functions, not a single widget.
library;

import 'package:flutter/foundation.dart';

import 'session_models.dart';

/// The mark in the corner of an own bubble (23-0d).
enum FeedMark {
  /// No mark.
  none,

  /// Said — a sage check.
  passed,

  /// The exchange comes back tomorrow — a brass dot.
  returns,
}

/// One bubble of the conversation.
@immutable
class FeedLine {
  const FeedLine({required this.exchangeRef, required this.own, required this.line, this.mark = FeedMark.none});

  /// The exchange the line belongs to (`x3`) — lines of one exchange stand 8 apart, of different ones 16.
  final String exchangeRef;

  /// The learner's line — a dark bubble on the right; the partner's — a paper one on the left.
  final bool own;
  final CardLine line;
  final FeedMark mark;
}

/// The two lines of an exchange — «comes back tomorrow» on the stage summaries (33-8, 35-6).
typedef ExchangePair = ({CardLine? partner, CardLine? own, bool learnerFirst});

abstract final class DialogueFeed {
  /// The lines [card] draws itself, in the order it draws them.
  static List<FeedLine> linesOf(SessionCard card) {
    final exchange = _exchangeRef(card);
    final mark = card.result == SessionResult.passed || card.result == SessionResult.hinted ? FeedMark.passed : FeedMark.none;
    FeedLine partner(CardLine line) => FeedLine(exchangeRef: exchange, own: false, line: line);
    FeedLine own(CardLine line) => FeedLine(exchangeRef: exchange, own: true, line: line, mark: card.returns ? FeedMark.returns : mark);
    return switch (card.payload) {
      DialoguePartnerPayload(:final partnerLine) => [partner(partnerLine)],
      final DialogueAnswerPayload p when card.kind == SessionKind.dialogueAsk => [
        own(saidOwnLine(p, card.response)),
        if (p.partnerLine case final line?) partner(line),
      ],
      final DialogueAnswerPayload p => [
        if (p.partnerLine case final line?) partner(line),
        own(saidOwnLine(p, card.response)),
      ],
      DialogueRescuePayload(:final askedLine, :final rescueLine, :final partnerRepeat) => [
        if (askedLine != null) FeedLine(exchangeRef: askedLine.ref, own: false, line: askedLine),
        own(rescueLine),
        partner(partnerRepeat),
      ],
      _ => const [],
    };
  }

  /// The conversation above [current]: the lines of the answered cards before it by `position`, each line once, none
  /// that [current] draws itself — except the partner's line a rescue already asked about: it stays in its place
  /// before «Could you say that more slowly?» and the slow repeat, and the answer that follows does not draw it again
  /// ([partnerInFeed]), so the conversation reads in the order it was spoken.
  static List<FeedLine> before(List<SessionCard> stageCards, SessionCard current) {
    final cards = [...stageCards]..sort((a, b) => a.position.compareTo(b.position));
    final earlier = [
      for (final card in cards)
        if (card.id != current.id && card.position < current.position && card.isAnswered) card,
    ];
    final rescued = {
      for (final card in earlier)
        if (card.payload case DialogueRescuePayload(:final askedLine?)) askedLine.ref,
    };
    final answer = current.payload is DialogueAnswerPayload && current.kind != SessionKind.dialogueAsk;
    final drawn = {
      for (final l in linesOf(current))
        if (!(answer && !l.own && rescued.contains(l.line.ref))) l.line.ref,
    };
    final seen = <String>{};
    return [
      for (final card in earlier)
        for (final line in linesOf(card))
          if (!drawn.contains(line.line.ref) && seen.add(line.line.ref)) line,
    ];
  }

  /// Whether [partner] already stands in [feed] (a rescue asked about it) — the answer card then draws only its own
  /// bubble.
  static bool partnerInFeed(List<FeedLine> feed, CardLine? partner) =>
      partner != null && feed.any((f) => !f.own && f.line.ref == partner.ref);

  /// The learner's line as it was said: an answer given with a chip carries its filler (`response.filler_index`) — the
  /// frame with that filler, its native line and its sound; otherwise the line of the lesson.
  static CardLine saidOwnLine(DialogueAnswerPayload p, Map<String, dynamic>? response) {
    final filler = p.frame.hasSlot ? p.frame.filler((response?['filler_index'] as num?)?.toInt()) : null;
    if (filler == null || filler.index == p.ownLine.fillerIndex) return p.ownLine;
    return CardLine(
      ref: p.ownLine.ref,
      textTarget: filledSentence(p.frame, filler),
      textNative: filler.nativeLine ?? p.frame.frameNative.replaceFirst(kSlotMark, filler.native),
      audio: filler.audio,
    );
  }

  /// The frame said with [filler]: the filler in the slot, no space before the closing mark.
  static String filledSentence(CardFrame frame, CardFiller filler) =>
      frame.filledWith(filler.target).replaceAllMapped(RegExp(r'\s+([.,!?;:…])'), (m) => m[1]!);

  /// The partner's and the learner's line of [exchangeRef] from any card of [cards] that carries them — by line
  /// `ref`: the partner's is the exchange's own ref, the learner's has `b`. An `ask` and a `rescue` start with the
  /// learner.
  static ExchangePair pairOf(Iterable<SessionCard> cards, String exchangeRef) {
    CardLine? partner;
    CardLine? own;
    var learnerFirst = false;
    for (final card in cards) {
      final lines = switch (card.payload) {
        DialoguePartnerPayload(:final partnerLine) => [partnerLine],
        final DialogueAnswerPayload p => [?p.partnerLine, saidOwnLine(p, card.response)],
        DialogueRescuePayload(:final rescueLine, :final partnerRepeat) => [rescueLine, partnerRepeat],
        SpeakAnswerPayload(:final partnerLine, :final ownLine) => [?partnerLine, ownLine],
        SpeakEchoPayload(:final partnerLine) => [partnerLine],
        // 35-4 carries the learner's own line only (BACK-TAILS-1 §1.1).
        SpeakRetellPayload(:final ownLine) => [ownLine],
        _ => const <CardLine>[],
      };
      for (final line in lines) {
        if (line.ref == exchangeRef) partner ??= line;
        if (line.ref == '${exchangeRef}b') own ??= line;
      }
      final exchange = switch (card.payload) {
        DialogueAnswerPayload(:final exchange) ||
        DialogueRescuePayload(:final exchange) ||
        DialoguePartnerPayload(:final exchange) ||
        SpeakAnswerPayload(:final exchange) => exchange,
        _ => null,
      };
      if (exchange != null && exchange.ref == exchangeRef && exchange.kind != 'answer') learnerFirst = true;
    }
    return (partner: partner, own: own, learnerFirst: learnerFirst);
  }

  static String _exchangeRef(SessionCard card) => switch (card.payload) {
    DialoguePartnerPayload(:final exchange) ||
    DialogueAnswerPayload(:final exchange) ||
    DialogueRescuePayload(:final exchange) => exchange.ref,
    _ => card.unit.ref,
  };
}
