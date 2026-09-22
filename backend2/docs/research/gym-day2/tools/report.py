#!/usr/bin/env python3
"""GYM-DUMP-2 — the report's tables, built from ../raw only (no database, no network).

Writes ../README.md and ../raw/cards-s3-s4.json (the cards of §3 and §4 as the phone got them, with the requests the
phone sent about them). Run after dump.py and views.php:  python3 docs/research/gym-day2/tools/report.py
"""

import collections
import datetime as dt
import json
import pathlib
import re
import subprocess

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parent
RAW = ROOT / "raw"
LOG = ROOT.parent.parent.parent / "storage" / "logs" / "laravel.log"

PLAN = "01M32DX8QCABM348XP45Z1ZD4M"
STAGES = ["words", "phrases", "dialogue", "listen", "speak", "recall", "repetition"]
RECOGNITIONS = {"phrase_slot", "phrase_choose_back", "phrase_slot_listen", "phrase_assemble"}  # PhraseSeries::CYCLE
SPOKEN_KINDS = {"speak_answer", "speak_echo", "speak_retell"}
VOICES = {  # config/generation.php + .env (SPEECH_VOICE_EN_*)
    "4NejU5DwQjevnR6mh3mb": "SPEECH_VOICE_EN_PARTNER_FEMALE",
    "EnjklPXGBMNldCJ7jqkE": "SPEECH_VOICE_EN_PARTNER_MALE",
    "Nhs7eitvQWFTQBsf0yiT": "SPEECH_VOICE_EN_LEARNER_FEMALE",
    "TWutjvRaJqAX89preB4e": "SPEECH_VOICE_EN_LEARNER_MALE",
}


def load(name):
    return json.loads((RAW / name).read_text(encoding="utf-8"))


plan = load("plan.json")
day = {n: load(f"day-{n}.json") for n in (1, 2)}
talk = {n: load(f"conversation-day-{n}.json") for n in (1, 2)}
talklog = {n: load(f"api-log-conversation-day-{n}.json") for n in (1, 2)}
daylog = load("api-log-days.json")
audios = load("line-audios.json")
room = {n: load(f"room-day-{n}.json")["data"] for n in (1, 2)}
cview = {n: load(f"conversation-view-day-{n}.json")["data"] for n in (1, 2)}
deal = load("phrases-deal.json")
check = load("views-check.json")

scene_title = {s["id"]: s["title_native"] for s in plan["scenes"]}
scene_gender = {s["id"]: s["partner_voice_gender"] for s in plan["scenes"]}
audio_by_id = {a["id"]: a for a in audios}
room_cards = {n: {c["id"]: c for st in room[n]["stages"] for c in st["cards"]} for n in (1, 2)}


# ── helpers ───────────────────────────────────────────────────────────────────────────────────────
def ts(s):
    return dt.datetime.fromisoformat(s.replace("Z", "+00:00"))


def hms(s):
    return ts(s).strftime("%H:%M:%S") if s else "—"


def mmss(seconds):
    seconds = int(seconds)
    return f"{seconds // 60}:{seconds % 60:02d}"


def esc(v):
    if v is None:
        return "—"
    if isinstance(v, bool):
        return "true" if v else "false"
    return str(v).replace("|", "\\|").replace("\n", " ")


def table(headers, rows):
    out = ["| " + " | ".join(headers) + " |", "|" + "|".join("---" for _ in headers) + "|"]
    out += ["| " + " | ".join(esc(c) if not isinstance(c, str) else c.replace("\n", " ") for c in r) + " |" for r in rows]
    return "\n".join(out)


def q(text):
    """A quoted text cell."""
    return "—" if text in (None, "") else "«" + str(text).replace("|", "\\|") + "»"


def ref_of(pid):
    return pid.split(":", 1)[1] if ":" in pid else pid


def audio_id(url):
    return url.rsplit("/", 1)[1] if url else None


def card_requests(card_id):
    return [r for r in daylog if f"/cards/{card_id}/" in r["path"]]


def stage_idx(stage):
    return STAGES.index(stage) if stage in STAGES else 99


def cards_of(n):
    return day[n]["cards"]


def lesson(n):
    return day[n]["scene"]["lesson_json"]


def phrase(n, ref):
    return next(p for p in lesson(n)["phrases"] if p["id"] == ref)


def fillers_md(p):
    fs = (p.get("slot") or {}).get("fillers") or []
    return "<br>".join(("● " if f.get("in_dialogue") else "○ ") + f"{f['target']} — {f['native']}" for f in fs)


def filler_of(card):
    """PhraseSeries::fillerOf, plus the rounds of «Скажи целиком»."""
    p, k = card["payload"], card["kind"]
    if k == "phrase_intro":
        return [(p.get("said") or {}).get("filler_index")]
    if k == "phrase_choose_back":
        return [(p.get("prompt") or {}).get("filler_index")]
    if k in ("phrase_slot_listen", "phrase_repeat"):
        return [p.get("filler_index")]
    if k == "phrase_assemble":
        return [(p.get("expected") or {}).get("filler_index")]
    if k == "phrase_combine":
        return [p.get("correct_filler")]
    if k == "phrase_other_slot":
        return [r.get("filler_index") for r in p.get("rounds") or []]
    if k == "phrase_slot":
        fillers = ((p.get("frame") or {}).get("slot") or {}).get("fillers") or []
        right = next((o for o in p.get("options") or [] if o.get("id") == p.get("correct")), None)
        for f in fillers:
            if right and f.get("target") == right.get("text"):
                return [f.get("index")]
        return [None]
    return []


TOKEN = re.compile(r"[a-z0-9']+")


def words(text):
    return TOKEN.findall((text or "").lower().replace("’", "'"))


# ── derived numbers ───────────────────────────────────────────────────────────────────────────────
def answered_sorted(n):
    return sorted([c for c in cards_of(n) if c["answered_at"]], key=lambda c: (ts(c["answered_at"]), stage_idx(c["stage"]), c["position"]))


def gaps(n):
    """(card, gap seconds from the previous answer of the day) for every answered card; the first has None."""
    out, prev = [], None
    for c in answered_sorted(n):
        t = ts(c["answered_at"])
        out.append((c, None if prev is None else int((t - prev).total_seconds())))
        prev = t
    return out


def stage_facts(n, limit):
    facts = collections.defaultdict(lambda: {"sec": 0, "excluded": []})
    for c, g in gaps(n):
        if g is None:
            continue
        if g <= limit:
            facts[c["stage"]]["sec"] += g
        else:
            facts[c["stage"]]["excluded"].append(g)
    return facts


