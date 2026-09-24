import { describe, expect, it } from 'vitest'
import { parseDmy } from './date'

describe('parseDmy (DD.MM.YYYY only, never MM/DD)', () => {
  it('parses day.month.year', () => {
    expect(parseDmy('31.10.2026')).toBe('2026-10-31')
    expect(parseDmy(' 1.2.2027 ')).toBe('2027-02-01')
  })
  it('refuses impossible and foreign formats', () => {
    expect(parseDmy('31.02.2026')).toBe('')
    expect(parseDmy('10/31/2026')).toBe('')
    expect(parseDmy('2026-10-31')).toBe('')
    expect(parseDmy('31.10.26')).toBe('')
  })
})
