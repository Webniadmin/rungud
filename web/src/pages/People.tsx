import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { api, qs } from '@/api/client'
import type { Paged, PersonRow } from '@/api/types'
import { Empty, ErrorNote, Loading, MembershipBadge, PageHead, Tip } from '@/components/ui'
import { Pager } from '@/components/Pager'
import { SearchBox } from '@/components/SearchBox'

export default function People() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params, setParams] = useSearchParams()
  const search = params.get('search') ?? ''
  const page = Number(params.get('page') ?? 1)

  const q = useQuery({
    queryKey: ['people', search, page],
    queryFn: () => api<Paged<PersonRow>>(`/people${qs({ search, page })}`),
    placeholderData: keepPreviousData,
  })

  return (
    <div className="page">
      <PageHead title={t('common.people')} sub={q.data ? t('people.count', { count: q.data.total }) : null} />
      <div className="filters">
        <SearchBox key={search} value={search} placeholder={t('people.searchPlaceholder')} onSubmit={(v) => setParams(v ? { search: v } : {})} />
      </div>
      {q.isLoading ? <Loading /> : null}
      {q.error ? <ErrorNote error={q.error} /> : null}
      {q.data && q.data.items.length === 0 ? <Empty>{search ? t('people.noneFound', { search }) : t('people.none')}</Empty> : null}
      {q.data && q.data.items.length > 0 ? (
        <>
          <div className="tablewrap panel" style={{ padding: 0 }}>
            <table>
              <thead>
                <tr>
                  <th>{t('common.name')}</th>
                  <th>{t('common.email')}</th>
                  <th>{t('common.classroom')}<Tip k="classroom" /></th>
                  <th className="r">{t('people.orders')}</th>
                </tr>
              </thead>
              <tbody>
                {q.data.items.map((p) => (
                  <tr key={p.id} className="rowlink" onClick={() => navigate(`/people/${p.id}`)}>
                    <td className="nm">{p.name}</td>
                    <td className="mut">{p.email}</td>
                    <td><MembershipBadge status={p.membership} /></td>
                    <td className="r num">{p.orders}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pager page={q.data.page} pages={q.data.pages} onPage={(n) => setParams({ ...(search ? { search } : {}), page: String(n) })} />
        </>
      ) : null}
    </div>
  )
}
