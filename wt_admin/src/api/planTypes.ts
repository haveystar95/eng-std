// The learner's plan page (наряд ADM-1) — the responses of `/users/{id}/plans` and `/plans/{code}/…`
// exactly as the wire carries them (snake_case, see httpGetRaw): every block of the page shows its
// raw JSON, and much of it is foreign JSON (the served lesson, card payloads, judge rulings).
// Mirrors backend2/openapi/openapi-admin.yaml, tag «Plan page».

export type PlanSection = 'issues' | 'days' | 'pipeline' | 'lesson' | 'passage' | 'conversations' | 'money'
export type CallSource = 'model' | 'voice' | 'judge' | 'client'

export interface PlanProgress {
  current_day: number | null
  days_total: number
  days_closed: number
  days_opened: number
}

export interface LearnerPlanRow {
  id: string
  code: string
  title_native: string | null
  title_target: string | null
  status: string
  event_date: string | null
  created_at: string
  progress: PlanProgress
  cost_usd: number
}

export interface PlanHeader {
  id: string
  code: string
  user_id: string
  user: { id: string; name: string; email: string | null } | null
  title_native: string | null
  title_target: string | null
  goal_text: string
  target_lang: string
  native_lang: string
  level: string
  event_date: string | null
  status: string
  stored_status: string
  created_at: string
  started_at: string | null
  finished_at: string | null
  today: string
  cover_image_url: string | null
  progress: PlanProgress
  versions: {
    plan: string | null
    lesson: string[]
    repair: string | null
    seam_judge: string | null
    slot_judge: string[]
    conversation: string[]
  }
  models: { plan: string | null; lesson: string[] }
  build_versions: { plan: string | null; lessons: string[] }
  cost_usd: number
  not_stored: string[]
}

// ── «Что не так» ──
export interface PlanIssue {
  check: string
  title: string
  severity: 'error' | 'warning'
  day: number | null
  place_kind: 'day' | 'line' | 'card' | 'turn' | 'talk' | 'call'
  place: string
  message: string
  detail: Record<string, string | number | boolean | null>
}
export interface PlanIssues {
  data: PlanIssue[]
  checks: { code: string; title: string; count: number }[]
  healthy: boolean
  /** Grey marks, not findings: what a check could not look at on this plan (e.g. a talk before 23.09). */
  notes: { check: string; day: number | null; place_kind: string; place: string; message: string }[]
  not_checked: string[]
}

// ── Обзор ──
export interface PlanDayRow {
  number: number
  type: 'scene' | 'review' | 'rehearsal'
  scene_id: string | null
  scene_title_native: string | null
  scene_title_target: string | null
  opens_on: string | null
  stored_status: string
  status: string
  lesson_status: string | null
  opened_at: string | null
  closed_at: string | null
  cards_total: number
  cards_done: number
  minutes_spent: number
  has_conversation: boolean
  stages_passed: { stage: string; passed_at: string; conversation_id: string | null }[]
  passed_at: string | null
  cost_usd: { generation: number | null; voice: number; conversation: number; slot_judge: number; total: number }
}
export interface PlanDays {
  data: PlanDayRow[]
}

// ── Конвейер ──
export interface ModelCallRow {
  id: string
  purpose: string | null
  status: string
  provider: string
  model: string
  answered_model: string | null
  started_at: string
  finished_at: string | null
  latency_ms: number | null
  estimated_tokens_in: number
  tokens_in: number | null
  cached_tokens: number | null
  tokens_out: number | null
  cost_usd: string | null
  http_status: number | null
  error: string | null
  log_id: string | null
  day: number | null
  window: { kind: string; subject_id: string; from: string; to: string; others: number }
  certain: boolean
}
export interface PipelineStage {
  key: string
  title: string
  status: string
  started_at: string | null
  finished_at: string | null
  duration_ms: number | null
  facts: Record<string, unknown>
  calls: ModelCallRow[]
  tokens_in: number | null
  tokens_out: number | null
  calls_cost_usd: number | null
  not_stored: string[]
}
export interface PlanPipeline {
  plan_build: PipelineStage
  days: { number: number; type: string; scene_id: string | null; stages: PipelineStage[]; note?: string }[]
}

