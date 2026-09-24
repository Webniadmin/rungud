import { screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { authState, renderWithProviders } from '@/test/render'
import Today from './Today'

vi.mock('@/api/client', async (orig) => ({
  ...(await orig<typeof import('@/api/client')>()),
  api: vi.fn(async () => ({
    date: '2026-08-20',
    role: 'backoffice',
    not_yet_available: [],
    items: [
      { key: 'commands_failed', tone: 'bad', count: 1, target: { screen: 'sync' }, params: { audit_id: 3, summary_en: "Monika Burkhard's access should have been removed.", summary_de: 'Monika Burkhards Zugang sollte entzogen werden.' } },
      { key: 'memberships_expiring_30d', tone: 'attn', count: 13, target: { screen: 'members' }, params: {} },
    ],
  })),
}))

describe('Today', () => {
  beforeEach(() => void i18n.changeLanguage('en'))
  afterEach(() => { authState.me = null })

  it('is a sentence list with plurals and the failure detail in the UI language', async () => {
    authState.me = { role: 'backoffice', capabilities: {} }
    renderWithProviders(<Today />)
    expect(await screen.findByText('change did not reach the website')).toBeInTheDocument()
    expect(screen.getByText('memberships expire within the next 30 days')).toBeInTheDocument()
    expect(screen.getByText("Monika Burkhard's access should have been removed.")).toBeInTheDocument()
    expect(screen.getByText('Thursday, 20 August 2026')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'See it' })).toHaveAttribute('href', '/sync')
    expect(screen.queryByText(/You have view access/)).not.toBeInTheDocument()
  })

  it('German, and Robert sees the view-only banner', async () => {
    await i18n.changeLanguage('de')
    authState.me = { role: 'owner', capabilities: {} }
    renderWithProviders(<Today />)
    expect(await screen.findByText('Änderung ist nicht auf der Website angekommen')).toBeInTheDocument()
    expect(screen.getByText('Monika Burkhards Zugang sollte entzogen werden.')).toBeInTheDocument()
    expect(screen.getByText('Donnerstag, 20. August 2026')).toBeInTheDocument()
    expect(screen.getByText(/Du hast Leserechte/)).toBeInTheDocument()
  })
})