def code_card_minutes(n):
    """DayMetricsCalculator: gaps between answers ≤ 600 s counted, ceil to minutes, at least 1."""
    secs = sum(g for _, g in gaps(n) if g is not None and g <= 600)
    return secs, max(1, -(-secs // 60))


def talk_active_seconds(n):
    """Conversation::activeSeconds: gaps between neighbouring turns, each clipped to 60 s."""
    turns = talk[n]["turns"]
    return sum(max(0, min(60, int((ts(b["created_at"]) - ts(a["created_at"])).total_seconds()))) for a, b in zip(turns, turns[1:]))


def talk_gaps_le60(n):
    turns = talk[n]["turns"]
    gs = [int((ts(b["created_at"]) - ts(a["created_at"])).total_seconds()) for a, b in zip(turns, turns[1:])]
    return sum(g for g in gs if g <= 60), [g for g in gs if g > 60]


def prestart_window(n):
    """The last `GET …/days/{n}` before the day was opened whose reply the log kept whole: (time, window) or None."""
    opened = ts(day[n]["plan_day"]["opened_at"])
    found = None
    for r in daylog:
        if r["method"] == "GET" and r["path"] == f"api/v1/plans/{PLAN}/days/{n}" and ts(r["occurred_at"]) <= opened:
            body = r["response_body"] or {}
            if isinstance(body, dict) and not body.get("_truncated") and "data" in body:
                found = (r["occurred_at"], body["data"]["window"])
    return found


# ── §1 ────────────────────────────────────────────────────────────────────────────────────────────
def model_replies(n):
    calls = sorted([r for r in talklog[n] if r["host"] == "api.openai.com"], key=lambda r: (r["occurred_at"], r["id"]))
    out = []
    for r in calls:
        body = r["response_body"] or {}
        try:
            out.append((r, json.loads(body["choices"][0]["message"]["content"])))
        except (KeyError, TypeError, ValueError):
            out.append((r, None))
    return out


def moves(n):
    return sorted([r for r in talklog[n] if r["direction"] == "inbound" and r["path"].endswith("/turn")], key=lambda r: (r["occurred_at"], r["id"]))


def transcript(n):
    turns = talk[n]["turns"]
    agents = [t for t in turns if t["speaker"] == "partner"]
    learners = [t for t in turns if t["speaker"] == "learner"]
    replies = model_replies(n)
    reqs = moves(n)
    reply_of = {t["turn_index"]: replies[i] for i, t in enumerate(agents)} if len(replies) == len(agents) else {}
    move_of = {t["turn_index"]: reqs[i] for i, t in enumerate(learners)} if len(reqs) == len(learners) else {}
    rows = []
    for t in turns:
        agent = t["speaker"] == "partner"
        rep = reply_of.get(t["turn_index"])
        req = move_of.get(t["turn_index"])
        heard = (req["request_body"] or {}).get("heard") if req else None
        rows.append([
            str(t["turn_index"]), hms(t["created_at"]),
            ("роль" if agent else "ученик") + " · " + t["kind"],
            q(t["text_target"]), q(t["text_native"]),
            "—" if agent else q(heard),
            "—" if agent else (", ".join(ref_of(p) for p in t["phrases_used"]) or "[]"),
            (", ".join(ref_of(p) for p in rep[1]["phrases_used"]) or "[]") if agent and rep and rep[1] else "—",
            esc(t["understood"]), esc(t["off_topic"]),
            esc(t["checkpoint_done"] and ("сцена " + scene_title.get(t["checkpoint_done"], t["checkpoint_done"]))),
            q(t["hint_native"]),
            f"{float(t['model_cost_usd']):.6f} + {float(t['speech_cost_usd']):.6f}" if agent else "0",
            f"{t['tokens_in']}/{t['tokens_out']}" if agent else "—",
            f"{t['model_latency_ms']}+{t['speech_latency_ms']}" if agent else "—",
        ])
    return rows, len(replies) == len(agents), len(reqs) == len(learners)


def section1():
    out = ["## §1 Разговор дня 2", ""]
    c = talk[2]["conversation"]
    out += [
        f"Разговор `{c['id']}` · день 2 · `type` {c['type']} · `state` {c['state']} · `ended_reason` {c['ended_reason']} · "
        f"`turn_limit` {c['turn_limit']} · `hints_enabled` {esc(c['hints_enabled'])} · начат {hms(c['started_at'])}, окончен "
        f"{hms(c['ended_at'])} UTC 22.09 · `cost_usd` **{c['cost_usd']}** · `checkpoints_done` "
        f"{', '.join(scene_title.get(s, s) for s in c['checkpoints_done'])}. Роль — «Тренер» / «Trainer», голос "
        f"`{talk[2]['turns'][0]['audio_voice_key']}`; модель `{talk[2]['turns'][0]['model']}`, промпт "
        f"`{talk[2]['turns'][0]['prompt_version']}`.",
        "",
        "### 1.1 Стенограмма (все ходы по порядку)",
        "",
        "Источники: `conversation_turns` (текст, `phrases_used` сервера, `understood`, `off_topic`, `checkpoint_done`, "
        "`hint_native`, цена, время записи хода); **heard** — поле `heard` тела запроса телефона `POST …/conversation/{id}/turn` "
        "(`api_request_logs`, входящие); **phrases_used модели** — поле `phrases_used` сырого ответа модели "
        "(`api_request_logs`, исходящие `api.openai.com`, `choices[0].message.content`) на этот ход роли — модель "
        "отдаёт его в ответе на ход ученика, стоящий строкой выше. В строке хода ученика `phrases_used` — то, что записал сервер "
        "(`PhraseUse`, наряд BACK-TAILS-2 §2); в строках роли колонка сервера пуста по построению (`phrasesUsed: []`). "
        "Ходов `rescue` и `skip` в разговоре нет. Время — UTC.",
        "",
    ]
    rows, ok_replies, ok_moves = transcript(2)
    out.append(table(["#", "время", "кто · вид", "text_target", "text_native", "heard (телефон)", "phrases_used сервера",
                      "phrases_used модели", "understood", "off_topic", "checkpoint_done", "hint_native (сохранён)",
                      "$ модель + голос", "токены in/out", "мс модель+голос"], rows))
    if not (ok_replies and ok_moves):
        out.append("\n> Число вызовов модели или запросов хода не совпало с числом ходов — сопоставление по порядку не выполнено.")
    out.append("")
    tot = collections.Counter()
    for t in talk[2]["turns"]:
        tot["model"] += float(t["model_cost_usd"])
        tot["speech"] += float(t["speech_cost_usd"])
        tot["cost"] += float(t["cost_usd"])
    out.append(f"Сумма по ходам: модель ${tot['model']:.6f} + голос ${tot['speech']:.6f} = **${tot['cost']:.6f}** "
               f"(`conversations.cost_usd` = {c['cost_usd']}). Подсказка на ходу у телефона — `hints.native` документа; "
               "в `conversation_turns.hint_native` хранится полная строка, в документе — её часть после «Скажи, что …» "
               "(`IntentClause`), см. `raw/conversation-view-day-2.json`.")
    out.append("")

    # 1.2 targets
    out += ["### 1.2 Цели разговора дня 2 (`targets[]`) и их каркасы из урока", "",
            "`targets[]` — из документа разговора (`raw/conversation-view-day-2.json`, тот же список, что `window.stages[conversation].targets`). "
            "Каркас — `lesson_json.phrases[]` сцены «С тренером» (`plan_scenes.lesson_json`, промпт "
            f"`{day[2]['scene']['prompt_version_lesson']}`); ● — наполнение, которое говорит диалог урока (`in_dialogue`). "
            "Реплика урока — шаг диалога, в котором ученик говорит этот каркас.", ""]
    rows = []
    mismatch = []
    terms = {t["ref"]: t for t in day[2]["plan_terms"] if t["kind"] == "phrase"}
    for t in cview[2]["targets"]:
        p = phrase(2, t["ref"])
        line = next((f"x{ex['step']}: {m['text_target']}" for ex in lesson(2)["dialogue"] for m in ex["messages"] if m.get("phrase_id") == t["ref"]), "—")
        rows.append([t["ref"], esc(t["scene_id"]), q(t["text_target"]), q(t["text_native"]), esc(t["said"]),
                     q(p["frame_target"]), q(p["frame_native"]), p.get("kind", "—"), q((p.get("slot") or {}).get("hint_native")),
                     fillers_md(p), q(line)])
        pt = terms.get(t["ref"])
        if pt is None or pt["frame_target"] != p["frame_target"] or pt["frame_native"] != p["frame_native"] or \
                [f["target"] for f in (pt["slot"] or {}).get("fillers", [])] != [f["target"] for f in (p.get("slot") or {}).get("fillers", [])]:
            mismatch.append(t["ref"])
    out.append(table(["ref", "scene_id", "text_target", "text_native", "said", "frame_target", "frame_native", "вид",
                      "окно (`hint_native`)", "наполнения", "реплика урока"], rows))
    out.append("")
    out.append("Каркасы и наполнения в `plan_terms` сцены совпадают с `lesson_json.phrases[]`." if not mismatch
               else f"Расхождение `plan_terms` ↔ `lesson_json` у: {', '.join(mismatch)}.")
    out.append("")

    # 1.3 target ↔ learner lines
    out += ["### 1.3 Цель ↔ реплики ученика (буквальное совпадение слов неподвижной части)", "",
            "Неподвижная часть — `frame_target` без `___` и знаков; слова — строчными, по `[a-z0-9']+`, «’» → «'». "
            "Реплика ученика — `text_target` ходов `said` (то, что прислал телефон). Совпадение — слово в слово "
            "(«I'm» и «I am» — разные слова). Оценки смысла нет.", ""]
    learner = [t for t in talk[2]["turns"] if t["speaker"] == "learner" and t["kind"] == "said"]
    rows = []
    for t in cview[2]["targets"]:
        p = phrase(2, t["ref"])
        fixed = words(p["frame_target"].replace("___", " "))
        hits, found = [], set()
        for lt in learner:
            w = set(words(lt["text_target"]))
            m = [x for x in fixed if x in w]
            if m:
                hits.append(f"ход {lt['turn_index']} {q(lt['text_target'])} — {', '.join(dict.fromkeys(m))}")
                found.update(m)
        missing = [x for x in dict.fromkeys(fixed) if x not in found]
        rows.append([t["ref"], q(p["frame_target"]), ", ".join(dict.fromkeys(fixed)), "<br>".join(hits) or "—",
                     ", ".join(missing) or "—"])
    out.append(table(["ref", "каркас", "слова неподвижной части", "реплики ученика с совпадениями (ход — текст — совпавшие слова)",
                      "не встретились ни в одной реплике"], rows))
    out.append("")

    # 1.4 summaries
    out += ["### 1.4 Итог разговора (`summary`) — дни 2 и 1", "",
            "Из документа разговора (`summary` ответа `GET …/conversation/{id}`, пересобран кодом приложения в режиме только "
            "чтения — `raw/conversation-view-day-{1,2}.json`; для дня 2 документ совпал с ответом последнего хода, "
            "который сохранил `api_request_logs`).", ""]
    keys = ["said_count", "phrases_used", "phrases_total", "understood_all", "not_understood", "rescues", "ended_reason", "minutes", "returns_tomorrow"]
    rows = []
    for k in keys:
        rows.append([f"`{k}`", esc(cview[2]["summary"].get(k)), esc(cview[1]["summary"].get(k))])
    rows.append(["не сказаны (`phrases[].used = false`)",
                 ", ".join(p["ref"] for p in cview[2]["summary"]["phrases"] if not p["used"]),
                 ", ".join(p["ref"] for p in cview[1]["summary"]["phrases"] if not p["used"])])
    out.append(table(["поле", "день 2", "день 1"], rows))
    out.append("")
    c1 = talk[1]["conversation"]
    out += [f"День 1: разговор `{c1['id']}`, {hms(c1['started_at'])}–{hms(c1['ended_at'])} UTC 21.09, `ended_reason` "
            f"{c1['ended_reason']}, `cost_usd` {c1['cost_usd']}. Цели дня 1 и `said`:", ""]
    out.append(table(["ref", "text_target", "text_native", "said"],
                     [[t["ref"], q(t["text_target"]), q(t["text_native"]), esc(t["said"])] for t in cview[1]["targets"]]))
    out.append("")
    replies1 = model_replies(1)
    agents1 = [t for t in talk[1]["turns"] if t["speaker"] == "partner"]
    if len(replies1) == len(agents1):
        out.append("Для справки (сырьё — `raw/conversation-day-1.json`, `raw/api-log-conversation-day-1.json`): `phrases_used` "
                   "сервера у ходов ученика дня 1 — " + ", ".join(
                       f"ход {t['turn_index']} {('[' + ', '.join(ref_of(p) for p in t['phrases_used']) + ']')}"
                       for t in talk[1]["turns"] if t["speaker"] == "learner")
                   + "; `phrases_used` модели в ответах роли — " + ", ".join(
                       f"ход {a['turn_index']} [{', '.join(ref_of(p) for p in r['phrases_used'])}]"
                       for a, (_, r) in zip(agents1, replies1) if r) + ".")
        out.append("")
    return out


# ── §2 ────────────────────────────────────────────────────────────────────────────────────────────
def stage_rows(n):
    cards = cards_of(n)
    by_stage = collections.defaultdict(list)
    for c in cards:
        by_stage[c["stage"]].append(c)
    planned = {s["stage"]: s["minutes"] for s in room[n]["window"]["stages"]}
    pre = prestart_window(n)
    before = {s["stage"]: (str(s["minutes"]) if "minutes" in s else "нет поля") for s in pre[1]["stages"]} if pre else {}
    f60 = stage_facts(n, 60)
    f600 = stage_facts(n, 600)
    rows = []
    for st in [s for s in STAGES if s in by_stage]:
        cs = by_stage[st]
        kinds = collections.Counter(c["kind"] for c in cs)
        retries = sum(1 for c in cs if c["retry_of"])
        phrase_cards = [c for c in cs if c["unit_kind"] == "phrase"]
        fillers = {(c["unit_ref"], f) for c in phrase_cards for f in filler_of(c) if f is not None}
        other = [c for c in cs if c["kind"] == "phrase_other_slot"]
        rounds = sum(len(c["payload"].get("rounds") or []) for c in other)
        own = sum(1 for c in other if c["payload"].get("own_round"))
        recog = [c for c in cs if c["kind"] in RECOGNITIONS]
        ex = f60[st]["excluded"]
        rows.append([
            f"`{st}`", before.get(st, "—"), str(planned.get(st, "—")), mmss(f60[st]["sec"]),
            f"{len(ex)} · {mmss(sum(ex))}" if ex else "0",
            mmss(f600[st]["sec"]),
            f"{len(cs)}" + (f" (копий {retries})" if retries else ""),
            "<br>".join(f"{k} {v}" for k, v in kinds.items()),
            str(len({(c["payload"].get("scene_id"), c["unit_ref"]) for c in cs if c["unit_kind"] == "word"})),
            str(len({(c["payload"].get("scene_id"), c["unit_ref"]) for c in phrase_cards})),
            str(len(fillers)) if phrase_cards else "—",
            str(len({(c["payload"].get("scene_id"), c["unit_ref"]) for c in cs if c["unit_kind"] == "exchange"})),
            f"{rounds} + своё {own}" if other else "—",
            (f"{sum(1 for c in recog if not c['retry_of'])}" + (f" + копий {sum(1 for c in recog if c['retry_of'])}" if any(c["retry_of"] for c in recog) else "")) if st == "phrases" else "—",
            str(sum(1 for c in cs if c["source"] == "returned")),
        ])
    # the talk
    c = talk[n]["conversation"]
    le60, over60 = talk_gaps_le60(n)
    first_turn = talk[n]["turns"][0]["created_at"]
    rows.append([
        "`conversation`", before.get("conversation", "—"), str(planned.get("conversation", "—")),
        mmss(le60), f"{len(over60)} · {mmss(sum(over60))}" if over60 else "0",
        f"код: {talk_active_seconds(n)} с → {cview[n]['summary']['minutes']} мин",
        f"ходов {len(talk[n]['turns'])}", f"начат {hms(c['started_at'])}, 1-й ход {hms(first_turn)}, окончен {hms(c['ended_at'])}",
        "—", f"целей {len(cview[n]['targets'])}", "—", "—", "—", "—", "—",
    ])
    return rows


def section2():
    out = ["## §2 Числа дней 1 и 2", "",
           "### 2.1 По этапам", "",
           "- **план, мин** — `window.stages[].minutes` окна дня: цена карточек этапа по `plan.pace` "
           "(`DayPace`, секунды на карточку по виду; «Скажи целиком» — за круг), вверх до минуты; у разговора — "
           "`plan.conversation.minutes.day` = 3. **До старта** — последний ответ `GET …/days/{n}` до открытия дня, "
           "сохранённый журналом целиком (день ещё не розданный — контур раздачи); **после дня** — пересобранный ответ "
           "на 12:55:54 22.09 (все карточки этапа, копии-повторы включительно).",
           "- **факт ≤ 60 с** — по `day_cards.answered_at`: все отвеченные карточки дня по времени ответа; промежуток от "
           "предыдущего ответа дня (любого этапа) до этого ответа относится к этапу этой карточки и считается, если он ≤ 60 с; "
           "первый ответ дня промежутка не несёт. `plan_stage_passages` у обоих дней содержит только строку `conversation` "
           "(этапы карточек в этот журнал не пишутся), поэтому время этапов карточек — только по `answered_at`. У разговора — "
           "промежутки между соседними ходами `conversation_turns.created_at` ≤ 60 с.",
           "- **исключено** — число и сумма промежутков > 60 с, отнесённых к этапу.",
           "- **по правилу дня (≤ 600 с)** — то же, но с порогом `DayMetricsCalculator::PAUSE_SECONDS` = 600, которым сервер "
           "считает `minutes_spent` дня; у разговора — `Conversation::activeSeconds()` (промежутки между ходами, каждый "
           "обрезан до 60 с) и `minutes()`.",
           "- **наполнений** — различные пары (каркас, `filler_index`), которыми сказаны карточки фраз этапа "
           "(`PhraseSeries::fillerOf` + `rounds[].filler_index` у «Скажи целиком»). **слов / каркасов / реплик** — различные "
           "(сцена карточки, `unit_ref`). **кругов** — сумма `rounds` карточек "
           "`phrase_other_slot` (+ число `own_round`). **узнаваний** — карточки видов `phrase_slot`, `phrase_choose_back`, "
           "`phrase_slot_listen`, `phrase_assemble` (`PhraseSeries::CYCLE`). **копий** — карточки с `retry_of` "
           "(повтор в конце этапа). **возвратов** — `source = returned`.", ""]
    headers = ["этап", "план до старта, мин", "план после дня, мин", "факт ≤ 60 с", "исключено > 60 с", "по правилу дня (≤ 600 с)", "карточек", "по видам",
               "слов", "каркасов", "наполнений", "реплик", "кругов", "узнаваний", "возвратов с дня 1"]
    for n in (1, 2):
        d = day[n]["plan_day"]
        w = room[n]["window"]
        secs, mins = code_card_minutes(n)
        talk_min = cview[n]["summary"]["minutes"]
        planned_cards = sum(s["minutes"] for s in w["stages"] if s["stage"] != "conversation")
        pre = prestart_window(n)
        pre_txt = (f"До старта (ответ дня {hms(pre[0])}): `window.day.minutes_estimate` **{pre[1]['day']['minutes_estimate']}**, "
                   f"`status` {pre[1]['day']['status']}. ") if pre else "Ответа дня до открытия целиком в журнале нет. "
        out += [pre_txt + f"**День {n}** — «{scene_title[day[n]['scene']['id']]}», `status` {d['status']}, открыт {d['opened_at']}, "
                f"закрыт {d['closed_at'] or '—'}; `cards_total` {d['cards_total']}, `cards_done` {d['cards_done']}, "
                f"`minutes_spent` **{d['minutes_spent']}**. План после дня: этапы карточек {planned_cards} мин + разговор "
                f"{next((s['minutes'] for s in w['stages'] if s['stage'] == 'conversation'), 0)} мин; потолок карточек дня "
                f"`plan.day_cards_budget` = 32 мин; потолок «Фраз» `plan.phrases_budget` = 690 с. "
                f"`minutes_spent` = карточки {secs} с → {mins} мин + разговор {talk_min} мин = {mins + talk_min}.", ""]
        out.append(table(headers, stage_rows(n)))
        out.append("")

    # 2.2 phrases per frame and the ladder
    out += ["### 2.2 «Фразы» по каркасам и лестница", "",
            "Лестница (`PhrasesStage`, наряд BACK-TAILS-2 §1): ступень 0 — сборка; 1 — третьи узнавания, пока влезают; "
            "2 — третий круг «Скажи целиком» снимается (каркас из ≥ 3 значений оставляет 2); 3 — второе узнавание снимается; "
            "дальше — выдача сверх потолка и строка `plan.phrases_over_ceiling` в журнале приложения.", ""]
    for n in (1, 2):
        cs = [c for c in cards_of(n) if c["stage"] == "phrases"]
        rows = []
        for p in lesson(n)["phrases"]:
            mine = [c for c in cs if c["unit_ref"] == p["id"]]
            recog = [c for c in mine if c["kind"] in RECOGNITIONS]
            other = [c for c in mine if c["kind"] == "phrase_other_slot"]
            rows.append([
                p["id"], q(p["frame_target"]), str(len((p.get("slot") or {}).get("fillers") or [])),
                "<br>".join(f"{c['position']} {c['kind']}" + (" (копия)" if c["retry_of"] else "") + f" → {c['result']}" for c in recog) or "—",
                "<br>".join(f"{c['position']}{' (копия)' if c['retry_of'] else ''}: " + "; ".join(r["expected_text"] for r in c["payload"].get("rounds") or [])
                            + (" + своё" if c["payload"].get("own_round") else "") + f" → {c['result']}, попыток {c['attempts']}" for c in other) or "—",
                "<br>".join(f"{c['position']} {c['kind']} → {c['result']}" for c in mine if c["kind"] not in RECOGNITIONS | {"phrase_other_slot"}) or "—",
            ])
        out += [f"**День {n}** (`lesson_json.phrases`, карточки этапа `phrases` по позициям):", ""]
        out.append(table(["ref", "каркас", "наполнений в уроке", "узнавания (позиция, вид → результат)",
                          "«Скажи целиком» (позиция: круги `expected_text` → результат)", "прочие (intro, combine)"], rows))
        out.append("")
    dl = deal["day_2"]
    out += ["**Лестница дня 2.** Пересчёт тем же кодом, которым день раздан (день 2 открыт 22.09 12:11:52 UTC; код "
            "BACK-TAILS-2 выкачен 22.09 11:13–11:17 UTC; изменений `app/`, `config/` в `main` после 12:11 UTC нет): "
            "`DayAssembler::phrasesDeal` над материалом сцены (`tools/views.php` → `raw/phrases-deal.json`).", ""]
    out.append(table(["ступень", "секунд", "карточек"], [[str(r["rung"]), str(r["seconds"]), str(r["cards"])] for r in dl["rungs"]]))
    out.append("")
    out.append(f"Итог: {dl['seconds']} с при потолке {dl['budget']} с, `over_ceiling` {esc(dl['over_ceiling'])}. Что оставлено каркасам:")
    out.append("")
    actual = {}
    for c in cards_of(2):
        if c["stage"] == "phrases" and not c["retry_of"]:
            a = actual.setdefault(c["unit_ref"], {"recognitions": 0, "rounds": None, "own": None})
            if c["kind"] in RECOGNITIONS:
                a["recognitions"] += 1
            if c["kind"] == "phrase_other_slot":
                a["rounds"] = len(c["payload"].get("rounds") or [])
                a["own"] = bool(c["payload"].get("own_round"))
    rows = []
    built = {b["ref"]: b for b in dl.get("built", [])}
    for ref, f in dl["frames"].items():
        a = actual.get(ref, {})
        b = built.get(ref, {})
        rows.append([ref, str(len((phrase(2, ref).get("slot") or {}).get("fillers") or [])), esc(b.get("fillers_most")),
                     f"{esc(b.get('recognitions'))} / {esc(b.get('rounds'))}",
                     f"{f['recognitions']} / {f['rounds']} / {esc(f['own'])}",
                     f"{a.get('recognitions')} / {a.get('rounds')} / {esc(a.get('own'))}"])
    out.append(table(["ref", "наполнений в уроке", "`PhraseSeries::most`", "ступень 0: узнаваний / кругов",
                      "после ступени 3: узнаваний / кругов / своё", "роздано в день 2: узнаваний / кругов / своё (без копий)"], rows))
    out.append("")
    ceiling_lines = count_ceiling_lines()
    out.append(f"Журнал сборки: строк `plan.phrases_over_ceiling` с `plan_id` {PLAN} в `storage/logs/laravel.log` — "
               f"**{ceiling_lines}** (строка пишется только когда ступени кончились, а этап всё ещё дороже потолка). "
               "День 1 раздан 21.09 16:50:34 UTC — до выката BACK-TAILS-2; его ступени нигде не записаны, по данным дня — "
               "таблица «День 1» выше (в `raw/phrases-deal.json` есть и `day_1` — это пересчёт ТЕКУЩИМ кодом, не тем, "
               "что раздал день 1).")
    out.append("")

    # 2.3 the ribbon of day 2
    out += ["### 2.3 Лента дня 2", "",
            f"День открыт {day[2]['plan_day']['opened_at']} (`POST …/open` — 12:11:53). Этап: первый и последний ответ, "
            "ответ, которым закончился предыдущий этап, число пауз > 60 с внутри этапа (включая вход в этап).", ""]
    rows = []
    prev_end = day[2]["plan_day"]["opened_at"]
    for st in [s for s in STAGES if any(c["stage"] == s for c in cards_of(2))]:
        cs = sorted([c for c in cards_of(2) if c["stage"] == st and c["answered_at"]], key=lambda c: ts(c["answered_at"]))
        span = int((ts(cs[-1]["answered_at"]) - ts(prev_end)).total_seconds())
        pauses = [g for c, g in gaps(2) if c["stage"] == st and g is not None and g > 60]
        rows.append([f"`{st}`", hms(prev_end), hms(cs[0]["answered_at"]), hms(cs[-1]["answered_at"]), mmss(span),
                     f"{len(pauses)} · {mmss(sum(pauses))}" if pauses else "0", mmss(span - sum(pauses))])
        prev_end = cs[-1]["answered_at"]
    c2 = talk[2]["conversation"]
    rows.append(["`conversation`", hms(prev_end), hms(c2["started_at"]), hms(c2["ended_at"]),
                 mmss((ts(c2["ended_at"]) - ts(prev_end)).total_seconds()), "0", mmss((ts(c2["ended_at"]) - ts(prev_end)).total_seconds())])
    out.append(table(["этап", "конец предыдущего этапа (ответ) / открытие дня", "первый ответ / старт", "последний ответ / конец",
                      "от конца предыдущего до конца этапа", "паузы > 60 с", "без пауз > 60 с"], rows))
    out.append("")
    out += ["Все паузы > 60 с между ответами дня 2 — с запросами телефона внутри паузы (`api_request_logs`, входящие "
            "по дню 2: чтения дня `GET …/days/2`, вызовы судьи `…/judge`; время ответа карточки = `answered_at`):", ""]
    rows = []
    seq = gaps(2)
    for i, (c, g) in enumerate(seq):
        if g is None or g <= 60:
            continue
        before = seq[i - 1][0]
        t0, t1 = ts(before["answered_at"]), ts(c["answered_at"])
        inside = [r for r in daylog if "/days/2" in r["path"] and t0 < ts(r["occurred_at"]) < t1
                  and not r["path"].endswith("/answer")]
        judge = [r for r in inside if r["path"].endswith("/judge")]
        reads = [r for r in inside if r["method"] == "GET"]
        rest = [r for r in inside if r not in judge and r not in reads]
        what = []
        if reads:
            what.append("GET день ×" + str(len(reads)) + " (" + ", ".join(hms(r["occurred_at"]) for r in reads) + ")")
        if judge:
            what.append("judge ×" + str(len(judge)) + " (" + ", ".join(hms(r["occurred_at"]) for r in judge) + ")")
        what += [f"{r['method']} {r['path'].split('/days/2')[1]} {hms(r['occurred_at'])}" for r in rest]
        rows.append([hms(before["answered_at"]), f"{before['stage']} {before['position']} {before['kind']} → {before['result']}",
                     hms(c["answered_at"]), f"{c['stage']} {c['position']} {c['kind']} → {c['result']}, попыток {c['attempts']}",
                     mmss(g), "; ".join(what) or "—"])
    out.append(table(["с (ответ)", "карточка перед паузой", "по (ответ)", "карточка после паузы", "длительность", "запросы внутри"], rows))
    out.append("")
    total = sum(g for _, g in seq if g is not None and g > 60)
    out.append(f"Паузы > 60 с: {len([1 for _, g in seq if g is not None and g > 60])} шт., {mmss(total)}. Промежутки ≤ 60 с между "
               f"ответами дня 2: {mmss(sum(g for _, g in seq if g is not None and g <= 60))}; разговор (между ходами): "
               f"{mmss(talk_gaps_le60(2)[0])}. От первого ответа ({hms(seq[0][0]['answered_at'])}) до конца разговора "
               f"({hms(c2['ended_at'])}): {mmss((ts(c2['ended_at']) - ts(seq[0][0]['answered_at'])).total_seconds())}.")
    out.append("")

    # 2.4 the day summary 30-7
    w = room[2]["window"]
    spoken = [c for c in cards_of(2) if c["stage"] in ("speak", "recall", "repetition") and c["kind"] in SPOKEN_KINDS | {"recall_scenes"}]
    passed = [c for c in spoken if c["result"] in ("passed", "hinted")]
    secs, mins = code_card_minutes(2)
    not_und = [t for t in talk[2]["turns"] if t["understood"] is False]
    out += ["### 2.4 Итог дня 2 как отдан клиенту (кадр 30-7)", "",
            "Ответ `GET /plans/{id}/days/2` на 12:55:54 UTC 22.09 (последнее чтение дня телефоном) пересобран кодом приложения "
            f"в режиме только чтения — `raw/room-day-2.json`; сверка с журналом: длина ответа {check['room_day_2']['wire_bytes_rebuilt']} "
            f"байт = {check['room_day_2']['wire_bytes_logged']} в `api_request_logs.response_bytes`, тело в журнале "
            f"{check['room_day_2']['body_bytes_logged']} = {check['room_day_2']['body_bytes_rebuilt']}, первые "
            f"{check['room_day_2']['preview_bytes']} байт превью совпадают. Клиент берёт минуты из `data.day.minutes_spent` "
            "(`session_controller.dart:286`), строки «Что было хорошо» — из `data.window.highlights` (`:275`).", ""]
    rows = [
        ["«N минут»", "`data.day.minutes_spent`", str(room[2]["day"]["minutes_spent"]),
         f"`plan_days.minutes_spent`; = карточки ({secs} с промежутков ≤ 600 с → {mins} мин) + разговор, прошедший этап "
         f"(`Conversation::minutes()` = {cview[2]['summary']['minutes']})"],
        ["строка 1", "`data.window.highlights[0]`", q(w["highlights"][0]),
         f"`DayHighlights`: карточки этапов `speak`/`recall`/`repetition` видов «говорю» — {len(spoken)}; `passed`/`hinted` — "
         f"{len(passed)}; прочие: " + ", ".join(f"{c['stage']} {c['position']} {c['kind']} {c['unit_ref']} → {c['result']}" for c in spoken if c not in passed)],
        ["строка 2", "`data.window.highlights[1]`", q(w["highlights"][1]),
         f"итог разговора, прошедшего этап: `phrases_used` {cview[2]['summary']['phrases_used']} из `phrases_total` "
         f"{cview[2]['summary']['phrases_total']} (сказана: " + ", ".join(p["ref"] for p in cview[2]["summary"]["phrases"] if p["used"]) + ")"],
        ["строка 3", "`data.window.highlights[2]`", q(w["highlights"][2]) if len(w["highlights"]) > 2 else "—",
         f"`not_understood` {cview[2]['summary']['not_understood']}: " + "; ".join(
             f"ход {t['turn_index']} роли {q(t['text_target'])} — `understood: false` (ответ на ход ученика {q(next((x['text_target'] for x in talk[2]['turns'] if x['turn_index'] == t['turn_index'] - 1), None))})"
             for t in not_und)],
        ["статус окна", "`data.window.day.status`", w["day"]["status"], "день не закрыт (`plan_days.status` in_progress)"],
        ["минуты окна", "`data.window.day.minutes_spent` / `minutes_estimate`", f"{esc(w['day']['minutes_spent'])} / {esc(w['day']['minutes_estimate'])}", "`minutes_spent` окна — только у пройденного дня"],
        ["кнопка", "`data.window.allowed_action`", w["allowed_action"], ""],
        ["«Повторить разговор»", "`data.window.talk_again`", esc(w["talk_again"]), ""],
        ["прогресс", "`data.window.day_progress`", esc(w["day_progress"]), ""],
        ["возвраты вкладок", "`data.window.program.{words,phrases,dialogue}.summary`",
         "; ".join(f"{k}: {v['summary']['done']}/{v['summary']['total']}, returns {v['summary']['returns']}" for k, v in w["program"].items()), ""],
    ]
    out.append(table(["что на экране", "поле", "значение", "из чего собрано"], rows))
    out.append("")
    return out


def count_ceiling_lines():
    try:
        res = subprocess.run(["grep", "-c", f"phrases_over_ceiling.*{PLAN}", str(LOG)], capture_output=True, text=True)
        return int(res.stdout.strip() or 0)
    except (OSError, ValueError):
        return "не прочитан"


# ── §3 ────────────────────────────────────────────────────────────────────────────────────────────
def section3(dump):
    out = ["## §3 Возвраты дня 2", "",
           "Карточки дня 2 с `source = returned` (`day_cards`); вид карточки как отдан — из пересобранного ответа дня "
           "(`raw/room-day-2.json`, совпал с журналом). `room.scene` дня 2 — «" + room[2]["scene"]["title_native"] + "» "
           f"(`{room[2]['scene']['id']}`). Полоса сцены в сессии: `SessionController.sceneOfCard` — "
           "`_day?.scene ?? _sceneById(card.payload.sceneId) ?? scene` (`mobile/lib/features/plan/session/session_controller.dart:211`): "
           "у дня со своей сценой берётся сцена дня, `payload.scene_id` читается только у дня без сцены.", ""]
    ret = [c for c in cards_of(2) if c["source"] == "returned"]
    rows = []
    for c in ret:
        v = room_cards[2][c["id"]]
        p = v["payload"]
        ol = p.get("own_line") or {}
        d1 = [x for x in cards_of(1) if x["unit_ref"] == c["unit_ref"] and x["payload"].get("scene_id") == p.get("scene_id")]
        rows.append([str(c["position"]), c["kind"], c["unit_ref"], q(ol.get("text_target")), q(ol.get("text_native")),
                     f"`{p.get('scene_id')}` — «{scene_title.get(p.get('scene_id'), '?')}»", str(v.get("source_day")),
                     f"{c['result']}, попыток {c['attempts']}, heard {q((c['response'] or {}).get('heard'))}",
                     "<br>".join(f"{x['stage']} {x['position']} {x['kind']} → {x['result']}" for x in d1) or "—"])
        dump["s3_returned"].append({"card": v, "requests": card_requests(c["id"])})
    out.append(table(["поз. в дне 2", "вид", "реплика", "own_line.text_target", "text_native", "payload.scene_id — сцена",
                      "source_day", "результат в дне 2", "карточки этой реплики в дне 1 (этап, позиция, вид → результат)"], rows))
    out.append("")
    out.append(f"Возвратов в дне 2: {len(ret)}, все — этап `speak`, вид `speak_retell`, `source_day_id` = день 1. У дня 1 "
               f"карточек с `returns = true` — {sum(1 for c in cards_of(1) if c['returns'])}.")
    out.append("")

    out += ["### 3.2 Озвучка своей реплики-возврата", "",
            "Голос строки ищется по (сцена, ref, голос): `SceneAudioIndex::rowOf` — говорящий по ref (`xNb` — ученик), пол — "
            "`VoiceCast` сцены (собеседник — `plan_scenes.partner_voice_gender`, ученик — противоположный), ключ голоса — "
            "`SPEECH_VOICE_EN_{PARTNER|LEARNER}_{FEMALE|MALE}`. Сцена 1 «Ресепшен зала»: собеседник "
            f"{scene_gender[day[1]['scene']['id']]}; сцена 2 «С тренером»: собеседник {scene_gender[day[2]['scene']['id']]}. "
            "День 1 — адрес звука из пересобранного ответа дня 1 (карточки дня 1 с этой же `own_line.ref`) и, где есть, из "
            "ответа `…/answer`, сохранённого журналом 21.09; день 2 — из ответа дня 2 и ответа `…/answer` карточки-возврата 22.09. "
            "Для сравнения — файл той же ref у сцены 2.", ""]
    rows = []
    for c in ret:
        v = room_cards[2][c["id"]]
        ol = v["payload"].get("own_line") or {}
        ref = ol.get("ref")
        a2 = audio_id((ol.get("audio") or {}).get("url"))
        logged2 = [r for r in card_requests(c["id"]) if r["path"].endswith("/answer")]
        a2_logged = None
        for r in logged2:
            body = r["response_body"] or {}
            u = ((((body.get("data") or {}).get("card") or {}).get("payload") or {}).get("own_line") or {}).get("audio") or {}
            a2_logged = audio_id(u.get("url")) or a2_logged
        # day 1: cards of the same scene with this own_line ref
        a1 = set()
        a1_logged = set()
        for x in cards_of(1):
            xv = room_cards[1].get(x["id"])
            xol = (xv or {}).get("payload", {}).get("own_line") or {}
            if xol.get("ref") == ref:
                if xol.get("audio", {}).get("url"):
                    a1.add(audio_id(xol["audio"]["url"]))
                for r in card_requests(x["id"]):
                    body = r["response_body"] or {}
                    u = ((((body.get("data") or {}).get("card") or {}).get("payload") or {}).get("own_line") or {}).get("audio") or {}
                    if u.get("url"):
                        a1_logged.add(audio_id(u["url"]))
        row = audio_by_id.get(a2)
        voice = row["voice_key"].split(":")[2] if row else None
        s2 = next((a for a in audios if a["scene_id"] == day[2]["scene"]["id"] and a["line_ref"] == ref), None)
        rows.append([
            str(c["position"]), ref, q(ol.get("text_target")),
            ", ".join(sorted(a1)) or "—", ", ".join(sorted(a1_logged)) or "—",
            a2 or "—", a2_logged or "—",
            (f"сцена «{scene_title[row['scene_id']]}», `{row['path']}`, создан {row['created_at']}, {row['characters']} симв., ${row['cost_usd']}") if row else "—",
            f"`{voice}` = {VOICES.get(voice, '?')}" if voice else "—",
            f"ученик (ref `{ref}`), сцена 1 собеседник {scene_gender[day[1]['scene']['id']]} → ученик male",
            (f"`{s2['id']}`, `{s2['voice_key'].split(':')[2]}` = {VOICES.get(s2['voice_key'].split(':')[2], '?')}, {s2['created_at']}") if s2 else "—",
        ])
    out.append(table(["поз.", "ref", "реплика", "день 1: audio id (ответ дня)", "день 1: audio id (журнал answer)",
                      "день 2: audio id (ответ дня)", "день 2: audio id (журнал answer)", "файл (`plan_line_audios`)",
                      "voice id", "роль и пол по данным", "та же ref у сцены 2 (для сравнения)"], rows))
    out.append("")
    return out


# ── §4 ────────────────────────────────────────────────────────────────────────────────────────────
def find_card(n, stage, position):
    return next(c for c in cards_of(n) if c["stage"] == stage and c["position"] == position)


def option_sources(n, text):
    out = []
    for ex in lesson(n)["dialogue"]:
        chk = ex.get("check")
        if not chk:
            continue
        for i, o in enumerate(chk["options"]):
            if o["text_native"].strip().lower() == (text or "").strip().lower():
                out.append(f"x{ex['step']}" + (" ✓" if i == chk["correct_option_index"] else ""))
    return ", ".join(out) or "нет в проверках урока"


def section4(dump):
    out = ["## §4 Сырьё трёх карточек дня 2", ""]
    # 4.1 weight loss
    c = next(c for c in cards_of(2) if c["kind"] == "phrase_other_slot" and "weight loss" in json.dumps(c["payload"]) and not c["retry_of"])
    v = room_cards[2][c["id"]]
    p = v["payload"]
    reqs = card_requests(c["id"])
    dump["s4_weight_loss"] = {"card": v, "requests": reqs}
    out += ["### 4.1 «Скажи фразу с каждым значением» — каркас с «weight loss»", "",
            f"Карточка `{c['id']}`, этап `phrases`, позиция {c['position']}, вид `{c['kind']}`, каркас `{c['unit_ref']}`. "
            "Полный payload как отдан — `raw/cards-s3-s4.json` → `s4_weight_loss.card`.", ""]
    rows = [
        ["`frame.frame_target` / `frame_native`", f"{q(p['frame']['frame_target'])} / {q(p['frame']['frame_native'])}"],
        ["`frame.slot.fillers` (как отдан)", "<br>".join(f"{f['index']}: {f['target']} — {f['native']} (`in_dialogue` {esc(f['in_dialogue'])})" for f in p["frame"]["slot"]["fillers"])],
        ["`rounds`", "<br>".join(f"filler {r['filler_index']}: {q(r['expected_text'])} — {q(r['task_native'])}" for r in p["rounds"])],
        ["`own_round`", json.dumps(p.get("own_round"), ensure_ascii=False)],
        ["`speech_mode`", p.get("speech_mode")],
        ["`partner_line`", f"{q(p['partner_line']['text_target'])} — {q(p['partner_line']['text_native'])}"],
        ["`key`", q(p.get("key"))],
        ["результат", f"{c['result']}, `attempts` {c['attempts']}, ответ {c['answered_at']}"],
        ["`response`", json.dumps(c["response"], ensure_ascii=False)],
    ]
    out.append(table(["поле", "значение"], rows))
    out.append("")
    pl = phrase(2, c["unit_ref"])
    out.append(f"Наполнения каркаса {c['unit_ref']} в уроке (`lesson_json.phrases`): {fillers_md(pl).replace('<br>', '; ')}.")
    out.append("")
    f = deal["day_2"]["frames"].get(c["unit_ref"])
    b = next((x for x in deal["day_2"].get("built", []) if x["ref"] == c["unit_ref"]), {})
    out.append(f"Лестница: в пересчёте раздачи дня 2 (§2.2) у каркаса {c['unit_ref']} на ступени 0 — узнаваний "
               f"{b.get('recognitions')}, кругов {b.get('rounds')}; после ступени 3 — узнаваний {f['recognitions']}, "
               f"кругов {f['rounds']}, своё {esc(f['own'])}; круги снимает только ступень 2 (`PhraseCards::rounds` "
               "ограничивается `ROUNDS_FLOOR` = 2 в `PhrasesStage::deal`, строки 160–177); секунды «Фраз» по ступеням: "
               + ", ".join(f"{r['rung']} — {r['seconds']} с" for r in deal["day_2"]["rungs"])
               + f". В журнале приложения строк `plan.phrases_over_ceiling` этого плана — {count_ceiling_lines()}.")
    out.append("")
    out.append("Запросы телефона по карточке (`api_request_logs`; `tokens_in`/`tokens_out` в журнале скрыты его редактором "
               "секретов — ключ содержит «token»):")
    out.append("")
    out.append(table(["время", "запрос", "тело запроса", "вердикт в ответе"],
                     [[hms(r["occurred_at"]), r["path"].rsplit("/", 1)[1], json.dumps(r["request_body"], ensure_ascii=False),
                       json.dumps(((((r["response_body"] or {}).get("data") or {}).get("card") or {}).get("response") or {}).get("judge"), ensure_ascii=False)]
                      for r in reqs]))
    out.append("")

    # 4.2 «Что тебе сказали?»
    out += ["### 4.2 «Что тебе сказали?» — вариант «К ушам»", "",
            "«Что тебе сказали?» клиент ставит над проверкой `dialogue_partner` (33-1) и над проверкой `dialogue_ask` после "
            "зачёта голосом (33-5), `mobile/lib/features/plan/session/cards/dialogue_cards.dart:59,156`. Варианты — три варианта "
            "проверки своего обмена из урока и четвёртый — верный вариант проверки самого дальнего по шагу обмена "
            "(`DialogueCards::check`, `farthestRightOption`). Колонка «источник» — где текст варианта стоит в проверках урока "
            "(`lesson_json.dialogue[].check.options`, ✓ — верный там). Выбор ученика — поле `choice` запроса `…/answer` "
            "(шлёт только `dialogue_ask`).", ""]
    both = []
    for n in (1, 2):
        for c in cards_of(n):
            if c["kind"] in ("dialogue_partner", "dialogue_ask") and c["payload"].get("options"):
                both.append((n, c))
    kushan = [(n, c) for n, c in both if any("ушам" in o["text"] for o in c["payload"]["options"])]
    dump["s4_what_said"] = []
    for n, c in both:
        view = room_cards[n].get(c["id"], {"payload": c["payload"]})
        dump["s4_what_said"].append({"day": n, "card": view, "requests": card_requests(c["id"])})

    def rows_of(items):
        rows = []
        for n, c in items:
            p = c["payload"]
            choice = next((((r["request_body"] or {}).get("choice")) for r in card_requests(c["id"]) if r["path"].endswith("/answer")), None)
            opts = "<br>".join(("**" if o["id"] == p.get("correct") else "") + f"{o['id']}: {o['text']}" + (" ✓" if o["id"] == p.get("correct") else "")
                               + ("**" if o["id"] == p.get("correct") else "") + f" ← {option_sources(n, o['text'])}" for o in p["options"])
            pl = p.get("partner_line") or {}
            rows.append([str(n), str(c["position"]), c["kind"], c["unit_ref"], f"{q(pl.get('text_target'))}<br>{q(pl.get('text_native'))}",
                         q(p.get("question_native")), opts, esc(choice), f"{c['result']}, попыток {c['attempts']}"])
        return rows

    hdr = ["день", "поз.", "вид", "обмен", "реплика собеседника (`partner_line`)", "`question_native`", "`options` (✓ = `correct`) ← источник", "выбор ученика", "результат"]
    out.append("Карточки с «К ушам»:")
    out.append("")
    out.append(table(hdr, rows_of(kushan)))
    out.append("")
    out.append("Все остальные карточки этих видов в днях 1–2:")
    out.append("")
    out.append(table(hdr, rows_of([x for x in both if x not in kushan])))
    out.append("")

    # 4.3 forty-five seconds
    fs = [c for c in cards_of(2) if c["kind"] not in ("listen_dialogue", "listen_review") and "forty-five seconds" in json.dumps(
        {k: c["payload"].get(k) for k in ("rounds", "own_line", "expected_text", "modes")}, ensure_ascii=False)]
    dump["s4_forty_five"] = [{"card": room_cards[2][c["id"]], "requests": card_requests(c["id"])} for c in fs]
    out += ["### 4.3 Карточки с «forty-five seconds»", "",
            "Карточки дня 2, которые требуют сказать «forty-five seconds» (в `rounds`, `own_line` или `expected_text`; "
            "`listen_dialogue`/`listen_review` — только слушание, не включены). heard — `day_cards.response.heard` (последняя "
            "попытка) и тела запросов телефона `…/answer` и `…/judge` (`api_request_logs`); судья — блок `judge` ответа.", ""]
    rows = []
    for c in fs:
        p = c["payload"]
        exp = [r["expected_text"] for r in p.get("rounds") or []] or [p.get("expected_text") or (p.get("own_line") or {}).get("text_target")]
        reqs = card_requests(c["id"])
        rq = "<br>".join(
            f"{hms(r['occurred_at'])} {r['path'].rsplit('/', 1)[1]}: {json.dumps(r['request_body'], ensure_ascii=False)}"
            + (f" → judge {json.dumps(((((r['response_body'] or {}).get('data') or {}).get('card') or {}).get('response') or {}).get('judge'), ensure_ascii=False)}"
               if r["path"].endswith("/judge") else "")
            for r in reqs)
        rows.append([str(c["position"]) + (" (копия)" if c["retry_of"] else ""), c["stage"], c["kind"], c["unit_ref"],
                     "<br>".join(q(e) for e in exp),
                     f"{p.get('speech_mode')}" + (f"; своё: {p['own_round'].get('speech_mode')}" if p.get("own_round") else ""),
                     f"{c['result']}, попыток {c['attempts']}", json.dumps(c["response"], ensure_ascii=False), rq or "—"])
    out.append(table(["поз.", "этап", "вид", "ref", "expected_text", "speech_mode", "результат", "`response` (в базе)", "запросы телефона"], rows))
    out.append("")
    notes = []
    for c in fs:
        heard_logged = sum(1 for r in card_requests(c["id"]) if "heard" in json.dumps(r["request_body"] or {}))
        if c["attempts"] > heard_logged:
            notes.append(f"позиция {c['position']}{' (копия)' if c['retry_of'] else ''}: `attempts` {c['attempts']}, запросов с heard — {heard_logged}")
    if notes:
        out.append("Попыток больше, чем сохранённых heard: " + "; ".join(notes) + ". Других записей о попытках нет: ни "
                   "запросов `…/judge`, ни второго `…/answer` по этим карточкам; в `day_cards.response` — одно поле `heard`.")
        out.append("")
    sp = room[2]["speech"]
    out.append("Правила сравнения речи, отданные с днём (`data.speech` ответа дня 2): `repeat_misses` "
               f"{sp['repeat_misses']}; `number_words` — " + ", ".join(f"{k} → {v}" for k, v in sp["number_words"].items())
               + "; `unstressed_words` — " + ", ".join(sp["unstressed_words"]) + ".")
    out.append("")
    return out


# ── the document ──────────────────────────────────────────────────────────────────────────────────
def main():
    dump = {"s3_returned": [], "s4_weight_loss": None, "s4_what_said": [], "s4_forty_five": []}
    p = plan["plan"]
    out = [
        "# GYM-DUMP-2 — план «Тренировка в зале», дни 1 и 2 (бой, только чтение)", "",
        "Факты для разбора архитектором: данные, цитаты и таблицы, без выводов и оценок. Снято 2026-09-22 с боя "
        "(`wt_db`, база `wordtrainer`). Покупок у вендоров нет: ни озвучки, ни генерации, ни судьи, ни модели.", "",
        table(["", ""], [
            ["Аккаунт", "user `01M12HTZ1QHPNDZ5J8SPKB58QP`"],
            ["План", f"`{p['id']}` — «{p['title_native']}» / «{p['title_target']}», `status` {p['status']}, уровень {p['level']}, {p['native_lang']} → {p['target_lang']}"],
            ["День 1", f"`{day[1]['plan_day']['id']}` · сцена «{scene_title[day[1]['scene']['id']]}» `{day[1]['scene']['id']}` · {day[1]['plan_day']['status']}"],
            ["День 2", f"`{day[2]['plan_day']['id']}` · сцена «{scene_title[day[2]['scene']['id']]}» `{day[2]['scene']['id']}` · {day[2]['plan_day']['status']}"],
            ["Разговоры", f"день 1 `{talk[1]['conversation']['id']}`, день 2 `{talk[2]['conversation']['id']}`"],
        ]), "",
        "## Как снято", "",
        "- `tools/dump.py` — только SELECT через `psql` в `wt_db` с `PGOPTIONS=-c default_transaction_read_only=on` "
        "(сессия отказывает в любой записи) → `raw/*.json` строк базы. Заголовки запросов из `api_request_logs` не выгружались.",
        "- `tools/views.php` — документы, которые получал телефон, пересобраны кодом приложения (`GetDayRoomHandler`, "
        "`GetConversationHandler` + `PlanJson`) в `wt_app`: сессия БД `READ ONLY` (проверено: "
        f"`default_transaction_read_only` {check['default_transaction_read_only']}, `transaction_read_only` {check['transaction_read_only']}), "
        "`LOG_CHANNEL=stderr`, `CACHE_STORE=array`, несуществующее подключение очереди, пустые ключи вендоров, "
        f"`Http::preventStrayRequests()`, часы заморожены на {check['clock']} (последнее чтение дня 2 телефоном). "
        f"Сверка с журналом: ответ дня 2 — {check['room_day_2']['wire_bytes_rebuilt']} байт против "
        f"{check['room_day_2']['wire_bytes_logged']} в журнале, тело журнала {check['room_day_2']['body_bytes_rebuilt']} = "
        f"{check['room_day_2']['body_bytes_logged']}, превью совпадает: {esc(check['room_day_2']['preview_is_prefix_of_rebuilt'])}; "
        f"документ разговора дня 2 равен ответу последнего хода в журнале: {esc(check['talk_day_2']['rebuilt_equals_logged_reply'])}.",
        "- `tools/report.py` — таблицы этого файла только из `raw/` (и счёт строк `plan.phrases_over_ceiling` в `storage/logs/laravel.log`).",
        "- Время везде UTC.", "",
    ]
    out += section1()
    out += section2()
    out += section3(dump)
    out += section4(dump)
    out += [
        "## Чего нет в базе (где искал)", "",
        "- **heard каждой попытки** карточек с распознаванием на телефоне (`phrase_other_slot` по кругам, `speak_retell`, "
        "`speak_echo`, `dialogue_answer`/`dialogue_ask`, `word_repeat`): в `day_cards.response` и в теле `…/answer` — только "
        "последняя попытка; `…/judge` вызывается лишь на кругах, которые судит сервер (своё окно «Скажи целиком», "
        "`speak_answer`), — для них каждая попытка есть в `api_request_logs`. `model_calls` — без привязки к карточке и без текста.",
        "- **`phrases_used` модели** в `conversation_turns` не хранится (у ходов роли `[]` по построению) — взят из сырых ответов "
        "модели в `api_request_logs` (исходящие `api.openai.com`, `purpose = plan`).",
        "- **Время этапов карточек**: `plan_stage_passages` у дней 1 и 2 содержит только строку `conversation`; открытие этапа "
        "не пишется нигде — только `day_cards.answered_at` и чтения дня `GET …/days/2` в `api_request_logs`.",
        "- **Ступени лестницы дня 1** не записаны нигде (день раздан до BACK-TAILS-2); строки `plan.phrases_over_ceiling` для "
        f"этого плана в `storage/logs/laravel.log` — {count_ceiling_lines()}.",
        "- **Ответ `GET …/days/{n}`** в `api_request_logs` — только превью 8 КБ (`_truncated`); полный документ пересобран "
        "(`raw/room-day-*.json`). Ответы `POST …/open` и `…/close` — тоже превью.", "",
        "## Файлы", "",
        table(["файл", "что внутри"], [
            ["`raw/plan.json`", "строка `plans`, все `plan_days`, `plan_scenes` без `lesson_json`"],
            ["`raw/day-1.json`, `raw/day-2.json`", "`plan_days`, `plan_scenes` целиком (с `lesson_json`), `plan_terms` сцены, все `day_cards` дня, `plan_stage_passages`, `plan_events`"],
            ["`raw/conversation-day-{1,2}.json`", "`conversations` + все `conversation_turns` разговора дня"],
            ["`raw/api-log-conversation-day-{1,2}.json`", "журнал запросов в окне разговора: ходы телефона (heard) и документы сервера, промпты и сырые ответы модели, вызовы голоса"],
            ["`raw/api-log-days.json`", "все входящие запросы телефона по дням 1 и 2 (open, чтения дня, answer, judge, close, conversation)"],
            ["`raw/line-audios.json`", "`plan_line_audios` сцен 1 и 2"],
            ["`raw/model-calls.json`", "`model_calls` в окнах времени дней (связи с пользователем в таблице нет — берутся все строки окна)"],
            ["`raw/room-day-{1,2}.json`", "ответ `GET /plans/{id}/days/{n}` на 12:55:54 UTC 22.09, пересобран (окно, этапы, карточки как отданы, `speech`)"],
            ["`raw/conversation-view-day-{1,2}.json`", "документ разговора, пересобран (ходы, `targets`, `summary`)"],
            ["`raw/phrases-deal.json`", "раздача «Фраз» кодом `DayAssembler::phrasesDeal` по ступеням; `day_2` — код, которым день раздан; `day_1` — ТЕКУЩИЙ код"],
            ["`raw/views-check.json`", "проверки режима только чтения и сверка пересборки с журналом"],
            ["`raw/cards-s3-s4.json`", "карточки §3 и §4 как отданы телефону + запросы телефона по каждой"],
        ]), "",
        "## Коммит", "",
        "Папка добавлена одним коммитом; коммит не может содержать собственный хеш — он в отчёте наряда и в "
        "`git log --oneline -- backend2/docs/research/gym-day2`.", "",
    ]
    (RAW / "cards-s3-s4.json").write_text(json.dumps(dump, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    (ROOT / "README.md").write_text("\n".join(out) + "\n", encoding="utf-8")
    print("wrote README.md, raw/cards-s3-s4.json")


if __name__ == "__main__":
    main()
