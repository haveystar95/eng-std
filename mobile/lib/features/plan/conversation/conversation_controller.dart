import 'dart:async';

import 'package:flutter/foundation.dart';

import 'package:eng_std/theme/motion.dart';

import '../../../data/api_client.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../session/session_voice.dart';

/// What the talk screen is doing right now — the CLIENT's own reading, on top of the server's
/// [TalkState] (наряд CLIENT-CONV-1a, кадры 37-6…37-11).
///
/// The server knows three states; the screen needs more that are nobody's business but the phone's: the role's voice
/// is SOUNDING ([agentSpeaking], кадр 37-6 — the microphone is dimmed and says «слушай»), a move is in flight
/// ([sending], кадр 37-8 — «врач думает», three dots), and a scene has ended while the next one waits behind the
/// transition card ([sceneChange], кадр 39-1).
enum TalkPhase {
  /// The talk is being started or re-read; the screen has nothing to draw yet.
  opening,

  /// The talk could not be started at all — the screen says so and offers «Повторить».
  openFailed,

  /// The role's line is sounding. A tap on the microphone cuts it (кадр 37-9).
  agentSpeaking,

  /// The learner's move: the microphone waits (кадр 37-7).
  yourTurn,

  /// The move is with the server — «врач думает».
  sending,

  /// A SCENE HAS SAID GOODBYE (39-1): the role of the scene just closed says it, the transition card stands under it,
  /// the constructions hide and the microphone is dimmed. The next role's greeting came in the same answer and is held
  /// back until «Продолжить» ([ConversationController.continueScene]).
  sceneChange,

  /// The role said goodbye (кадры 37-11, 37-12).
  ended,
}

/// What went wrong on the last move — кадр 37-10. None of these words say «error», «server» or
/// «API»: a failure is described by what is visible.
enum TalkTrouble {
  /// The recogniser heard nothing — «не расслышал — скажи ещё раз». Local: silence is not sent.
  unheard,

  /// The move did not reach the server — «связь пропала — разговор продолжится отсюда».
  offline,

  /// The role did not answer (503 `plan_conversation_unavailable`) — «врач не отвечает». Nothing was
  /// written, so the same move may be repeated one for one.
  agentSilent,
}

/// A move waiting to be sent again after a failure — `kind` and, for `said`, what was heard.
typedef TalkMove = ({String kind, String? heard});

/// THE HINT PLATE AS IT STANDS (кадры 37-7, 37-7e, 37-8e) — the lesson's sentence in the learner's language, the
/// target's line in the target language under it when [open], and whether there is such a line at all.
typedef TalkHintView = ({String sentence, String? line, bool open});

/// What the talk does with the server. Separate from [ApiClient] — a test substitutes its own.
abstract interface class ConversationBackend {
  Future<PlanConversation> start(String planId, int day, {required bool again, required bool hints});
  Future<PlanConversation> move(String planId, String conversationId, {required String kind, String? heard});
  Future<PlanConversation> read(String planId, String conversationId);
}

/// The server via [ApiClient].
class ApiConversationBackend implements ConversationBackend {
  const ApiConversationBackend(this.api);

  final ApiClient api;

  @override
  Future<PlanConversation> start(String planId, int day, {required bool again, required bool hints}) =>
      api.startConversation(planId, day, again: again, hints: hints);

  @override
  Future<PlanConversation> move(String planId, String conversationId, {required String kind, String? heard}) =>
      api.conversationTurn(planId, conversationId, kind: kind, heard: heard);

  @override
  Future<PlanConversation> read(String planId, String conversationId) => api.conversation(planId, conversationId);
}

/// THE TALK WITH THE AGENT — the state of one screen (наряды CLIENT-CONV-1a, CLIENT-FIX-4). Draws nothing.
///
/// THE SERVER IS THE DOCUMENT. Every call answers with the whole talk, so an answer REPLACES what
/// the screen held: there is no local ribbon to merge, no local count, no guess at what the other
/// half of a lost answer said. What the phone owns is what the server has no field for: whether the role's voice is
/// sounding, whether the learner cut it off (кадр 37-9), whether the next scene's greeting is still held behind the
/// transition card (39-1), which of the learner's lines the judge found «almost» (37-8e), and whether the learner has
/// asked for the hint under «Без подсказок» or opened its second line.
class ConversationController extends ChangeNotifier {
  ConversationController({
    required this.backend,
    required this.planId,
    required this.day,
    required this.voice,
    required this.hints,
  });

