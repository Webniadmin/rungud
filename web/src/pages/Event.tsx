import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { api } from '@/api/client'
import type { EventDetail } from '@/api/types'
import { DateText, Empty, ErrorNote, Loading, MembershipBadge, Money, OrderStatus, ReadOnlyBanner, Tabs, Tip } from '@/components/ui'
import { formatDate, formatMoney } from '@/lib/format'
import { downloadCsv } from '@/lib/csv'
import { AuditList } from './Sync'
import { BookingBadge, DateRange } from '@/components/EventBits'

type Tab = 'overview' | 'participants' | 'waiting' | 'history'

export default function Event() {
  const { t, i18n } = useTranslation()
  const { id } = useParams()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab) || 'participants'
  const q = useQuery({ queryKey: ['event', id], queryFn: () => api<EventDetail>(`/events/${id}`) })

  if (q.isLoading) return <div className="page"><Loading /></div>
  if (q.error || !q.data) return <div className="page"><ErrorNote error={q.error} /></div>
  const { event: e, participants, history } = q.data
  const lang = i18n.language === 'de' ? 'de' : 'en'

  const exportCsv = () =>
    downloadCsv(
      `${e.slug || 'event'}-participants.csv`,
      [t('common.name'), t('common.email'), t('common.billTo'), t('common.regOn'), t('common.classroom'), t('event.seats'), t('common.amount'), t('event.orderStatus')],
      participants.map((p) => [
        p.name, p.email, p.company ?? p.name, p.date ? formatDate(p.date) : '', t(`membership.${p.membership}`, { defaultValue: t('common.none') }),
        p.quantity, p.total ? formatMoney(p.total, p.currency ?? e.currency, lang) : '', t(`orderStatus.${p.order_status}`, { defaultValue: p.order_status }),
      ]),
    )

  return (
    <div className={tab === 'participants' ? 'page wide' : 'page'}>
      <Link to="/events" className="crumb">{t('event.back')}</Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start' }}>
        <div>
          <h1 className="h1">{e.title}</h1>
          <div className="sub"><DateRange e={e} />{e.venue ? ` · ${[e.venue, e.location].filter(Boolean).join(', ')}` : ''}</div>
        </div>
        {e.seats_total !== null ? (
          <div style={{ textAlign: 'right' }}>
            <div style={{ fontSize: 22 }} className="num">{e.booked} / {e.seats_total}</div>
            <div className="mut">
              {t('event.placesLeft', { count: e.seats_left ?? 0 })}
              {e.waiting ? ` · ${t('event.onWaitingList', { count: e.waiting })}` : ''}
            </div>
          </div>
        ) : null}
      </div>
      <ReadOnlyBanner />
      <Tabs<Tab>
        value={tab}
        onChange={(v) => setParams(v === 'participants' ? {} : { tab: v })}
        tabs={[
          { id: 'overview', label: t('common.overview') },
          { id: 'participants', label: t('common.participants') },
          { id: 'waiting', label: <>{t('common.waiting')}{e.waiting ? ` (${e.waiting})` : ''}</> },
          { id: 'history', label: t('common.history') },
        ]}
      />
      <div style={{ marginTop: 20 }}>
        {tab === 'overview' ? (
          <div className="grid2">
            <div className="panel">
              <h3>{t('common.dateAndTime')}</h3>
              <dl className="dl">
                <dt>{t('event.dates')}</dt><dd><DateRange e={e} /></dd>
                <dt>{t('event.times')}</dt><dd>{[e.time, e.time_end].filter(Boolean).join(' – ') || '—'}</dd>
                <dt>{t('common.location')}</dt><dd>{[e.venue, e.location].filter(Boolean).join(', ') || '—'}</dd>
                <dt>{t('common.capacity')}</dt><dd className="num">{e.seats_total !== null ? `${e.booked} / ${e.seats_total} — ${t('event.placesLeft', { count: e.seats_left ?? 0 })}` : '—'}</dd>
                <dt>{t('person.status')}</dt><dd><BookingBadge e={e} /></dd>
              </dl>
            </div>
            <div className="panel">
              <h3>{t('common.price')}</h3>
              <dl className="dl">
                <dt>{t('event.fullPrice')}</dt><dd><Money amount={e.price} currency={e.currency} /></dd>
                <dt>{t('events.memberPrice')}</dt><dd><Money amount={e.member_price} currency={e.currency} /></dd>
              </dl>
              <div className="hint" style={{ marginTop: 12 }}>{t('event.priceOwner')}</div>
              {e.url ? <a className="btn sm" style={{ marginTop: 14, display: 'inline-block' }} href={e.url} target="_blank" rel="noreferrer">{t('event.onWebsite')}</a> : null}
            </div>
          </div>
        ) : null}

        {tab === 'participants' ? (
          <>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 12 }}>
              <div className="mut">{t('event.orderCount', { count: participants.length })}</div>
              <div className="btnrow">
                <button type="button" className="btn sm" onClick={exportCsv} disabled={participants.length === 0}>{t('common.export')}</button>
                <button type="button" className="btn sm" onClick={() => window.print()}>{t('common.print')}</button>
              </div>
            </div>
            <div className="note info" style={{ marginBottom: 14 }}>{t('event.perOrderNote')}</div>
            {q.data.participants_error ? <div className="note bad">{t('event.participantsError')}</div> : null}
            {participants.length === 0 ? <Empty>{t('event.noParticipants')}</Empty> : (
              <div className="tablewrap panel" style={{ padding: 0 }}>
                <table>
                  <thead>
                    <tr>
                      <th>{t('common.name')}</th>
                      <th>{t('common.billTo')}</th>
                      <th>{t('common.regOn')}</th>
                      <th>{t('common.classroom')}<Tip k="classroom" /></th>
                      <th className="r">{t('event.seats')}</th>
                      <th className="r">{t('common.amount')}</th>
                      <th>{t('event.orderStatus')}</th>
                      <th>{t('event.health')}<Tip k="health" side="left" /></th>
                      <th>{t('common.certificate')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {participants.map((p) => (
                      <tr key={p.order_id}>
                        <td>
                          <div className="nm">{p.user_id ? <Link className="plain" to={`/people/${p.user_id}`}>{p.name}</Link> : p.name}</div>
                          <div className="mut">{p.email}</div>
                        </td>
                        <td className="bill">{p.company ?? p.name}<div className="mut num">#{p.order_number}</div></td>
                        <td><DateText value={p.date} /></td>
                        <td><MembershipBadge status={p.membership} /></td>
                        <td className="r num">{p.quantity}</td>
                        <td className="r"><Money amount={p.total} currency={p.currency ?? e.currency} /></td>
                        <td><OrderStatus status={p.order_status} /></td>
                        <td className="mut">{t('event.notRecorded')}</td>
                        <td className="mut">—</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </>
        ) : null}

        {tab === 'waiting' ? (
          <>
            <div className="note info">{t('event.waitingInfo')}</div>
            <div className="panel" style={{ marginTop: 16 }}>
              {e.waiting ? t('event.waitingCount', { count: e.waiting }) : t('event.waitingNone')}
              <div className="hint">{t('event.waitingNamesMissing')}</div>
            </div>
          </>
        ) : null}

        {tab === 'history' ? <AuditList rows={history} empty={t('event.noHistory')} /> : null}
      </div>
    </div>
  )
}
