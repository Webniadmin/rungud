import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api, qs } from '@/api/client'
import { useCommand, useErrorText, useRequestId } from '@/api/commands'
import type { Order } from '@/api/types'
import { Dialog, Field } from '@/components/Dialog'
import { useToast } from '@/components/Toast'
import { formatDate, formatMoney } from '@/lib/format'
import { LangPicker } from './Access'

/** Refund a website order through WooCommerce. Amount, person and consequence in one sentence. */
export function RefundDialog({ order, onClose }: { order: Order; onClose: () => void }) {
  const { t, i18n } = useTranslation()
  const err = useErrorText()
  const [amount, setAmount] = useState(order.refund.remaining)
  const [reason, setReason] = useState('')
  const rid = useRequestId()
  const cmd = useCommand([['documents'], ['orders'], ['person']])
  const toast = useToast()
  const lang = i18n.language === 'de' ? 'de' : 'en'
  const normalised = amount.replace(',', '.').trim()
  const valid = /^\d+(\.\d{1,2})?$/.test(normalised) && Number(normalised) > 0
  const who = order.customer.name ?? order.customer.email ?? ''
  return (
    <Dialog
      title={t('refund.title', { number: order.number })}
      tone="warn"
      confirmLabel={t('refund.confirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!valid || !reason.trim()}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/orders/${order.id}/refund`, body: { request_id: rid.id, amount: normalised, reason } }, {
        onSuccess: () => { toast.notify(t('refund.done')); onClose() },
        onError: rid.renew,
      })}
      consequence={valid ? t(order.refund.automatic ? 'refund.consequenceAuto' : 'refund.consequenceBank', { amount: formatMoney(normalised, order.currency, lang), name: who, gateway: order.refund.gateway, date: formatDate(todayYmd()) }) : null}
    >
      <Field label={t('common.amount')} hint={t('refund.remaining', { amount: formatMoney(order.refund.remaining, order.currency, lang) })}>
        <input inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} />
      </Field>
      <Field label={t('access.reason')}><textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      <div className="hint">{t('refund.accessNote')}</div>
    </Dialog>
  )
}

/** Send a document that already exists (Stripe invoice PDF, Woo PDF) to any address with a cover text. */
export function SendDialog({ docType, docRef, label, defaultTo, name, onClose }: { docType: 'stripe_invoice' | 'woo_invoice'; docRef: string; label: string; defaultTo: string; name: string; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const [to, setTo] = useState(defaultTo)
  const [lang, setLang] = useState<'de' | 'en'>('de')
  const [draft, setDraft] = useState<{ lang: string; subject: string; body: string } | null>(null)
  const cover = useQuery({
    queryKey: ['cover', docType, label, lang, name],
    queryFn: () => api<{ subject: string; body: string }>(`/documents/cover${qs({ doc_type: docType, label, lang, name })}`),
  })
  const subject = draft?.lang === lang ? draft.subject : (cover.data?.subject ?? '')
  const body = draft?.lang === lang ? draft.body : (cover.data?.body ?? '')
  const rid = useRequestId()
  const cmd = useCommand([['audit']])
  const toast = useToast()
  return (
    <Dialog
      title={t('send.title', { label })}
      confirmLabel={t('access.sendConfirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!to.trim() || !subject || !body}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: '/documents/send', body: { request_id: rid.id, doc_type: docType, doc_ref: docRef, doc_label: label, to, lang, subject, body } }, {
        onSuccess: (r) => { toast.notify(t((r.result as { status: string }).status === 'logged' ? 'send.logged' : 'send.sent', { to })); onClose() },
        onError: rid.renew,
      })}
      consequence={t('send.consequence', { label, to })}
    >
      <Field label={t('send.to')}><input type="email" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
      <LangPicker lang={lang} setLang={setLang} />
      <Field label={t('send.subject')}><input value={subject} onChange={(e) => setDraft({ lang, subject: e.target.value, body })} /></Field>
      <Field label={t('send.body')}><textarea rows={6} value={body} onChange={(e) => setDraft({ lang, subject, body: e.target.value })} /></Field>
    </Dialog>
  )
}

function todayYmd(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
