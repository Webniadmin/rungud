import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, useLocation } from 'react-router-dom'
import { ApiError } from '@/api/client'
import { useAuth } from '@/auth/AuthProvider'

export default function Login() {
  const { t, i18n } = useTranslation()
  const { status, login } = useAuth()
  const location = useLocation()
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  if (status === 'authenticated') {
    const from = (location.state as { from?: string } | null)?.from ?? '/today'
    return <Navigate to={from} replace />
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const me = await login(username.trim(), password)
      if (me.lang !== i18n.language && !localStorageHasLang()) void i18n.changeLanguage(me.lang)
    } catch (err) {
      const code = err instanceof ApiError ? err.code : 'unknown'
      setError(t(`errors.${code}`, { defaultValue: '' }) || t('errors.unknown'))
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="login">
      <form className="box" onSubmit={submit} noValidate>
        <div className="brand">
          inZENtive<small>{t('nav.backoffice')}</small>
        </div>
        <div className="seg" role="group" aria-label={t('common.language')}>
          {(['en', 'de'] as const).map((l) => (
            <button key={l} type="button" className={i18n.language === l ? 'on' : ''} onClick={() => void i18n.changeLanguage(l)}>
              {l.toUpperCase()}
            </button>
          ))}
        </div>
        <div className="field">
          <label htmlFor="username">{t('auth.username')}</label>
          <input id="username" autoComplete="username" value={username} onChange={(e) => setUsername(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="password">{t('auth.password')}</label>
          <input id="password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          <div className="hint">{t('auth.forgot')}</div>
        </div>
        {error ? <div className="note bad" role="alert" style={{ marginBottom: 16 }}>{error}</div> : null}
        <button type="submit" className="btn primary" disabled={busy || !username || !password}>
          {busy ? t('auth.signingIn') : t('auth.signIn')}
        </button>
      </form>
    </div>
  )
}

function localStorageHasLang(): boolean {
  try {
    return localStorage.getItem('rungud.lang') !== null
  } catch {
    return false
  }
}
