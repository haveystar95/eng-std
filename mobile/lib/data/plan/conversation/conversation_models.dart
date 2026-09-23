/// THE TALK WITH THE AGENT — `PlanConversation` as the phone reads it (наряд CONV-1, кадры
/// 37-5…37-12; contract `../backend2/docs/plan-api.md`, «Разговор с агентом»).
///
/// All three calls — start, move, re-read — answer with the WHOLE talk: the ribbon, whose move it
/// is, the intention offered for the next one and, once it is over, the summary. So there is no
/// state to merge on the phone: an answer REPLACES what the screen held.
///
/// THE PARSE IS CLOSED, like the day window's: a missing field or a word this build has no meaning
/// for is a [PlanContractError], and the screen says the talk could not be read. The client words
/// nothing the server did not send — not a count, not a minute, not «what probably happened».
library;

import '../plan_models.dart';
import '../session/session_models.dart' show CardAudio;

/// Whose move it is. There is no «thinking»: the three dots are the client's own, drawn while a
/// request is in flight.
enum TalkState {
  agentTurn,
  yourTurn,
  ended;

  static TalkState fromWire(Object? s) => switch (s) {
    'agent_turn' => agentTurn,
    'your_turn' => yourTurn,
    'ended' => ended,
    _ => throw PlanContractError('conversation state «$s»'),
  };
}

/// What kind of day the talk closes: 3–4 moves at the end of a scene day, the whole visit on the
/// rehearsal, 3–4 on a review day.
enum TalkType {
  day,
  rehearsal,
  review;

  static TalkType fromWire(Object? s) => switch (s) {
    'day' => day,
    'rehearsal' => rehearsal,
    'review' => review,
    _ => throw PlanContractError('conversation type «$s»'),
  };
}

/// Who said a line.
enum TalkSpeaker {
  partner,
  learner;

  static TalkSpeaker fromWire(Object? s) => switch (s) {
    'partner' => partner,
    'learner' => learner,
    _ => throw PlanContractError('turn speaker «$s»'),
  };
}

/// The role's line, or what the learner did with their move.
enum TalkTurnKind {
  agent,
  said,
  rescue,
  skip;

  static TalkTurnKind fromWire(Object? s) => switch (s) {
    'agent' => agent,
    'said' => said,
    'rescue' => rescue,
    'skip' => skip,
    _ => throw PlanContractError('turn kind «$s»'),
  };
}

/// Where a scene of the talk stands.
enum TalkSceneState {
  current,
  done,
  locked;

  static TalkSceneState fromWire(Object? s) => switch (s) {
    'current' => current,
    'done' => done,
    'locked' => locked,
    _ => throw PlanContractError('conversation scene state «$s»'),
  };
}

/// Why the talk ended. Nothing on the screens of серия 37 reads it, so an unfamiliar reason is
/// [unknown] rather than a refusal to draw the summary the learner earned.
enum TalkEnd {
  natural,
  limit,
  declined,
  replayed,
  unknown;

  static TalkEnd? fromWire(Object? s) => switch (s) {
    null => null,
    'natural' => natural,
    'limit' => limit,
    'declined' => declined,
    'replayed' => replayed,
    _ => unknown,
  };
}

/// Who the learner is talking to NOW, and where — the scene strip (кадр 30-2b). Both change with the
/// scene on the rehearsal: «Запись к врачу · администратор» → «Приём у врача · врач».
class TalkPartner {
  const TalkPartner({required this.roleNative, required this.sceneNative});

  final String roleNative;
  final String sceneNative;
}

/// A scene of the talk — «Из каких сцен» (кадр 37-1).
class TalkScene {
  const TalkScene({
    required this.sceneId,
    required this.titleNative,
    required this.roleNative,
    required this.lines,
    required this.state,
  });

  final String sceneId;
  final String titleNative;
  final String roleNative;

  /// How many lines of their own the learner prepared for it.
  final int lines;
  final TalkSceneState state;
}

/// The policy of hints for THIS talk, fixed when it started (кадр 37-7).
class TalkHints {
  const TalkHints({required this.enabled, required this.delayMs, this.native});

  /// False — «Без подсказок»: no chip and no «Подсказать» on the screen at all.
  final bool enabled;

  /// How long a silence stands before the chip comes up by itself — 5 000.
  final int delayMs;

  /// The intention for the next move, in the learner's language, WITHOUT a prefix and AS A CLAUSE —
  /// «у моего сына температура» (наряд CONV-2, п. 11): the client prints it as it came inside its own
  /// «Скажи, что …». Null when hints are off, when the move is not the learner's and when the talk
  /// has ended.
  final String? native;

