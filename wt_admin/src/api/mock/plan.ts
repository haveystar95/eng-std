// Offline demo of the plan page (ADM-1): one small synthetic plan in the exact wire shapes of
// `/users/{id}/plans` and `/plans/{code}/…` — three days (two scenes and the rehearsal), a talk, a
// few voiced lines, and the kinds of findings «Что не так» reports. Nothing here is real data.
import type {
  CallSource,
  LearnerPlanRow,
  PlanCallRow,
  PlanCalls,
  PlanCallsQuery,
  PlanDays,
  PlanHeader,
  PlanIssues,
  PlanLesson,
  PlanMoney,
  PlanPassage,
  PlanPipeline,
  PlanSection,
  PlanTalks,
  VoiceLineRow,
} from '../planTypes'
import { MOCK_NOW, users } from './data'

export const MOCK_PLAN_ID = '01M2K7M2QX4Z9R8T7W6V5S4Q3P'
export const MOCK_PLAN_CODE = 'K7M2QX'

const at = (hoursAgo: number): string => new Date(MOCK_NOW - hoursAgo * 3_600_000).toISOString()
const SCENES = [
  { id: '01M2K7M3AA0000000000000001', title: 'Приём у врача', target: 'At the doctor' },
  { id: '01M2K7M3AA0000000000000002', title: 'В аптеке', target: 'At the pharmacy' },
]
const PROGRESS = { current_day: 2, days_total: 3, days_closed: 1, days_opened: 2 }
const VOICE = (identity: string, id: string) => ({
  key: `elevenlabs:eleven_v3_conversational:${id}:s50`,
  provider: 'elevenlabs',
  model: 'eleven_v3_conversational',
  voice_id: id,
  short_id: `${id.slice(0, 4)}…`,
  identity,
})

export function mockLearnerPlans(userId: string): LearnerPlanRow[] {
  if (userId !== users[0]?.detail.id) return []
  return [
    {
      id: MOCK_PLAN_ID,
      code: MOCK_PLAN_CODE,
      title_native: 'Поход к врачу',
      title_target: 'Doctor visit',
      status: 'active',
      event_date: '2026-08-12',
      created_at: at(60),
      progress: PROGRESS,
      cost_usd: 0.2914,
    },
  ]
}

function header(): PlanHeader {
  const user = users[0]?.detail
  return {
    id: MOCK_PLAN_ID,
    code: MOCK_PLAN_CODE,
    user_id: user?.id ?? '',
    user: user ? { id: user.id, name: user.name, email: user.email } : null,
    title_native: 'Поход к врачу',
    title_target: 'Doctor visit',
    goal_text: 'Иду к врачу с ребёнком, болит спина.',
    target_lang: 'en',
    native_lang: 'ru',
    level: 'beginner',
    event_date: '2026-08-12',
    status: 'active',
    stored_status: 'active',
    created_at: at(60),
    started_at: at(59),
    finished_at: null,
    today: '2026-08-09',
    cover_image_url: null,
    progress: PROGRESS,
    versions: {
      plan: 'plan-builder-v2',
      lesson: ['lesson_day.v4.7'],
      repair: null,
      seam_judge: null,
      slot_judge: ['slot_judge.v2'],
      conversation: ['conversation_agent.v3'],
    },
    models: { plan: 'gpt-5.4', lesson: ['gpt-5.4'] },
    build_versions: { plan: 'bca543ce', lessons: ['bca543ce'] },
    cost_usd: 0.2914,
    not_stored: ['версия промта P2R у плана/сцены не хранится (только в коде и конфиге)'],
  }
}

