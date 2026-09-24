import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { api, qs } from '@/api/client'
import type { EventSummary } from '@/api/types'
import { Empty, ErrorNote, Loading, Money, PageHead } from '@/components/ui'
import { BookingBadge, DateRange } from '@/components/EventBits'

type When = 'upcoming' | 'past' | 'all'

export default function Events() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const when = (params.get('when') as When) || 'upcoming'
  const q = useQuery({ queryKey: ['events', when], queryFn: () => api<{ items: EventSummary[] }>(`/events${qs({ when })}`) })

  return (
    <div className="page">
      <PageHead title={t('common.events')} sub={q.data ? t('events.count', { count: q.data.items.length }) : null} />
      <div className="filters">
        {(['upcoming', 'past', 'all'] as When[]).map((w) => (
          <button key={w} type="button" className={`chip${when === w ? ' on' : ''}`} onClick={() => setParams(w === 'upcoming' ? {} : { when: w })}>
            {t(`events.${w}`)}
          </button>
        ))}
      </div>
      {q.isLoading ? <Loading /> : null}
      {q.error ? <ErrorNote error={q.error} /> : null}
      {q.data && q.data.items.length === 0 ? <Empty>{t('events.none')}</Empty> : null}
      {q.data && q.data.items.length > 0 ? (
        <div className="tablewrap panel" style={{ padding: 0 }}>
          <table>
            <thead>
              <tr>
                <th>{t('events.event')}</th>
                <th>{t('common.dateAndTime')}</th>
                <th>{t('common.location')}</th>
                <th>{t('events.booked')}</th>
                <th>{t('common.waiting')}</th>
                <th className="r">{t('common.price')}</th>
                <th>{t('person.status')}</th>
              </tr>
            </thead>
            <tbody>
              {q.data.items.map((e) => (
                <tr key={e.id} className="rowlink" onClick={() => navigate(`/events/${e.id}`)}>
                  <td>
                    <div className="nm">{e.title}</div>
                  </td>
                  <td>
                    <div><DateRange e={e} /></div>
                    {e.time ? <div className="mut">{[e.time, e.time_end].filter(Boolean).join(' – ')}</div> : null}
                  </td>
                  <td>
                    <div>{e.venue ?? '—'}</div>
                    {e.location ? <div className="mut">{e.location}</div> : null}
                  </td>
                  <td className="num">{e.booked !== null && e.seats_total !== null ? `${e.booked}/${e.seats_total}` : '—'}</td>
                  <td className="num">{e.waiting ? e.waiting : '—'}</td>
                  <td className="r">
                    <div><Money amount={e.price} currency={e.currency} /></div>
                    {e.member_price ? <div className="mut">{t('events.memberPrice')} <Money amount={e.member_price} currency={e.currency} /></div> : null}
                  </td>
                  <td><BookingBadge e={e} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  )
}
