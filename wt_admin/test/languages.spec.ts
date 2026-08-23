import { describe, expect, it } from 'vitest'
import { LANGUAGES, LANGUAGE_CODES, langLabel } from '@/utils/languages'

// The list `docs/research/language-capability-matrix.md` names, split the way the matrix splits it.
// Written out rather than derived from LANGUAGES: a test that reads its expectation out of the
// thing under test proves nothing.
const TAUGHT = ['en', 'ro', 'pl', 'de', 'es', 'it', 'fr']
const REFERENCE_ONLY = ['zh', 'ja']
const SUPPORT = ['ru', 'uk', 'en']

describe('language catalogue', () => {
  it('covers every language the capability matrix names', () => {
    for (const code of [...TAUGHT, ...REFERENCE_ONLY, ...SUPPORT]) {
      expect(LANGUAGE_CODES, `${code} is in the matrix but not in the catalogue`).toContain(code)
    }
  })

  it('has no duplicate codes', () => {
    expect(new Set(LANGUAGE_CODES).size).toBe(LANGUAGE_CODES.length)
  })

  it('fills every column of every row', () => {
    for (const [code, entry] of Object.entries(LANGUAGES)) {
      expect(code).toMatch(/^[a-z]{2}$/)
      expect(entry.endonym.trim()).not.toBe('')
      expect(entry.nameRu.trim()).not.toBe('')
      expect(entry.nameEn.trim()).not.toBe('')
      expect(entry.flag.trim()).not.toBe('')
    }
  })

  it('names Romanian as the LANGUAGE, not as the country', () => {
    // `România` is the country; the endonym of the language is `Română` (QA-OBS-16).
    expect(LANGUAGES.ro.endonym).toBe('Română')
  })
})

describe('langLabel', () => {
  it('shows the endonym', () => {
    expect(langLabel('ru')).toBe('Русский')
    expect(langLabel('ro')).toBe('Română')
    expect(langLabel('pl')).toBe('Polski')
  })

  it('shows an unknown code rather than inventing a name for it', () => {
    // `lang` comes from the server: a language enabled after this panel was deployed must read as a
    // visible `SW`, not as a blank cell.
    expect(langLabel('sw')).toBe('SW')
  })
})
