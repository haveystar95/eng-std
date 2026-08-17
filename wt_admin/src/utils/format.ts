// Formatting helpers shared across the paper UI.

const RU_MONTHS = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек']

/** Exact absolute timestamp for tooltips, e.g. "9 авг 2026, 18:30". */
export function absoluteTime(iso: string | null | undefined): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${d.getDate()} ${RU_MONTHS[d.getMonth()]} ${d.getFullYear()}, ${hh}:${mm}`
}

/** Relative time in Russian, e.g. "5 мин назад", "вчера", "3 дня назад". */
export function relativeTime(iso: string | null | undefined, now: number = Date.now()): string {
  if (!iso) return '—'
  const t = new Date(iso).getTime()
  if (Number.isNaN(t)) return '—'
  const diff = now - t
  const abs = Math.abs(diff)
  const future = diff < 0
  const sec = Math.round(abs / 1000)
  const min = Math.round(sec / 60)
  const hr = Math.round(min / 60)
  const day = Math.round(hr / 24)

  if (sec < 45) return 'только что'
  if (min < 60) return withDir(future, `${min} ${plural(min, 'минуту', 'минуты', 'минут')}`)
  if (hr < 24) return withDir(future, `${hr} ${plural(hr, 'час', 'часа', 'часов')}`)
  if (day === 1) return future ? 'завтра' : 'вчера'
  if (day < 30) return withDir(future, `${day} ${plural(day, 'день', 'дня', 'дней')}`)
  const mon = Math.round(day / 30)
  if (mon < 12) return withDir(future, `${mon} ${plural(mon, 'месяц', 'месяца', 'месяцев')}`)
  const yr = Math.round(day / 365)
  return withDir(future, `${yr} ${plural(yr, 'год', 'года', 'лет')}`)
}

function withDir(future: boolean, phrase: string): string {
  return future ? `через ${phrase}` : `${phrase} назад`
}

/** Russian pluralisation for 1 / few / many. */
export function plural(n: number, one: string, few: string, many: string): string {
  const mod10 = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few
  return many
}

/** Money with 4 decimals (costs are sub-cent). */
export function money(amount: number | null | undefined): string {
  if (amount === null || amount === undefined || Number.isNaN(amount)) return '—'
  return `$${amount.toFixed(4)}`
}

/** Compact integer with thin-space grouping, e.g. "51 200". */
export function count(n: number | null | undefined): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—'
  return n.toLocaleString('ru-RU')
}

/** Percentage from a 0..1 fraction. */
export function percent(fraction: number | null | undefined): string {
  if (fraction === null || fraction === undefined || Number.isNaN(fraction)) return '—'
  return `${Math.round(fraction * 100)}%`
}
