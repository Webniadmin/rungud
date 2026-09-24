import { describe, expect, it } from 'vitest'
import { formatDate, formatDateTime, formatMoney } from './format'

describe('formatMoney', () => {
  it('formats EUR per language', () => {
    expect(formatMoney('1200.00', 'EUR', 'en')).toBe('€1,200.00')
    expect(formatMoney('1200.00', 'EUR', 'de')).toBe('1.200,00 €')
  })
  it('formats CHF with apostrophes in both languages', () => {
    expect(formatMoney('1200', 'CHF', 'en')).toBe("CHF 1'200.00")
    expect(formatMoney('1234567.5', 'CHF', 'de')).toBe("CHF 1'234'567.50")
  })
  it('uses a real minus sign and never shows −0', () => {
    expect(formatMoney('-300.00', 'EUR', 'en')).toBe('−€300.00')
    expect(formatMoney('-300.00', 'EUR', 'de')).toBe('−300,00 €')
    expect(formatMoney('-0.00', 'EUR', 'en')).toBe('€0.00')
  })
  it('handles small amounts and numbers', () => {
    expect(formatMoney('0.5', 'EUR', 'en')).toBe('€0.50')
    expect(formatMoney(408.17, 'EUR', 'de')).toBe('408,17 €')
  })
  it('refuses non-decimal input rather than guessing', () => {
    expect(() => formatMoney('1,200.00', 'EUR', 'en')).toThrow()
  })
})

describe('formatDate', () => {
  it('is DD.MM.YYYY and never shifts a calendar date', () => {
    expect(formatDate('2026-09-10')).toBe('10.09.2026')
    expect(formatDate('2026-01-02')).toBe('02.01.2026')
  })
  it('formats timestamps with 24h time', () => {
    expect(formatDateTime('2026-08-20T17:05:00')).toBe('20.08.2026 17:05')
  })
})