function issues(day: number | null): PlanIssues {
  const all: PlanIssues['data'] = [
    {
      check: 'voice_gender_mismatch',
      title: 'Голос не по полу',
      severity: 'error',
      day: 2,
      place_kind: 'line',
      place: 'x1b',
      message: 'Строка x1b: голос ученика — learner:female, а по правилу — male; звучит телефон',
      detail: { scene_id: SCENES[1].id },
    },
    {
      check: 'passed_without_summary',
      title: 'День пройден без итога',
      severity: 'warning',
      day: 1,
      place_kind: 'day',
      place: '1',
      message: 'День 1 закрыт, а записи итога (day_passed) нет',
      detail: {},
    },
  ]
  const data = all.filter((i) => day === null || i.day === day)
  return {
    data,
    checks: [
      { code: 'voice_gender_mismatch', title: 'Голос не по полу', count: data.filter((i) => i.check === 'voice_gender_mismatch').length },
      { code: 'passed_without_summary', title: 'День пройден без итога', count: data.filter((i) => i.check === 'passed_without_summary').length },
      { code: 'lost_model_call', title: 'Вызов модели lost', count: 0 },
    ],
    healthy: data.length === 0,
    not_checked: ['причина «телефонного голоса» (402 вендора, предохранитель, кап) не хранится — видно только, что файла нет'],
  }
}

function days(day: number | null): PlanDays {
  const rows: PlanDays['data'] = [1, 2, 3].map((n) => ({
    number: n,
    type: n === 3 ? 'rehearsal' : 'scene',
    scene_id: n === 3 ? null : SCENES[n - 1].id,
    scene_title_native: n === 3 ? null : SCENES[n - 1].title,
    scene_title_target: n === 3 ? null : SCENES[n - 1].target,
    opens_on: n === 3 ? null : `2026-08-0${7 + n}`,
    stored_status: n === 1 ? 'closed' : n === 2 ? 'in_progress' : 'locked',
    status: n === 1 ? 'closed' : n === 2 ? 'in_progress' : 'locked',
    lesson_status: n === 3 ? null : 'ready',
    opened_at: n < 3 ? at(50 - n * 20) : null,
    closed_at: n === 1 ? at(28) : null,
    cards_total: n < 3 ? 64 : 0,
    cards_done: n === 1 ? 64 : n === 2 ? 20 : 0,
    minutes_spent: n === 1 ? 31 : 0,
    has_conversation: n < 3,
    stages_passed: n === 1 ? [{ stage: 'words', passed_at: at(29), conversation_id: null }] : [],
    passed_at: null,
    cost_usd: { generation: n < 3 ? 0.0842 : null, voice: n < 3 ? 0.052 : 0, conversation: n === 1 ? 0.011 : 0, slot_judge: 0.002, total: n < 3 ? 0.1492 : 0 },
  }))
  return { data: rows.filter((r) => day === null || r.number === day) }
}

const call = (id: string, purpose: string, day: number | null, status = 'completed') => ({
  id,
  purpose,
  status,
  provider: 'openai',
  model: purpose === 'judge' ? 'gpt-5.4-mini' : 'gpt-5.4',
  answered_model: purpose === 'judge' ? 'gpt-5.4-mini-2026-03-17' : 'gpt-5.4-2026-03-05',
  started_at: at(55),
  finished_at: at(54.99),
  latency_ms: 41_200,
  estimated_tokens_in: 9800,
  tokens_in: 9700,
  cached_tokens: 0,
  tokens_out: 3100,
  cost_usd: '0.071200',
  http_status: 200,
  error: null,
  log_id: null,
  day,
  window: { kind: day === null ? 'plan' : 'scene', subject_id: day === null ? MOCK_PLAN_ID : SCENES[0].id, from: at(55), to: at(54.9), others: 0 },
  certain: true,
})

