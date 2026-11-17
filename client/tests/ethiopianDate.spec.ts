import { describe, it, expect } from 'vitest'
import {
  gregorianToEthiopian,
  ethiopianToGregorian,
  parseAndConvertEthiopianToGregorian,
} from '../src/utils/ethiopianDate'

describe('Bidirectional Ethiopian Calendar Conversion', () => {
  it('converts Reference Letter 2 date correctly (Gregorian to Ethiopian)', () => {
    // 2026-09-28 -> 18/01/2019 ዓ.ም
    const res = gregorianToEthiopian('2026-09-28')
    expect(res.formatted).toBe('18/01/2019 ዓ.ም')
    expect(res.year).toBe(2019)
    expect(res.month).toBe(1)
    expect(res.day).toBe(18)
  })

  it('converts Reference Letter 1 date correctly (Gregorian to Ethiopian)', () => {
    // 2026-08-26 -> 20/12/2018 ዓ.ም
    const res = gregorianToEthiopian('2026-08-26')
    expect(res.formatted).toBe('20/12/2018 ዓ.ም')
    expect(res.year).toBe(2018)
    expect(res.month).toBe(12)
    expect(res.day).toBe(20)
  })

  it('converts Reference Letter 1 date correctly (Ethiopian to Gregorian)', () => {
    // 20/12/2018 -> 2026-08-26
    const res = ethiopianToGregorian(2018, 12, 20)
    expect(res.formatted).toBe('2026-08-26')
    expect(res.year).toBe(2026)
    expect(res.month).toBe(8)
    expect(res.day).toBe(26)
  })

  it('converts Reference Letter 2 date correctly (Ethiopian to Gregorian)', () => {
    // 18/01/2019 -> 2026-09-28
    const res = ethiopianToGregorian(2019, 1, 18)
    expect(res.formatted).toBe('2026-09-28')
    expect(res.year).toBe(2026)
    expect(res.month).toBe(9)
    expect(res.day).toBe(28)
  })

  it('parses formatted Ethiopian date strings to Gregorian', () => {
    expect(parseAndConvertEthiopianToGregorian('18/01/2019 ዓ.ም')).toBe('2026-09-28')
    expect(parseAndConvertEthiopianToGregorian('20/12/2018 9.9')).toBe('2026-08-26')
    expect(parseAndConvertEthiopianToGregorian('20/12/2018')).toBe('2026-08-26')
    expect(parseAndConvertEthiopianToGregorian(null)).toBeNull()
  })

  it('handles Pagume leap year and throws on invalid day', () => {
    // 2015 E.C. leap year -> Pagume 6 is 2023-09-11
    const p6 = ethiopianToGregorian(2015, 13, 6)
    expect(p6.formatted).toBe('2023-09-11')

    // 2016 E.C. non-leap year -> Pagume 6 throws error
    expect(() => ethiopianToGregorian(2016, 13, 6)).toThrow()
  })
})
