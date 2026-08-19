import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sqlite3/sqlite3.dart';

import 'package:eng_std/data/local/app_database.dart';

/// QA-23 — a half-applied migration must be able to finish, not brick the device forever.
///
/// The live failure, from a phone: `duplicate column name: accepted_variants` on
/// `ALTER TABLE "terms" ADD COLUMN "accepted_variants"`. SQLite applies each `ALTER TABLE` as it
/// goes, but `user_version` only advances once the whole `onUpgrade` returns — so one failing step
/// leaves the schema PARTLY migrated at the OLD version, and every launch afterwards restarts from
/// that old version, hits the column it already added, and dies the same way. The local store is
/// then permanently unopenable, which takes the whole app with it: no sync, no collections, and no
/// generation (whose first act is a local write).
///
/// These tests build exactly that state — an old `user_version` with a NEW column already present —
/// and require the next open to converge.
void main() {
  /// A database at [version] whose schema is the CURRENT one (so every column a later migration
  /// would add is already there). That is the shape a partly-applied upgrade leaves behind.
  Future<NativeDatabase> partlyMigrated(int version) async {
    final raw = sqlite3.openInMemory();
    // Let drift create the full, current schema…
    final seed = AppDatabase.forTesting(NativeDatabase.opened(raw, closeUnderlyingOnClose: false));
    await seed.customSelect('SELECT 1').get(); // force the open + onCreate
    await seed.close();
    // …then wind the recorded version back, as a failed upgrade would have left it.
    raw.execute('PRAGMA user_version = $version;');

    return NativeDatabase.opened(raw);
  }

  test('an upgrade that re-runs step 10 over an existing column now completes', () async {
    // user_version 9 → `from < 10` runs `ALTER TABLE terms ADD COLUMN accepted_variants`, and the
    // column is already there. This is the exact statement the device reported.
    final db = AppDatabase.forTesting(await partlyMigrated(9));

    await db.setMeta('probe', 'ok');
    expect(await db.getMeta('probe'), 'ok');

    await db.close();
  });

  test('every version the app has ever shipped can still open a partly-migrated file', () async {
    // Not just step 10: any of the addColumn steps can be the one that already ran, depending on
    // where the original failure landed. All of them have to be safe to re-run.
    for (var from = 1; from <= 15; from++) {
      final db = AppDatabase.forTesting(await partlyMigrated(from));

      await db.setMeta('probe', 'v$from');
      expect(await db.getMeta('probe'), 'v$from', reason: 'stuck at user_version $from');

      await db.close();
    }
  });

  test('the recovered database is fully usable, not merely openable', () async {
    final db = AppDatabase.forTesting(await partlyMigrated(9));

    // The columns the late migrations add are exactly the ones a duplicate-column crash would have
    // left behind — reading and writing them proves the schema is whole, not just unlocked.
    await db.applyDelta(
      termUpserts: [
        TermsCompanion.insert(
          id: 't1',
          updatedAt: DateTime.utc(2026, 8, 19),
          termText: const Value('reservation'),
          acceptedVariants: const Value('["booking"]'),
          exampleDistractors: const Value('[]'),
        ),
      ],
    );

    final term = await db.termById('t1');
    expect(term?.acceptedVariants, '["booking"]');

    await db.close();
  });
}
