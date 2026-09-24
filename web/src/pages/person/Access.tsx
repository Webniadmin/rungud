import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from '@/api/client'
import { newRequestId, useCommand, useErrorText, useRequestId } from '@/api/commands'
import type { Access, Licence, MembershipView, Plan, Programme, RedeemCode, SiteCapabilities } from '@/api/types'
import { useCan } from '@/auth/AuthProvider'
import { DateField } from '@/components/DateField'
import { Dialog, Field } from '@/components/Dialog'
import { useToast } from '@/components/Toast'
import { Badge, DateText, ErrorNote, Loading, MembershipBadge, Tip, type Tone } from '@/components/ui'
import { formatDate } from '@/lib/format'

type Open =
  | null
  | { kind: 'membership'; action: 'cancel-at-period-end' | 'cancel-now' | 'refund-cancel' }
  | { kind: 'grant' }
  | { kind: 'checkout' }
  | { kind: 'addLicence' }
  | { kind: 'verdict'; licence: Licence; verdict: 'reject' | 'revoke' }
  | { kind: 'code' }

export function AccessTab({ id, name, email }: { id: number; name: string; email: string }) {
  const { t } = useTranslation()
  const can = useCan()
  const [open, setOpen] = useState<Open>(null)
  const access = useQuery({ queryKey: ['access', id], queryFn: () => api<Access>(`/people/${id}/access`) })
  const caps = useQuery({ queryKey: ['site-caps'], queryFn: () => api<SiteCapabilities>('/site/capabilities'), staleTime: 300_000 })

  if (access.isLoading) return <Loading />
  if (access.error || !access.data) return <ErrorNote error={access.error} />
  const a = access.data
  const m = a.membership
  const courses = a.site.courses ?? []
  const professional = Boolean(a.site.user?.professional)
  const close = () => setOpen(null)

  return (
    <>
      <div className="note info">{t('access.info')}</div>
      {!a.learndash ? <div className="note" style={{ marginTop: 12 }}>{t('person.noLearndash')}</div> : null}
      <div className="grid2" style={{ marginTop: 20 }}>
        <div className="panel">
          <h3>{t('person.membership')}</h3>
          <MembershipFacts m={m} />
          {can('finance') || can('write') || can('send') ? (
            <div className="btnrow" style={{ marginTop: 16 }}>
              {can('finance')
                ? (['cancel-at-period-end', 'cancel-now', 'refund-cancel'] as const).map((action) => (
                    <button
                      key={action}
                      type="button"
                      className={action === 'refund-cancel' ? 'btn sm warn' : 'btn sm'}
                      disabled={m.status === 'none' || m.status === 'free_access' || !caps.data?.membership[action]}
                      onClick={() => setOpen({ kind: 'membership', action })}
                    >
                      {t(`access.${action}`)}
                    </button>
                  ))
                : null}
              {can('send') ? <button type="button" className="btn sm" disabled={!caps.data?.pricing_url} onClick={() => setOpen({ kind: 'checkout' })}>{t('access.checkout')}</button> : null}
              {can('write') ? <button type="button" className="btn sm" disabled={!caps.data?.grant} onClick={() => setOpen({ kind: 'grant' })}>{t('access.grant')}</button> : null}
            </div>
          ) : null}
          {can('finance') && caps.data && !caps.data.membership['cancel-now'] ? <div className="hint">{t('access.membershipMissing')}</div> : null}
          {can('send') && caps.data && !caps.data.pricing_url ? <div className="hint">{t('access.pricingMissing')}</div> : null}
          <div className="hint">{t('access.noPause')}</div>
        </div>
        <Licences userId={id} list={a.site.licences ?? []} onAdd={() => setOpen({ kind: 'addLicence' })} onVerdict={(licence, verdict) => setOpen({ kind: 'verdict', licence, verdict })} name={name} />
      </div>

      <div className="grid2" style={{ marginTop: 20 }}>
        <RedeemCodes userId={id} onCreate={() => setOpen({ kind: 'code' })} />
        <div className="panel">
          <h3>{t('person.whatTheySee')}</h3>
          {courses.length === 0 ? <div className="mut">{t('person.noCourses')}</div> : (
            <table>
              <tbody>
                {courses.map((c) => (
                  <tr key={c.id}>
                    <td>{c.title}</td>
                    <td className="r num">{t('person.progress', { done: c.completed_steps, total: c.total_steps })}</td>
                    <td className="r num">{c.percentage}%</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <div className="hint" style={{ marginTop: 12 }}>{t('person.readFromSite')}</div>
        </div>
      </div>

      {open?.kind === 'membership' ? <MembershipDialog userId={id} name={name} m={m} action={open.action} onClose={close} /> : null}
      {open?.kind === 'grant' ? <GrantDialog userId={id} name={name} onClose={close} /> : null}
      {open?.kind === 'checkout' ? <CheckoutDialog userId={id} name={name} email={email} professional={professional} onClose={close} /> : null}
      {open?.kind === 'addLicence' ? <AddLicenceDialog userId={id} name={name} onClose={close} /> : null}
      {open?.kind === 'verdict' ? <VerdictDialog userId={id} name={name} licence={open.licence} verdict={open.verdict} onClose={close} /> : null}
      {open?.kind === 'code' ? <CodeDialog userId={id} name={name} email={email} onClose={close} /> : null}
    </>
  )
}

function MembershipFacts({ m }: { m: MembershipView }) {
  const { t } = useTranslation()
  if (m.status === 'none') return <div className="mut">{t('person.noMembership')}</div>
  return (
    <dl className="dl">
      <dt>{t('person.plan')}</dt>
      <dd>{m.plan ? t(`plans.${m.plan}`, { defaultValue: m.plan }) : t('membership.free_access')}</dd>
      <dt>{t('person.status')}</dt>
      <dd><MembershipBadge status={m.status} /></dd>
      <dt>{m.renews ? t('person.renewsOn') : t('person.accessUntil')}</dt>
      <dd><DateText value={m.until} /></dd>
      {m.subscription_id ? (<><dt>Stripe</dt><dd className="mut num">{m.subscription_id}</dd></>) : null}
    </dl>
  )
}

/* ---------------------------------------------------------------- licences */

function licenceTone(l: Licence): Tone {
  if (l.status === 'verified') return l.in_force === false ? 'bad' : 'ok'
  if (l.status === 'pending') return 'warn'
  if (l.status === 'rejected') return 'bad'
  return 'neu'
}

function Licences({ userId, list, onAdd, onVerdict, name }: { userId: number; list: Licence[]; onAdd: () => void; onVerdict: (l: Licence, v: 'reject' | 'revoke') => void; name: string }) {
  const { t } = useTranslation()
  const can = useCan()
  const toast = useToast()
  const verify = useCommand([['access', userId], ['person', String(userId)]])
  return (
    <div className="panel">
      <h3>{t('person.licences')}<Tip k="licence" /></h3>
      {list.length === 0 ? <div className="mut">{t('person.noLicences')}</div> : (
        <table>
          <tbody>
            {list.map((l, i) => (
              <tr key={`${l.program_slug}-${i}`} style={l.status === 'pending' ? { background: 'var(--warn-bg)' } : undefined}>
                <td>
                  <div className="nm num">{l.number || '—'}</div>
                  <div className="mut">{[l.program_title ?? l.program_slug, l.organisation].filter(Boolean).join(' · ')}</div>
                </td>
                <td><Badge tone={licenceTone(l)}>{t(`licence.${l.status === 'verified' && l.in_force === false ? 'expired' : l.status}`)}</Badge></td>
                <td className="num">{l.valid_until ? <DateText value={l.valid_until} /> : l.status === 'verified' ? t('licence.noExpiry') : '—'}</td>
                <td className="r">
                  {can('write') && l.status === 'pending' && l.program_slug ? (
                    <div className="btnrow" style={{ justifyContent: 'flex-end' }}>
                      <button
                        type="button"
                        className="btn sm primary"
                        onClick={() =>
                          toast.withUndo(t('access.verifyToast', { number: l.number, name }), () =>
                            verify.mutateAsync({ path: `/people/${userId}/licences/${l.program_slug}/verify`, body: { request_id: newRequestId() } }),
                          )
                        }
                      >
                        {t('common.verify')}
                      </button>
                      <button type="button" className="btn sm" onClick={() => onVerdict(l, 'reject')}>{t('access.reject')}</button>
                    </div>
                  ) : null}
                  {can('write') && l.status === 'verified' && l.program_slug ? (
                    <button type="button" className="btn sm" onClick={() => onVerdict(l, 'revoke')}>{t('access.revoke')}</button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {can('write') ? <div style={{ marginTop: 14 }}><button type="button" className="btn sm primary" onClick={onAdd}>{t('access.addLicence')}</button></div> : null}
      <div className="hint" style={{ marginTop: 12 }}>{t('person.licenceNote')}</div>
    </div>
  )
}

function AddLicenceDialog({ userId, name, onClose }: { userId: number; name: string; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const programs = useQuery({ queryKey: ['programs'], queryFn: () => api<{ items: Programme[] }>('/programs') })
  const licensable = (programs.data?.items ?? []).filter((p) => p.takes_licence)
  const [slug, setSlug] = useState('')
  const [number, setNumber] = useState('')
  const [org, setOrg] = useState('inZENtive')
  const [until, setUntil] = useState('')
  const rid = useRequestId()
  const cmd = useCommand([['access', userId], ['person', String(userId)], ['programs']])
  const pick = (s: string) => {
    setSlug(s)
    const p = licensable.find((x) => x.slug === s)
    if (p?.next_number) setNumber(p.next_number)
  }
  // Typing a number with a known prefix suggests the programme (the site never reads access from it).
  const onNumber = (v: string) => {
    setNumber(v)
    const prefix = v.toUpperCase().split('-')[0]
    const match = licensable.find((p) => p.licence_prefix === prefix)
    if (match && !slug) setSlug(match.slug)
  }
  const programme = licensable.find((p) => p.slug === slug)
  return (
    <Dialog
      title={t('access.addLicence')}
      confirmLabel={t('access.addLicenceConfirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!slug || !number.trim()}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/licences`, body: { request_id: rid.id, program: slug, number, organisation: org, valid_until: until || null } }, { onSuccess: onClose, onError: rid.renew })}
      consequence={programme && number ? t('access.addLicenceConsequence', { number: number.toUpperCase(), programme: programme.title, name, until: until ? formatDate(until) : t('licence.noExpiry') }) : null}
    >
      <Field label={t('access.programme')}>
        <select value={slug} onChange={(e) => pick(e.target.value)}>
          <option value="">{t('access.choose')}</option>
          {licensable.map((p) => <option key={p.slug} value={p.slug}>{p.title}{p.licence_prefix ? ` (${p.licence_prefix})` : ''}</option>)}
        </select>
      </Field>
      <Field label={t('access.number')} hint={t('access.numberHint')}>
        <input value={number} onChange={(e) => onNumber(e.target.value)} />
      </Field>
      <Field label={t('access.organisation')}>
        <input value={org} onChange={(e) => setOrg(e.target.value)} />
      </Field>
      <Field label={t('access.validUntil')} hint={t('access.validUntilHint')}>
        <DateField id="lic-until" value={until} onChange={setUntil} />
      </Field>
    </Dialog>
  )
}

function VerdictDialog({ userId, name, licence, verdict, onClose }: { userId: number; name: string; licence: Licence; verdict: 'reject' | 'revoke'; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const [reason, setReason] = useState('')
  const rid = useRequestId()
  const cmd = useCommand([['access', userId], ['person', String(userId)]])
  return (
    <Dialog
      title={t(`access.${verdict}Title`)}
      tone="warn"
      confirmLabel={t(`access.${verdict}`)}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!reason.trim()}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/licences/${licence.program_slug}/${verdict}`, body: { request_id: rid.id, reason } }, { onSuccess: onClose, onError: rid.renew })}
      consequence={t(`access.${verdict}Consequence`, { number: licence.number, programme: licence.program_title ?? licence.program_slug, name })}
    >
      <Field label={t('access.reason')} hint={t('access.reasonHint')}>
        <textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} />
      </Field>
    </Dialog>
  )
}

/* ------------------------------------------------------------- membership */

function MembershipDialog({ userId, name, m, action, onClose }: { userId: number; name: string; m: MembershipView; action: 'cancel-at-period-end' | 'cancel-now' | 'refund-cancel'; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const [amount, setAmount] = useState('')
  const [reason, setReason] = useState('')
  const rid = useRequestId()
  const cmd = useCommand([['access', userId], ['person', String(userId)], ['memberships']])
  const plan = m.plan ? t(`plans.${m.plan}`, { defaultValue: m.plan }) : ''
  return (
    <Dialog
      title={t(`access.${action}`)}
      tone={action === 'cancel-at-period-end' ? 'primary' : 'warn'}
      confirmLabel={t(`access.${action}Confirm`)}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={action === 'refund-cancel' && !amount}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/membership/${action}`, body: { request_id: rid.id, amount: amount || null, reason } }, { onSuccess: onClose, onError: rid.renew })}
      consequence={t(`access.${action}Consequence`, { name, plan, date: m.until ? formatDate(m.until) : '—', amount })}
    >
      {action === 'refund-cancel' ? (
        <Field label={t('common.amount')} hint={t('access.refundHint')}>
          <input inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="200.00" />
        </Field>
      ) : null}
      <Field label={t('access.reason')}>
        <textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} />
      </Field>
    </Dialog>
  )
}

function GrantDialog({ userId, name, onClose }: { userId: number; name: string; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const [scope, setScope] = useState<'b2c' | 'all'>('b2c')
  const [until, setUntil] = useState('')
  const [reason, setReason] = useState('')
  const rid = useRequestId()
  const cmd = useCommand([['access', userId], ['person', String(userId)], ['memberships']])
  return (
    <Dialog
      title={t('access.grant')}
      confirmLabel={t('access.grantConfirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!until || !reason.trim()}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/access/grant`, body: { request_id: rid.id, scope, expires_at: until, reason } }, { onSuccess: onClose, onError: rid.renew })}
      consequence={until ? t('access.grantConsequence', { name, scope: t(`scope.${scope}`), date: formatDate(until) }) : null}
    >
      <div className="radios" style={{ marginBottom: 16 }}>
        {(['b2c', 'all'] as const).map((s) => (
          <label key={s} className={`radio${scope === s ? ' on' : ''}`}>
            <input type="radio" name="scope" checked={scope === s} onChange={() => setScope(s)} />
            <span className="rt">{t(`scope.${s}`)}</span>
          </label>
        ))}
      </div>
      <Field label={t('access.lastDay')}><DateField id="grant-until" value={until} onChange={setUntil} /></Field>
      <Field label={t('access.reason')}><textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
      <div className="hint">{t('access.grantHint')}</div>
    </Dialog>
  )
}

function CheckoutDialog({ userId, name, email, professional, onClose }: { userId: number; name: string; email: string; professional: boolean; onClose: () => void }) {
  const { t } = useTranslation()
  const err = useErrorText()
  const plans = useQuery({ queryKey: ['plans'], queryFn: () => api<{ items: Plan[] }>('/plans') })
  const offered = (plans.data?.items ?? []).filter((p) => p.professional === professional)
  const [plan, setPlan] = useState('')
  const [lang, setLang] = useState<'de' | 'en'>('de')
  const [draft, setDraft] = useState<{ key: string; subject: string; body: string } | null>(null)
  const key = `${plan}|${lang}`
  const preview = useQuery({
    queryKey: ['checkout-preview', userId, plan, lang],
    queryFn: () => api<{ subject: string; body: string }>(`/people/${userId}/checkout-link?plan=${encodeURIComponent(plan)}&lang=${lang}`),
    enabled: Boolean(plan),
  })
  const subject = draft?.key === key ? draft.subject : (preview.data?.subject ?? '')
  const body = draft?.key === key ? draft.body : (preview.data?.body ?? '')
  const rid = useRequestId()
  const cmd = useCommand([['audit']])
  const toast = useToast()
  const title = offered.find((p) => p.slug === plan)?.title ?? ''
  return (
    <Dialog
      title={t('access.checkout')}
      confirmLabel={t('access.sendConfirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!plan || !subject || !body}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/checkout-link`, body: { request_id: rid.id, plan, lang, subject, body } }, {
        onSuccess: (r) => { toast.notify(t((r.result as { status: string }).status === 'logged' ? 'send.logged' : 'send.sent', { to: email })); onClose() },
        onError: rid.renew,
      })}
      consequence={plan ? t('access.checkoutConsequence', { plan: title, name, email }) : null}
    >
      <Field label={t('access.plan')} hint={professional ? t('access.planProfessional') : undefined}>
        <select value={plan} onChange={(e) => setPlan(e.target.value)}>
          <option value="">{t('access.choose')}</option>
          {offered.map((p) => <option key={p.slug} value={p.slug}>{p.title}</option>)}
        </select>
      </Field>
      <LangPicker lang={lang} setLang={setLang} />
      <Field label={t('send.subject')}><input value={subject} onChange={(e) => setDraft({ key, subject: e.target.value, body })} /></Field>
      <Field label={t('send.body')}><textarea rows={7} value={body} onChange={(e) => setDraft({ key, subject, body: e.target.value })} /></Field>
    </Dialog>
  )
}

export function LangPicker({ lang, setLang }: { lang: 'de' | 'en'; setLang: (l: 'de' | 'en') => void }) {
  const { t } = useTranslation()
  return (
    <Field label={t('send.language')} hint={t('send.languageHint')}>
      <div className="seg" style={{ width: 'fit-content' }}>
        {(['de', 'en'] as const).map((l) => (
          <button key={l} type="button" className={lang === l ? 'on' : ''} onClick={() => setLang(l)}>{l.toUpperCase()}</button>
        ))}
      </div>
    </Field>
  )
}

/* ------------------------------------------------------------ redeem codes */

function RedeemCodes({ userId, onCreate }: { userId: number; onCreate: () => void }) {
  const { t } = useTranslation()
  const can = useCan()
  const q = useQuery({ queryKey: ['redeem-codes', userId], queryFn: () => api<{ items: RedeemCode[] }>(`/people/${userId}/redeem-codes`) })
  const tone: Record<RedeemCode['state'], Tone> = { open: 'ok', used: 'neu', expired: 'bad', deleted: 'bad' }
  return (
    <div className="panel">
      <h3>{t('codes.title')}</h3>
      {(q.data?.items ?? []).length === 0 ? <div className="mut">{t('codes.none')}</div> : (
        <table>
          <tbody>
            {q.data!.items.map((c) => (
              <tr key={c.code}>
                <td><div className="nm num">{c.code}</div><div className="mut">{t('codes.days', { count: c.days ?? 0 })} · {t(`scope.${c.scope ?? 'all'}`)} · {t('codes.made', { date: formatDate(c.made_at) })}</div></td>
                <td><Badge tone={tone[c.state]}>{t(`codes.state_${c.state}`)}</Badge></td>
                <td className="num mut">{c.valid_until ? t('codes.validUntil', { date: formatDate(c.valid_until) }) : ''}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {can('write') ? <div style={{ marginTop: 14 }}><button type="button" className="btn sm" onClick={onCreate}>{t('codes.create')}</button></div> : null}
      <div className="hint" style={{ marginTop: 12 }}>{t('codes.hint')}</div>
    </div>
  )
}

function CodeDialog({ userId, name, email, onClose }: { userId: number; name: string; email: string; onClose: () => void }) {
  const { t, i18n } = useTranslation()
  const err = useErrorText()
  const [days, setDays] = useState('7')
  const [scope, setScope] = useState<'b2c' | 'all'>('b2c')
  const [restrict, setRestrict] = useState(true)
  const [note, setNote] = useState('')
  const rid = useRequestId()
  const cmd = useCommand<{ code: string; days: number; code_valid_until: string }>([['redeem-codes', userId]])
  const created = cmd.data?.result
  if (created) {
    const lang = i18n.language === 'de' ? 'de' : 'en'
    const text = t('codes.message', { lng: lang, first: name.split(' ')[0], code: created.code, days: created.days, date: formatDate(created.code_valid_until) })
    return (
      <Dialog title={t('codes.created')} confirmLabel={t('common.close')} onConfirm={onClose} onClose={onClose}>
        <div style={{ fontSize: 28, letterSpacing: '.08em', margin: '6px 0 12px' }} className="num">{created.code}</div>
        <p className="mut">{t('codes.onlyOnce')}</p>
        <div className="btnrow">
          <button type="button" className="btn sm" onClick={() => void navigator.clipboard.writeText(created.code)}>{t('codes.copy')}</button>
          <button type="button" className="btn sm" onClick={() => void navigator.clipboard.writeText(text)}>{t('codes.copyText')}</button>
          <a className="btn sm" href={`https://wa.me/?text=${encodeURIComponent(text)}`} target="_blank" rel="noreferrer">{t('codes.whatsapp')}</a>
        </div>
        <pre style={{ whiteSpace: 'pre-wrap', background: 'var(--paper)', padding: 12, marginTop: 14, fontFamily: 'inherit', fontSize: 14 }}>{text}</pre>
      </Dialog>
    )
  }
  const n = Number(days)
  const valid = Number.isInteger(n) && n >= 1 && n <= 365
  return (
    <Dialog
      title={t('codes.create')}
      confirmLabel={t('codes.createConfirm')}
      busy={cmd.isPending}
      error={cmd.error ? err(cmd.error) : null}
      disabled={!valid}
      onClose={onClose}
      onConfirm={() => cmd.mutate({ path: `/people/${userId}/redeem-codes`, body: { request_id: rid.id, days: n, scope, restrict, note } }, { onError: rid.renew })}
      consequence={valid ? t('codes.consequence', { count: n, scope: t(`scope.${scope}`), name, who: restrict ? email : t('codes.anyone') }) : null}
    >
      <Field label={t('codes.duration')}>
        <div className="btnrow">
          {['7', '30'].map((d) => <button key={d} type="button" className={`chip${days === d ? ' on' : ''}`} onClick={() => setDays(d)}>{t('codes.days', { count: Number(d) })}</button>)}
          <input style={{ width: 90 }} inputMode="numeric" value={days} onChange={(e) => setDays(e.target.value.replace(/\D/g, ''))} aria-label={t('codes.customDays')} />
        </div>
      </Field>
      <div className="radios" style={{ marginBottom: 16 }}>
        {(['b2c', 'all'] as const).map((s) => (
          <label key={s} className={`radio${scope === s ? ' on' : ''}`}>
            <input type="radio" name="code-scope" checked={scope === s} onChange={() => setScope(s)} />
            <span className="rt">{t(`scope.${s}`)}</span>
          </label>
        ))}
      </div>
      <label style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 14 }}>
        <input type="checkbox" checked={restrict} onChange={(e) => setRestrict(e.target.checked)} />
        {t('codes.restrict', { email })}
      </label>
      <Field label={t('codes.note')}><input value={note} onChange={(e) => setNote(e.target.value)} /></Field>
    </Dialog>
  )
}

