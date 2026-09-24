import type { ReactNode } from 'react'
import { render } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import '@/i18n'

/** Renders with router + query client; `me` is injected through a mocked useAuth. */
export function renderWithProviders(ui: ReactNode, path = '/') {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={[path]}>{ui}</MemoryRouter>
    </QueryClientProvider>,
  )
}

export const authState = { me: null as null | Record<string, unknown> }

vi.mock('@/auth/AuthProvider', () => ({
  useAuth: () => ({ status: 'authenticated', me: authState.me, login: vi.fn(), logout: vi.fn() }),
  useCan: () => (cap: string) => Boolean((authState.me?.capabilities as Record<string, boolean> | undefined)?.[cap]),
}))