  Duration get delay => Duration(milliseconds: delayMs);
}

/// A CONSTRUCTION THE SERVER HEARD IN THE LEARNER'S LINE — the sage underline of кадр 37-8. Since наряд FIX-3 (§6) the
/// wire carries the pair alone: WHAT the construction is and what went into its window stand in `targets[]`, in one
/// place, and the line is underlined by that construction's own text.
typedef TalkPhraseRef = ({String sceneId, String ref});

/// ONE CONSTRUCTION THE TALK IS FOR — «Скажи в разговоре» (кадр 37-5), the plates over the microphone and their sheet
/// (37-7…37-11, 37-8d) and the summary's cards (37-12); наряд FIX-3 §6. The target of a talk is a FRAME WITH A WINDOW,
/// not a phrase: the lesson's own value stands grey beside it, and what the learner put in the window is theirs.
/// [said] and [valueTarget] are the SERVER's: it turns true on the move the server heard the construction by its own
/// rule (`PhraseUse`), and the phone counts nothing of its own.
class TalkTarget {
  const TalkTarget({
    required this.sceneId,
    required this.ref,
    required this.frameTarget,
    required this.frameNative,
    required this.said,
    this.exampleTarget,
    this.exampleNative,
    this.valueTarget,
  });

  final String sceneId;
  final String ref;

  /// КАРКАС С ОКНОМ — «I have pain in my ___.» Окно на проводе — `___`; фраза без окна приходит целиком.
  final String frameTarget;

  /// Тот же каркас на родном — «У меня болит ___.»
  final String frameNative;

  /// Значение урока, которым каркас сказан в уроке («lower back») — серым примером на входе и в листе; null у каркаса
  /// без окна.
  final String? exampleTarget;
  final String? exampleNative;

  /// Сказана ли конструкция в этом разговоре — считает СЕРВЕР своим правилом (`PhraseUse`), не клиент.
  final bool said;

  /// ЧТО УЧЕНИК ВСТАВИЛ В ОКНО, как услышано («Lisbon») — сервер (`value_target`); null, пока не сказана, и у каркаса
  /// без окна.
  final String? valueTarget;

  /// Окно каркаса на проводе.
  static const window = '___';

  /// НЕПОДВИЖНАЯ ЧАСТЬ КАРКАСА — «I have pain in my ___.» → «I have pain in my .»: слова, которые в реплике ученика
  /// принадлежат конструкции, что бы он ни вставил в окно (приёмка окна 2, п. 1).
  String get frameFixed => frameTarget.replaceAll(window, ' ');

  /// Каркас, сказанный значением: «I have pain in my ___.» + «lower back» → «I have pain in my lower back.»
  /// Нет значения — каркас как есть.
  String saidWith(String? value) => _with(frameTarget, value);

  /// То же на родном: «У меня болит ___.» + «поясница» → «У меня болит поясница.»
  String nativeWith(String? value) => _with(frameNative, value);

  static String _with(String frame, String? value) =>
      value == null || value.trim().isEmpty ? frame : frame.replaceFirst(window, value.trim());

  /// Что показывать в плашке и в листе: сказанное учеником значение, пока его нет — каркас с пустым окном.
  String get chipText => said ? saidWith(valueTarget) : frameTarget;

  /// The list as the server sent it, in its order (наряд FIX-3 §6 — цель разговора это КОНСТРУКЦИЯ). ADDITIVE: no list
  /// — none; an item without its frame is left out rather than guessed.
  static List<TalkTarget> listOf(Object? raw) => [
    if (raw is List)
      for (final t in raw)
        if (t is Map<String, dynamic> &&
            t['ref'] is String &&
            t['frame_target'] is String &&
            (t['frame_target'] as String).trim().isNotEmpty &&
            t['frame_native'] is String)
          TalkTarget(
            sceneId: (t['scene_id'] as String?) ?? '',
            ref: t['ref'] as String,
            frameTarget: t['frame_target'] as String,
            frameNative: t['frame_native'] as String,
            exampleTarget: _some(t['example_target']),
            exampleNative: _some(t['example_native']),
            said: t['said'] == true,
            valueTarget: _some(t['value_target']),
          ),
  ];

  static String? _some(Object? v) => v is String && v.trim().isNotEmpty ? v : null;
}

/// ONE LINE OF THE RIBBON, written once and never changed.
class TalkTurn {
  const TalkTurn({
    required this.index,
    required this.speaker,
    required this.kind,
    this.textTarget,
    this.textNative,
    this.audio,
    this.understood,
    this.offTopic,
    this.phrasesUsed = const [],
  });

