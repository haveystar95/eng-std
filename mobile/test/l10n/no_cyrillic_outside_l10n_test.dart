// Localisation drift guard — the deptrac of the copy deck (sibling of the
// hex-guard in test/theme/no_hex_outside_theme_test.dart).
//
// A3 rule: every string of a reskinned screen goes through AppLocalizations.
// A Cyrillic character inside a Dart *string literal* anywhere in `lib/` (except
// `lib/l10n/`, the ARB home, and generated `*.g.dart`) is a hardcoded UI string
// and fails here. Comments — including the many Russian doc comments — are
// ignored: only string literals are scanned.
//
// During the A-block redesign the legacy screens still carry Russian literals;
// they are listed in [_legacyAllowlist] and MUST shrink to empty by the end of
// A3. The test also fails if an allowlisted file has become clean or was
// deleted — so the list can never silently rot.

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Legacy files still permitted to contain Cyrillic string literals. Remove
/// entries as each screen migrates to AppLocalizations. Target: empty.
const _legacyAllowlist = <String>{};

/// First line (1-based) with a Cyrillic char inside a string literal, or -1.
/// A tiny scanner that skips `//`, `/* */` comments and understands raw and
/// triple-quoted strings — so only genuine literals are inspected.
int firstCyrillicStringLine(String src) {
  final n = src.length;
  var i = 0;
  var line = 1;

  bool isCyrillic(int c) => c >= 0x0400 && c <= 0x04FF;

  while (i < n) {
    final c = src[i];
    if (c == '\n') {
      line++;
      i++;
      continue;
    }
    // Comments.
    if (c == '/' && i + 1 < n) {
      final d = src[i + 1];
      if (d == '/') {
        i += 2;
        while (i < n && src[i] != '\n') {
          i++;
        }
        continue;
      }
      if (d == '*') {
        i += 2;
        while (i + 1 < n && !(src[i] == '*' && src[i + 1] == '/')) {
          if (src[i] == '\n') line++;
          i++;
        }
        i += 2; // skip closing */
        continue;
      }
    }
    // String literals ('...', "...", '''...''', r'...').
    if (c == '\'' || c == '"') {
      final quote = c;
      final raw = i > 0 && (src[i - 1] == 'r' || src[i - 1] == 'R');
      final triple = i + 2 < n && src[i + 1] == quote && src[i + 2] == quote;
      i += triple ? 3 : 1;
      while (i < n) {
        final s = src[i];
        if (s == '\n') {
          line++;
          i++;
          continue;
        }
        if (!raw && s == '\\') {
          i += 2; // escape — skip the escaped char
          continue;
        }
        if (triple) {
          if (s == quote && i + 2 < n && src[i + 1] == quote && src[i + 2] == quote) {
            i += 3;
            break;
          }
        } else if (s == quote) {
          i += 1;
          break;
        }
        if (isCyrillic(s.codeUnitAt(0))) return line;
        i++;
      }
      continue;
    }
    i++;
  }
  return -1;
}

void main() {
  test('no Cyrillic string literals outside lib/l10n/', () {
    final libDir = Directory('lib');
    expect(libDir.existsSync(), isTrue, reason: 'run from the mobile/ package root');

    final offenders = <String>[]; // "path:line"
    final allowlistedHit = <String>{};

    for (final entity in libDir.listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      final path = entity.path.replaceAll(r'\', '/');
      if (path.startsWith('lib/l10n/')) continue; // ARB home + generated code
      if (path.endsWith('.g.dart')) continue; // generated (drift etc.)

      final line = firstCyrillicStringLine(entity.readAsStringSync());
      if (line < 0) continue;

      if (_legacyAllowlist.contains(path)) {
        allowlistedHit.add(path);
      } else {
        offenders.add('$path:$line');
      }
    }

    expect(
      offenders,
      isEmpty,
      reason: 'Cyrillic string literals outside lib/l10n/. Route the copy through '
          'AppLocalizations (add the key to lib/l10n/app_ru.arb):\n${offenders.join('\n')}',
    );

    final stale = _legacyAllowlist.where((p) {
      final f = File(p);
      return !f.existsSync() || !allowlistedHit.contains(p);
    }).toList();
    expect(
      stale,
      isEmpty,
      reason: 'Legacy allowlist is stale — these files are clean or gone, remove '
          'them from _legacyAllowlist (the list must shrink to empty):\n${stale.join('\n')}',
    );
  });
}