function pipeline(day: number | null): PlanPipeline {
  const stage = (key: string, title: string, facts: Record<string, unknown> = {}, calls: PlanPipeline['plan_build']['calls'] = []) => ({
    key,
    title,
    status: 'done',
    started_at: at(55),
    finished_at: at(54.9),
    duration_ms: 360_000,
    facts,
    calls,
    tokens_in: calls.length ? 9700 : null,
    tokens_out: calls.length ? 3100 : null,
    calls_cost_usd: calls.length ? 0.0712 : null,
    not_stored: [],
  })
  return {
    plan_build: stage('plan', 'Job плана', { model: 'gpt-5.4', prompt_version: 'plan-builder-v2', attempts: 1 }, [call('01M2K7M4BB0000000000000001', 'plan', null)]),
    days: [1, 2, 3]
      .filter((n) => day === null || n === day)
      .map((n) => ({
        number: n,
        type: n === 3 ? 'rehearsal' : 'scene',
        scene_id: n === 3 ? null : SCENES[n - 1].id,
        stages:
          n === 3
            ? [stage('served', 'Выдан клиенту')]
            : [
                stage('lesson', 'Урок дня', { model: 'gpt-5.4', prompt_version: 'lesson_day.v4.7', attempts: 1 }, [call(`01M2K7M4CC000000000000000${n}`, 'lesson', n)]),
                stage('validator', 'Валидатор', {
                  findings: [{ code: 'frame.native_agreement', address: 'p2', detail: '«какие» согласуется с окном', fatal: false }],
                  fatal: 0,
                  warnings: 1,
                }),
                stage('repair', 'Починки P2R', { calls: 0 }),
                stage('seam_judge', 'Судья швов', { rejected: [] }, [call(`01M2K7M4DD000000000000000${n}`, 'judge', n)]),
                stage('images', 'Картинки', { terms: 8, terms_with_photo: 7, terms_without_photo: 1 }),
                stage('voice', 'Озвучка', { lines: 3, characters: 60, credits: 15, cost_usd: 0.003 }),
                stage('served', 'Выдан клиенту', { opened_at: at(40) }),
              ],
      })),
  }
}

function voiceLine(ref: string, speaker: 'partner' | 'learner', text: string, status: VoiceLineRow['status']): VoiceLineRow {
  const identity = speaker === 'partner' ? 'partner:female' : 'learner:male'
  const id = speaker === 'partner' ? '4NejU5DwQjevnR6mh3mb' : 'TWutjvRaJqAX89preB4e'
  return {
    ref,
    kind: speaker === 'partner' ? 'partner_line' : 'learner_line',
    speaker,
    text,
    gender: speaker === 'partner' ? 'female' : 'male',
    rule: speaker === 'partner' ? 'собеседник: role_gender урока — female' : 'ученик: пол в профиле не указан → по умолчанию male',
    voice: VOICE(identity, id),
    status,
    audio:
      status === 'voiced'
        ? { id: `01M2K7M5EE00000000000000${ref.length}${speaker === 'partner' ? 'A' : 'B'}`, characters: text.length, credits: Math.ceil(text.length / 4), cost_usd: '0.001200', duration_ms: 1400, bytes: 22000, request_id: 'demo', created_at: at(54) }
        : null,
    voiced_text: status === 'voiced' ? text : null,
    other_voices: [],
  }
}

function lesson(day: number | null): PlanLesson {
  return {
    days: [1, 2, 3]
      .filter((n) => day === null || n === day)
      .map((n) => ({
        number: n,
        type: n === 3 ? 'rehearsal' : 'scene',
        scene:
          n === 3
            ? null
            : {
                id: SCENES[n - 1].id,
                title_native: SCENES[n - 1].title,
                title_target: SCENES[n - 1].target,
                teaches_native: 'описать симптомы',
                goals_native: ['сказать, что болит', 'спросить про лекарство'],
                topic_description: 'Ребёнок на приёме у врача',
                lesson_status: 'ready',
                partner_role_native: 'врач',
                partner_role_target: 'doctor',
                learner_role_native: 'родитель',
                learner_role_target: 'parent',
                role_gender: 'female',
                partner_voice_gender: 'female',
                learner_voice_gender: null,
              },
        lesson:
          n === 3
            ? null
            : {
                topic: { title_target: 'At the doctor', title_native: 'У врача' },
                role_gender: 'female',
                dialogue: [
                  {
                    step: 1,
                    kind: 'answer',
                    messages: [
                      { speaker: 'A', role_native: 'Врач', text_target: 'What brings you in today?', text_native: 'Что вас привело?' },
                      { speaker: 'B', role_native: 'Родитель', phrase_id: 'p1', filler: 'back', text_target: 'My back hurts.', text_native: 'У меня болит спина.' },
                    ],
                    check: {
                      text_native: 'Что болит?',
                      options: [{ text_target: 'Back' }, { text_target: 'Head' }, { text_target: 'Leg' }],
                      correct_option_index: 0,
                    },
                  },
                ],
                phrases: [
                  {
                    id: 'p1',
                    kind: 'answer',
                    frame_target: 'My ___ hurts.',
                    frame_native: 'У меня болит ___.',
                    slot: { hint_native: 'что болит', fillers: [{ target: 'back', native: 'спина', in_dialogue: true }, { target: 'head', native: 'голова', in_dialogue: false }] },
                  },
                ],
                vocabulary: [{ id: 'v1', kind: 'word', term_target: 'fever', translation_native: 'температура', used_in: ['A2'] }],
                listening: { questions: [] },
              },
        scenes_covered: n === 3 ? SCENES.map((s, i) => ({ id: s.id, title_native: s.title, day: i + 1 })) : [],
        returns: n === 2 ? [{ unit_kind: 'phrase', unit_ref: 'p1', from_day: 1, scene_id: SCENES[0].id, cards: 2 }] : [],
        voice:
          n === 3
            ? { lines: [], voiced: 0, phone: 0, none: 0 }
            : {
                lines: [
                  voiceLine('x1', 'partner', 'What brings you in today?', 'voiced'),
                  voiceLine('x1b', 'learner', 'My son has a fever.', n === 2 ? 'phone' : 'voiced'),
                ],
                voiced: n === 2 ? 1 : 2,
                phone: n === 2 ? 1 : 0,
                none: 0,
              },
      })),
  }
}