  /// Its place in the journal — the server's number, not the client's.
  final int index;
  final TalkSpeaker speaker;
  final TalkTurnKind kind;

  /// The role's line, what the recogniser heard, or — on a rescue — the words a learner says when they did not catch it
  /// («Sorry?», from the target language's pack: наряд CONV-2, п. 4а); null on a skip.
  final String? textTarget;

  /// The role's line in the learner's language; null on the learner's own lines — an own bubble is
  /// only what was said (кадр 37-8, «Вычтено: перевод под своей репликой»).
  final String? textNative;

  /// The role's voice for this line; null when the vendor did not say it — the phone reads it.
  final CardAudio? audio;

  /// Did the move answer what was asked — the role's judgement; null on a rescue, a skip and the
  /// opening line.
  final bool? understood;
  final bool? offTopic;

  /// The phrases of the plan heard in this line, by the server's own rule of spoken grading.
  final List<TalkPhraseRef> phrasesUsed;

  bool get isOwn => speaker == TalkSpeaker.learner;

  factory TalkTurn.fromJson(Map<String, dynamic> j) => TalkTurn(
    index: _int(j['index'], 'turn.index'),
    speaker: TalkSpeaker.fromWire(j['speaker']),
    kind: TalkTurnKind.fromWire(j['kind']),
    textTarget: _text(j['text_target']),
    textNative: _text(j['text_native']),
    audio: CardAudio.maybe(j['audio']),
    understood: j['understood'] as bool?,
    offTopic: j['off_topic'] as bool?,
    phrasesUsed: [
      for (final p in _list(j['phrases_used'], 'turn.phrases_used'))
        if (p is Map<String, dynamic>)
          (sceneId: _string(p['scene_id'], 'phrase.scene_id'), ref: _string(p['ref'], 'phrase.ref')),
    ],
  );
}

/// THE SUMMARY (кадр 37-12) — every count in it is the server's.
class TalkSummary {
  const TalkSummary({
    required this.saidCount,
    required this.phrasesUsed,
    required this.phrasesTotal,
    required this.phrases,
    required this.understoodAll,
    required this.notUnderstood,
    required this.rescues,
    required this.returnsTomorrow,
    this.endedReason,
    this.minutes,
  });

  /// «Сказал сам N реплик».
  final int saidCount;
  final int phrasesUsed;
  final int phrasesTotal;

  /// THE TALK'S CONSTRUCTIONS AS IT LEFT THEM (кадр 37-12) — the same list and the same shape as `targets[]`, `said`
  /// and `value_target` final (наряд FIX-3 §6).
  final List<TalkTarget> phrases;
  final bool understoodAll;

  /// Moves the role ruled were not an answer to what it asked.
  final int notUnderstood;

  /// «переспросил N раз» — always neutral.
  final int rescues;
  final TalkEnd? endedReason;

  /// «Разговор окончен · 3 минуты».
  final int? minutes;

  /// Do the phrases that did not sound come back tomorrow. False on the rehearsal: there is no
  /// tomorrow before the event — «повтори перед приёмом».
  final bool returnsTomorrow;

  /// The constructions that did not sound, in the order the server listed them.
  List<TalkTarget> get notSaid => [for (final p in phrases) if (!p.said) p];

  /// The constructions that did sound.
  List<TalkTarget> get said => [for (final p in phrases) if (p.said) p];

  factory TalkSummary.fromJson(Map<String, dynamic> j) => TalkSummary(
    saidCount: _int(j['said_count'], 'summary.said_count'),
    phrasesUsed: _int(j['phrases_used'], 'summary.phrases_used'),
    phrasesTotal: _int(j['phrases_total'], 'summary.phrases_total'),
    phrases: TalkTarget.listOf(_list(j['phrases'], 'summary.phrases')),
    understoodAll: j['understood_all'] == true,
    notUnderstood: _int(j['not_understood'], 'summary.not_understood'),
    rescues: _int(j['rescues'], 'summary.rescues'),
    endedReason: TalkEnd.fromWire(j['ended_reason']),
    minutes: (j['minutes'] as num?)?.toInt(),
    returnsTomorrow: j['returns_tomorrow'] == true,
  );
}

/// THE WHOLE TALK — one document, the answer of all three calls.
class PlanConversation {
  const PlanConversation({
    required this.id,
    required this.planId,
    required this.day,
    required this.type,
    required this.state,
    required this.partner,
    required this.scenes,
    required this.minutesEstimate,
    required this.turnsLeft,
    required this.hints,
    required this.turns,
    this.summary,
    this.replay = false,
    this.titleNative,
    this.targets = const [],
  });