// ── Урок и голос ──
export interface VoiceRef {
  key: string
  provider: string | null
  model: string | null
  voice_id: string
  short_id: string | null
  identity: string | null
}
export interface VoiceAudio {
  id: string
  characters: number | null
  credits: number | null
  cost_usd: string | null
  duration_ms: number | null
  bytes: number
  request_id: string | null
  created_at: string | null
}
export interface VoiceLineRow {
  ref: string
  kind: 'partner_line' | 'learner_line' | 'phrase' | 'filler' | 'word'
  speaker: 'partner' | 'learner'
  text: string
  gender: string
  rule: string
  voice: VoiceRef | null
  status: 'voiced' | 'phone' | 'none'
  audio: VoiceAudio | null
  voiced_text: string | null
  other_voices: { voice: VoiceRef | null; audio: VoiceAudio }[]
}
export interface LessonDay {
  number: number
  type: string
  scene: {
    id: string
    title_native: string
    title_target: string
    teaches_native: string
    goals_native: string[]
    topic_description: string
    lesson_status: string
    partner_role_native: string
    partner_role_target: string
    learner_role_native: string
    learner_role_target: string
    role_gender: string | null
    partner_voice_gender: string | null
    learner_voice_gender: string | null
  } | null
  lesson: Record<string, unknown> | null
  scenes_covered: { id: string; title_native: string | null; day: number | null }[]
  returns: { unit_kind: string; unit_ref: string; from_day: number | null; scene_id: string | null; cards: number }[]
  voice: { lines: VoiceLineRow[]; voiced: number; phone: number; none: number }
}
export interface PlanLesson {
  days: LessonDay[]
}

// ── Прохождение ──
export interface PassageCard {
  id: string
  stage: string
  position: number
  kind: string
  unit_kind: string
  unit_ref: string
  source: string
  from_day: number | null
  retry_of: string | null
  expected: string | null
  answer: Record<string, unknown> | null
  heard: string | null
  judge: Record<string, unknown> | null
  result: string | null
  attempts: number
  answered_at: string | null
  returns: boolean
  payload: Record<string, unknown>
}
export interface PassageDay {
  number: number
  type: string
  status: string
  opened_at: string | null
  closed_at: string | null
  summary: {
    cards_total: number
    cards_done: number
    minutes_spent: number
    passed_at: string | null
    results: Record<string, number>
    stages_passed: { stage: string; passed_at: string; conversation_id: string | null }[]
  }
  cards: PassageCard[]
}
export interface PlanPassage {
  client: { user_agent: string | null; last_sync_at: string | null; last_path: string | null; build: null; device: null }
  days: PassageDay[]
  not_stored: string[]
}

