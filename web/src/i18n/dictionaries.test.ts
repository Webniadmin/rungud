import { describe, expect, it } from 'vitest'
import en from './locales/en.json'
import de from './locales/de.json'

type Dict = { [k: string]: string | Dict }

function keys(obj: Dict, prefix = ''): string[] {
  return Object.entries(obj).flatMap(([k, v]) =>
    typeof v === 'string' ? [`${prefix}${k}`] : keys(v, `${prefix}${k}.`),
  )
}

describe('dictionaries (CLAUDE.md rule 7)', () => {
  it('en and de have exactly the same keys', () => {
    expect(keys(de as Dict).sort()).toEqual(keys(en as Dict).sort())
  })
  it('no value is empty', () => {
    for (const dict of [en, de] as Dict[]) {
      for (const k of keys(dict)) {
        const v = k.split('.').reduce<string | Dict>((o, p) => (o as Dict)[p], dict)
        expect(v, k).not.toBe('')
      }
    }
  })
  it('German finance terms match the §A7 glossary', () => {
    expect(de.glossary.credit_note).toBe('Gutschrift')
    expect(de.glossary.outstanding).toBe('Offener Betrag')
    expect(de.glossary.pending_approval).toBe('Zur Freigabe')
  })
})
