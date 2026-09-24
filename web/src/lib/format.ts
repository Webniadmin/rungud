/**
 * Display formatting only (CLAUDE.md rule 8). Amounts arrive from the API
 * already calculated; this file never does arithmetic on money beyond
 * splitting the given decimal string into digits.
 *
 *   EUR, en  →  €1,200.00
 *   EUR, de  →  1.200,00 €
 *   CHF, any →  CHF 1'200.00
 *   dates    →  DD.MM.YYYY, times 24h — in every language
 */

export type Lang = 'en' | 'de'
export type Currency = 'EUR' | 'CHF'

const MINUS = '−'

function splitAmount(amount: string | number): { negative: boolean; int: string; frac: string } {
  const raw = typeof amount === 'number' ? amount.toFixed(2) : amount.trim()
  const m = /^(-)?(\d+)(?:\.(\d{1,2}))?$/.exec(raw)
  if (!m) throw new Error(`Not a decimal amount: ${String(amount)}`)
  const int = m[2].replace(/^0+(?=\d)/, '')
  const frac = (m[3] ?? '').padEnd(2, '0')
  const negative = m[1] === '-' && (int !== '0' || frac !== '00')
  return { negative, int, frac }
}

function group(int: string, sep: string): string {
  return int.replace(/\B(?=(\d{3})+(?!\d))/g, sep)
}

export function formatMoney(amount: string | number, currency: Currency, lang: Lang): string {
  const { negative, int, frac } = splitAmount(amount)
  const sign = negative ? MINUS : ''
  if (currency === 'CHF') return `CHF ${sign}${group(int, "'")}.${frac}`
  if (lang === 'de') return `${sign}${group(int, '.')},${frac} €`
  return `${sign}€${group(int, ',')}.${frac}`
}

const pad = (n: number) => String(n).padStart(2, '0')

/** Accepts `YYYY-MM-DD` (calendar date, no timezone shift) or a full ISO timestamp. */
function toParts(value: string): { y: number; mo: number; d: number; h?: number; mi?: number } {
  const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (dateOnly) return { y: +dateOnly[1], mo: +dateOnly[2], d: +dateOnly[3] }
  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) throw new Error(`Not a date: ${value}`)
  return { y: dt.getFullYear(), mo: dt.getMonth() + 1, d: dt.getDate(), h: dt.getHours(), mi: dt.getMinutes() }
}

export function formatDate(value: string): string {
  const p = toParts(value)
  return `${pad(p.d)}.${pad(p.mo)}.${p.y}`
}

export function formatDateTime(value: string): string {
  const p = toParts(value)
  return `${formatDate(value)} ${pad(p.h ?? 0)}:${pad(p.mi ?? 0)}`
}
