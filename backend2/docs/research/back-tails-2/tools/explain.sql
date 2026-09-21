-- BACK-TAILS-2 §14 · EXPLAIN of every query the наряд added or changed.
--   docker exec -i wt_db psql -U wordtrainer -d <db> < docs/research/back-tails-2/tools/explain.sql
-- Read only: the session is told so first, and no statement below writes (the migration's UPDATE is EXPLAINed, not run).
-- The talks' tables hold a handful of rows on every stand, so the planner reads them whole; each such query is shown a
-- second time with `enable_seqscan = off` — the plan it takes once the table grows, and the index that serves it.

SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY;
-- `enable_seqscan = off` prices every plan past `jit_above_cost`, and the JIT's compile time would be read as the query's.
SET jit = off;
\pset pager off

\echo '== Q1 (new) ConversationRepository::replaysSince — StartConversationHandler, the cap of §7'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT count(*) AS aggregate FROM conversations
WHERE day_id = (SELECT day_id FROM conversations ORDER BY started_at DESC LIMIT 1)
  AND started_at >= '2026-09-21 00:00:00+00' AND id <> '00000000000000000000000000';
SET enable_seqscan = off;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT count(*) AS aggregate FROM conversations
WHERE day_id = (SELECT day_id FROM conversations ORDER BY started_at DESC LIMIT 1)
  AND started_at >= '2026-09-21 00:00:00+00' AND id <> '00000000000000000000000000';
RESET enable_seqscan;

\echo '== Q2 (changed: + id DESC) ConversationRepository::latestForDay — the room, the talk row''s targets'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations WHERE day_id = (SELECT day_id FROM conversations ORDER BY started_at DESC LIMIT 1)
ORDER BY started_at DESC, id DESC LIMIT 1;
SET enable_seqscan = off;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations WHERE day_id = (SELECT day_id FROM conversations ORDER BY started_at DESC LIMIT 1)
ORDER BY started_at DESC, id DESC LIMIT 1;
RESET enable_seqscan;

\echo '== Q3 (changed: + id) ConversationRepository::latestForDays — the plan''s route (the app passes the plan''s day ids as literals)'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations
WHERE day_id = ANY (ARRAY(SELECT id FROM plan_days WHERE plan_id = (SELECT plan_id FROM conversations ORDER BY started_at DESC LIMIT 1)))
ORDER BY started_at ASC, id ASC;
SET enable_seqscan = off;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations
WHERE day_id = ANY (ARRAY(SELECT id FROM plan_days WHERE plan_id = (SELECT plan_id FROM conversations ORDER BY started_at DESC LIMIT 1)))
ORDER BY started_at ASC, id ASC;
RESET enable_seqscan;

\echo '== Q4 (new, now on every answer) StagePassageRepository::of + ConversationRepository::findById — DayMetricsOf, §8'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM plan_stage_passages WHERE day_id = (SELECT day_id FROM plan_stage_passages LIMIT 1) AND stage = 'conversation' LIMIT 1;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations WHERE id = (SELECT conversation_id FROM plan_stage_passages LIMIT 1) LIMIT 1;
SET enable_seqscan = off;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM plan_stage_passages WHERE day_id = (SELECT day_id FROM plan_stage_passages LIMIT 1) AND stage = 'conversation' LIMIT 1;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversations WHERE id = (SELECT conversation_id FROM plan_stage_passages LIMIT 1) LIMIT 1;
RESET enable_seqscan;

\echo '== Q5 (new) plan:reconcile-scenes — the sheets of «Вспомнить», a console command run by hand'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT c.id, c.payload, d.plan_id, d.number FROM day_cards AS c INNER JOIN plan_days AS d ON d.id = c.day_id
WHERE c.kind = 'recall_scenes' ORDER BY d.plan_id ASC, d.number ASC, c.id ASC;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT id, title_native, title_target FROM plan_scenes WHERE id IN (SELECT id FROM plan_scenes LIMIT 3);
EXPLAIN (COSTS OFF)
UPDATE day_cards SET payload = payload WHERE id = '00000000000000000000000000';

\echo '== Q6 (new) the migration 2026_09_22_100000 — one UPDATE, once (EXPLAIN only, nothing run)'
EXPLAIN (COSTS OFF)
UPDATE day_cards SET stage = 'repetition' WHERE stage = 'speak' AND day_id IN (SELECT id FROM plan_days WHERE type = 'review');