function passage(day: number | null): PlanPassage {
  return {
    client: { user_agent: 'Dart/3.12 (dart:io)', last_sync_at: at(2), last_path: `api/v1/plans/${MOCK_PLAN_ID}/days/2`, build: null, device: null },
    days: [1, 2, 3]
      .filter((n) => day === null || n === day)
      .map((n) => ({
        number: n,
        type: n === 3 ? 'rehearsal' : 'scene',
        status: n === 1 ? 'closed' : n === 2 ? 'in_progress' : 'locked',
        opened_at: n < 3 ? at(50 - n * 20) : null,
        closed_at: n === 1 ? at(28) : null,
        summary: {
          cards_total: n < 3 ? 2 : 0,
          cards_done: n === 1 ? 2 : 0,
          minutes_spent: n === 1 ? 31 : 0,
          passed_at: null,
          results: { passed: n === 1 ? 1 : 0, hinted: 0, failed: 0, skipped: 0, unanswered: n === 1 ? 1 : 0 },
          stages_passed: [],
        },
        cards:
          n === 1
            ? [
                {
                  id: '01M2K7M6FF0000000000000001',
                  stage: 'speak',
                  position: 0,
                  kind: 'speak_answer',
                  unit_kind: 'exchange',
                  unit_ref: 'x1',
                  source: 'today',
                  from_day: null,
                  retry_of: null,
                  expected: 'My son has a fever.',
                  answer: { heard: 'my son has fever', mode: 'voice_hint' },
                  heard: 'my son has fever',
                  judge: { by: 'model', accepted: true, model: 'gpt-5.4-mini', cost_usd: '0.000501' },
                  result: 'passed',
                  attempts: 1,
                  answered_at: at(30),
                  returns: false,
                  payload: { scene_id: SCENES[0].id },
                },
              ]
            : [],
      })),
    not_stored: ['версия сборки клиента — клиент её не присылает', 'устройство — клиент его не присылает (только User-Agent Dart)'],
  }
}

