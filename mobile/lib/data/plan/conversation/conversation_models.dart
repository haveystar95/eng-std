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
///
/// The fields of FIX-4 and FIX-4b (наряд CLIENT-FIX-4) are ADDITIVE and read leniently: a target without `state` is
/// read off its `said`, a line without `scene_id` / `scene_event` is a line of a talk from before them, and a hint
/// without `sentence` is no hint — none of them makes a talk unreadable.
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

/// WHERE A LINE STANDS BETWEEN TWO SCENES (`turns[].scene_event`, FIX-4 §4) — the rehearsal and a review talk walk
/// several scenes: the role of the scene says goodbye ([end]) and, in the same answer, the next scene's role greets the
/// learner first ([start]). The talk's very first line is a [start] too. A day talk has one scene and no boundaries.
enum TalkSceneEvent {
  start,
  end;

  /// Null — an ordinary line, a line of a talk from before FIX-4, or a word this build has no meaning for: the line is
  /// drawn as an ordinary one rather than the talk refused.
  static TalkSceneEvent? fromWire(Object? s) => switch (s) {
    'start' => start,
    'end' => end,
    _ => null,
  };
}

/// WHERE A CONSTRUCTION STANDS IN THE TALK (`targets[].state`, FIX-4 §2) — the server's judge of whole frames decides it
/// on every move: not yet ([none]), one word off ([almost] — the target still waits), said ([said]).
enum TalkTargetState {
  none,
  almost,
  said;

