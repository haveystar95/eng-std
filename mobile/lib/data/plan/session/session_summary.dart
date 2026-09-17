/// THE NUMBERS OF THE STAGE AND DAY SUMMARIES (work order SESSION-1c, sections 3–5; canvases 34-8, 35-6, 30-7) — read
/// off the cards the server returned, never counted from the learner's taps: a summary shown after a session resumed
/// tomorrow says the same.
///
/// Pure functions, not a single widget.
library;

import '../plan_models.dart';
import 'session_models.dart';
import 'session_queue.dart';

/// The day's units coming back tomorrow, by kind.
typedef DayReturns = ({int words, int phrases, int exchanges});

/// What the day summary says about the next day's lesson.
enum NextDayLesson { building, ready }

abstract final class SessionSummaries {
  /// The listening questions — the choices of «Listen and answer».
  static const Set<SessionKind> listenQuestions = {SessionKind.listenQuestion, SessionKind.listenPredict, SessionKind.listenNumber};

  /// «Understood N questions of M» (34-8): the listening questions answered right, of all of them.
  static ({int right, int total}) understood(Iterable<SessionCard> listenCards) {
    final questions = listenCards.where((c) => listenQuestions.contains(c.kind)).toList();
    return (right: questions.where((c) => c.result == SessionResult.passed).length, total: questions.length);
  }

  /// «Said N lines of M myself» (35-6): the cards of «Speak myself» passed — by the learner's voice or by the judge,
  /// with the frame hinted or not — of all of them.
  static ({int said, int total}) spoke(Iterable<SessionCard> speakCards) {
    final cards = speakCards.toList();
    final said = cards.where((c) => c.result == SessionResult.passed || c.result == SessionResult.hinted).length;
    return (said: said, total: cards.length);
  }

  /// The day's units that come back tomorrow (30-7): the units whose card the server marked `returns`, once each.
  static DayReturns dayReturns(SessionQueue queue) {
    final seen = <String>{};
    var words = 0;
    var phrases = 0;
    var exchanges = 0;
    for (final stage in PlanStage.known) {
      for (final card in queue.cardsOf(stage)) {
        if (!card.returns || card.unit.isDay || !seen.add('${card.unit.kind.name}:${card.unit.ref}')) continue;
        switch (card.unit.kind) {
          case PlanUnitKind.word:
            words++;
          case PlanUnitKind.phrase:
            phrases++;
          case PlanUnitKind.exchange:
            exchanges++;
          case PlanUnitKind.unknown:
            break;
        }
      }
    }
    return (words: words, phrases: phrases, exchanges: exchanges);
  }

  /// The «Day N+1» line of 30-7, as the contract states the next day (SESSION-2a §6) — nothing derived on the phone:
  /// its lesson written (`lesson_status: ready`, the day not `building`) — «ready»; the server writing it (`status:
  /// building`, or the lesson `building` / `pending`) — «building»; failed, not asked for yet, or no next day — no line.
  static NextDayLesson? nextDayLesson(PlanDayRoute? next) {
    if (next == null || next.lessonFailed) return null;
    if (next.lessonBuilding) return NextDayLesson.building;
    if (next.lessonStatus == LessonStatus.ready) return NextDayLesson.ready;
    return null;
  }
}
