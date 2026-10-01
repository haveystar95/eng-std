// ignore_for_file: avoid_print
/// SETS THE `.arb` FILES BY THE TYPOGRAPHY RULE (наряд CLIENT-22-1 §2) — `lib/data/typography.dart`, the very function
/// the app applies to the server's native texts at display. gen-l10n has no hook to run code on a string, so the
/// interface's strings carry the rule in the file itself: `app_ru.arb` by ru, `app_en.arb` by en.
///
/// ```bash
/// dart run tool/typeset_arb.dart && flutter gen-l10n
/// ```
///
/// Rewrites message lines only (each message of these files is one line) and only where the rule changes them; the
/// guard `test/l10n/nbsp_typography_test.dart` fails on any string the rule would still change — run this after
/// editing the files.
library;

import 'dart:convert';
import 'dart:io';

import 'package:eng_std/data/typography.dart';

void main() {
  for (final (file, language) in [('lib/l10n/app_ru.arb', 'ru'), ('lib/l10n/app_en.arb', 'en')]) {
    final message = RegExp(r'^(  "(?!@)[^"]+": )(".*")(,?)$');
    var changed = 0;
    final lines = [
      for (final line in File(file).readAsLinesSync())
        if (message.firstMatch(line) case final m?)
          () {
            final value = jsonDecode(m[2]!) as String;
            final set = typeset(value, language);
            if (set == value) return line;
            changed++;
            return '${m[1]}${jsonEncode(set)}${m[3]}';
          }()
        else
          line,
    ];
    File(file).writeAsStringSync('${lines.join('\n')}\n');
    print('$file: $changed messages set');
  }
}