  /// A target without `state` (a talk from before FIX-4) or with a word this build does not know is read off its
  /// `said`, which the server keeps sending.
  static TalkTargetState fromWire(Object? s, {required bool said}) => switch (s) {
    'said' => TalkTargetState.said,
    'almost' => TalkTargetState.almost,
    'none' => TalkTargetState.none,
    _ => said ? TalkTargetState.said : TalkTargetState.none,
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

/// THE HINT OF THE LEARNER'S NEXT MOVE (кадры 37-7, 37-8e; FIX-4 §5, FIX-4b §2) — the policy fixed when the talk
/// started, and the server's hint for the move, which changes with every line of the role.
///
/// The phone prints the lesson's own sentence as it came — no «Скажи, что …» around it any more (DECISIONS п. 412):
/// the frame turned a question into «Скажи, что мне сказать вам его температуру?». `hints.native` (the same sentence
/// as a clause, for the build (20)) and `delay_ms` (the silence that raised the old chip) are not read.
class TalkHints {
  const TalkHints({required this.enabled, this.sentence, this.target, this.sceneId, this.ref});

  /// False — «Без подсказок»: the plate stands only after «Подсказать» (37-7c). The hint itself comes all the same
  /// (FIX-4c §2) — the same target the server would hint with hints on.
  final bool enabled;

  /// THE LESSON'S SENTENCE of the target, in the learner's language, with its capital and its closing mark — «У меня
  /// есть боль в плече.», «Мне сказать вам его температуру?». Null when the move is not the learner's, when everything
  /// of the scene is said and when the talk has ended — in both modes (FIX-4c §2).
  final String? sentence;

  /// THE EXACT LINE in the target language — «The pain is sharp when he bends.» — only on the move right after the
  /// learner said this target ALMOST (one word off); null otherwise.
  final String? target;

  /// Which target the hint is for — its pair in `targets[]`.
  final String? sceneId;
  final String? ref;
}

/// A CONSTRUCTION THE SERVER HEARD IN THE LEARNER'S LINE — the sage underline of кадр 37-8. Since наряд FIX-3 (§6) the
/// wire carries the pair alone: WHAT the construction is and what went into its window stand in `targets[]`, in one
/// place, and the line is underlined by that construction's own text.
typedef TalkPhraseRef = ({String sceneId, String ref});

/// ONE CONSTRUCTION THE TALK IS FOR — «Скажи в разговоре» (кадр 37-5), the plates over the microphone and their sheet
/// (37-7…37-11, 37-8d) and the summary's cards (37-12); наряд FIX-3 §6. The target of a talk is a FRAME WITH A WINDOW,
/// not a phrase: the lesson's own value stands grey beside it, and what the learner put in the window is theirs.
/// [state] and [valueTarget] are the SERVER's: its judge of whole frames decides them on every move (FIX-4 §2), and
/// the phone counts nothing of its own. The same shape carries «Ещё вспомнил» (`extra_said[]`) — constructions of the
/// scene said beyond the targets.
class TalkTarget {
  const TalkTarget({
    required this.sceneId,
    required this.ref,
    required this.frameTarget,
    required this.frameNative,
    required this.state,
    this.exampleTarget,
    this.exampleNative,
    this.valueTarget,
    this.lineNative,
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

  /// Where the construction stands in the talk — the server's judge, not the phone's.
  final TalkTargetState state;

  /// ЧТО УЧЕНИК ВСТАВИЛ В ОКНО, как услышано («Lisbon») — сервер (`value_target`); null, пока не сказана, и у каркаса
  /// без окна.
  final String? valueTarget;

  /// THE LESSON'S WHOLE LINE in the learner's language, as the model wrote it — «Мне нужна запись на приём.»
  /// (`line_native`, LANG-1b §3). Shown as it came: glueing [frameNative] with [exampleNative] disagrees with the frame
  /// («Мне нужно запись на приём»). Null — a talk from before LANG-1b: no native line is drawn.
  final String? lineNative;

  /// Said in this talk — the same as the wire's `said`.
  bool get said => state == TalkTargetState.said;

  /// One word off the whole frame: the target still waits (37-8e).
  bool get almost => state == TalkTargetState.almost;

  /// The construction's pair — `scene_id` + `ref` name it everywhere on the wire.
  String get key => '$sceneId:$ref';

  /// THE LESSON'S LINE in the target language — the frame said with the lesson's own value: «I have some shoulder
  /// pain.» (the line «почти — скажи целиком: …» and «из урока: …» of the sheet 37-8d, the second line of the hint).
  String get lessonLine => saidWith(exampleTarget);

  /// Окно каркаса на проводе.
  static const window = '___';

  /// НЕПОДВИЖНАЯ ЧАСТЬ КАРКАСА — «I have pain in my ___.» → «I have pain in my .»: слова, которые в реплике ученика
  /// принадлежат конструкции, что бы он ни вставил в окно (приёмка окна 2, п. 1).
  String get frameFixed => frameTarget.replaceAll(window, ' ');

  /// Каркас, сказанный значением: «I have pain in my ___.» + «lower back» → «I have pain in my lower back.»
  /// Нет значения — каркас как есть.
  String saidWith(String? value) => _with(frameTarget, value);

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
            state: TalkTargetState.fromWire(t['state'], said: t['said'] == true),
            valueTarget: _some(t['value_target']),
            lineNative: _some(t['line_native']),
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
    this.extraSaid = const [],
    this.sceneId,
    this.sceneEvent,
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

  /// «Ещё вспомнил» (FIX-4 §2): constructions of the scene this line said beyond the targets — they are underlined as
  /// the targets are.
  final List<TalkPhraseRef> extraSaid;

  /// The scene the line was said in; null on a line from before FIX-4.
  final String? sceneId;

  /// The role's goodbye to its scene or the next role's greeting — null on an ordinary line.
  final TalkSceneEvent? sceneEvent;

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
    phrasesUsed: _refs(_list(j['phrases_used'], 'turn.phrases_used')),
    // FIX-4's fields are additive: a line without them is a line from before them.
    extraSaid: _refs(j['extra_said'] is List ? j['extra_said'] as List : const []),
    sceneId: _text(j['scene_id']),
    sceneEvent: TalkSceneEvent.fromWire(j['scene_event']),
  );

  static List<TalkPhraseRef> _refs(List<Object?> raw) => [
    for (final p in raw)
      if (p is Map<String, dynamic>) (sceneId: _string(p['scene_id'], 'phrase.scene_id'), ref: _string(p['ref'], 'phrase.ref')),
  ];
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
    this.extraSaid = const [],
    this.endedByLimit = false,
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
  /// tomorrow before the event — «повтори перед разговором».
  final bool returnsTomorrow;

  /// «ЕЩЁ ВСПОМНИЛ» (кадры 37-12, 37-12b; FIX-4 §2) — constructions of the talk's scenes the learner said beyond the
  /// targets, in the targets' shape; empty — the group is not drawn.
  final List<TalkTarget> extraSaid;

  /// The talk ran out of its time, its moves or its money (FIX-4 §4) — the role still said goodbye, and the summary
  /// says «Разговор закончился по времени» over the plates (37-12).
  final bool endedByLimit;

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
    extraSaid: TalkTarget.listOf(j['extra_said']),
    endedByLimit: j['ended_by_limit'] == true,
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
    this.extraSaid = const [],
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

  /// «Ещё вспомнил» so far (FIX-4 §2) — the scenes' constructions said beyond [targets]; the lines that said them are
  /// underlined by their words.
  final List<TalkTarget> extraSaid;

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

  /// The learner's last line — the one the judge's «Почти — скажи целиком» stands under (37-8e).
  TalkTurn? get lastOwnTurn {
    for (final t in turns.reversed) {
      if (t.isOwn) return t;
    }
    return null;
  }

  /// The journal's last number — what a later answer's new lines are counted from.
  int get lastIndex => turns.isEmpty ? 0 : turns.last.index;

  /// A scene of the talk by its id; null for an id the talk does not name.
  TalkScene? sceneOf(String? sceneId) {
    if (sceneId == null) return null;
    for (final s in scenes) {
      if (s.sceneId == sceneId) return s;
    }
    return null;
  }

  /// «Сцена N из M» — the scene's place among the talk's scenes, from one; null — not a scene of this talk.
  int? sceneNumberOf(String? sceneId) {
    for (final (i, s) in scenes.indexed) {
      if (s.sceneId == sceneId) return i + 1;
    }
    return null;
  }

  /// THE SCENE THE TALK IS IN NOW — the one `scenes[]` marks `current`; an ended talk marks none, and then it is the
  /// scene of the role's last line. Null — the talk names no scene of its own, and every target is the scene's.
  String? get currentSceneId {
    for (final s in scenes) {
      if (s.state == TalkSceneState.current) return s.sceneId;
    }
    for (final t in turns.reversed) {
      if (!t.isOwn && t.sceneId != null) return t.sceneId;
    }
    return null;
  }

  /// The constructions of [sceneId], in the server's order; null — every construction of the talk.
  List<TalkTarget> targetsOf(String? sceneId) =>
      sceneId == null ? targets : [for (final t in targets) if (t.sceneId == sceneId) t];

  /// A construction by its pair — a target or one of «Ещё вспомнил»; null — the talk does not hold it.
  TalkTarget? constructionOf(String sceneId, String ref) {
    for (final t in [...targets, ...extraSaid]) {
      if (t.sceneId == sceneId && t.ref == ref) return t;
    }
    return null;
  }

  /// A BOUNDARY OF THE RIBBON (39-1): the line at [position] of [turns] is the next scene's greeting right after the
  /// previous scene's goodbye. The talk's very first line is a `start` too, and it is no boundary — the entry (37-5)
  /// opened that scene.
  bool opensScene(int position) =>
      position > 0 &&
      position < turns.length &&
      turns[position].sceneEvent == TalkSceneEvent.start &&
      turns[position - 1].sceneEvent == TalkSceneEvent.end;

  /// THE SCENE CHANGE NOT TAKEN YET — the last line is a boundary greeting and the learner has said nothing after it:
  /// the transition card stands (39-1), the greeting waits for «Продолжить», and so it does after a restart (наряд
  /// CLIENT-FIX-4 §1). Null — no scene is waiting.
  TalkTurn? get pendingSceneStart => !isEnded && opensScene(turns.length - 1) ? turns.last : null;

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
        sentence: _text(hints['sentence']),
        target: _text(hints['target']),
        sceneId: _text(hints['scene_id']),
        ref: _text(hints['ref']),
      ),
      turns: [
        for (final t in _list(j['turns'], 'conversation.turns')) TalkTurn.fromJson(_map(t, 'conversation.turn')),
      ],
      summary: j['summary'] == null ? null : TalkSummary.fromJson(_map(j['summary'], 'conversation.summary')),
      // CONV-2's fields are ADDITIVE: a talk without them is a talk from before them, not one that failed to load.
      replay: j['replay'] == true,
      titleNative: _text(j['talk_title_native']),
      targets: TalkTarget.listOf(j['targets']),
      extraSaid: TalkTarget.listOf(j['extra_said']),
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
