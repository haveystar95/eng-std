import 'dart:convert';

import 'local/app_database.dart';
import 'plan_models.dart';

/// WHERE A ПРИСЕСТ IS WHEN NOBODY IS LOOKING — the durable position of a plan day (наряд SIT-1, Ч-6).
///
/// A day is dealt whole and cut into присесты by the learner's own minutes. Between two of them the
/// app is closed, the phone is put down, the process is killed by iOS — and coming back has to
/// resume the SAME sitting, at the same card, with the same tail. Rebuilding it from the server
/// instead would deal the day again at whatever the ladder says by then: the cards already answered
/// would be gone from the checklist, the ones requeued after a slip would not be, and «продолжить»
/// would quietly mean «начать заново с середины».
///
/// So the whole sitting is written down, verbatim:
///
///   the PAYLOAD the server sent, so the session can be re-parsed rather than re-asked
///   ([PlanSession.raw]);
///   the ORDER the cards are being played in, which is not the payload's order once a wrong answer
///   has sent a card to the tail (Ч-5);
///   where the присест breaks fall, which move with that tail;
///   the position, and which cards have already used their one repeat.
///
/// One row in `sync_meta`, and one at a time: a learner is on ONE plan day, and a second stored
/// sitting would be a second answer to «where was I». Opening a different day overwrites it, which
/// is the honest reading — the day they left is the day they left.
///
/// Nothing here is a source of truth about PROGRESS. The answers themselves ride the durable review
/// queue exactly as they always have, so an app killed mid-sitting has already recorded everything
/// it graded; what this restores is the RUNNING ORDER, which nothing else knows.
class PlanSittingStore {
  const PlanSittingStore(this._db);

  final AppDatabase _db;

  static const _key = 'plan_sitting';

  /// Write the position. Called on every card boundary — a sitting is a couple of dozen small
  /// writes to a local table, and the alternative is losing the one the learner was on.
  Future<void> save(PlanSittingState state) =>
      _db.setMeta(_key, jsonEncode(state.toJson()));

  /// The stored sitting for THIS day, or null — because there is none, because it belongs to another
  /// day, or because it cannot be read.
  ///
  /// A stored sitting for a different plan or a different day is not resumed and not deleted here:
  /// deleting is [clear]'s job and it happens when a day is finished or deliberately restarted, so a
  /// read must never be the thing that throws away somebody's place.
  Future<PlanSittingState?> restore({required String planId, required int dayIndex}) async {
    final raw = await _db.getMeta(_key);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! Map<String, dynamic>) return null;
      final state = PlanSittingState.fromJson(decoded);
      if (state == null || state.planId != planId || state.dayIndex != dayIndex) return null;

      return state;
    } on FormatException {
      // A half-written or older shape: the day is simply started fresh, which is what happened
      // before there was a store at all.
      return null;
    }
  }

  Future<void> clear() => _db.setMeta(_key, null);
}

/// One sitting, frozen — see [PlanSittingStore].
class PlanSittingState {
  const PlanSittingState({
    required this.planId,
    required this.dayIndex,
    required this.payload,
    required this.order,
    required this.sittingEnds,
    required this.position,
    required this.requeued,
  });

  final String planId;
  final int dayIndex;

  /// The session payload the server sent, verbatim — re-parsed by [session].
  final Map<String, dynamic> payload;

  /// Indices into the payload's `tasks`, in the order they are being played. Longer than `tasks`
  /// once a card has been sent to the tail.
  final List<int> order;

  /// Exclusive end offsets into [order], one per присест.
  final List<int> sittingEnds;

  /// How far along [order] the learner is.
  final int position;

  /// Task indices that have already used their one repeat this sitting (Ч-5).
  final List<int> requeued;

  PlanSession get session => PlanSession.fromJson(payload);

  Map<String, dynamic> toJson() => {
    'plan_id': planId,
    'day_index': dayIndex,
    'payload': payload,
    'order': order,
    'sitting_ends': sittingEnds,
    'position': position,
    'requeued': requeued,
  };

  static PlanSittingState? fromJson(Map<String, dynamic> j) {
    final payload = j['payload'];
    final planId = j['plan_id'];
    if (payload is! Map<String, dynamic> || planId is! String || planId.isEmpty) return null;

    List<int> ints(Object? v) =>
        ((v as List?) ?? const []).map((e) => (e as num).toInt()).toList(growable: true);

    final order = ints(j['order']);
    if (order.isEmpty) return null;

    return PlanSittingState(
      planId: planId,
      dayIndex: (j['day_index'] as num?)?.toInt() ?? 1,
      payload: payload,
      order: order,
      sittingEnds: ints(j['sitting_ends']),
      // Clamped rather than trusted: a position past the end would open a finished sitting on a
      // card that is not there, and the honest answer to a corrupt offset is «the last card».
      position: ((j['position'] as num?)?.toInt() ?? 0).clamp(0, order.length - 1),
      requeued: ints(j['requeued']),
    );
  }
}
