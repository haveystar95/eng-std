-- FIX-3 · EXPLAIN of every query the наряд added or made hot, on the e2e stand (read-only, the writes in a rolled-back
-- transaction). The tables of the stand are small, so the planner prefers a sequential scan; `enable_seqscan = off`
-- shows the plan the index gives, and `jit = off` keeps that setting from switching the JIT on (BACK-TAILS-2).
--
--   docker exec -i wt_db psql -U wordtrainer -d wordtrainer_e2e_test < docs/research/fix-3/tools/explain.sql > docs/research/fix-3/explain.txt
SET jit = off;
SET enable_seqscan = off;

-- 1. `EloquentConversationRepository::replaysSince` — now on EVERY read of a day room (the talk row's `again`, §8).
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT count(*) FROM conversations
 WHERE day_id = '01M2H1ABCN5RGETN9MYPBR523R' AND started_at >= '2026-09-22 00:00:00+00' AND id <> '01M34WQM6APS2XK0CWY47QQAYF';

-- 2. The learner's gender (`IdentityLearnerGender` → `UserReader::byId`): the user by primary key, its profile by the
--    unique `user_id` — now on the day room, the plan, the notifications and the voice of a scene (§1).
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM users WHERE id = '01M2H1ABCB2BDE8ZE378867Y2D' LIMIT 1;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM profiles WHERE profiles.user_id = '01M2H1ABCB2BDE8ZE378867Y2D' AND profiles.user_id IS NOT NULL LIMIT 1;

-- 3. `SceneLocator::voicesOf` — the voices of the scenes a day's cards come from (returned cards, §1): primary key.
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT id, user_id, partner_voice_gender FROM plan_scenes WHERE id IN ('01M2QRHF9M69AMBE2XGD18KQ8P', '01M2QRHF9M86KAM8JW8XYRAWDR');

-- 4. `PlanRepository::savePace` — one column by primary key (§2, `plan:repace`).
BEGIN;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
UPDATE plans SET pace = '{"word_intro": 5}'::jsonb, updated_at = now() WHERE id = '01M2H1ABCMNQXQ6YP9C9A3HD6B';
ROLLBACK;

-- 5. `plan:repace --all` — the plans to walk: every plan not deleted, oldest first. An ops command over a small table;
--    no index (a sequential scan is its plan with the setting back on).
SET enable_seqscan = on;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT id FROM plans WHERE status <> 'deleted' ORDER BY created_at;

-- 6. The data migration `2026_09_23_100200` (once, at the deploy): the echo cards still carrying `partner_line`. No
--    index on `kind`: one pass over `day_cards` at the deploy (e2e — 1 row of 20 k, already taken off here; the live
--    base — 10).
BEGIN;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
UPDATE day_cards SET payload = payload - 'partner_line' WHERE kind = 'speak_echo' AND payload->'partner_line' IS NOT NULL;
ROLLBACK;
