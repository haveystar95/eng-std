/// THE SERVER'S FIXTURES — the bodies of its replies (`GET …/days/{n}`, the talk's document) as the server keeps them
/// in `backend2/docs/fixtures/`, byte for byte, so that a test here draws what the server actually sends.
///
/// The client is built for the contract of BACK-TAILS-2 (`repetition`, `window.sources`, `minutes` on every row, the
/// talk row's `targets`, `talk_again`, «Вспомнить» of own lines only, the echo without `partner_line`), and main still
/// keeps the fixtures of the contract before it. Until that branch is merged, its fixtures are copied here, unchanged,
/// from `back-tails-2` at `890eac9f` (наряд CLIENT-CONV-1c, второй заход). Once it is merged the two sets are the same
/// files: point [kServerFixtures] back at `../backend2/docs/fixtures` and delete `test/fixtures/server/`.
library;

import 'dart:convert';
import 'dart:io';

/// Where the server's fixtures are read from.
const String kServerFixtures = 'test/fixtures/server';

/// The fixture file [name] (`day-doctor`, `conversation-day-open`).
File serverFixture(String name) => File('$kServerFixtures/$name.json');

/// The fixture [name] as the reply's JSON (the `data` envelope, when there is one, is kept).
Map<String, dynamic> serverFixtureJson(String name) =>
    jsonDecode(serverFixture(name).readAsStringSync()) as Map<String, dynamic>;
