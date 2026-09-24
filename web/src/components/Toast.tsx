import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

interface Pending {
  id: number
  message: string
  undoable: boolean
  error?: string
}

interface ToastApi {
  /** Shows the message with Undo; `run` happens only if nobody presses Undo within `ms`. */
  withUndo: (message: string, run: () => Promise<unknown>, ms?: number) => void
  notify: (message: string) => void
}

const Ctx = createContext<ToastApi | null>(null)

export function ToastProvider({ children, errorText }: { children: ReactNode; errorText: (e: unknown) => string }) {
  const { t } = useTranslation()
  const [toast, setToast] = useState<Pending | null>(null)
  const timer = useRef<number | null>(null)
  const seq = useRef(0)

  const clear = () => {
    if (timer.current) window.clearTimeout(timer.current)
    timer.current = null
  }

  const withUndo = useCallback((message: string, run: () => Promise<unknown>, ms = 8000) => {
    clear()
    const id = ++seq.current
    setToast({ id, message, undoable: true })
    timer.current = window.setTimeout(() => {
      timer.current = null
      setToast((cur) => (cur?.id === id ? { ...cur, undoable: false } : cur))
      run()
        .then(() => setToast((cur) => (cur?.id === id ? null : cur)))
        .catch((e) => setToast((cur) => (cur?.id === id ? { ...cur, error: errorText(e) } : cur)))
    }, ms)
  }, [errorText])

  const notify = useCallback((message: string) => {
    clear()
    const id = ++seq.current
    setToast({ id, message, undoable: false })
    timer.current = window.setTimeout(() => setToast((cur) => (cur?.id === id ? null : cur)), 5000)
  }, [])

  useEffect(() => clear, [])

  return (
    <Ctx.Provider value={{ withUndo, notify }}>
      {children}
      {toast ? (
        <div className="toast" role="status">
          <span>{toast.error ?? toast.message}</span>
          {!toast.error && toast.undoable ? (
            <button type="button" onClick={() => { clear(); setToast(null) }}>{t('common.undo')}</button>
          ) : (
            <button type="button" onClick={() => setToast(null)}>{t('common.close')}</button>
          )}
        </div>
      ) : null}
    </Ctx.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export function useToast(): ToastApi {
  const ctx = useContext(Ctx)
  if (!ctx) throw new Error('useToast outside ToastProvider')
  return ctx
}
