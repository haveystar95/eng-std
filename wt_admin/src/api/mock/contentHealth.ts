// Mock adapter for «Здоровье контента».
//
// A DEMO MIRROR of the backend's rules, not a second source of truth. The real verdicts come from
// Learning's own playability gate (ModeContentRequirements → TermPlayability), which is the same
// derivation the live session runs; this file exists so the standalone SPA and the component tests
// have something shaped like the wire when no backend is configured. If the two ever disagree, the
// backend is right and this is stale — mirroring it here is the price of the mock adapter existing
// at all, and it is confined to this file.
import type {
  CollectionContentHealth,
  CollectionType,
  ContentHealthCollection,
  ContentHealthScope,
  ContentHealthSummary,
  ContentHealthTerm,
  ExerciseMode,
  ModeSimulation,
  NeedsEnrichmentReason,
  PassportDistractor,
  TermContentPassport,
  TermDetail,
} from '../types'
import { collectionRows, termDetails } from './data'

/** The станок's stocking target — three, not the card's two, so one deletion doesn't break a term. */
const MIN_DISTRACTORS = 3
const COST_PER_TERM_USD = 0.004
const CURRENT_VERSION = 'mech-v12.1'

/** The order the backend's ExerciseMode enum declares — the simulation list keeps it. */
const MODE_ORDER: ExerciseMode[] = [
  'multiple_choice', 'word_bank', 'typing', 'listening', 'cloze',
  'scramble', 'dictation', 'pick_correct', 'speaking', 'intro',
]

function tokens(sentence: string): string[] {
  const parts = sentence.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return []
  const last = parts[parts.length - 1].replace(/[.!?…]+$/u, '')
  if (last === '') return parts.slice(0, -1)
  return [...parts.slice(0, -1), last]
}

function sameTokens(a: string, b: string): boolean {
  const fold = (s: string) => tokens(s).map((t) => t.toLowerCase()).join(' ')
  return fold(a) === fold(b)
}

/** One distractor per `error_span` — the rule the card assembler applies. */
function usableIndexes(spans: string[]): number[] {
  const seen = new Set<string>()
  const kept: number[] = []
  spans.forEach((span, i) => {
    const folded = span.trim().toLowerCase()
    if (folded === '' || seen.has(folded)) return
    seen.add(folded)
    kept.push(i)
  })
  return kept
}

interface Facts {
  text: string
  example: string | null
  exampleTranslation: string | null
  usable: number
  usableSet: Set<number>
  words: number
  tokenCount: number
  clozeable: boolean
  exampleIsAnswer: boolean
  variants: number
}

function facts(term: TermDetail): Facts {
  const pinned = term.examples.find((e) => e.isPinned) ?? null
  const example = pinned?.sentence ?? null
  const hasExample = !!example
  const indexes = hasExample ? usableIndexes((pinned?.distractors ?? []).map((d) => d.errorSpan)) : []
  return {
    text: term.text,
    example,
    exampleTranslation: pinned?.translation ?? null,
    usable: indexes.length,
    usableSet: new Set(indexes),
    words: term.text.trim().split(/\s+/).filter(Boolean).length,
    tokenCount: hasExample ? tokens(example!).length : 0,
    clozeable: hasExample && example!.toLowerCase().includes(term.text.toLowerCase()),
    exampleIsAnswer: hasExample && sameTokens(example!, term.text),
    variants: term.acceptedVariants.length,
  }
}

