/**
 * Exact Bidirectional Gregorian <-> Ethiopian Calendar Date Conversion Utility.
 * Matches App\Services\EthiopianDateService mathematically.
 */

export interface EthiopianDate {
  year: number
  month: number
  day: number
  formatted: string
  formattedLong?: string
}

export interface GregorianDate {
  year: number
  month: number
  day: number
  formatted: string
}

const ETHIOPIC_MONTHS: Record<number, string> = {
  1: 'መስከረም',
  2: 'ጥቅምት',
  3: 'ህዳር',
  4: 'ታህሳስ',
  5: 'ጥር',
  6: 'የካቲት',
  7: 'መጋቢት',
  8: 'ሚያዝያ',
  9: 'ግንቦት',
  10: 'ሰኔ',
  11: 'ሐምሌ',
  12: 'ነሐሴ',
  13: 'ጳጉሜ',
}

/**
 * Convert Gregorian date to Ethiopian calendar.
 */
export function gregorianToEthiopian(date: Date | string): EthiopianDate {
  const d =
    typeof date === 'string'
      ? new Date(date.includes('T') ? date : date + 'T00:00:00Z')
      : date
  const gYear = d.getUTCFullYear()
  const gMonth = d.getUTCMonth() + 1 // 1-12
  const gDay = d.getUTCDate()

  // Julian day number from Gregorian date
  const a = Math.floor((14 - gMonth) / 12)
  const y = gYear + 4800 - a
  const m = gMonth + 12 * a - 3
  const jdn =
    gDay +
    Math.floor((153 * m + 2) / 5) +
    365 * y +
    Math.floor(y / 4) -
    Math.floor(y / 100) +
    Math.floor(y / 400) -
    32045

  // JDN of Ethiopian epoch (1 Mäskäräm 1 E.C. = Julian Day 1723856)
  const r = (jdn - 1723856) % 1461
  const n = (r % 365) + 365 * Math.floor(r / 1460)

  const ethYear =
    4 * Math.floor((jdn - 1723856) / 1461) +
    Math.floor(r / 365) -
    Math.floor(r / 1460)
  const ethMonth = Math.floor(n / 30) + 1
  const ethDay = (n % 30) + 1

  const dd = String(ethDay).padStart(2, '0')
  const mm = String(ethMonth).padStart(2, '0')
  const yyyy = String(ethYear)
  const monthName = ETHIOPIC_MONTHS[ethMonth] || ''

  return {
    year: ethYear,
    month: ethMonth,
    day: ethDay,
    formatted: `${dd}/${mm}/${yyyy} ዓ.ም`,
    formattedLong: `${monthName} ${ethDay} ቀን ${ethYear} ዓ.ም`,
  }
}

/**
 * Convert Ethiopian date to Gregorian date.
 */
export function ethiopianToGregorian(
  ethYear: number,
  ethMonth: number,
  ethDay: number
): GregorianDate {
  if (ethMonth < 1 || ethMonth > 13) {
    throw new Error(`Invalid Ethiopian month: ${ethMonth}`)
  }
  const maxDays = ethMonth === 13 ? (ethYear % 4 === 3 ? 6 : 5) : 30
  if (ethDay < 1 || ethDay > maxDays) {
    throw new Error(`Invalid Ethiopian day: ${ethDay} for month ${ethMonth}`)
  }

  // Exact bijection formula to Julian Day Number
  const jdn =
    1723856 +
    365 * ethYear +
    Math.floor(ethYear / 4) +
    30 * (ethMonth - 1) +
    ethDay -
    1

  // Fliegel-Van Flandern algorithm: JDN to Gregorian Date
  const l = jdn + 68569
  const n = Math.floor((4 * l) / 146097)
  const l1 = l - Math.floor((146097 * n + 3) / 4)
  const i = Math.floor((4000 * (l1 + 1)) / 1461001)
  const l2 = l1 - Math.floor((1461 * i) / 4) + 31
  const j = Math.floor((80 * l2) / 2447)
  const day = l2 - Math.floor((2447 * j) / 80)
  const l3 = Math.floor(j / 11)
  const month = j + 2 - 12 * l3
  const year = 100 * (n - 49) + i + l3

  const mm = String(month).padStart(2, '0')
  const dd = String(day).padStart(2, '0')

  return {
    year,
    month,
    day,
    formatted: `${year}-${mm}-${dd}`,
  }
}

/**
 * Parse an Ethiopian date string and return Gregorian YYYY-MM-DD.
 */
export function parseAndConvertEthiopianToGregorian(str?: string | null): string | null {
  if (!str) return null
  const cleaned = str.replace(/(ዓ\.?ም\.?|[0-9]\.[0-9]|E\.?C\.?)/g, '').trim()

  const isoMatch = cleaned.match(/^(\d{4})[\/\.-](\d{1,2})[\/\.-](\d{1,2})$/)
  if (isoMatch) {
    const year = parseInt(isoMatch[1], 10)
    const month = parseInt(isoMatch[2], 10)
    const day = parseInt(isoMatch[3], 10)
    try {
      return ethiopianToGregorian(year, month, day).formatted
    } catch {
      return null
    }
  }

  const match = cleaned.match(/^(\d{1,2})[\/\.-](\d{1,2})[\/\.-](\d{4})$/)
  if (!match) return null

  const day = parseInt(match[1], 10)
  const month = parseInt(match[2], 10)
  const year = parseInt(match[3], 10)

  try {
    return ethiopianToGregorian(year, month, day).formatted
  } catch {
    return null
  }
}
