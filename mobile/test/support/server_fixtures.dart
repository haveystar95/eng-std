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

/// The fixture [name] as the reply's JSON (the `data` envelope, when there is one, is kept).
Map<String, dynamic> serverFixtureJson(String name) =>
    jsonDecode(serverFixture(name).readAsStringSync()) as Map<String, dynamic>;