  final ConversationBackend backend;
  final String planId;
  final int day;
  final SessionVoice voice;

  /// «Без подсказок» as the entry card left it — sent once, at the start, and fixed for the talk.
  final bool hints;

  /// HOW LONG A RECORDING WAITS OUT A PAUSE IN THE TALK — 1.5 s, where a trainer card waits 1 s
  /// (`SpeechTurnConfig.silenceAfterSpeech`, наряд FIX-1 §3).
  ///
  /// The number is the наряд's, and the difference is the point: on a card the learner says one
  /// line they have just read, in a talk they think mid-sentence, and a second of thought closing
  /// the move mid-thought is the whole of «врач ответил не на то». The mechanism is unchanged — a
  /// pause after the last word, or a tap.
  static const Duration silenceClosesTurn = Duration(milliseconds: 1500);

  PlanConversation? _talk;
  TalkPhase _phase = TalkPhase.opening;
  TalkTrouble? _trouble;
  Object? _openError;
  TalkMove? _lastMove;
  bool _disposed = false;

  /// The role's lines the learner cut off — by their `index` in the journal (кадр 37-9). The server
  /// has no field for it and does not need one: it is a fact about listening, not about the talk.
  final Set<int> _interrupted = {};

  /// The index of the role's line whose voice is sounding (or was last asked to sound).
  int? _sounding;
  int _playSerial = 0;

  /// The next scene's greeting the transition card holds back — by its index; null — nothing is held.
  int? _heldStart;

  /// The learner's line the judge found «almost» — «Почти — скажи целиком» stands under it (37-8e).
  int? _almostAt;

  /// «Подсказать» was tapped under «Без подсказок» — the plate stands for this move (37-7c).
  bool _hintRevealed = false;

  /// The plate's second line — the target's line in the target language — is open (37-7e, 37-8e).
  bool _hintOpen = false;

  PlanConversation? get talk => _talk;
  TalkPhase get phase => _phase;
  TalkTrouble? get trouble => _trouble;

  /// Why the talk would not start — for the code the screen turns into a sentence.
  Object? get openError => _openError;

  /// THE MOVE THE SERVER HAS NOT ANSWERED YET — the learner's own line while it is in flight, and
  /// after a failure until it is sent again (кадры 37-8 «врач думает» and 37-10 keep it in the
  /// ribbon). The server writes the learner's line only together with the role's answer, so for these
  /// seconds the phone is the only one who knows what was said; the server's own copy replaces it the
  /// moment a document arrives. Null — nothing is waiting.
  TalkMove? get pendingMove => _lastMove;

  bool interruptedAt(int index) => _interrupted.contains(index);

  /// Is the role's line [index] the one that is sounding now.
  bool sounding(int index) => _sounding == index;

  /// A tap on the microphone would cut a line that is sounding now (кадр 37-9) — never the breath before the next
  /// scene's greeting, when there is nothing to cut yet.
  bool get canInterrupt => _phase == TalkPhase.agentSpeaking && _sounding != null;

  /// The next scene's greeting is held back — the ribbon stops before it (39-1, and the breath after «Продолжить»).
  int? get heldStart => _heldStart;

  /// The learner's line «Почти — скажи целиком» stands under, while it is the learner's last one (37-8e).
  int? get almostAt => _almostAt;

  /// THE END SHEET (37-11) rises once the role's goodbye has been said — «Разговор окончен» over the words, not before
  /// them (наряд CLIENT-FIX-4 §4).
  bool get endSheetUp => _phase == TalkPhase.ended && _sounding == null;

  /// THE SCENE THE STRIP NAMES (30-2b) — the one the talk is in, except while the transition card stands: then the scene
  /// that has just said goodbye («полоса сцены под шапкой ещё старая», 39-1).
  String? get stripSceneId {
    final talk = _talk;
    if (talk == null) return null;
    if (_phase == TalkPhase.sceneChange && talk.turns.length >= 2) return talk.turns[talk.turns.length - 2].sceneId;
    return talk.currentSceneId;
  }

