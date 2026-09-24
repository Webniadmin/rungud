import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { api, qs } from '@/api/client'
import type { Order, Paged } from '@/api/types'
import { DateText, Empty, ErrorNote, Loading, Money, OrderStatus, PageHead, Tabs } from '@/components/ui'
import { Pager } from '@/components/Pager'
import { SearchBox } from '@/components/SearchBox'

type Tab = 'woo' | 'cms'
const STATUSES = ['any', 'processing', 'completed', 'on-hold', 'pending', 'refunded', 'cancelled'] as const

export default function Invoices() {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab) || 'woo'
  return (
    <div className="page">
      <PageHead title={t('common.invoices')} sub={t('invoices.sub')} />
      <Tabs<Tab>
        value={tab}
        onChange={(v) => setParams(v === 'woo' ? {} : { tab: v })}
        tabs={[
          { id: 'woo', label: t('invoices.tabWoo') },
          { id: 'cms', label: t('invoices.tabCms') },
        ]}
      />
      {tab === 'woo' ? <WooOrders /> : <div className="note info" style={{ marginTop: 20 }}>{t('invoices.cmsLater')}</div>}
    </div>
  )
}

function WooOrders() {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const search = params.get('search') ?? ''
  const status = params.get('status') ?? 'any'
  const page = Number(params.get('page') ?? 1)
  const q = useQuery({
    queryKey: ['orders', search, status, page],
    queryFn: () => api<Paged<Order>>(`/orders${qs({ search, status, page })}`),
    placeholderData: keepPreviousData,
  })
  const set = (next: Record<string, string>) => {
    const merged = { search, status, ...next }
    setParams(Object.fromEntries(Object.entries(merged).filter(([k, v]) => v && !(k === 'status' && v === 'any') && !(k === 'page' && v === '1'))))
  }
  return (
    <>
      <div className="filters">
        {STATUSES.map((s) => (
          <button key={s} type="button" className={`chip${status === s ? ' on' : ''}`} onClick={() => set({ status: s, page: '1' })}>
            {s === 'any' ? t('common.all') : t(`orderStatus.${s}`)}
          </button>
        ))}
        <div style={{ marginLeft: 'auto' }}>
          <SearchBox key={search} value={search} width={340} placeholder={t('invoices.search')} onSubmit={(v) => set({ search: v, page: '1' })} />
        </div>
      </div>
      {q.isLoading ? <Loading /> : null}
      {q.error ? <ErrorNote error={q.error} /> : null}
      {q.data && q.data.items.length === 0 ? <Empty>{t('invoices.none')}</Empty> : null}
      {q.data && q.data.items.length > 0 ? (
        <>
          <div className="tablewrap panel" style={{ padding: 0 }}>
            <table>
              <thead>
                <tr>
                  <th>{t('common.number')}</th>
                  <th>{t('common.date')}</th>
                  <th>{t('common.recipient')}</th>
                  <th>{t('person.item')}</th>
                  <th className="r">{t('common.amount')}</th>
                  <th>{t('person.status')}</th>
                  <th>PDF</th>
                </tr>
              </thead>
              <tbody>
                {q.data.items.map((o) => (
                  <tr key={o.id}>
                    <td className="nm num">#{o.number}</td>
                    <td><DateText value={o.date} /></td>
                    <td>
                      {o.customer.user_id ? <Link className="plain" to={`/people/${o.customer.user_id}`}>{o.customer.name ?? o.customer.email}</Link> : (o.customer.name ?? o.customer.email ?? '—')}
                      {o.customer.company ? <div className="mut">{o.customer.company}</div> : null}
                    </td>
                    <td>{o.items.map((i) => (i.quantity > 1 ? `${i.name} · ${i.quantity}×` : i.name)).join(', ')}</td>
                    <td className="r">
                      <Money amount={o.total} currency={o.currency} />
                      {o.refunded && o.refunded !== '0.00' ? <div className="mut">{t('invoices.refunded')} <Money amount={o.refunded} currency={o.currency} /></div> : null}
                    </td>
                    <td><OrderStatus status={o.status} /></td>
                    <td>{o.invoice_pdf ? <a className="btn sm" href={o.invoice_pdf} target="_blank" rel="noreferrer">PDF</a> : <span className="mut">{t('person.pdfNotAvailable')}</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pager page={q.data.page} pages={q.data.pages} onPage={(n) => set({ page: String(n) })} />
        </>
      ) : null}
    </>
  )
}
