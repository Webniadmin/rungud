import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { api } from '@/api/client'
import type { Today as TodayData, TodayItem } from '@/api/types'
import { ErrorNote, Loading, ReadOnlyBanner } from '@/components/ui'

const SCREEN_PATH: Record<string, string> = {
  invoices: '/invoices', members: '/members', payments: '/payments', sync: '/sync', codes: '/codes',
  event: '/events', events: '/events', certs: '/certs', people: '/people', messages: '/messages',
}

function longDate(iso: string, lang: string): string {
  const [y, m, d] = iso.split('-').map(Number)
  const date = new Date(y, m - 1, d)
  const weekday = date.toLocaleDateString(lang === 'de' ? 'de-DE' : 'en-GB', { weekday: 'long' })
  const month = date.toLocaleDateString(lang === 'de' ? 'de-DE' : 'en-GB', { month: 'long' })
  return lang === 'de' ? `${weekday}, ${d}. ${month} ${y}` : `${weekday}, ${d} ${month} ${y}`
}

function Row({ item }: { item: TodayItem }) {
  const { t, i18n } = useTranslation()
  const detail =
    item.key === 'commands_failed'
      ? String(item.params[i18n.language === 'de' ? 'summary_de' : 'summary_en'] ?? '')
      : t(`today.${item.key}.d`, { ...item.params, defaultValue: '' })
  return (
    <div className="work-row">
      <div className={`work-n ${item.tone}`}>{item.count}</div>
      <div className="work-body">
        <div className="work-t">{t(`today.${item.key}.t`, { count: item.count })}</div>
        {detail ? <div className="work-d">{detail}</div> : null}
      </div>
      <div className="work-act">
        <Link className="btn" to={SCREEN_PATH[item.target.screen] ?? '/today'}>
          {t(`today.${item.key}.b`, { defaultValue: t('common.open') })}
        </Link>
      </div>
    </div>
  )
}

export default function Today() {
  const { t, i18n } = useTranslation()
  const q = useQuery({ queryKey: ['today'], queryFn: () => api<TodayData>('/today') })
  return (
    <div className="page">
      <div>
        <h1 className="h1">{t('common.today')}</h1>
        {q.data ? <div className="sub">{longDate(q.data.date, i18n.language)}</div> : null}
      </div>
      <ReadOnlyBanner />
      {q.isLoading ? <Loading /> : null}
      {q.error ? <ErrorNote error={q.error} /> : null}
      {q.data ? (
        <div className="work">
          {q.data.items.length === 0 ? <div className="allclear">{t('common.allclear')}</div> : q.data.items.map((item) => <Row key={item.key} item={item} />)}
        </div>
      ) : null}
    </div>
  )
}