function talks(day: number | null): PlanTalks {
  const talk: PlanTalks['data'][number] = {
    id: '01M2K7M7GG0000000000000001',
    day: 1,
    type: 'day',
    state: 'ended',
    ended_reason: 'natural',
    ended_label: 'прощание',
    started_at: at(29),
    ended_at: at(28.9),
    turn_limit: 12,
    hints_enabled: true,
    cost_usd: 0.011,
    scenes: [{ scene_id: SCENES[0].id, title_native: SCENES[0].title, role_native: 'врач', partner_gender: 'female', done: true }],
    checkpoints_done: [SCENES[0].id],
    targets: [
      { scene_id: SCENES[0].id, ref: 'p1', frame_target: 'My ___ hurts.', frame_native: 'У меня болит ___.', example_target: 'My back hurts.', status: 'said', said_turn: 1, key_words: { found: 2, total: 2, turn: 1 }, opened_on_turn: 0 },
      { scene_id: SCENES[0].id, ref: 'p2', frame_target: 'Can I take ___?', frame_native: 'Можно принимать ___?', example_target: null, status: 'none', said_turn: null, key_words: { found: 0, total: 2, turn: null }, opened_on_turn: null },
    ],
    turns: [
      {
        index: 0, kind: 'agent', speaker: 'partner', text_target: 'Hello! What seems to be the problem?', text_native: 'Здравствуйте! Что случилось?', heard: null,
        audio_id: '01M2K7M8HH0000000000000001',
        audio: { voice: VOICE('partner:female', '4NejU5DwQjevnR6mh3mb'), expected_voice: VOICE('partner:female', '4NejU5DwQjevnR6mh3mb'), duration_ms: 1900, characters: 36, credits: 9, cost_usd: '0.001800' },
        understood: null, off_topic: null, phrases_used: [], opens_target: `${SCENES[0].id}:p1`, checkpoint_done: null, hint_native: 'Скажи, что болит спина',
        model: 'gpt-5.4-mini', prompt_version: 'conversation_agent.v3', tokens_in: 3900, tokens_out: 90, model_cost_usd: 0.0021, speech_cost_usd: 0.0018, cost_usd: 0.0039,
        latency_ms: 2100, model_latency_ms: 1500, speech_latency_ms: 600, created_at: at(29),
      },
      {
        index: 1, kind: 'said', speaker: 'learner', text_target: 'my back hurts', text_native: null, heard: 'my back hurts', audio_id: null, audio: null,
        understood: true, off_topic: false, phrases_used: [`${SCENES[0].id}:p1`], opens_target: null, checkpoint_done: null, hint_native: null,
        model: null, prompt_version: null, tokens_in: null, tokens_out: null, model_cost_usd: 0, speech_cost_usd: 0, cost_usd: 0,
        latency_ms: null, model_latency_ms: null, speech_latency_ms: null, created_at: at(28.95),
      },
    ],
    not_stored: ['сырой текст распознавания и его альтернативы — сервер получает только итоговую строку heard'],
  }
  return { data: [talk].filter((t) => day === null || t.day === day) }
}

function money(day: number | null): PlanMoney {
  const d = days(day).data.map((r) => ({
    number: r.number,
    type: r.type,
    generation: r.cost_usd.generation === null ? null : { cost_usd: r.cost_usd.generation, lesson_usd: 0.0712, repair_usd: 0, judge_usd: 0.013, tokens_in: 19_000, tokens_out: 3300, calls: 2, certain: true },
    images: { cost_usd: null },
    voice: { lines: r.type === 'scene' ? 48 : 0, characters: r.type === 'scene' ? 1685 : 0, credits: r.type === 'scene' ? 421 : 0, cost_usd: r.cost_usd.voice },
    conversation: { talks: r.number === 1 ? 1 : 0, turns: r.number === 1 ? 2 : 0, model_usd: r.number === 1 ? 0.0021 : 0, tokens_in: r.number === 1 ? 3900 : 0, tokens_out: r.number === 1 ? 90 : 0, speech_usd: r.number === 1 ? 0.0018 : 0, characters: 36, credits: 9, recognition_usd: null, total_usd: r.cost_usd.conversation },
    slot_judge: { calls: 1, tokens_in: 542, tokens_out: 21, cost_usd: r.cost_usd.slot_judge },
    build_usd: r.cost_usd.generation === null ? null : r.cost_usd.generation + r.cost_usd.voice,
    total_usd: r.cost_usd.total,
    over_canon: false,
    repair_share: r.cost_usd.generation === null ? null : 0,
  }))
  const sum = (f: (x: (typeof d)[number]) => number) => d.reduce((a, x) => a + f(x), 0)
  return {
    canon: { day_usd: 0.16, generation_usd: 0.08, voice_usd: 0.08, repair_share: 0.1 },
    plan_build: { cost_usd: 0.0212, model: 'gpt-5.4', attempts: 1, tokens_in: 9700, tokens_out: 3100, included: day === null },
    days: d,
    totals: {
      plan_build_usd: day === null ? 0.0212 : 0,
      generation_usd: sum((x) => x.generation?.cost_usd ?? 0),
      voice: { lines: sum((x) => x.voice.lines), characters: sum((x) => x.voice.characters), credits: sum((x) => x.voice.credits), cost_usd: sum((x) => x.voice.cost_usd) },
      conversation: { model_usd: sum((x) => x.conversation.model_usd), tokens_in: sum((x) => x.conversation.tokens_in), tokens_out: sum((x) => x.conversation.tokens_out), speech_usd: sum((x) => x.conversation.speech_usd), characters: sum((x) => x.conversation.characters), credits: sum((x) => x.conversation.credits), total_usd: sum((x) => x.conversation.total_usd) },
      slot_judge: { calls: sum((x) => x.slot_judge.calls), tokens_in: sum((x) => x.slot_judge.tokens_in), tokens_out: sum((x) => x.slot_judge.tokens_out), cost_usd: sum((x) => x.slot_judge.cost_usd) },
      total_usd: (day === null ? 0.0212 : 0) + sum((x) => x.total_usd),
    },
    not_stored: ['цена фото — не хранится нигде (Pexels бесплатен)'],
  }
}

