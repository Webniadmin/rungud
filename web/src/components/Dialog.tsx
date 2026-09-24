import { useEffect, type FormEvent, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

/**
 * The prototype's modal. Every destructive or binding action goes through
 * it: a title, ONE sentence that states the consequence (amount, person,
 * date), the fields, and two buttons — the action and Cancel.
 */
export function Dialog({
  title,
  consequence,
  children,
  confirmLabel,
  onConfirm,
  onClose,
  busy,
  error,
  tone = 'primary',
  disabled,
}: {
  title: string
  consequence?: ReactNode
  children?: ReactNode
  confirmLabel: string
  onConfirm: () => void
  onClose: () => void
  busy?: boolean
  error?: string | null
  tone?: 'primary' | 'warn'
  disabled?: boolean
}) {
  const { t } = useTranslation()
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && !busy && onClose()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [busy, onClose])
  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!busy && !disabled) onConfirm()
  }
  return (
    <div className="modal" role="dialog" aria-modal="true" aria-label={title} onMouseDown={(e) => e.target === e.currentTarget && !busy && onClose()}>
      <form className="box" onSubmit={submit} style={{ maxWidth: 560 }}>
        <h3>{title}</h3>
        {children}
        {consequence ? <p style={{ marginTop: 14 }}>{consequence}</p> : null}
        {error ? <div className="note bad" role="alert" style={{ marginTop: 12 }}>{error}</div> : null}
        <div className="acts">
          <button type="button" className="btn" onClick={onClose} disabled={busy}>{t('common.cancel')}</button>
          <button type="submit" className={`btn ${tone}`} disabled={busy || disabled}>{busy ? t('state.working') : confirmLabel}</button>
        </div>
      </form>
    </div>
  )
}

export function Field({ label, hint, children }: { label: string; hint?: string; children: ReactNode }) {
  return (
    <div className="field" style={{ maxWidth: 'none' }}>
      <label>
        {label}
        {children}
      </label>
      {hint ? <div className="hint">{hint}</div> : null}
    </div>
  )
}