  final String id;
  final String planId;
  final int day;
  final TalkType type;
  final TalkState state;

  /// «Ещё раз» over a walked stage (наряд CONV-2, п. 2): an earlier talk of the day walked the sixth stage, this one is
  /// practice — it walks nothing and gives nothing back tomorrow (`summary.returns_tomorrow` false).
  final bool replay;

  /// «Поговори с врачом» — the entry's title as the server inflected it (`talk_title_native`, CONV-2 п. 12); null — a
  /// server before that, and then the screen prints no title of its own.
  final String? titleNative;

  /// THE CONSTRUCTIONS THE TALK IS FOR (`targets[]`, CONV-2 п. 10, FIX-3 §6), with [TalkTarget.said] and
  /// [TalkTarget.valueTarget] recounted by the server on every move — the entry lists them, the dock holds them as
  /// plates, the summary closes them. Empty — the server sent none, and the plates are not drawn.
  final List<TalkTarget> targets;

  /// The role and the scene the talk is in NOW.
  final TalkPartner partner;

  /// Every scene of the talk, in the order it walks them.
  final List<TalkScene> scenes;

  /// «около N минут».
  final int minutesEstimate;

  /// Moves of the scene left; a rescue spends none of them.
  final int turnsLeft;
  final TalkHints hints;

  /// The ribbon, oldest first. The server writes the role's opening line at the start, so it is
  /// never empty.
  final List<TalkTurn> turns;

  /// Null while the talk goes on.
  final TalkSummary? summary;

  bool get isEnded => state == TalkState.ended;

  /// The last line of the role — the one a tap on the microphone interrupts.
  TalkTurn? get lastPartnerTurn {
    for (final t in turns.reversed) {
      if (!t.isOwn) return t;
    }
    return null;
  }

  factory PlanConversation.fromJson(Object? json) {
    final j = _map(json, 'conversation');
    final partner = _map(j['partner'], 'conversation.partner');
    final scene = _map(j['scene'], 'conversation.scene');
    final hints = _map(j['hints'], 'conversation.hints');

    return PlanConversation(
      id: _string(j['id'], 'conversation.id'),
      planId: _string(j['plan_id'], 'conversation.plan_id'),
      day: _int(j['day'], 'conversation.day'),
      type: TalkType.fromWire(j['type']),
      state: TalkState.fromWire(j['state']),
      partner: TalkPartner(
        roleNative: _string(partner['role_native'], 'partner.role_native'),
        sceneNative: _string(scene['title_native'], 'scene.title_native'),
      ),
      scenes: [
        for (final s in _list(j['scenes'], 'conversation.scenes')) _scene(_map(s, 'conversation.scene')),
      ],
      minutesEstimate: _int(j['minutes_estimate'], 'conversation.minutes_estimate'),
      turnsLeft: _int(j['turns_left'], 'conversation.turns_left'),
      hints: TalkHints(
        enabled: hints['enabled'] == true,
        delayMs: _int(hints['delay_ms'], 'hints.delay_ms'),
        native: _text(hints['native']),
      ),
      turns: [
        for (final t in _list(j['turns'], 'conversation.turns')) TalkTurn.fromJson(_map(t, 'conversation.turn')),
      ],
      summary: j['summary'] == null ? null : TalkSummary.fromJson(_map(j['summary'], 'conversation.summary')),
      // CONV-2's fields are ADDITIVE: a talk without them is a talk from before them, not one that failed to load.
      replay: j['replay'] == true,
      titleNative: _text(j['talk_title_native']),
      targets: TalkTarget.listOf(j['targets']),
    );
  }

  static TalkScene _scene(Map<String, dynamic> s) => TalkScene(
    sceneId: _string(s['scene_id'], 'scene.scene_id'),
    titleNative: _string(s['title_native'], 'scene.title_native'),
    roleNative: _string(s['role_native'], 'scene.role_native'),
    lines: _int(s['lines'], 'scene.lines'),
    state: TalkSceneState.fromWire(s['state']),
  );
}

Map<String, dynamic> _map(Object? v, String what) =>
    v is Map<String, dynamic> ? v : throw PlanContractError('$what is missing');

List<Object?> _list(Object? v, String what) => v is List ? v : throw PlanContractError('$what is missing');

String _string(Object? v, String what) => v is String ? v : throw PlanContractError('$what is missing');

/// Необязательный текст: пустая строка — то же, что его нет.
String? _text(Object? v) => v is String && v.trim().isNotEmpty ? v : null;

int _int(Object? v, String what) => v is num ? v.toInt() : throw PlanContractError('$what is missing');
