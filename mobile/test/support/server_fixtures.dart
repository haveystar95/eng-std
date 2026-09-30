/// THE SERVER'S FIXTURES — the bodies of its replies (`GET …/days/{n}`, the talk's document) as the server keeps them
/// in `backend2/docs/fixtures/`, read from there, so that a test here draws what the server actually sends.
///
/// The client is built for the contract of BACK-TAILS-2 (`repetition`, `window.sources`, `minutes` on every row, the
/// talk row's `targets`, `talk_again`, «Вспомнить» of own lines only, the echo without `partner_line`). Until that branch
/// was merged its fixtures lived here as copies; merged into main (22.09), the server's own files are the only set
/// (наряд CLIENT-CONV-1c, закрытие).
library;

import 'dart:convert';
import 'dart:io';

/// Where the server's fixtures are read from — the server's own folder, relative to `mobile/`.
const String kServerFixtures = '../backend2/docs/fixtures';

/// The fixture file [name] (`day-doctor`, `conversation-day-open`).
File serverFixture(String name) => File('$kServerFixtures/$name.json');

/// The fixture [name] as the reply's JSON (the `data` envelope, when there is one, is kept) — with the fields the
/// server's file predates filled in from e2e ([withE2eFields]).
Map<String, dynamic> serverFixtureJson(String name) =>
    withE2eFields(jsonDecode(serverFixture(name).readAsStringSync()) as Map<String, dynamic>);

/// THE FIELDS THE OLDER FIXTURES PREDATE (work order CLIENT-START: fixtures «обновлённые с e2e чтением, если полей
/// нет»). `line_native` (LANG-1b §3) of every construction and `partner_gender` (FIX-4c §3) of every scene came after
/// `day-rehearsal`, `day-review` and the `conversation-*` files were taken; `test/fixtures/plan/e2e_refresh.json` holds
/// them as e2e answers today for the same plans and talks (read READ ONLY by the application's read path), keyed by
/// `scene_id/ref` and `scene_id`. A field the file already has is left as it is.
Map<String, dynamic> withE2eFields(Map<String, dynamic> json) {
  final refresh = _refresh ??= jsonDecode(File('test/fixtures/plan/e2e_refresh.json').readAsStringSync()) as Map<String, dynamic>;
  final lines = (refresh['line_native'] as Map).cast<String, String>();
  final genders = (refresh['partner_gender'] as Map).cast<String, String>();

  void constructions(Object? list) {
    if (list is! List) return;
    for (final t in list.whereType<Map<String, dynamic>>()) {
      if (t['line_native'] != null) continue;
      final line = lines['${t['scene_id']}/${t['ref']}'];
      if (line != null) t['line_native'] = line;
    }
  }

  final window = json['window'];
  if (window is Map<String, dynamic>) {
    for (final source in (window['sources'] as List? ?? const []).whereType<Map<String, dynamic>>()) {
      if (source['partner_gender'] == null && genders.containsKey(source['scene_id'])) {
        source['partner_gender'] = genders[source['scene_id']];
      }
    }
    for (final row in (window['stages'] as List? ?? const []).whereType<Map<String, dynamic>>()) {
      constructions(row['targets']);
    }
  }
  constructions(json['targets']);
  constructions(json['extra_said']);
  final summary = json['summary'];
  if (summary is Map<String, dynamic>) constructions(summary['phrases']);
  return json;
}

Map<String, dynamic>? _refresh;