// ── Разговоры ──
export interface TalkTurn {
  index: number
  kind: string
  speaker: 'partner' | 'learner'
  text_target: string | null
  text_native: string | null
  heard: string | null
  audio_id: string | null
  audio: {
    voice: VoiceRef | null
    expected_voice: VoiceRef | null
    duration_ms: number | null
    characters: number | null
    credits: number | null
    cost_usd: string | null
  } | null
  /** the scene the line was said in (FIX-4 §4); null on a line of before it */
  scene_id: string | null
  /** a new role's greeting (`start`) or a scene's goodbye (`end`) in a talk over several scenes */
  scene_event: 'start' | 'end' | null
  understood: boolean | null
  off_topic: boolean | null
  /** the constructions the move said, `<scene>:<ref>` — targets and extras alike */
  phrases_used: string[]
  /** …and the ones it said almost (one word off, FIX-4 §2) */
  phrases_almost: string[]
  /** of `phrases_used`, the ones that are no target of the talk — «ещё вспомнил» */
  extra_said: string[]
  opens_target: string | null
  checkpoint_done: string | null
  hint_native: string | null
  /** what the server refused of the role on this line: each refused attempt of the model, each door dropped (FIX-4 §§3, 6) */
  rejections: TalkRejection[]
  model: string | null
  prompt_version: string | null
  tokens_in: number | null
  tokens_out: number | null
  model_cost_usd: number
  speech_cost_usd: number
  cost_usd: number
  latency_ms: number | null
  model_latency_ms: number | null
  speech_latency_ms: number | null
  created_at: string
}
export interface TalkRejection {
  attempt: number
  kind: 'rejected_answer' | 'dropped_opening'
  /** learner_line · learner_echo · same_words · own_line · early_end | foreign_scene · already_said · unknown_id */
  reason: string
  /** the row of `model_calls` the refused answer came from */
  model_call_id: string | null
  detail: Record<string, unknown>
}
export interface TalkTarget {
  scene_id: string
  ref: string
  /** what the role knows it by — `T3` (FIX-4 §3) */
  short_id: string | null
  frame_target: string
  frame_native: string
  example_target: string | null
  line_target: string
  status: 'said' | 'almost' | 'none'
  said_turn: number | null
  almost_turn: number | null
  opened_on_turn: number | null
}
export interface TalkExtra {
  id: string
  frame_target: string | null
  said_turn: number
}
export interface PlanTalk {
  id: string
  day: number
  type: string
  state: string
  ended_reason: string | null
  /** a limit ended the talk — `limit`, or a goodbye the moves forced before its scenes were walked (FIX-4 §4) */
  ended_by_limit: boolean
  ended_label: string | null
  started_at: string
  ended_at: string | null
  turn_limit: number
  hints_enabled: boolean
  cost_usd: number
  scenes: { scene_id: string; title_native: string; role_native: string; partner_gender: string; done: boolean }[]
  checkpoints_done: string[]
  /** false — begun before `opens_target` was recorded (23.09): its openings are not judged */
  openers_checked: boolean
  targets: TalkTarget[]
  /** the constructions of the talk's scenes said that are no target (FIX-4 §2) */
  extra_said: TalkExtra[]
  /** how many things the server refused of the role in the talk */
  rejections: number
  turns: TalkTurn[]
  not_stored: string[]
}
export interface PlanTalks {
  data: PlanTalk[]
}

// ── Деньги ──
export interface DayMoney {
  number: number
  type: string
  generation: {
    cost_usd: number | null
    lesson_usd: number | null
    repair_usd: number | null
    judge_usd: number | null
    tokens_in: number | null
    tokens_out: number | null
    calls: number
    certain: boolean
  } | null
  images: { cost_usd: null }
  voice: { lines: number; characters: number; credits: number; cost_usd: number }
  conversation: {
    talks: number
    turns: number
    model_usd: number
    tokens_in: number
    tokens_out: number
    speech_usd: number
    characters: number
    credits: number
    recognition_usd: null
    total_usd: number
  }
  slot_judge: { calls: number; tokens_in: number; tokens_out: number; cost_usd: number }
  build_usd: number | null
  total_usd: number
  over_canon: boolean
  repair_share: number | null
}
export interface PlanMoney {
  canon: {
    day_usd: number
    generation_usd: number
    voice_usd: number
    repair_share: number
    warn_ratio: number
    error_ratio: number
    openers_since: string
  }
  plan_build: {
    cost_usd: number | null
    model: string | null
    attempts: number | null
    tokens_in: number | null
    tokens_out: number | null
    included: boolean
  }
  days: DayMoney[]
  totals: {
    plan_build_usd: number
    generation_usd: number
    voice: { lines: number; characters: number; credits: number; cost_usd: number }
    conversation: {
      model_usd: number
      tokens_in: number
      tokens_out: number
      speech_usd: number
      characters: number
      credits: number
      total_usd: number
    }
    slot_judge: { calls: number; tokens_in: number; tokens_out: number; cost_usd: number }
    total_usd: number
  }
  not_stored: string[]
}

// ── Вызовы API ──
export interface PlanCallRow {
  id: string
  source: CallSource
  at: string
  day: number | null
  stage: string
  status: string
  is_error: boolean
  model?: string | null
  duration_ms?: number | null
  tokens_in?: number | null
  tokens_out?: number | null
  cost_usd?: number | null
  characters?: number | null
  credits?: number | null
  log_id?: string | null
  path?: string
  method?: string
  http_status?: number | null
  response_bytes?: number | null
  [key: string]: unknown
}
export interface PlanCalls {
  data: PlanCallRow[]
  meta: { total: number; per_page: number; next_cursor: string | null; not_stored: string[] }
}
export interface PlanCallsQuery {
  day?: number | null
  source?: CallSource | null
  cursor?: string | null
  limit?: number
}
