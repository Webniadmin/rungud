import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { api, qs } from '@/api/client'
import type { Memberships } from '@/api/types'
import { DateText, Empty, ErrorNote, Loading, MembershipBadge, PageHead, Tip } from '@/components/ui'
import { downloadCsv } from '@/lib/csv'
import { formatDate } from '@/lib/format'

const FILTERS = ['expiring', 'ending', 'active', 'free_access', 'all'] as const
type Filter = (typeof FILTERS)[number]

export default function Members() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const filter = (params.get('filter') as Filter) || 'expiring'
  const q = useQuery({ queryKey: ['memberships', filter], queryFn: () => api<Memberships>(`/memberships${qs({ filter })}`) })

  const exportCsv = () =>
    q.data &&
    downloadCsv(
      `memberships-${filter}.csv`,
      [t('common.name'), t('common.email'), t('person.plan'), t('person.status'), t('members.until'), t('members.remaining')],
      q.data.items.map((m) => [m.name, m.email, m.plan ? t(`plans.${m.plan}`, { defaultValue: m.plan }) : '', t(`membership.${m.status}`), m.until ? formatDate(m.until) : '', m.days_left ?? '']),
    )

  return (
    <div className="page">
      <PageHead
        title={t('common.members')}
        sub={t('members.sub')}
        right={<button type="button" className="btn" onClick={exportCsv} disabled={!q.data?.items.length}>{t('common.export')}</button>}
      />
      {q.data && !q.data.learndash ? <div className="note" style={{ marginTop: 18 }}>{t('members.noLearndash')}</div> : null}
      {q.data && q.data.errors > 0 ? <div className="note bad" style={{ marginTop: 18 }}>{t('members.someFailed', { count: q.data.errors })}</div> : null}
      <div className="filters">
        {FILTERS.map((f) => (
          <button key={f} type="button" className={`chip${filter === f ? ' on' : ''}`} onClick={() => setParams(f === 'expiring' ? {} : { filter: f })}>
            {t(`members.f_${f}`)}
            {q.data ? <span className="c">{q.data.counts[f]}</span> : null}
          </button>
        ))}
        <Tip k="expiring" />
      </div>
      {q.isLoading ? <Loading /> : null}
      {q.error ? <ErrorNote error={q.error} /> : null}
      {q.data && q.data.items.length === 0 ? <Empty>{t(`members.empty_${filter}`)}</Empty> : null}
      {q.data && q.data.items.length > 0 ? (
        <div className="tablewrap panel" style={{ padding: 0 }}>
          <table>
            <thead>
              <tr>
                <th>{t('common.name')}</th>
                <th>{t('person.plan')}</th>
                <th>{t('members.until')}</th>
                <th className="r">{t('members.remaining')}</th>
                <th>{t('person.status')}</th>
              </tr>
            </thead>
            <tbody>
              {q.data.items.map((m) => (
                <tr key={m.user_id} className="rowlink" onClick={() => navigate(`/people/${m.user_id}`)}>
                  <td><div className="nm">{m.name}</div><div className="mut">{m.email}</div></td>
                  <td>{m.plan ? t(`plans.${m.plan}`, { defaultValue: m.plan }) : t('membership.free_access')}</td>
                  <td><DateText value={m.until} /></td>
                  <td className="r num">{m.days_left !== null ? t('person.daysLeft', { count: m.days_left }) : '—'}</td>
                  <td><MembershipBadge status={m.status} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  )
}