  /// THE HINT PLATE (37-7, 37-8e) — what it says, or null when none stands. It stands under the role's last line in
  /// every state of the learner's move (waiting, listening, after «Sorry?», after «не расслышал») and never while the
  /// role speaks, a move is on its way or a failed move waits to be sent again (37-10).
  ///
  /// It is always the SERVER's hint as it came: `hints.sentence`, and under it `hints.target` — the exact line after an
  /// «almost», open at once — or, on a tap, the target's lesson line. The server sends it in both modes (FIX-4c §2);
  /// under «Без подсказок» the plate stands only after «Подсказать» ([showHint]).
  TalkHintView? get hint {
    final talk = _talk;
    if (talk == null || _phase != TalkPhase.yourTurn || _lastMove != null) return null;
    if (!talk.hints.enabled && !_hintRevealed) return null;
    final sentence = talk.hints.sentence;
    if (sentence == null) return null;
    final line = talk.hints.target ?? _targetOf(talk, talk.hints.sceneId, talk.hints.ref)?.lessonLine;
    return (sentence: sentence, line: line, open: _hintOpen && line != null);
  }

  /// «Подсказать» stands only under «Без подсказок», while the move is the learner's, the plate is not up yet and the
  /// server has a hint to show (37-7c); with hints on the plate is always there and the button is gone.
  bool get hintButtonShown {
    final talk = _talk;
    return talk != null &&
        !talk.hints.enabled &&
        _phase == TalkPhase.yourTurn &&
        _lastMove == null &&
        !_hintRevealed &&
        talk.hints.sentence != null;
  }

  static TalkTarget? _targetOf(PlanConversation talk, String? sceneId, String? ref) {
    if (sceneId == null || ref == null) return null;
    for (final t in talk.targets) {
      if (t.sceneId == sceneId && t.ref == ref) return t;
    }
    return null;
  }

  /// START (кадр 37-5) — or step back into the talk this day already has open: the same call answers
  /// with it, so a phone coming back from the background continues where it stood.
  /// [open], answered as soon as the SERVER has answered — the role's first line may still be sounding. A screen that
  /// shows the ribbon on this answer shows the line while it is said (37-6); [open] itself returns only once the line
  /// has been said, and a screen waiting on it would hold the role's first words over the entry (37-5).
  Future<void> openAnswered({bool again = false}) {
    final answered = Completer<void>();
    void heard() {
      if (_phase != TalkPhase.opening && !answered.isCompleted) answered.complete();
    }

    addListener(heard);
    unawaited(open(again: again).whenComplete(() {
      if (!answered.isCompleted) answered.complete();
    }));
    return answered.future.whenComplete(() {
      if (!_disposed) removeListener(heard);
    });
  }

  Future<void> open({bool again = false}) async {
    _phase = TalkPhase.opening;
    _openError = null;
    _trouble = null;
    _notify();
    try {
      final talk = await backend.start(planId, day, again: again, hints: hints);
      if (_disposed) return;
      if (again) _interrupted.clear();
      await _accept(talk, speak: true);
    } catch (e) {
      if (_disposed) return;
      _openError = e;
      _phase = TalkPhase.openFailed;
      _notify();
    }
  }

  /// The learner said something. Silence never travels: an empty recording is «не расслышал», local
  /// and free (кадр 37-10).
  Future<void> say(String heard) async {
    if (heard.trim().isEmpty) {
      _trouble = TalkTrouble.unheard;
      _notify();
      return;
    }
    await _move((kind: 'said', heard: heard));
  }

  /// «Не понял» — the role says the same thing again, simpler and slower. It spends no move of the
  /// scene and carries no judgement.
  Future<void> rescue() => _move((kind: 'rescue', heard: null));

  /// «Пропустить» — the turn is let go; the move is spent and nothing is judged.
  Future<void> skip() => _move((kind: 'skip', heard: null));

  /// «Повторить» on кадр 37-10: the ribbon is re-read first — a dropped connection may have hidden
  /// an answer the server did write — and only a move that is still the learner's is sent again.
  Future<void> retry() async {
    final move = _lastMove;
    if (move == null) {
      await reread();
      return;
    }
    _trouble = null;
    _phase = TalkPhase.sending;
    _notify();
    final before = _talk?.turns.length ?? 0;
    final fresh = await _read();
    if (_disposed) return;
    if (fresh == null) {
      _trouble = TalkTrouble.offline;
      _phase = TalkPhase.yourTurn;
      _notify();
      return;
    }
    // The move DID get through: the journal grew while the phone was not listening.
    if (fresh.turns.length > before || fresh.state != TalkState.yourTurn) {
      _lastMove = null;
      await _accept(fresh, speak: true);
      return;
    }
    await _accept(fresh, speak: false);
    if (_disposed) return;
    await _move(move);
  }

