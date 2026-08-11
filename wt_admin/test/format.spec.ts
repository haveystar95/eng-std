import { describe, expect, it } from 'vitest'
import { absoluteTime, count, money, percent, plural, relativeTime } from '@/utils/format'

describe('money', () => {
  it('always renders 4 decimals (costs are sub-cent)', () => {
    expect(money(0.31)).toBe('$0.3100')
    expect(money(44.7781)).toBe('$44.7781')
    expect(money(0)).toBe('$0.0000')
  })
  it('renders a dash for missing values', () => {
    expect(money(null)).toBe('—')
    expect(money(undefined)).toBe('—')
  })
})

describe('plural (Russian)', () => {
  it('picks one/few/many correctly', () => {
    expect(plural(1, 'день', 'дня', 'дней')).toBe('день')
    expect(plural(3, 'день', 'дня', 'дней')).toBe('дня')
    expect(plural(5, 'день', 'дня', 'дней')).toBe('дней')
    expect(plural(11, 'день', 'дня', 'дней')).toBe('дней')
    expect(plural(21, 'день', 'дня', 'дней')).toBe('день')
  })
})

describe('relativeTime', () => {
  const now = new Date('2026-08-09T18:00:00.000Z').getTime()
  it('handles past and future', () => {
    expect(relativeTime(new Date(now - 5 * 60_000).toISOString(), now)).toBe('5 минут назад')
    expect(relativeTime(new Date(now - 86_400_000).toISOString(), now)).toBe('вчера')
    expect(relativeTime(new Date(now + 86_400_000).toISOString(), now)).toBe('завтра')
    expect(relativeTime(new Date(now - 3 * 86_400_000).toISOString(), now)).toBe('3 дня назад')
  })
  it('renders a dash for empty input', () => {
    expect(relativeTime(null, now)).toBe('—')
  })
})

describe('count & percent', () => {
  it('formats', () => {
    expect(count(0)).toBe('0')
    expect(percent(0.5)).toBe('50%')
    expect(percent(null)).toBe('—')
  })
})

describe('absoluteTime', () => {
  it('produces a readable exact stamp', () => {
    expect(absoluteTime('2026-08-09T00:00:00.000Z')).toContain('2026')
    expect(absoluteTime(null)).toBe('—')
  })
})