function verdict(mode: ExerciseMode, f: Facts): ModeSimulation {
  const has = !!f.example
  const blocked = (reason: ModeSimulation['reason'], explanation: string): ModeSimulation =>
    ({ mode, status: 'blocked', reason, explanation })
  const ok = (explanation: string): ModeSimulation => ({ mode, status: 'ok', reason: null, explanation })

  switch (mode) {
    case 'multiple_choice':
      return {
        mode,
        status: 'pool_dependent',
        reason: 'options_from_pool',
        explanation:
          'зависит от пула: неверные варианты берутся из ДРУГИХ слов сессии, а не из контента термина — контент термина здесь ничего не решает.',
      }
    case 'typing':
    case 'listening':
      return ok('спрашивает сам термин — подходит любому термину.')
    case 'speaking':
      return ok('спрашивает сам термин вслух; на верхней ступени читается пример.')
    case 'intro':
      return ok('карточка только показывает слово — контент для неё не нужен.')
    case 'word_bank':
      return f.words >= 2
        ? ok(`ответ из ${f.words} слов — есть что собирать из фишек.`)
        : blocked('single_word', 'ответ — одно слово: сборка из одной фишки ничего не спрашивает (нужно минимум 2 слова).')
    case 'cloze':
      if (!has) return blocked('no_example', 'у термина нет закреплённого примера — вырезать пропуск не из чего.')
      return f.clozeable
        ? ok('пример содержит термин — есть откуда вырезать пропуск.')
        : blocked('example_lacks_term', 'пример не содержит сам термин, поэтому пропуск вырезать не из чего.')
    case 'scramble':
      if (!has) return blocked('no_example', 'у термина нет закреплённого примера — перемешивать нечего.')
      if (f.exampleIsAnswer) return blocked('example_is_term', 'пример совпадает с самим термином — это была бы та же карточка, что и сборка слова.')
      if (!f.exampleTranslation) return blocked('no_example_translation', 'у примера нет перевода, а именно перевод и есть вопрос карточки.')
      if (f.tokenCount < 4) return blocked('example_too_short', `в примере ${f.tokenCount} слов(а) — слишком короткий для этого тренажёра.`)
      if (f.tokenCount > 12) return blocked('example_too_long', `в примере ${f.tokenCount} слов — длиннее потолка этого тренажёра.`)
      return ok(`пример на ${f.tokenCount} слов с переводом — есть что собирать.`)
    case 'dictation':
      if (!has) return blocked('no_example', 'у термина нет закреплённого примера — диктовать нечего.')
      if (f.exampleIsAnswer) return blocked('example_is_term', 'пример совпадает с самим термином — это уже делает аудио-режим.')
      if (f.tokenCount < 4) return blocked('example_too_short', `в примере ${f.tokenCount} слов(а) — слишком короткий для этого тренажёра.`)
      if (f.tokenCount > 10) return blocked('example_too_long', `в примере ${f.tokenCount} слов — длиннее потолка этого тренажёра.`)
      return ok(`пример на ${f.tokenCount} слов — есть что диктовать.`)
    case 'pick_correct':
      if (!has) return blocked('no_example', 'у термина нет закреплённого примера — не из чего собирать варианты.')
      if (f.exampleIsAnswer) return blocked('example_is_term', 'пример совпадает с самим термином.')
      if (!f.exampleTranslation) return blocked('no_example_translation', 'у примера нет перевода, а именно перевод и есть вопрос карточки.')
      return f.usable >= 2
        ? ok(`годных дистракторов ${f.usable} — хватает на эталон + 2 неверных.`)
        : blocked('too_few_distractors', `годных дистракторов ${f.usable}, а карточке нужно минимум 2 (эталон + 2 неверных предложения).`)
  }
}

function reasons(hasExample: boolean, usable: number, variants: number): NeedsEnrichmentReason[] {
  if (!hasExample) return []
  const out: NeedsEnrichmentReason[] = []
  if (usable < MIN_DISTRACTORS) out.push('few_distractors')
  if (variants === 0) out.push('no_variants')
  return out
}

function termRow(term: TermDetail): ContentHealthTerm {
  const f = facts(term)
  const hasExample = !!f.example
  const pinned = term.examples.find((e) => e.isPinned) ?? null
  const why = reasons(hasExample, f.usable, f.variants)
  return {
    termId: term.id,
    text: term.text,
    translation: term.translations.find((t) => t.isPrimary)?.text ?? term.translations[0]?.text ?? null,
    hasExample,
    missingExample: !hasExample,
    usableDistractors: f.usable,
    rawDistractors: pinned?.distractors.length ?? 0,
    pickCorrectReady: verdict('pick_correct', f).status === 'ok',
    variants: f.variants,
    enrichmentVersion: term.enrichmentVersion,
    needsEnrichment: hasExample && why.length > 0,
    needsEnrichmentReasons: why,
  }
}

function scope(name: ContentHealthScope['scope'], rows: ContentHealthTerm[]): ContentHealthScope {
  const needs = rows.filter((r) => r.needsEnrichment).length
  const versions = new Map<string | null, number>()
  for (const r of rows) versions.set(r.enrichmentVersion, (versions.get(r.enrichmentVersion) ?? 0) + 1)
  const never = versions.get(null) ?? 0
  versions.delete(null)
  return {
    scope: name,
    terms: rows.length,
    withDistractors: rows.filter((r) => r.usableDistractors > 0).length,
    pickCorrectReady: rows.filter((r) => r.pickCorrectReady).length,
    withVariants: rows.filter((r) => r.variants > 0).length,
    withoutExample: rows.filter((r) => r.missingExample).length,
    needsEnrichment: needs,
    estimatedTopupUsd: round(needs * COST_PER_TERM_USD),
    enrichmentVersions: [
      ...(never > 0 ? [{ version: null, terms: never }] : []),
      ...[...versions.entries()].sort((a, b) => (a[0]! < b[0]! ? 1 : -1)).map(([version, terms]) => ({ version, terms })),
    ],
  }
}

function round(n: number): number {
  return Math.round(n * 10_000) / 10_000
}

function command(collectionIds: string[]): string {
  const flags = collectionIds.map((id) => `--collection=${id}`).join(' ')
  return `php artisan enrich:backfill ${flags} --topup=${MIN_DISTRACTORS}`.replace(/\s+/g, ' ').trim()
}

function termsOf(collectionId: string): TermDetail[] {
  return termDetails.filter((t) => t.collections.some((c) => c.id === collectionId))
}