function callRows(): PlanCallRow[] {
  const rows: PlanCallRow[] = []
  // More than a page (50), so the demo — and the tests — page it.
  for (let i = 0; i < 60; i++) {
    const source: CallSource = (['model', 'voice', 'judge', 'client'] as const)[i % 4]
    rows.push({
      id: `01M2K7M9JJ${String(1000 + i).padStart(16, '0')}`,
      source,
      at: at(40 - i),
      day: (i % 2) + 1,
      stage: source === 'client' ? `GET api/v1/plans/${MOCK_PLAN_ID}/days/${(i % 2) + 1}` : source === 'model' ? 'lesson' : source,
      status: source === 'client' ? (i === 3 ? '500' : '200') : source === 'model' ? (i === 0 ? 'lost' : 'completed') : 'bought',
      is_error: i === 0 || i === 3,
      model: source === 'model' ? 'gpt-5.4' : null,
      duration_ms: 800 + i * 10,
      tokens_in: source === 'model' ? 9000 : null,
      tokens_out: source === 'model' ? 3000 : null,
      cost_usd: source === 'client' ? null : 0.001 * (i + 1),
      log_id: null,
    })
  }
  return rows.sort((a, b) => (a.at < b.at ? 1 : -1))
}

export function mockPlanCalls(code: string, q: PlanCallsQuery = {}): PlanCalls {
  if (code !== MOCK_PLAN_CODE && code !== MOCK_PLAN_ID) throw notFoundPlan()
  const limit = q.limit ?? 50
  const all = callRows().filter((r) => (q.day == null || r.day === q.day) && (q.source == null || r.source === q.source))
  const start = q.cursor ? Number(q.cursor) : 0
  const page = all.slice(start, start + limit)
  return {
    data: page,
    meta: { total: all.length, per_page: limit, next_cursor: start + limit < all.length ? String(start + limit) : null, not_stored: [] },
  }
}

function notFoundPlan(): Error {
  const err = new Error('План не найден') as Error & { status?: number }
  err.status = 404
  return err
}

export function mockPlanHeader(code: string): PlanHeader {
  if (code !== MOCK_PLAN_CODE && code !== MOCK_PLAN_ID) throw notFoundPlan()
  return header()
}

export function mockPlanSection(code: string, section: PlanSection, day: number | null): unknown {
  if (code !== MOCK_PLAN_CODE && code !== MOCK_PLAN_ID) throw notFoundPlan()
  switch (section) {
    case 'issues':
      return issues(day)
    case 'days':
      return days(day)
    case 'pipeline':
      return pipeline(day)
    case 'lesson':
      return lesson(day)
    case 'passage':
      return passage(day)
    case 'conversations':
      return talks(day)
    case 'money':
      return money(day)
  }
}
