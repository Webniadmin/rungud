import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { api } from '@/api/client'
import type { Access, Documents, Licence, MembershipView, Order, Person as PersonData } from '@/api/types'
import { Badge, DateText, Empty, ErrorNote, Loading, MembershipBadge, Money, OrderStatus, Tabs, Tip, type Tone } from '@/components/ui'

type Tab = 'overview' | 'access' | 'documents'

export default function Person() {
  const { t } = useTranslation()
  const { id } = useParams()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab) || 'overview'
  const q = useQuery({ queryKey: ['person', id], queryFn: () => api<PersonData>(`/people/${id}`) })

  if (q.isLoading) return <div className="page"><Loading /></div>
  if (q.error || !q.data) return <div className="page"><ErrorNote error={q.error} /></div>
  const p = q.data
  const address = [p.address.company, p.address.street, [p.address.postal_code, p.address.city].filter(Boolean).join(' '), p.address.country].filter(Boolean).join(', ')

  return (
    <div className="page">
      <Link to="/people" className="crumb">{t('common.back')}</Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16, alignItems: 'flex-start' }}>
        <div>
          <h1 className="h1">{p.name}</h1>
          <div className="sub">{[p.email, address].filter(Boolean).join(' · ')}</div>
        </div>
        {p.membership ? <MembershipBadge status={p.membership.status} until={p.membership.until} /> : null}
      </div>
      {p.site_error ? <div className="note bad" style={{ marginTop: 18 }}>{t('person.siteError')}</div> : null}
      <Tabs<Tab>
        value={tab}
        onChange={(v) => setParams(v === 'overview' ? {} : { tab: v })}
        tabs={[
          { id: 'overview', label: t('common.overview') },
          { id: 'access', label: t('person.tabAccess') },
          { id: 'documents', label: t('person.tabDocuments') },
        ]}
      />
      <div style={{ marginTop: 22 }}>
        {tab === 'overview' ? <Overview p={p} /> : null}
        {tab === 'access' ? <AccessTab id={p.id} /> : null}
        {tab === 'documents' ? <DocumentsTab id={p.id} /> : null}
      </div>
    </div>
  )
}

function MembershipPanel({ m }: { m: MembershipView | null }) {
  const { t } = useTranslation()
  return (
    <div className="panel">
      <h3>{t('person.membership')}</h3>
      {!m ? (
        <div className="mut">{t('person.siteError')}</div>
      ) : m.status === 'none' ? (
        <div className="mut">{t('person.noMembership')}</div>
      ) : (
        <dl className="dl">
          <dt>{t('person.plan')}</dt>
          <dd>{m.plan ? t(`plans.${m.plan}`, { defaultValue: m.plan }) : m.status === 'free_access' ? t('membership.free_access') : '—'}</dd>
          <dt>{t('person.status')}</dt>
          <dd><MembershipBadge status={m.status} /></dd>
          <dt>{m.renews ? t('person.renewsOn') : t('person.accessUntil')}<Tip k="expiring" /></dt>
          <dd><DateText value={m.until} />{m.days_left !== null && !m.renews ? <span className="mut"> · {t('person.daysLeft', { count: m.days_left })}</span> : null}</dd>
          {m.free_access ? (
            <>
              <dt>{t('person.freeScope')}</dt>
              <dd>{t(`scope.${m.free_access.scope}`, { defaultValue: m.free_access.scope })}{m.free_access.reason ? ` · ${m.free_access.reason}` : ''}</dd>
            </>
          ) : null}
          {m.subscription_id ? (
            <>
              <dt>Stripe</dt>
              <dd className="mut num">{m.subscription_id}</dd>
            </>
          ) : null}
        </dl>
      )}
    </div>
  )
}

function licenceTone(l: Licence): Tone {
  if (l.status === 'verified') return l.in_force === false ? 'bad' : 'ok'
  if (l.status === 'pending') return 'warn'
  if (l.status === 'rejected') return 'bad'
  return 'neu'
}

