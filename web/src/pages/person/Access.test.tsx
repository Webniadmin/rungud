import { screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { authState, renderWithProviders } from '@/test/render'
import { ToastProvider } from '@/components/Toast'
import { AccessTab } from './Access'

const caps = { membership: { 'cancel-at-period-end': false, 'cancel-now': false, 'refund-cancel': false }, licences: true, grant: true, woo_pdf: false, stripe: false, mail_mode: 'log', pricing_url: true }
const access = {
  learndash: true,
  membership: { status: 'active', plan: 'instructor-annual', until: '2027-09-20', renews: true, expiring: false, days_left: 361, subscription_id: 'sub_1', customer_id: null, free_access: null },
  site: {
    user: { professional: true },
    courses: [],
    licences: [
      { program_slug: 'breath-coach', program_title: 'Breath Coach', status: 'pending', number: 'BW-2211', organisation: 'inZENtive' },
      { program_slug: 'bodyart', program_title: 'bodyART', status: 'verified', in_force: true, number: 'BA-0287', valid_until: '2026-11-09' },
    ],
  },
}

vi.mock('@/api/client', async (orig) => ({
  ...(await orig<typeof import('@/api/client')>()),
  api: vi.fn(async (path: string) => {
    if (path === '/site/capabilities') return caps
    if (path.endsWith('/redeem-codes')) return { items: [] }
    return access
  }),
}))

function renderTab() {
  return renderWithProviders(
    <ToastProvider errorText={() => 'x'}>
      <AccessTab id={7} name="Beata Tothova" email="beata@example.com" />
    </ToastProvider>,
  )
}

describe('Access tab', () => {
  beforeEach(() => void i18n.changeLanguage('en'))
  afterEach(() => { authState.me = null })

  it('Robert sees the data and no command at all', async () => {
    authState.me = { role: 'owner', capabilities: { read: true, promise: true } }
    renderTab()
    expect(await screen.findByText('BW-2211')).toBeInTheDocument()
    for (const label of ['Verify', 'Reject', 'Revoke', 'Add licence', 'Cancel now', 'Grant free access until …', 'Create timed-access code']) {
      expect(screen.queryByRole('button', { name: label })).not.toBeInTheDocument()
    }
  })

  it('Gudrun gets the commands; membership ones are disabled while the website lacks them', async () => {
    authState.me = { role: 'backoffice', capabilities: { read: true, promise: true, write: true, finance: true, send: true } }
    renderTab()
    expect(await screen.findByRole('button', { name: 'Verify' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Revoke' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Add licence' })).toBeEnabled()
    expect(await screen.findByRole('button', { name: 'Cancel now' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Refund and cancel' })).toBeDisabled()
    expect(screen.getByText(/needs a command the website does not offer yet/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Grant free access until …' })).toBeEnabled()
  })
})