  /// Re-read the talk — the door back in (кадр 37-10, «разговор продолжится отсюда»).
  Future<void> reread() async {
    final fresh = await _read();
    if (_disposed || fresh == null) return;
    await _accept(fresh, speak: true);
  }

  /// «Не расслышал» is dismissed by the next recording.
  void clearTrouble() {
    if (_trouble == null) return;
    _trouble = null;
    _notify();
  }

  /// A TAP ON THE MICROPHONE WHILE THE ROLE IS SPEAKING (кадр 37-9): the sound stops, the line stays
  /// in the ribbon with «прервано», and the move is the learner's at once. The server is not told —
  /// it has no field for it and the talk did not change.
  Future<void> interrupt() async {
    final index = _sounding;
    if (!canInterrupt || index == null) return;
    _playSerial++;
    _interrupted.add(index);
    _sounding = null;
    _phase = TalkPhase.yourTurn;
    _notify();
    await voice.stop();
  }

  /// «ПРОДОЛЖИТЬ» ON THE TRANSITION CARD (39-1 → 39-1b): the card folds into the scene's divider, the strip turns to the
  /// next scene, and after a breath the next role greets the learner in its own voice — the line the answer brought and
  /// the card held back. The voice is the server's (by the role's sex); the phone chooses nothing.
  Future<void> continueScene() async {
    final talk = _talk;
    final held = _heldStart;
    if (_phase != TalkPhase.sceneChange || talk == null || held == null) return;
    final serial = ++_playSerial;
    // A goodbye still sounding is let go: «Продолжить» is the learner's «I am ready».
    if (_sounding != null) {
      _sounding = null;
      unawaited(voice.stop());
    }
    _phase = TalkPhase.agentSpeaking;
    _notify();
    await Future<void>.delayed(AppMotion.talkSceneGreetingDelay);
    if (_disposed || serial != _playSerial) return;
    _heldStart = null;
    final greeting = talk.turns.where((t) => t.index == held).firstOrNull;
    if (greeting == null) {
      _phase = TalkPhase.yourTurn;
      _notify();
      return;
    }
    _sounding = greeting.index;
    _notify();
    await _say(greeting, keepPhase: false);
  }

  /// «Подсказать» under «Без подсказок» (37-7c) — the plate stands for this move and the button goes away; after an
  /// «almost» it stands with its second line open, as the server's plate would.
  void showHint() {
    final talk = _talk;
    if (talk == null || talk.hints.enabled || _hintRevealed || talk.hints.sentence == null) return;
    _hintRevealed = true;
    // After an «almost» the server's plate stands with its exact line open.
    _hintOpen = talk.hints.target != null;
    _notify();
  }

  /// A tap on the plate opens the target's line under the sentence, a second tap folds it (37-7e).
  void toggleHint() {
    _hintOpen = !_hintOpen;
    _notify();
  }

  /// The recording started — whatever went wrong before it is no longer the news.
  void recordingStarted() => clearTrouble();

  Future<void> _move(TalkMove move) async {
    final talk = _talk;
    if (talk == null || _phase == TalkPhase.sending) return;
    _lastMove = move;
    _trouble = null;
    _phase = TalkPhase.sending;
    _notify();
    try {
      final fresh = await backend.move(planId, talk.id, kind: move.kind, heard: move.heard);
      if (_disposed) return;
      _lastMove = null;
      await _accept(fresh, speak: true);
    } catch (e) {
      if (_disposed) return;
      await _onMoveFailed(e);
    }
  }

