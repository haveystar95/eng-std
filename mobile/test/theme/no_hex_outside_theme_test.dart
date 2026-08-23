// Design-system drift guard — the deptrac of the theme.
//
// Rule 01/02 of the design system: the palette lives in `lib/theme/` and nowhere
// else. A raw colour hex literal anywhere else in `lib/` is drift and fails here.
//
// During the A-block redesign the legacy dark theme still carries hex; those
// files are listed in [_legacyAllowlist] and MUST shrink to empty as screens
// migrate. The test also fails if an allowlisted file has become clean or has
// been deleted — so the list can never silently rot.
//
// Escape hatch for a genuine non-colour 8-digit hex (e.g. a bitmask): put
// `// hex-ok` on the same line.

import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Legacy files still permitted to contain colour hex. Remove entries as the
/// old `lib/core/` theme is dismantled in A3. Target: empty.
const _legacyAllowlist = <String>{};

final _hexColor = RegExp(r'Color\(\s*0x|Color\.fromARGB\(|Color\.fromRGBO\(|0x[0-9A-Fa-f]{8}');

void main() {
  test('no colour hex literals outside lib/theme/', () {
    final libDir = Directory('lib');
    expect(libDir.existsSync(), isTrue, reason: 'run from the mobile/ package root');

    final offenders = <String>[]; // "path:line: content"
    final allowlistedHit = <String>{};

    for (final entity in libDir.listSync(recursive: true)) {
      if (entity is! File || !entity.path.endsWith('.dart')) continue;
      final path = entity.path.replaceAll(r'\', '/');
      if (path.startsWith('lib/theme/')) continue; // the one legal home

      final lines = entity.readAsLinesSync();
      for (var i = 0; i < lines.length; i++) {
        final line = lines[i];
        if (!_hexColor.hasMatch(line)) continue;
        if (line.contains('// hex-ok')) continue;

        if (_legacyAllowlist.contains(path)) {
          allowlistedHit.add(path);
        } else {
          offenders.add('$path:${i + 1}: ${line.trim()}');
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason:
          'Colour hex outside lib/theme/. Use tokens from '
          'package:eng_std/theme/theme.dart (add the token there if missing):\n'
          '${offenders.join('\n')}',
    );

    // Keep the allowlist honest: every entry must still exist and still offend.
    final stale = _legacyAllowlist.where((p) {
      final f = File(p);
      return !f.existsSync() || !allowlistedHit.contains(p);
    }).toList();
    expect(
      stale,
      isEmpty,
      reason:
          'Legacy allowlist is stale — these files are clean or gone, '
          'remove them from _legacyAllowlist (the list must shrink to empty):\n'
          '${stale.join('\n')}',
    );
  });
}