export function mockContentHealthSummary(): ContentHealthSummary {
  const rows = termDetails.map(termRow)
  const byId = new Map(rows.map((r) => [r.termId, r]))
  const isSystem = (type: CollectionType) => type === 'system'

  const system: ContentHealthTerm[] = []
  const user: ContentHealthTerm[] = []
  for (const term of termDetails) {
    const row = byId.get(term.id)!
    if (term.collections.some((c) => isSystem(c.type as CollectionType))) system.push(row)
    if (term.collections.some((c) => !isSystem(c.type as CollectionType))) user.push(row)
  }

  const collections: ContentHealthCollection[] = collectionRows.map((c) => {
    const mine = termsOf(c.id).map((t) => byId.get(t.id)!)
    const needs = mine.filter((r) => r.needsEnrichment).length
    return {
      id: c.id,
      title: c.title,
      type: c.type,
      terms: mine.length,
      withoutExample: mine.filter((r) => r.missingExample).length,
      pickCorrectReady: mine.filter((r) => r.pickCorrectReady).length,
      needsEnrichment: needs,
      estimatedTopupUsd: round(needs * COST_PER_TERM_USD),
    }
  })

  return {
    scopes: { all: scope('all', rows), system: scope('system', system), user: scope('user', user) },
    collections,
    suppressions: {
      total: 5,
      bySource: [
        { label: 'audit', count: 2 },
        { label: 'review', count: 3 },
      ],
    },
    generationRejections: { total: 2, byField: [{ label: 'translation', count: 2 }] },
    currentGeneratorVersion: CURRENT_VERSION,
    minDistractors: MIN_DISTRACTORS,
    costPerTermUsd: COST_PER_TERM_USD,
  }
}

export function mockCollectionContentHealth(id: string): CollectionContentHealth | null {
  const collection = collectionRows.find((c) => c.id === id)
  if (!collection) return null

  const rows = termsOf(id).map(termRow).sort((a, b) => {
    if (a.hasExample !== b.hasExample) return a.hasExample ? 1 : -1
    if (a.usableDistractors !== b.usableDistractors) return a.usableDistractors - b.usableDistractors
    if (a.variants !== b.variants) return a.variants - b.variants
    return a.text.localeCompare(b.text)
  })
  const needs = rows.filter((r) => r.needsEnrichment).length

  return {
    collectionId: collection.id,
    title: collection.title,
    type: collection.type,
    terms: rows,
    needsEnrichment: needs,
    withoutExample: rows.filter((r) => r.missingExample).length,
    pickCorrectReady: rows.filter((r) => r.pickCorrectReady).length,
    estimatedTopupUsd: round(needs * COST_PER_TERM_USD),
    topupCommand: command([collection.id]),
    minDistractors: MIN_DISTRACTORS,
    costPerTermUsd: COST_PER_TERM_USD,
  }
}

export function mockTermContentPassport(id: string): TermContentPassport | null {
  const term = termDetails.find((t) => t.id === id)
  if (!term) return null

  const f = facts(term)
  const pinned = term.examples.find((e) => e.isPinned) ?? null
  const distractors: PassportDistractor[] = (pinned?.distractors ?? []).map((d, i) => ({
    ...d,
    usable: f.usableSet.has(i),
  }))
  const why = reasons(!!f.example, f.usable, f.variants)

  return {
    termId: term.id,
    text: term.text,
    lang: term.lang,
    type: term.type,
    translations: term.translations,
    example: pinned ? { id: pinned.id, sentence: pinned.sentence, translation: pinned.translation } : null,
    distractors,
    errorTypeNote:
      'Ярлык error_type — догадка станка о том, какую ошибку он написал; ничем не проверяется. ' +
      'Читайте сам фрагмент и исправление, ярлык — только подсказка.',
    suppressed:
      term.enrichmentVersion !== null
        ? [{ sentence: `the bank sent me a ${term.text} at monday`, source: 'review', createdAt: null }]
        : [],
    acceptedVariants: term.acceptedVariants,
    enrichmentVersions: term.enrichmentVersion
      ? [{ version: term.enrichmentVersion, createdAt: term.updatedAt }]
      : [],
    enrichmentVersion: term.enrichmentVersion,
    findings: term.findings,
    simulation: MODE_ORDER.map((mode) => verdict(mode, f)),
    usableDistractors: f.usable,
    missingExample: !f.example,
    needsEnrichment: !!f.example && why.length > 0,
    needsEnrichmentReasons: why,
    collections: term.collections,
    topupCommand: command(term.collections.map((c) => c.id)),
    topupHint:
      term.enrichmentVersion === CURRENT_VERSION
        ? `Термин уже помечен текущей версией станка (${CURRENT_VERSION}): обычный прогон его пропустит. ` +
          'Догон выше идёт через --topup и метку версии игнорирует; для полного перепрогона задайте другую --generator=<версия>.'
        : null,
    currentGeneratorVersion: CURRENT_VERSION,
    minDistractors: MIN_DISTRACTORS,
    costPerTermUsd: COST_PER_TERM_USD,
  }
}
