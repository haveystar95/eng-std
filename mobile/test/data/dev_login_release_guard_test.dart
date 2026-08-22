// The dev door must not exist in a release build — a source guard, in the family of
// test/l10n/no_cyrillic_outside_l10n_test.dart and test/theme/no_hex_outside_theme_test.dart.
//
// Why a SOURCE test and not a behavioural one: `flutter test` always runs in debug, so no test
// executed here can observe what a release build contains. What CAN be pinned, exactly and
// cheaply, is the shape the release-safety argument rests on:
//
//   1. the gate is `kDebugMode` and only `kDebugMode` — no `bool.fromEnvironment`, no `||`, no
//      second term. A `--dart-define` is precisely how a dev door reaches a shipped build, and
//      every OTHER flag in config.dart is one, so this is the mistake worth pinning;
//   2. `/auth/dev` appears in exactly one file (the API client) and inside a guarded method;
//   3. every reference to the dev-login entry points is inside an `if (kDevLoginEnabled)` guard or
//      is the guard's own definition — so a const-false gate lets the tree-shaker drop all of it.
//
// The artifact-level proof (build release, grep the snapshot) is a manual step in
// docs/qa/PLAYBOOK.md; this file is the one that runs on every commit.

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/config.dart';

/// Every `.dart` file under `lib/`, as (path, source).
List<({String path, String src})> _libSources() {
  final root = Directory('lib');
  return root
      .listSync(recursive: true)
      .whereType<File>()
      .where((f) => f.path.endsWith('.dart'))
      .map((f) => (path: f.path, src: f.readAsStringSync()))
      .toList()
    ..sort((a, b) => a.path.compareTo(b.path));
}

void main() {
  test('the dev-login gate is kDebugMode and nothing else', () {
    final src = File('lib/data/config.dart').readAsStringSync();

    // The exact definition. Written out in full so that widening it (a --dart-define, an `||`,
    // a `var`) fails here and has to be argued for, not merged.
    expect(
      src.contains('const bool kDevLoginEnabled = kDebugMode;'),
      isTrue,
      reason: 'kDevLoginEnabled must be defined exactly as `const bool kDevLoginEnabled = kDebugMode;`',
    );

    // And no second definition anywhere.
    final defs = RegExp(r'\bkDevLoginEnabled\s*=').allMatches(src).length;
    expect(defs, 1, reason: 'kDevLoginEnabled must be defined exactly once');

    // In a test run (debug) the constant is true — which is also the proof that the constant is
    // wired to kDebugMode rather than pinned to a literal.
    expect(kDevLoginEnabled, isTrue);
  });

  test('the dev-login endpoint path appears in exactly one file', () {
    final hits = _libSources().where((f) => f.src.contains('/auth/dev')).map((f) => f.path).toList();

    expect(hits, ['lib/data/api_client.dart'],
        reason: 'the dev endpoint must be reachable from one place only');
  });

  test('every dev-login call site is behind the gate', () {
    // Files allowed to mention the dev door at all, and what makes each of them safe.
    const guarded = <String, String>{
      // defines the gate and the account
      'lib/data/config.dart': 'kDevLoginEnabled',
      // `if (!kDevLoginEnabled) throw` at the top of devLogin()
      'lib/data/api_client.dart': 'if (!kDevLoginEnabled)',
      // `if (!kDevLoginEnabled) throw` at the top of signInWithDev()
      'lib/data/auth_repository.dart': 'if (!kDevLoginEnabled)',
      // `if (!kDevLoginEnabled) return;` before the controller does anything
      'lib/data/providers.dart': 'if (!kDevLoginEnabled)',
      // the button is inside `if (kDevLoginEnabled) ...[ ]`
      'lib/features/auth/login_screen.dart': 'if (kDevLoginEnabled)',
    };

    final mentions = _libSources()
        .where((f) => f.src.contains('devLogin') ||
            f.src.contains('signInWithDev') ||
            f.src.contains('kDevLoginEmail') ||
            f.src.contains('kDevLoginEnabled'))
        .toList();

    for (final f in mentions) {
      expect(guarded.containsKey(f.path), isTrue,
          reason: '${f.path} mentions the dev login but is not a known, guarded site — '
              'either guard it with kDevLoginEnabled and add it here, or remove the reference');
      expect(f.src.contains(guarded[f.path]!), isTrue,
          reason: '${f.path} must contain the guard `${guarded[f.path]}`');
    }

    // The list may not rot: every file it names must still mention the door.
    for (final path in guarded.keys) {
      expect(mentions.any((f) => f.path == path), isTrue,
          reason: '$path no longer mentions the dev login — drop it from this list');
    }
  });

  test('the dev door targets the QA account and no other', () {
    expect(kDevLoginEmail, 'qa@wt.test');
    // No free-text entry: a run that could happen under any address is a run whose data
    // qa:time-travel / qa:reset are not allowed to touch.
    final screen = File('lib/features/auth/login_screen.dart').readAsStringSync();
    expect(screen.contains('TextField'), isFalse,
        reason: 'the login screen must not grow an email field for the dev door');
  });
}
