import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as client from './client'

const user = { id: 1, name: 'Gudrun', email: 'g@x', role: 'backoffice', lang: 'de', capabilities: {} }

function json(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

describe('api client', () => {
  let fetchMock: ReturnType<typeof vi.fn>

  beforeEach(() => {
    client.__resetForTests()
    localStorage.clear()
    fetchMock = vi.fn()
    vi.stubGlobal('fetch', fetchMock)
  })
  afterEach(() => vi.unstubAllGlobals())

  it('logs in and sends the bearer token', async () => {
    fetchMock.mockResolvedValueOnce(json(200, { access_token: 'A1', refresh_token: 'R1', expires_in: 900, user }))
    await client.login('g', 'pw')
    expect(localStorage.getItem('rungud.refresh')).toBe('R1')
    fetchMock.mockResolvedValueOnce(json(200, { ok: true }))
    await client.api('/today')
    const headers = fetchMock.mock.calls[1][1].headers as Headers
    expect(headers.get('Authorization')).toBe('Bearer A1')
  })

  it('refreshes once for parallel 401s (single flight)', async () => {
    localStorage.setItem('rungud.refresh', 'R1')
    let refreshCalls = 0
    fetchMock.mockImplementation(async (url: string, init: RequestInit) => {
      if (url.endsWith('/auth/refresh')) {
        refreshCalls++
        await new Promise((r) => setTimeout(r, 5))
        return json(200, { access_token: 'A2', refresh_token: 'R2', expires_in: 900, user })
      }
      const auth = new Headers(init.headers).get('Authorization')
      return auth === 'Bearer A2' ? json(200, { ok: true }) : json(401, { code: 'rungud_invalid_token' })
    })
    await Promise.all([client.api('/a'), client.api('/b'), client.api('/c')])
    expect(refreshCalls).toBe(1)
    expect(localStorage.getItem('rungud.refresh')).toBe('R2')
  })

  it('ends the session when refresh is refused', async () => {
    localStorage.setItem('rungud.refresh', 'R-old')
    const ended = vi.fn()
    client.setSessionEndHandler(ended)
    fetchMock.mockImplementation(async (url: string) =>
      url.endsWith('/auth/refresh') ? json(401, { code: 'rungud_invalid_token' }) : json(401, { code: 'rungud_invalid_token' }),
    )
    await expect(client.api('/today')).rejects.toMatchObject({ status: 401 })
    expect(ended).toHaveBeenCalled()
    expect(localStorage.getItem('rungud.refresh')).toBeNull()
  })

  it('surfaces the API error code', async () => {
    fetchMock.mockResolvedValueOnce(json(401, { code: 'rungud_bad_credentials', message: 'x' }))
    await expect(client.login('g', 'bad')).rejects.toMatchObject({ code: 'rungud_bad_credentials', status: 401 })
  })
})

describe('api client across tabs', () => {
  beforeEach(() => {
    client.__resetForTests()
    localStorage.clear()
  })
  afterEach(() => vi.unstubAllGlobals())

  it('reads the refresh token inside the tab lock, after another tab rotated it', async () => {
    localStorage.setItem('rungud.refresh', 'R1')
    const sent: string[] = []
    // Simulated lock: before our callback runs, "another tab" rotates the token.
    vi.stubGlobal('navigator', {
      locks: {
        request: async (_name: string, fn: () => Promise<unknown>) => {
          localStorage.setItem('rungud.refresh', 'R2-from-other-tab')
          return fn()
        },
      },
    })
    vi.stubGlobal(
      'fetch',
      vi.fn(async (_url: string, init: RequestInit) => {
        sent.push(JSON.parse(String(init.body)).refresh_token)
        return json(200, { access_token: 'A3', refresh_token: 'R3', expires_in: 900, user })
      }),
    )
    await client.refresh()
    expect(sent).toEqual(['R2-from-other-tab'])
    expect(localStorage.getItem('rungud.refresh')).toBe('R3')
  })
})
