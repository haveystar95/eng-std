/// THE LIVE REHEARSAL OF FIX-4b — every answer of the API over one rehearsal of two scenes on `wordtrainer_e2e_test`
/// (`backend2/docs/research/fix-4b/live/live-rehearsal.json`, talk `01M39DK2G7…`, plan `01M2QRH5MY…`, day 3): the
/// fixtures of the talk across scenes (наряд CLIENT-FIX-4). Read from the server's own research folder, as
/// `server_fixtures.dart` reads the server's fixtures — no copy here to drift from what the server answered.
///
/// A state the run did not leave behind (a talk under «Без подсказок», a summary the time ran out on) is the same
/// answer with [rehearsalTalk]'s edit applied — named in the test that makes it.
///
/// `line_native` (LANG-1b §3) came after this run: every construction of it is filled in from e2e by (scene_id, ref),
/// as the server's fixtures are ([withE2eFields]) — the run as the server answers it today.
library;

import 'dart:convert';
import 'dart:io';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';

import 'server_fixtures.dart' show withE2eFields;

/// Where the live run is read from, relative to `mobile/`.
const String kLiveRehearsal = '../backend2/docs/research/fix-4b/live/live-rehearsal.json';

/// THE MOMENTS OF THE RUN — the step whose answer stands at each.
abstract final class RehearsalStep {
  /// The receptionist's first line («Запись к врачу», scene 1 of 2), seven targets, the hint «У него болит поясница.».
  static const opened = 0;

  /// «It hurts in his lower back.» — p1 of scene 1 said.
  static const firstSaid = 1;

  /// «It started two days ago.» — p2 said; the hint is p3.
  static const beforeAlmost = 2;

  /// Turn 6 «The pain is sharp when she bends.» — p3 ALMOST; turn 7 carries `hints.target` «The pain is sharp when he
  /// bends.».
  static const almost = 3;

  /// Turn 8 says p3 whole — it leaves the row; the hint is p4.
  static const almostSaid = 4;

  /// Turn 10 says p4, the last target of scene 1: turn 11 is the receptionist's goodbye (`end`), turn 12 the doctor's
  /// greeting (`start`) of scene 2 — both in this one answer.
  static const sceneChange = 5;

  /// Turn 13 says p1 of scene 2.
  static const secondScene = 6;

  /// Turn 15 says p2 of scene 2 and «I gave him paracetamol.» — p4, a construction beyond the targets (`extra_said`).
  static const extraSaid = 7;

  /// Turn 17 says p3; turn 18 is the doctor's goodbye and the talk's end: the summary with its `extra_said`.
  static const ended = 8;
}

List<Object?>? _run;

/// The whole run, as the file holds it.
List<Object?> _steps() => _run ??= jsonDecode(File(kLiveRehearsal).readAsStringSync()) as List<Object?>;

/// The talk as step [step] answered it — a fresh copy of the JSON each time, so an edit touches only its own test.
Map<String, dynamic> rehearsalJson(int step) =>
    withE2eFields(jsonDecode(jsonEncode((_steps()[step]! as Map<String, dynamic>)['talk'])) as Map<String, dynamic>);

/// What the learner said to get step [step] — null at the opening.
String? rehearsalMove(int step) => (_steps()[step]! as Map<String, dynamic>)['move'] as String?;

/// Step [step]'s talk, with [edit] applied to its JSON before it is read.
PlanConversation rehearsalTalk(int step, [void Function(Map<String, dynamic> json)? edit]) {
  final json = rehearsalJson(step);
  edit?.call(json);
  return PlanConversation.fromJson(json);
}

/// «Без подсказок» as the server answers it since FIX-4c §2: hints off — and the hint itself as in the normal mode (the
/// phone hides the plate and «Подсказать» puts it up for the move).
void blind(Map<String, dynamic> json) {
  (json['hints'] as Map<String, dynamic>)['enabled'] = false;
}
