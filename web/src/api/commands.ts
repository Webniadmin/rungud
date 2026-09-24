import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { api, ApiError } from './client'

/** One id per opened dialog: a double click or a network retry runs the command once. */
export function newRequestId(): string {
  return crypto.randomUUID()
}

/** Plain-language text for a failed command, in the operator's language. */
export function errorText(t: TFunction, err: unknown): string {
  if (!(err instanceof ApiError)) return t('errors.unknown')
  if (err.reason) {
    const r = t(`reasons.${err.reason}`, { defaultValue: '' })
    if (r) return r
  }
  if (err.code === 'rungud_refused') return t('errors.rungud_refused', { message: err.message })
  if (err.code === 'rungud_command_failed') return t('errors.rungud_command_failed')
  return t(`errors.${err.code}`, { defaultValue: '' }) || t('errors.unknown')
}

export interface CommandResult<T> {
  ok: true
  result: T
  audit_id: number
  replay?: boolean
}

/**
 * POST a command. The caller keeps the request id for the dialog's lifetime
 * (useRequestId) so pressing the button twice cannot run it twice.
 */
export function useCommand<T = unknown>(invalidate: unknown[][] = []) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ path, body }: { path: string; body: Record<string, unknown> }) =>
      api<CommandResult<T>>(path, { method: 'POST', body: JSON.stringify(body) }),
    onSettled: () => {
      for (const key of [...invalidate, ['today'], ['audit']]) void qc.invalidateQueries({ queryKey: key })
    },
  })
}

/** Stable request id per mount of a dialog; `renew()` after a refused attempt so the corrected form is a new command. */
export function useRequestId() {
  const [id, setId] = useState(newRequestId)
  return { id, renew: () => setId(newRequestId()) }
}

export function useErrorText() {
  const { t } = useTranslation()
  return (err: unknown) => errorText(t, err)
}
