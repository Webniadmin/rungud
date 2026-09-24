import { useEffect, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ApiError } from '@/api/client'
import type { MembershipStatus } from '@/api/types'
import { formatDate, formatMoney } from '@/lib/format'
import { useAuth } from '@/auth/AuthProvider'

export type Tone = 'ok' | 'warn' | 'bad' | 'neu'

/** Status is always colour + text, never colour alone. */
export function Badge({ tone, children }: { tone: Tone; children: ReactNode }) {
  return <span className={`b ${tone}`}>{children}</span>
}

/** The prototype's "?" — click or hover shows the explanation. Positioned like the prototype (fixed bubble). */
export function Tip({ k, side }: { k: string; side?: 'left' }) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLSpanElement>(null)
  const [pos, setPos] = useState<{ left: number; top: number } | null>(null)
  useEffect(() => {
    if (!open || !ref.current) return
    const r = ref.current.getBoundingClientRect()
    const left = side === 'left' ? Math.max(8, r.right - 268) : Math.min(window.innerWidth - 276, r.left)
    setPos({ left, top: r.bottom + 6 })
  }, [open, side])
  return (
    <span
      ref={ref}
      className={`tip${open ? ' open' : ''}`}
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
    >
      <button type="button" aria-label="?" onClick={() => setOpen((o) => !o)}>
        ?
      </button>
      <span className="bub" role="tooltip" style={pos ?? undefined}>
        {t(`tips.${k}`)}
      </span>
    </span>
  )
}

export function Money({ amount, currency }: { amount: string | null | undefined; currency?: string | null }) {
  const { i18n } = useTranslation()
  if (amount === null || amount === undefined) return <>—</>
  return <span className="num" style={{ whiteSpace: 'nowrap' }}>{formatMoney(amount, currency ?? 'EUR', i18n.language === 'de' ? 'de' : 'en')}</span>
}

export function DateText({ value }: { value: string | null | undefined }) {
  if (!value) return <>—</>
  return <span className="num">{formatDate(value)}</span>
}

export function MembershipBadge({ status, until }: { status: MembershipStatus; until?: string | null }) {
  const { t } = useTranslation()
  const date = until ? ` ${formatDate(until)}` : ''
  switch (status) {
    case 'active':
      return <Badge tone="ok">{t('membership.active')}{until ? ` · ${t('membership.renews')}${date}` : ''}</Badge>
    case 'ending':
      return <Badge tone="warn">{t('membership.ending')}{date}</Badge>
    case 'free_access':
      return <Badge tone="warn">{t('membership.free_access')}{date}</Badge>
    default:
      return <Badge tone="neu">{t('common.none')}</Badge>
  }
}

export function PageHead({ title, sub, right }: { title: ReactNode; sub?: ReactNode; right?: ReactNode }) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
      <div>
        <h1 className="h1">{title}</h1>
        {sub ? <div className="sub">{sub}</div> : null}
      </div>
      {right}
    </div>
  )
}

/** Robert's banner on every screen that has anything to change. */
export function ReadOnlyBanner() {
  const { t } = useTranslation()
  const { me } = useAuth()
  if (me?.role !== 'owner') return null
  return <div className="ro-banner" style={{ marginTop: 18 }}>{t('common.viewOnly')}</div>
}

export function Loading() {
  const { t } = useTranslation()
  return <div className="empty">{t('state.loading')}</div>
}

export function ErrorNote({ error }: { error: unknown }) {
  const { t } = useTranslation()
  const code = error instanceof ApiError ? error.code : 'unknown'
  const key = `errors.${code}`
  const text = t(key, { defaultValue: '' }) || t('errors.unknown')
  return <div className="note bad" style={{ marginTop: 20 }}>{text}</div>
}

export function Empty({ children }: { children: ReactNode }) {
  return <div className="empty">{children}</div>
}

export function Tabs<T extends string>({ value, onChange, tabs }: { value: T; onChange: (v: T) => void; tabs: { id: T; label: ReactNode }[] }) {
  return (
    <div className="tabs" role="tablist">
      {tabs.map((tab) => (
        <button key={tab.id} type="button" role="tab" aria-selected={value === tab.id} className={value === tab.id ? 'on' : ''} onClick={() => onChange(tab.id)}>
          {tab.label}
        </button>
      ))}
    </div>
  )
}

/** Woo order status → badge. */
export function OrderStatus({ status }: { status: string }) {
  const { t } = useTranslation()
  const tone: Tone = ['completed', 'processing'].includes(status)
    ? 'ok'
    : ['pending', 'on-hold'].includes(status)
      ? 'warn'
      : ['cancelled', 'failed', 'refunded'].includes(status)
        ? 'bad'
        : 'neu'
  return <Badge tone={tone}>{t(`orderStatus.${status}`, { defaultValue: t('orderStatus.other') })}</Badge>
}
