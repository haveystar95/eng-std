SET jit = off;
-- 1. The journal of refusals of a plan's talks (EloquentPlanInspectionReader::talks)
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT * FROM conversation_rejections WHERE conversation_id IN (SELECT id FROM conversations WHERE plan_id = '01M2QRH5MYEFZ1DEQ78RH54P6X') ORDER BY conversation_id, turn_index, attempt;
-- 2. The turns of a plan's talks with the new columns (the same query as before, wider rows)
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT turn_index, scene_id, scene_event, phrases_almost FROM conversation_turns WHERE conversation_id IN (SELECT id FROM conversations WHERE plan_id = '01M2QRH5MYEFZ1DEQ78RH54P6X') ORDER BY conversation_id, turn_index;
-- 3. The others' build windows with built_at (EloquentPlanInspectionReader::windows)
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
SELECT f, t FROM (
    SELECT COALESCE(p.build_started_at, p.created_at) AS f,
           COALESCE((SELECT MIN(e.occurred_at) FROM plan_events e WHERE e.plan_id = p.id AND e.kind = 'plan_ready'),
                    CASE WHEN p.status IN ('failed', 'unclear') THEN p.updated_at WHEN p.status = 'building' THEN now() END) AS t
      FROM plans p WHERE p.id <> '01M2QRH5MYEFZ1DEQ78RH54P6X'
    UNION ALL
    SELECT s.build_started_at,
           COALESCE(s.built_at, CASE WHEN s.lesson_status = 'failed' THEN s.updated_at WHEN s.lesson_status IN ('building', 'illustrating') THEN now() END)
      FROM plan_scenes s WHERE s.plan_id <> '01M2QRH5MYEFZ1DEQ78RH54P6X' AND s.build_started_at IS NOT NULL
    UNION ALL
    SELECT c.started_at, COALESCE(c.ended_at, now()) FROM conversations c WHERE c.plan_id <> '01M2QRH5MYEFZ1DEQ78RH54P6X'
) w WHERE t IS NOT NULL AND f <= now() AND t >= now() - interval '30 days';
-- 4. The rebuild of card texts reads a plan's cards (plan:rebuild-card-texts), unchanged path; the finish of a lesson writes built_at by id
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF)
UPDATE plan_scenes SET built_at = built_at WHERE id = '01M2QRHF9M69AMBE2XGD18KQ8P' AND lesson_status = 'illustrating';
