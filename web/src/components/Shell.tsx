import { useState, type FormEvent, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from '@/api/client'
import type { Today } from '@/api/types'
import { useAuth } from '@/auth/AuthProvider'
import { Icon } from './Icon'
import { ToastProvider } from './Toast'
import { useErrorText } from '@/api/commands'
import type { IconName } from './icons'

type NavItem = [path: string, icon: IconName, labelKey: string, count?: number]

function Rail() {
  const { t } = useTranslation()
  const today = useQuery({ queryKey: ['today'], queryFn: () => api<Today>('/today'), staleTime: 60_000 })
  const items = today.data?.items ?? []
  const failed = items.find((i) => i.key === 'commands_failed')?.count ?? 0
  const groups: [string | null, NavItem[]][] = [
    [null, [['/today', 'today', 'common.today', items.length]]],
    ['nav.operations', [
      ['/events', 'events', 'common.events'],
      ['/people', 'people', 'common.people'],
      ['/members', 'members', 'common.members'],
      ['/certs', 'certs', 'nav.certificates'],
    ]],
    ['nav.money', [
      ['/invoices', 'invoices', 'common.invoices'],
      ['/payments', 'payments', 'common.payments'],
      ['/codes', 'codes', 'nav.codes'],
    ]],
    ['nav.reach', [
      ['/insights', 'insights', 'nav.insights'],
      ['/video', 'video', 'nav.video'],
      ['/messages', 'messages', 'nav.messages'],
    ]],
    ['nav.system', [['/sync', 'sync', 'nav.sync', failed]]],
    [null, [['/help', 'help', 'common.help']]],
  ]
  return (
    <div className="rail">
      <div className="brand">
        inZENtive<small>{t('nav.backoffice')}</small>
      </div>
      <nav className="nav">
        {groups.map(([group, navItems], gi) => (
          <div key={gi}>
            {group ? <div className="navgroup">{t(group)}</div> : null}
            {navItems.map(([path, icon, label, count]) => (
              <NavLink key={path} to={path} className={({ isActive }) => (isActive ? 'on' : '')}>
                <Icon name={icon} />
                <span>{t(label)}</span>
                {count ? <span className="count">{count}</span> : null}
              </NavLink>
            ))}
          </div>
        ))}
      </nav>
      <footer>rungud</footer>
    </div>
  )
}

function Top() {
  const { t, i18n } = useTranslation()
  const { me, logout } = useAuth()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const [menu, setMenu] = useState(false)
  const submit = (e: FormEvent) => {
    e.preventDefault()
    navigate(`/people?search=${encodeURIComponent(q.trim())}`)
  }
  const initial = (me?.name ?? '?').trim().charAt(0).toUpperCase()
  return (
    <div className="top">
      <form className="search" onSubmit={submit} role="search">
        <Icon name="search" small />
        <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t('common.search')} aria-label={t('common.search')} />
      </form>
      <div className="spacer" />
      <div className="seg" role="group" aria-label={t('common.language')}>
        {(['en', 'de'] as const).map((l) => (
          <button key={l} type="button" className={i18n.language === l ? 'on' : ''} onClick={() => void i18n.changeLanguage(l)}>
            {l.toUpperCase()}
          </button>
        ))}
      </div>
      <div className="who" style={{ position: 'relative' }}>
        <span className="av" aria-hidden="true">{initial}</span>
        <button type="button" onClick={() => setMenu((m) => !m)} aria-expanded={menu} style={{ background: 'none', border: 0, fontSize: 14.5, padding: 2 }}>
          {me?.name} ▾
        </button>
        {menu ? (
          <div className="panel" style={{ position: 'absolute', right: 0, top: 'calc(100% + 6px)', padding: '10px 12px', minWidth: 200, zIndex: 50 }}>
            <div className="mut" style={{ marginBottom: 8 }}>{t(`roles.${me?.role ?? 'owner'}`)}</div>
            <button type="button" className="btn sm" onClick={() => void logout()}>{t('auth.logout')}</button>
          </div>
        ) : null}
      </div>
    </div>
  )
}

export function Shell({ children }: { children: ReactNode }) {
  const errorText = useErrorText()
  return (
    <ToastProvider errorText={errorText}>
    <div className="app">
      <Rail />
      <div className="main">
        <Top />
        {children}
      </div>
    </div>
    </ToastProvider>
  )
}
