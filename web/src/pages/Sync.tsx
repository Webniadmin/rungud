import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useCommand, useErrorText, useRequestId } from '@/api/commands'
import { useCan } from '@/auth/AuthProvider'
import { Dialog } from '@/components/Dialog'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { api, qs } from '@/api/client'
import type { AuditPage, AuditRow } from '@/api/types'
import { Badge, Empty, ErrorNote, Loading, PageHead, Tabs, Tip } from '@/components/ui'
import { Pager } from '@/components/Pager'
import { formatDateTime } from '@/lib/format'

type Tab = 'log' | 'owners'

function RetryDialog({ row, onClose }: { row: AuditRow; onClose: () => void }) {
  const { t, i18n } = useTranslation()
  const err = useErrorText()
  const rid = useRequestId()
  const cmd = useCommand([['audit'], ['today']])
  const summary = (i18n.language === 'de' ? row.summary_de : row.summary_en).replace(/^(Not done|Nicht ausgeführt): /, '')
  return (
    <Dialog
      title={t('sync.retryTitle')}
      confirmLabel={t('sync.retry')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/audit/${row.id}/retry`, body: { request_id: rid.id } }, { onSuccess: onClose, onError: rid.renew })}
      consequence={t('sync.retryConsequence', { summary })}
    />
  )
}

export function AuditList({ rows, empty }: { rows: AuditRow[]; empty: string }) {
  const { t, i18n } = useTranslation()
  const can = useCan()
  const [retry, setRetry] = useState<AuditRow | null>(null)
  if (rows.length === 0) return <Empty>{empty}</Empty>
  return (
    <div className="tablewrap panel" style={{ padding: 0 }}>
      <table>
        <thead>
          <tr>
            <th>{t('sync.when')}</th>
            <th>{t('sync.who')}</th>
            <th>{t('sync.what')}</th>
            <th>{t('person.status')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.id} style={r.open ? { background: 'var(--bad-bg)' } : undefined}>
              <td className="num" style={{ whiteSpace: 'nowrap' }}>{formatDateTime(`${r.created_at.replace(' ', 'T')}Z`)}</td>
              <td>{r.actor_label === 'system' ? t('sync.system') : r.actor_label}</td>
              <td>
                <div>{i18n.language === 'de' ? r.summary_de : r.summary_en}</div>
                {r.error ? <div className="mut">{r.error}</div> : null}
              </td>
              <td>
                {r.status === 'ok' ? <Badge tone="ok">{t('sync.ok')}</Badge> : r.status === 'refused' ? <Badge tone="warn">{t('sync.refused')}</Badge> : r.open ? <Badge tone="bad">{t('sync.failed')}</Badge> : <Badge tone="neu">{t('sync.failedRetried')}</Badge>}
                {r.open && r.retryable && can('write') ? <div style={{ marginTop: 6 }}><button type="button" className="btn sm primary" onClick={() => setRetry(r)}>{t('sync.retry')}</button></div> : null}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {retry ? <RetryDialog row={retry} onClose={() => setRetry(null)} /> : null}
    </div>
  )
}

/** v2.2 ownership map (docs/website-integration.md §1). Row keys → dictionary. */
const OWNERS: [key: string, owner: 'site' | 'cms' | 'both'][] = [
  ['people', 'site'], ['events', 'site'], ['purchases', 'site'], ['attendees', 'both'], ['memberships', 'site'],
  ['freeAccess', 'site'], ['licences', 'both'], ['codes', 'site'], ['certificates', 'cms'], ['handInvoices', 'cms'], ['audit', 'cms'],
]

export default function Sync() {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab) || 'log'
  const page = Number(params.get('page') ?? 1)
  const q = useQuery({ queryKey: ['audit', page], queryFn: () => api<AuditPage>(`/audit${qs({ page })}`), placeholderData: keepPreviousData, enabled: tab === 'log' })

  return (
    <div className="page">
      <PageHead
        title={t('nav.sync')}
        sub={t('sync.sub')}
        right={
          q.data ? (
            <div style={{ textAlign: 'right' }}>
              {q.data.open_failures ? <Badge tone="bad">{t('sync.notArrived', { count: q.data.open_failures })}</Badge> : <Badge tone="ok">{t('sync.allArrived')}</Badge>}
              <Tip k="syncfail" side="left" />
              <div className="mut" style={{ marginTop: 6 }}>{t('sync.last72', { count: q.data.last_72h })}</div>
            </div>
          ) : null
        }
      />
      <Tabs<Tab> value={tab} onChange={(v) => setParams(v === 'log' ? {} : { tab: v })} tabs={[{ id: 'log', label: t('sync.log') }, { id: 'owners', label: t('sync.owners') }]} />
      <div style={{ marginTop: 20 }}>
        {tab === 'log' ? (
          <>
            {q.isLoading ? <Loading /> : null}
            {q.error ? <ErrorNote error={q.error} /> : null}
            {q.data ? (
              <>
                <AuditList rows={q.data.items} empty={t('sync.empty')} />
                <Pager page={q.data.page} pages={q.data.pages} onPage={(n) => setParams({ page: String(n) })} />
              </>
            ) : null}
          </>
        ) : (
          <>
            <div className="tablewrap panel" style={{ padding: 0 }}>
              <table>
                <thead>
                  <tr>
                    <th>{t('sync.what')}</th>
                    <th>{t('sync.owner')}<Tip k="ownership" /></th>
                    <th>{t('sync.cmsDoes')}</th>
                  </tr>
                </thead>
                <tbody>
                  {OWNERS.map(([key, owner]) => (
                    <tr key={key}>
                      <td>{t(`owners.${key}.what`)}</td>
                      <td><Badge tone={owner === 'cms' ? 'ok' : owner === 'both' ? 'warn' : 'neu'}>{t(`sync.owner_${owner}`)}</Badge></td>
                      <td className="mut">{t(`owners.${key}.cms`)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="note info" style={{ marginTop: 16 }}>{t('sync.rule')}</div>
          </>
        )}
      </div>
    </div>
  )
}
