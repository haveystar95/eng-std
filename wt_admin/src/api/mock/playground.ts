// Mock adapter for «Песочница».
//
// A DEMO STAND-IN, not a second validator. The real verdicts come from the backend's own
// EnrichmentValidator — the object the станок runs — and nothing on this side could or should
// reproduce its thirteen checks. What this file does is answer with the few shapes the screen has
// to render: a KEEP, a couple of the commoner REJECTs, and a provider that cannot be called.
//
// It exists so the SPA runs standalone with no backend configured, and so the view tests have
// something deterministic to mount against.
import type {
  PlaygroundProvider,
  PlaygroundResult,
  PlaygroundValidateInput,
  PlaygroundValidation,
  PlaygroundValidationRow,
  ValidationGate,
} from '../types'

const MODELS: Record<string, string[]> = {
  openai: ['gpt-5.4', 'gpt-4o-mini', 'gpt-4o'],
  anthropic: ['claude-haiku-4-5', 'claude-opus-5'],
}

export function mockPlaygroundProviders(): PlaygroundProvider[] {
  return [
    { provider: 'openai', label: 'OpenAI', models: MODELS.openai, available: true, reason: '' },
    {
      provider: 'anthropic',
      label: 'Anthropic',
      models: MODELS.anthropic,
      available: false,
      reason: 'нет ключа (ANTHROPIC_API_KEY не задан)',
    },
  ]
}

/** A believable model answer: three distractors against one example, one of them broken on purpose. */
const DEMO_ANSWER = {
  distractors: [
    {
      sentence: 'I would like to withdrawing money from my account.',
      error_span: 'to withdrawing',
      correction: 'to withdraw',
      error_type: 'modal_to',
    },
    {
      sentence: 'I would like to withdraw money from my account.',
      error_span: 'from',
      correction: 'from',
      error_type: 'preposition',
    },
    {
      sentence: 'I would like to withdraw money at my account.',
      error_span: 'zzz',
      correction: 'from',
      error_type: 'preposition',
    },
  ],
}

export function mockPlaygroundGenerate(provider: string, model: string): PlaygroundResult {
  const known = mockPlaygroundProviders().find((p) => p.provider === provider)
  if (!known || !known.available) {
    return {
      provider,
      model,
      rawText: '',
      parsedJson: null,
      parseError: null,
      usage: { tokensIn: null, tokensOut: null, costUsd: null },
      latencyMs: 0,
      error: known ? `${known.label}: ${known.reason}` : `неизвестный провайдер «${provider}».`,
    }
  }

  const raw = JSON.stringify(DEMO_ANSWER, null, 2)
  return {
    provider,
    model,
    rawText: raw,
    parsedJson: DEMO_ANSWER,
    parseError: null,
    usage: { tokensIn: 1240, tokensOut: 320, costUsd: '0.003410' },
    latencyMs: 1830,
    error: null,
  }
}

/**
 * The three verdicts the screen has to be able to draw. Matched on the demo rows above by their
 * own fields, so a hand-typed row still gets a plausible answer.
 */
function verdictFor(item: PlaygroundValidateInput['items'][number], index: number): PlaygroundValidationRow {
  const base = {
    index,
    sentence: item.sentence,
    errorSpan: item.error_span,
    correction: item.correction,
    errorType: item.error_type ?? 'article',
    errorTypeDefaulted: item.error_type === undefined,
  }

  let gate: ValidationGate = 'kept'
  let reason = 'прошёл все проверки.'
  if (item.error_span.trim() !== '' && !item.sentence.toLowerCase().includes(item.error_span.toLowerCase())) {
    gate = 'span_not_found'
    reason = 'error_span пуст или не встречается в своём же предложении — подчёркивать нечего.'
  } else if (item.error_span.trim().toLowerCase() === item.correction.trim().toLowerCase()) {
    gate = 'no_op_correction'
    reason = 'correction ничего не исправляет: span и correction совпадают.'
  }

  return { ...base, verdict: gate === 'kept' ? 'KEEP' : 'REJECT', gate, reason }
}

export function mockPlaygroundValidate(input: PlaygroundValidateInput): PlaygroundValidation {
  const items = input.items.map(verdictFor)

  return {
    items,
    kept: items.filter((i) => i.verdict === 'KEEP').length,
    total: items.length,
    source: input.termId ? 'term' : 'manual',
    termId: input.termId ?? null,
    termText: input.termId ? 'withdraw money' : (input.manual?.term_text ?? ''),
    exampleSentence: input.termId
      ? 'I would like to withdraw money from my account.'
      : (input.manual?.example_text ?? null),
    existingCount: input.termId ? 2 : 0,
    suppressedCount: input.termId ? 1 : 0,
    matchedTermId: null,
    persisted: false,
  }
}
