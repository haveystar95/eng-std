/// THE NUMBERS OF THE STAGE AND DAY SUMMARIES (work orders SESSION-1c, CLIENT-CONV-1c; canvases 30-6, 30-7) — read off
/// the cards the server returned, never counted from the learner's taps: a summary shown after a session resumed
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

/// THE NUMBERS OF ONE STAGE'S SUMMARY (30-6, решение архитектора 22.09): [units] — the stage's words, phrases, lines,
/// questions or cards; [firstTry] — how many of them were passed at the first attempt without a hint; [returns] — its
/// units the server sends back tomorrow; for «Вспомнить», [scenes] — how many scenes its lines come from; for «Диалог»,
/// [saidAloud] — the learner's own lines passed by voice and [rescues] — the «Не понял» exchanges walked.
typedef StageTally = ({int units, int firstTry, int returns, int scenes, int saidAloud, int rescues});

abstract final class SessionSummaries {
  /// The listening questions — the choices of «Listen and answer».
  static const Set<SessionKind> listenQuestions = {SessionKind.listenQuestion, SessionKind.listenPredict, SessionKind.listenNumber};

  /// THE TALLY OF [stage] (30-6). «С первого раза» is a card `passed` at its first attempt — never `hinted`, never after
  /// a lapse (a lapse deals a copy, and the copy's unit is not first-time any more), never skipped:
  ///
  /// - «Слушаю и отвечаю» counts its QUESTIONS — its unit is the whole visit, and a question answered wrong is final;
  /// - «Повторение» counts its CARDS as dealt (a lapse's copy is not a second card of the day);
  /// - «Вспомнить» counts its lines said aloud and the scenes they come from; its sheet of lines is not a line;
  /// - every other stage counts its UNITS ([SessionQueue.unitsOf]) — a unit is first-time when all of its cards are.
  static StageTally stageTally(SessionQueue queue, PlanStage stage) {
    final cards = queue.cardsOf(stage);
    bool firstTime(SessionCard c) => c.result == SessionResult.passed && c.attempts <= 1;
    final returns = queue.returningUnits(stage).length;
    switch (stage) {
      case PlanStage.listen:
        final questions = [for (final c in cards) if (listenQuestions.contains(c.kind)) c];
        return (units: questions.length, firstTry: questions.where(firstTime).length, returns: returns, scenes: 0, saidAloud: 0, rescues: 0);
      case PlanStage.repetition:
        final dealt = [for (final c in cards) if (c.retryOf == null) c];
        return (units: dealt.length, firstTry: dealt.where(firstTime).length, returns: returns, scenes: 0, saidAloud: 0, rescues: 0);
      case PlanStage.recall:
        final lines = [for (final c in cards) if (c.kind != SessionKind.recallScenes && c.retryOf == null) c];
        final scenes = {for (final c in lines) c.payload.sceneId}.length;
        return (units: lines.length, firstTry: lines.where(firstTime).length, returns: 0, scenes: scenes, saidAloud: 0, rescues: 0);
      default:
        final units = queue.unitsOf(stage);
        final first = units.where((key) {
          final own = [for (final c in cards) if (!c.unit.isDay && queue.unitKey(c) == key) c];
          return own.isNotEmpty && own.every(firstTime);
        }).length;
        final aloud = cards.where((c) =>
            (c.kind == SessionKind.dialogueAnswer || c.kind == SessionKind.dialogueAsk) &&
            (c.result == SessionResult.passed || c.result == SessionResult.hinted) &&
            c.response?['mode'] != 'chips').length;
        final rescues = cards.where((c) => c.kind == SessionKind.dialogueRescue && c.result == SessionResult.passed).length;
        return (units: units.length, firstTry: first, returns: returns, scenes: 0, saidAloud: aloud, rescues: rescues);
    }
  }

  /// The day's units that come back tomorrow (30-7): the units whose card the server marked `returns`, once each — a
  /// unit is its scene's (x3 of one scene of a review is not x3 of another).
  static DayReturns dayReturns(SessionQueue queue) {
    final seen = <String>{};
    var words = 0;
    var phrases = 0;
    var exchanges = 0;
    for (final stage in PlanStage.known) {
      for (final card in queue.cardsOf(stage)) {
        if (!card.returns || card.unit.isDay || !seen.add('${card.payload.sceneId}/${card.unit.kind.name}:${card.unit.ref}')) continue;
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
