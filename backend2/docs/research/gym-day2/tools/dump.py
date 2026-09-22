#!/usr/bin/env python3
"""GYM-DUMP-2 — raw rows of the owner's plan «Тренировка в зале», days 1 and 2, off the live database.

Read-only by construction: every query runs through `psql` in `wt_db` with
`PGOPTIONS=-c default_transaction_read_only=on`, so the session refuses any write. Only SELECTs are sent.
Writes nothing but `../raw/*.json`.

Run from anywhere: python3 docs/research/gym-day2/tools/dump.py
"""

import json
import pathlib
import subprocess

RAW = pathlib.Path(__file__).resolve().parent.parent / "raw"

PLAN = "01M32DX8QCABM348XP45Z1ZD4M"
USER = "01M12HTZ1QHPNDZ5J8SPKB58QP"
DAYS = {1: "01M32DX8QF4042C0CDVAY9P7DE", 2: "01M32DX8QF5SQ3BMJ8R4PDD7R8"}
SCENES = {1: "01M32DXHYG50H7SWQEMD33A39F", 2: "01M32DXHYGYMK0E1DBR01PYDHA"}
TALKS = {1: "01M32FJ5FQNRNSQH6PNC7DP5E0", 2: "01M34HXHP05JY8NVF1305NRY5N"}
LOG_COLUMNS = ("l.id, l.direction, l.method, l.host, l.path, l.service, l.purpose, l.status, l.duration_ms, l.user_id,"
               " l.request_bytes, l.response_bytes, l.request_body, l.response_body, l.error, l.occurred_at")
STAGE_ORDER = "array['words','phrases','dialogue','listen','speak','recall','repetition']"


def q(sql: str):
    """One SELECT returning one JSON value (or NULL)."""
    out = subprocess.run(
        [
            "docker", "exec", "-e", "PGOPTIONS=-c default_transaction_read_only=on", "wt_db",
            "psql", "-U", "wordtrainer", "-d", "wordtrainer", "-At", "-v", "ON_ERROR_STOP=1",
            "-c", "SET TIME ZONE 'UTC'", "-c", sql,
        ],
        check=True, capture_output=True, text=True,
    ).stdout
    # the first line is the SET's own output
    body = out.split("\n", 1)[1].strip() if out.startswith("SET") else out.strip()
    return json.loads(body) if body else None


def rows(sql: str):
    return q(f"select coalesce(json_agg(t), '[]'::json) from ({sql}) t")


def row(sql: str):
    return q(f"select row_to_json(t) from ({sql}) t")


def save(name: str, data) -> None:
    RAW.mkdir(parents=True, exist_ok=True)
    (RAW / name).write_text(json.dumps(data, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print("wrote", name)


def main() -> None:
    assert q("select to_json(current_setting('default_transaction_read_only'))") == "on"

    save("plan.json", {
        "plan": row(f"select * from plans where id = '{PLAN}'"),
        "days": rows(f"select * from plan_days where plan_id = '{PLAN}' order by number"),
        "scenes": rows(
            f"select id, plan_id, \"order\", kind, title_native, title_target, learner_role_native, learner_role_target,"
            f" partner_role_native, partner_role_target, partner_voice_gender, lesson_status, prompt_version_lesson,"
            f" model_lesson, generated_at, created_at, updated_at from plan_scenes where plan_id = '{PLAN}' order by \"order\""
        ),
    })

    for n, day in DAYS.items():
        scene = SCENES[n]
        save(f"day-{n}.json", {
            "plan_day": row(f"select * from plan_days where id = '{day}'"),
            "scene": row(f"select * from plan_scenes where id = '{scene}'"),
            "plan_terms": rows(f"select * from plan_terms where scene_id = '{scene}' order by position"),
            "cards": rows(
                f"select c.* from day_cards c where c.day_id = '{day}'"
                f" order by array_position({STAGE_ORDER}, c.stage::text), c.position"
            ),
            "stage_passages": rows(f"select * from plan_stage_passages where day_id = '{day}' order by passed_at"),
            "plan_events": rows(f"select * from plan_events where plan_id = '{PLAN}' and (day_id = '{day}' or day_number = {n}) order by occurred_at"),
        })

        talk = TALKS[n]
        save(f"conversation-day-{n}.json", {
            "conversation": row(f"select * from conversations where id = '{talk}'"),
            "turns": rows(f"select * from conversation_turns where conversation_id = '{talk}' order by turn_index"),
        })
        # The talk's HTTP trail: the phone's moves (what it heard) and the server's documents, and the model's raw
        # replies (its own `phrases_used`) with the prompts it was sent, and the voice calls — by the talk's time window.
        save(f"api-log-conversation-day-{n}.json", rows(
            f"select {LOG_COLUMNS} from api_request_logs l, conversations c where c.id = '{talk}'"
            f" and l.occurred_at between c.started_at - interval '10 seconds' and c.ended_at + interval '10 seconds'"
            f" and ((l.direction = 'inbound' and l.user_id = '{USER}' and l.path like '%conversation%')"
            f"   or (l.direction = 'outbound' and l.host in ('api.openai.com', 'api.elevenlabs.io')))"
            f" order by l.occurred_at, l.id"
        ))

    # Every request the phone made about days 1 and 2 of the plan: open, room reads, answers, judges, closes.
    save("api-log-days.json", rows(
        f"select {LOG_COLUMNS} from api_request_logs l where l.user_id = '{USER}' and direction = 'inbound'"
        f" and (path like 'api/v1/plans/{PLAN}/days/1%' or path like 'api/v1/plans/{PLAN}/days/2%')"
        f" order by occurred_at, id"
    ))

    save("line-audios.json", rows(
        f"select * from plan_line_audios where scene_id in ('{SCENES[1]}', '{SCENES[2]}') order by scene_id, line_ref, created_at"
    ))

    # model_calls has no user or plan column: the rows are taken by the two days' time windows.
    save("model-calls.json", rows(
        "select * from model_calls where (started_at between '2026-09-21 16:48:00+00' and '2026-09-21 17:22:00+00')"
        " or (started_at between '2026-09-22 12:11:00+00' and '2026-09-22 12:39:00+00') order by started_at, id"
    ))


if __name__ == "__main__":
    main()
