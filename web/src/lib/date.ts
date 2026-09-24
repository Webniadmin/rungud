/**
 * A date typed as DD.MM.YYYY in every language (CLAUDE.md rule 8) — the
 * browser's native date input would show MM/DD in some locales. Emits
 * YYYY-MM-DD or '' while incomplete/invalid.
 */
export function parseDmy(text: string): string {
  const m = /^\s*(\d{1,2})\.(\d{1,2})\.(\d{4})\s*$/.exec(text)
  if (!m) return ''
  const [d, mo, y] = [Number(m[1]), Number(m[2]), Number(m[3])]
  const date = new Date(y, mo - 1, d)
  if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) return ''
  return `${y}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`
}

