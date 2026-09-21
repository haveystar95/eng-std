import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../data/api_client.dart';
import '../../../data/plan/conversation/conversation_models.dart';
import '../session/session_voice.dart';

/// What the talk screen is doing right now — the CLIENT's own reading, on top of the server's
/// [TalkState] (наряд CLIENT-CONV-1a, кадры 37-6…37-11).
///
/// The server knows three states; the screen needs two more that are nobody's business but the
/// phone's: the role's voice is SOUNDING ([agentSpeaking], кадр 37-6 — the microphone is dimmed and
/// says «слушай»), and a move is in flight ([sending], кадр 37-8 — «врач думает», three dots).
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

/// THE TALK WITH THE AGENT — the state of one screen (наряд CLIENT-CONV-1a). Draws nothing.
///
/// THE SERVER IS THE DOCUMENT. Every call answers with the whole talk, so an answer REPLACES what
/// the screen held: there is no local ribbon to merge, no local count, no guess at what the other
/// half of a lost answer said. What the phone owns is exactly three things the server has no field
/// for: whether the role's voice is sounding, whether the learner cut it off (кадр 37-9), and the
/// five seconds of silence after which the hint chip comes up by itself.
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

  /// The hint chip is up — by silence or by «Подсказать»; it never stands together with the button.
  bool _chip = false;
  Timer? _silence;

  PlanConversation? get talk => _talk;
  TalkPhase get phase => _phase;
  TalkTrouble? get trouble => _trouble;

  /// Why the talk would not start — for the code the screen turns into a sentence.
  Object? get openError => _openError;
  bool get chipShown => _chip;

  /// «Подсказать» stands only while the learner's move is open, hints are on for this talk and the
  /// chip is not up yet (кадр 37-7: «вместе они не стоят»).
  bool get hintButtonShown =>
      _phase == TalkPhase.yourTurn && (_talk?.hints.enabled ?? false) && !_chip && hintNative != null;

  /// The intention to offer, as the server worded it — null when there is none.
  String? get hintNative => _talk?.hints.native;

  /// THE MOVE THE SERVER HAS NOT ANSWERED YET — the learner's own line while it is in flight, and
  /// after a failure until it is sent again (кадры 37-8 «врач думает» and 37-10 keep it in the
  /// ribbon). The server writes the learner's line only together with the role's answer, so for these
  /// seconds the phone is the only one who knows what was said; the server's own copy replaces it the
  /// moment a document arrives. Null — nothing is waiting.
  TalkMove? get pendingMove => _lastMove;

  bool interruptedAt(int index) => _interrupted.contains(index);

  /// Is the role's line [index] the one that is sounding now.
  bool sounding(int index) => _sounding == index && _phase == TalkPhase.agentSpeaking;

  /// START (кадр 37-5) — or step back into the talk this day already has open: the same call answers
  /// with it, so a phone coming back from the background continues where it stood.
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
    if (_phase != TalkPhase.agentSpeaking) return;
    final index = _sounding;
    _playSerial++;
    if (index != null) _interrupted.add(index);
    _sounding = null;
    _phase = TalkPhase.yourTurn;
    _armSilence();
    _notify();
    await voice.stop();
  }

  /// «Подсказать» — the chip comes up now and the button goes away.
  void showHint() {
    if (!(_talk?.hints.enabled ?? false) || _chip) return;
    _silence?.cancel();
    _chip = true;
    _notify();
  }

  /// The recording started — the silence that would raise the chip is over.
  void recordingStarted() {
    _silence?.cancel();
    clearTrouble();
  }

  Future<void> _move(TalkMove move) async {
    final talk = _talk;
    if (talk == null || _phase == TalkPhase.sending) return;
    _silence?.cancel();
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

  /// A fresh document replaces the screen. [speak] — say the role's newest line aloud, unless the
  /// learner has already cut that very line off.
  Future<void> _accept(PlanConversation fresh, {required bool speak}) async {
    _talk = fresh;
    _chip = false;
    if (fresh.isEnded) {
      _phase = TalkPhase.ended;
      _sounding = null;
      _notify();
      if (speak) await _say(fresh.lastPartnerTurn, keepPhase: true);
      return;
    }
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
    _armSilence();
    _notify();
  }

  /// Say the role's line and hand the move back. A line the vendor did not read is read by the
  /// phone — the talk does not wait for a file that is not there.
  Future<void> _say(TalkTurn? line, {required bool keepPhase}) async {
    if (line == null) return;
    final serial = ++_playSerial;
    await voice.play(line.audio, fallback: line.textTarget ?? '', key: 'talk-${line.index}');
    if (_disposed || serial != _playSerial) return;
    if (keepPhase) {
      _sounding = null;
      _notify();
      return;
    }
    _sounding = null;
    _phase = TalkPhase.yourTurn;
    _armSilence();
    _notify();
  }

  /// FIVE SECONDS OF SILENCE RAISE THE CHIP BY ITSELF (кадр 37-7) — the delay is the server's
  /// (`hints.delay_ms`), the counting is the phone's. Never under «Без подсказок».
  void _armSilence() {
    _silence?.cancel();
    final talk = _talk;
    if (talk == null || !talk.hints.enabled || talk.hints.native == null || _chip) return;
    _silence = Timer(talk.hints.delay, () {
      if (_disposed || _phase != TalkPhase.yourTurn) return;
      _chip = true;
      _notify();
    });
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _silence?.cancel();
    super.dispose();
  }
}