  Future<void> _onMoveFailed(Object e) async {
    switch (problemCodeOf(e)) {
      // The role did not answer. Nothing was written: the same move goes again, one for one.
      case 'plan_conversation_unavailable':
        _trouble = TalkTrouble.agentSilent;
        _phase = TalkPhase.yourTurn;
        _notify();
      // The previous move is still being answered. A second call buys no second reply — the phone
      // reads the talk instead.
      case 'plan_conversation_not_your_turn':
        _lastMove = null;
        final fresh = await _read();
        if (_disposed) return;
        if (fresh == null) {
          _trouble = TalkTrouble.offline;
          _phase = TalkPhase.yourTurn;
          _notify();
          return;
        }
        await _accept(fresh, speak: true);
      // The role has already said goodbye: the summary is what is left to show.
      case 'plan_conversation_ended':
        _lastMove = null;
        final fresh = await _read();
        if (_disposed) return;
        await _accept(fresh ?? _talk!, speak: false);
      default:
        _trouble = TalkTrouble.offline;
        _phase = TalkPhase.yourTurn;
        _notify();
    }
  }

  Future<PlanConversation?> _read() async {
    final talk = _talk;
    if (talk == null) return null;
    try {
      return await backend.read(planId, talk.id);
    } catch (e) {
      debugPrint('[talk] re-read: $e');
      return null;
    }
  }

  /// A fresh document replaces the screen. [speak] — say the role's newest line aloud, unless the learner has already
  /// cut that very line off.
  ///
  /// An answer that closed a scene (FIX-4 §4) ends on the role's goodbye and the next role's greeting: the goodbye is
  /// said, the greeting is HELD behind the transition card until «Продолжить». The goodbye is said once — with the
  /// answer that brought it; a talk re-entered on that boundary shows the card again without saying it twice.
  Future<void> _accept(PlanConversation fresh, {required bool speak}) async {
    final previous = _talk;
    _talk = fresh;
    _almostAt = _almostOf(previous, fresh);
    _hintRevealed = false;
    _hintOpen = fresh.hints.target != null;
    if (fresh.isEnded) {
      _heldStart = null;
      _phase = TalkPhase.ended;
      final goodbye = fresh.lastPartnerTurn;
      if (speak && goodbye != null && !_interrupted.contains(goodbye.index)) {
        _sounding = goodbye.index;
        _notify();
        await _say(goodbye, keepPhase: true);
        return;
      }
      _sounding = null;
      _notify();
      return;
    }
    final greeting = fresh.pendingSceneStart;
    if (greeting != null) {
      _heldStart = greeting.index;
      _phase = TalkPhase.sceneChange;
      final goodbye = fresh.turns[fresh.turns.length - 2];
      final fresher = previous != null && previous.id == fresh.id && goodbye.index > previous.lastIndex;
      if (speak && fresher) {
        _sounding = goodbye.index;
        _notify();
        await _say(goodbye, keepPhase: true);
        return;
      }
      _sounding = null;
      _notify();
      return;
    }
    _heldStart = null;
    final line = fresh.lastPartnerTurn;
    if (speak && line != null && !_interrupted.contains(line.index)) {
      _phase = TalkPhase.agentSpeaking;
      _sounding = line.index;
      _notify();
      await _say(line, keepPhase: false);
      return;
    }
    _phase = TalkPhase.yourTurn;
    _sounding = null;
    _notify();
  }

  /// WHICH OF THE LEARNER'S LINES THE JUDGE FOUND «ALMOST» (37-8e): the move whose answer brought a target to `almost`,
  /// or the one right before a hint with its exact line (`hints.target` comes only on the move after an «almost»). A
  /// document with nothing new said keeps the line where it stood; the next move of the learner takes it away.
  int? _almostOf(PlanConversation? previous, PlanConversation fresh) {
    final own = fresh.lastOwnTurn;
    if (own == null) return null;
    final before = previous != null && previous.id == fresh.id ? previous : null;
    if (before != null && own.index <= before.lastIndex) return _almostAt;
    if (fresh.hints.target != null) return own.index;
    if (before == null) return null;
    final was = {for (final t in before.targets) if (t.almost) t.key};
    return fresh.targets.any((t) => t.almost && !was.contains(t.key)) ? own.index : null;
  }

  /// Say the role's line and hand the move back. A line the vendor did not read is read by the
  /// phone — the talk does not wait for a file that is not there.
  Future<void> _say(TalkTurn? line, {required bool keepPhase}) async {
    if (line == null) return;
    final serial = ++_playSerial;
    await voice.play(line.audio, fallback: line.textTarget ?? '', key: 'talk-${line.index}');
    if (_disposed || serial != _playSerial) return;
    _sounding = null;
    if (!keepPhase) _phase = TalkPhase.yourTurn;
    _notify();
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    super.dispose();
  }
}
