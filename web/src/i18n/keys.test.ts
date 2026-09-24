import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import en from './locales/en.json'
import de from './locales/de.json'

type Dict = { [k: string]: string | Dict }

function has(dict: Dict, key: string): boolean {
  const parts = key.split('.')
  let node: string | Dict | undefined = dict
  for (const p of parts) {
    if (typeof node !== 'object' || node === null) return false
    node = node[p]
  }
  if (typeof node === 'string') return true
  // plural forms
  const parent = parts.slice(0, -1).reduce<Dict | undefined>((o, p) => (o?.[p] as Dict | undefined), dict)
  const last = parts[parts.length - 1]
  return Boolean(parent && typeof parent[`${last}_one`] === 'string' && typeof parent[`${last}_other`] === 'string')
}

function sources(dir: string): string[] {
  return readdirSync(dir).flatMap((f: string) => {
    const p = join(dir, f)
    if (statSync(p).isDirectory()) return sources(p)
    return /\.tsx?$/.test(f) && !/\.test\.tsx?$/.test(f) ? [p] : []
  })
}

describe('every literal translation key exists (CLAUDE.md rule 7)', () => {
  const root = join(import.meta.dirname, '..')
  const keys = new Set<string>()
  for (const file of sources(root)) {
    const text = readFileSync(file, 'utf8')
    for (const m of text.matchAll(/\bt\(\s*'([a-zA-Z0-9_.-]+)'/g)) keys.add(m[1])
    for (const m of text.matchAll(/(?:titleKey|labelKey)=["']([a-zA-Z0-9_.-]+)["']/g)) keys.add(m[1])
  }

  it('found keys to check', () => expect(keys.size).toBeGreaterThan(50))
  for (const key of keys) {
    it(key, () => {
      expect(has(en as Dict, key), `en: ${key}`).toBe(true)
      expect(has(de as Dict, key), `de: ${key}`).toBe(true)
    })
  }
})
