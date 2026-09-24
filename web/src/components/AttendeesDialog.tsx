import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { newRequestId, useCommand } from '@/api/commands'
import type { Attendee } from '@/api/types'
import { Dialog } from './Dialog'
import { useToast } from './Toast'

/**
 * Names per seat on a company order line. Saved with Undo (reversible: the
 * previous names are in the audit row), stored on the website's order line.
 */
export function AttendeesDialog({ orderId, orderNumber, itemId, quantity, current, onClose, invalidate }: { orderId: number; orderNumber: string; itemId: number; quantity: number; current: Attendee[]; onClose: () => void; invalidate: unknown[][] }) {
  const { t } = useTranslation()
  const toast = useToast()
  const cmd = useCommand(invalidate)
  const [rows, setRows] = useState<Attendee[]>(() => Array.from({ length: quantity }, (_, i) => current[i] ?? { name: '', email: '' }))
  const set = (i: number, k: keyof Attendee, v: string) => setRows((r) => r.map((a, j) => (j === i ? { ...a, [k]: v } : a)))
  const complete = rows.every((a) => a.name.trim())
  return (
    <Dialog
      title={t('attendees.title', { number: orderNumber })}
      confirmLabel={t('attendees.save')}
      disabled={!complete}
      onClose={onClose}
      onConfirm={() => {
        const payload = rows.map((a) => ({ name: a.name.trim(), email: a.email.trim() }))
        toast.withUndo(t('attendees.saving', { count: quantity }), () =>
          cmd.mutateAsync({ path: `/orders/${orderId}/attendees`, body: { request_id: newRequestId(), item_id: itemId, attendees: payload } }),
        )
        onClose()
      }}
      consequence={t('attendees.consequence', { count: quantity })}
    >
      <table>
        <thead>
          <tr><th>#</th><th>{t('common.name')}</th><th>{t('attendees.emailOptional')}</th></tr>
        </thead>
        <tbody>
          {rows.map((a, i) => (
            <tr key={i}>
              <td className="num">{i + 1}</td>
              <td><div className="field" style={{ margin: 0 }}><input aria-label={`${t('common.name')} ${i + 1}`} value={a.name} onChange={(e) => set(i, 'name', e.target.value)} /></div></td>
              <td><div className="field" style={{ margin: 0 }}><input type="email" aria-label={`${t('common.email')} ${i + 1}`} value={a.email} onChange={(e) => set(i, 'email', e.target.value)} /></div></td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="hint">{t('attendees.hint')}</div>
    </Dialog>
  )
}
