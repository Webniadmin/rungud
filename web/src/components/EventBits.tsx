import { useTranslation } from 'react-i18next'
import type { EventSummary } from '@/api/types'
import { Badge, DateText, type Tone } from './ui'

function bookingTone(e: EventSummary): Tone {
  if (e.booking_status === 'sold-out' || e.seats_left === 0) return 'bad'
  if (e.booking_status === 'waitlist') return 'warn'
  return 'ok'
}

export function BookingBadge({ e }: { e: EventSummary }) {
  const { t } = useTranslation()
  const key = e.seats_left === 0 && !e.booking_status ? 'sold-out' : (e.booking_status ?? 'seats-left')
  return <Badge tone={bookingTone(e)}>{t(`booking.${key}`, { defaultValue: t('booking.seats-left') })}</Badge>
}

export function DateRange({ e }: { e: EventSummary }) {
  return (
    <>
      <DateText value={e.start} />
      {e.end && e.end !== e.start ? <> – <DateText value={e.end} /></> : null}
    </>
  )
}

