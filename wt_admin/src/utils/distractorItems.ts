// Finding the distractor rows inside whatever JSON a model just produced.
//
// The sandbox cannot demand a shape: the whole point is that the prompt is the operator's, so the
// answer arrives as `{items: […]}` one minute and `{result: {distractors: […]}}` the next. Rather
// than make someone re-shape it by hand before validating, this looks for the first array whose
// objects carry the three fields a distractor IS — sentence, error_span, correction — at the top
// level and a few levels down.
//
// When nothing matches it says WHAT WAS MISSING rather than «не найдено»: an array of objects that
// has `sentence` and `span` is one rename away from working, and the person needs to know which
// rename.

export interface DistractorItem {
  sentence: string
  error_span: string
  correction: string
  error_type?: string
}

export interface ItemsFound {
  items: DistractorItem[]
  /** Where they were found, e.g. `result.distractors` — empty string for the root array. */
  path: string
  /** Keys that were seen on candidate objects but are not the ones needed. */
  sawKeys: string[]
  /** The required keys that were missing from the closest candidate. */
  missing: string[]
}

const REQUIRED = ['sentence', 'error_span', 'correction'] as const

/** Deep enough for `{result: {data: {distractors: […]}}}` and no deeper — a guard, not a limit. */
const MAX_DEPTH = 4

function isRecord(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v)
}

/** A row is usable when all three fields are present and are strings (empty strings included:
 *  an empty correction is a real case the validator has a gate for, and hiding it here would make
 *  the sandbox disagree with the server about what was even submitted). */
function isItem(v: unknown): v is DistractorItem {
  if (!isRecord(v)) return false
  return REQUIRED.every((k) => typeof v[k] === 'string')
}

function toItem(v: Record<string, unknown>): DistractorItem {
  return {
    sentence: String(v.sentence ?? ''),
    error_span: String(v.error_span ?? ''),
    correction: String(v.correction ?? ''),
    ...(typeof v.error_type === 'string' ? { error_type: v.error_type } : {}),
  }
}

/**
 * The first array of distractor-shaped objects, breadth-first so a top-level `items` wins over a
 * deeper one. Returns an empty `items` with a diagnosis when there is none.
 */
export function findDistractorItems(root: unknown): ItemsFound {
  const queue: { value: unknown; path: string; depth: number }[] = [{ value: root, path: '', depth: 0 }]
  let bestSaw: string[] = []
  let bestMissing: string[] = REQUIRED.slice()

  while (queue.length > 0) {
    const { value, path, depth } = queue.shift()!

    if (Array.isArray(value)) {
      const objects = value.filter(isRecord)
      if (objects.length > 0) {
        if (objects.every(isItem)) {
          return { items: objects.map(toItem), path, sawKeys: [], missing: [] }
        }
        // Not a match — but remember what it DID have, so the hint can name the rename.
        const saw = Object.keys(objects[0])
        const missing = REQUIRED.filter((k) => !saw.includes(k))
        if (missing.length < bestMissing.length) {
          bestSaw = saw
          bestMissing = missing
        }
      }
    }

    if (depth >= MAX_DEPTH) continue

    if (isRecord(value)) {
      for (const [key, child] of Object.entries(value)) {
        queue.push({ value: child, path: path === '' ? key : `${path}.${key}`, depth: depth + 1 })
      }
    } else if (Array.isArray(value)) {
      // Arrays of arrays are rare but free to walk.
      value.forEach((child, i) => queue.push({ value: child, path: `${path}[${i}]`, depth: depth + 1 }))
    }
  }

  return { items: [], path: '', sawKeys: bestSaw, missing: bestMissing }
}

/** One sentence for the screen: what to fix so the Validate button lights up. */
export function itemsHint(found: ItemsFound): string {
  if (found.items.length > 0) return ''
  if (found.sawKeys.length === 0) {
    return 'В ответе нет массива объектов с полями sentence / error_span / correction — валидировать нечего.'
  }
  return (
    `Нашёлся массив объектов с полями: ${found.sawKeys.join(', ')}. ` +
    `Не хватает: ${found.missing.join(', ')}.`
  )
}