function Licences({ list }: { list: Licence[] }) {
  const { t } = useTranslation()
  return (
    <div className="panel">
      <h3>{t('person.licences')}<Tip k="licence" /></h3>
      {list.length === 0 ? <div className="mut">{t('person.noLicences')}</div> : (
        <table>
          <tbody>
            {list.map((l, i) => (
              <tr key={`${l.program_slug}-${i}`}>
                <td>
                  <div className="nm num">{l.number || '—'}</div>
                  <div className="mut">{[l.program_title ?? l.program_slug, l.organisation].filter(Boolean).join(' · ')}</div>
                </td>
                <td><Badge tone={licenceTone(l)}>{t(`licence.${l.status === 'verified' && l.in_force === false ? 'expired' : l.status}`)}</Badge></td>
                <td className="num">{l.valid_until ? <DateText value={l.valid_until} /> : l.status === 'verified' ? t('licence.noExpiry') : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <div className="hint" style={{ marginTop: 12 }}>{t('person.licenceNote')}</div>
    </div>
  )
}

function Overview({ p }: { p: PersonData }) {
  const { t } = useTranslation()
  return (
    <>
      <div className="grid2">
        <MembershipPanel m={p.membership} />
        <div className="panel">
          <h3>{t('person.recentOrders')}</h3>
          {p.orders.length === 0 ? <div className="mut">{t('person.noOrders')}</div> : (
            <table>
              <tbody>
                {p.orders.slice(0, 5).map((o) => (
                  <tr key={o.id}>
                    <td className="nm num">#{o.number}</td>
                    <td className="mut">{o.items.map((i) => i.name).join(', ')}</td>
                    <td className="r"><Money amount={o.total} currency={o.currency} /></td>
                    <td><OrderStatus status={o.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
      <div className="sectitle">{t('person.qualifications')}</div>
      <Licences list={p.licences} />
      <div className="sectitle">{t('common.educationHistory')}</div>
      <div className="panel">
        {p.education.length === 0 ? <div className="mut">{t('person.noEducation')}</div> : null}
      </div>
    </>
  )
}

function AccessTab({ id }: { id: number }) {
  const { t } = useTranslation()
  const q = useQuery({ queryKey: ['access', id], queryFn: () => api<Access>(`/people/${id}/access`) })
  if (q.isLoading) return <Loading />
  if (q.error || !q.data) return <ErrorNote error={q.error} />
  const a = q.data
  const courses = a.site.courses ?? []
  return (
    <>
      <div className="note info">{t('person.accessInfo')}</div>
      {!a.learndash ? <div className="note" style={{ marginTop: 12 }}>{t('person.noLearndash')}</div> : null}
      <div className="grid2" style={{ marginTop: 20 }}>
        <MembershipPanel m={a.membership} />
        <Licences list={a.site.licences ?? []} />
      </div>
      <div className="sectitle">{t('person.whatTheySee')}</div>
      <div className="panel">
        {courses.length === 0 ? <div className="mut">{t('person.noCourses')}</div> : (
          <table>
            <tbody>
              {courses.map((c) => (
                <tr key={c.id}>
                  <td>{c.title}</td>
                  <td className="r num">{t('person.progress', { done: c.completed_steps, total: c.total_steps })}</td>
                  <td className="r num">{c.percentage}%</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        <div className="hint" style={{ marginTop: 12 }}>{t('person.readFromSite')}</div>
      </div>
    </>
  )
}

function DocumentsTab({ id }: { id: number }) {
  const { t } = useTranslation()
  const q = useQuery({ queryKey: ['documents', id], queryFn: () => api<Documents>(`/people/${id}/documents`) })
  if (q.isLoading) return <Loading />
  if (q.error || !q.data) return <ErrorNote error={q.error} />
  const d = q.data
  const rows: { key: string; source: string; number: string; date: string | null; item: string; amount: string | null; currency: string; status: React.ReactNode; pdf: string | null }[] = [
    ...d.woo.map((o: Order) => ({
      key: `w${o.id}`, source: 'WooCommerce', number: `#${o.number}`, date: o.date,
      item: o.items.map((i) => (i.quantity > 1 ? `${i.name} · ${i.quantity}×` : i.name)).join(', '),
      amount: o.total, currency: o.currency, status: <OrderStatus status={o.status} />, pdf: o.invoice_pdf,
    })),
    ...d.stripe.map((s) => ({
      key: `s${s.id}`, source: 'Stripe', number: s.number ?? s.id, date: s.date, item: t('person.membershipInvoice'),
      amount: s.amount, currency: s.currency,
      status: <Badge tone={s.status === 'paid' ? 'ok' : s.status === 'open' ? 'warn' : 'neu'}>{t(`stripeStatus.${s.status}`, { defaultValue: s.status })}</Badge>,
      pdf: s.pdf,
    })),
  ].sort((a, b) => String(b.date).localeCompare(String(a.date)))

  return (
    <>
      <div className="note info">{t('person.documentsInfo')}</div>
      {d.stripe_state !== 'ok' ? <div className="note" style={{ marginTop: 12 }}>{t(`person.stripe_${d.stripe_state}`)}</div> : null}
      {rows.length === 0 ? <Empty>{t('person.noDocuments')}</Empty> : (
        <div className="tablewrap panel" style={{ padding: 0, marginTop: 20 }}>
          <table>
            <thead>
              <tr>
                <th>{t('person.source')}</th>
                <th>{t('common.number')}</th>
                <th>{t('common.date')}</th>
                <th>{t('person.item')}</th>
                <th className="r">{t('common.amount')}</th>
                <th>{t('person.status')}</th>
                <th>PDF</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.key}>
                  <td>{r.source}</td>
                  <td className="nm num">{r.number}</td>
                  <td><DateText value={r.date} /></td>
                  <td>{r.item}</td>
                  <td className="r"><Money amount={r.amount} currency={r.currency} /></td>
                  <td>{r.status}</td>
                  <td>{r.pdf ? <a className="btn sm" href={r.pdf} target="_blank" rel="noreferrer">PDF</a> : <span className="mut">{t('person.pdfNotAvailable')}</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  )
}
