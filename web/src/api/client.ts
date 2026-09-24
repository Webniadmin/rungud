/**
 * The only way the UI talks to the plugin. Access token in memory, refresh
 * token in localStorage (per-device convenience; the server rotates it and
 * ends the whole session if an old one is replayed). Refresh is single-flight:
 * parallel 401s wait for one refresh instead of racing — a race would look
 * like token reuse to the server and log the user out.
 */

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  constructor(status: number, code: string, message: string) {
    super(message)
    this.status = status
    this.code = code
  }
}

export type Role = 'owner' | 'backoffice' | 'admin'

export interface Me {
  id: number
  name: string
  email: string
  role: Role
  lang: 'en' | 'de'
  capabilities: Record<'read' | 'promise' | 'write' | 'finance' | 'send' | 'settings', boolean>
}

interface TokenResponse {
  access_token: string
  refresh_token: string
  expires_in: number
  user: Me
}

const REFRESH_KEY = 'rungud.refresh'

function storageGet(): string | null {
  try {
    return localStorage.getItem(REFRESH_KEY)
  } catch {
    return null
  }
}
function storageSet(value: string | null) {
  try {
    if (value) localStorage.setItem(REFRESH_KEY, value)
    else localStorage.removeItem(REFRESH_KEY)
  } catch {
    /* storage unavailable: session lasts until reload */
  }
}

export function apiBase(): string {
  return (import.meta.env.VITE_API_BASE ?? '').replace(/\/+$/, '')
}

let accessToken: string | null = null
let refreshing: Promise<Me | null> | null = null
let onSessionEnd: (() => void) | null = null

export function setSessionEndHandler(fn: (() => void) | null) {
  onSessionEnd = fn
}

async function parse(res: Response): Promise<unknown> {
  const text = await res.text()
  if (!text) return null
  try {
    return JSON.parse(text)
  } catch {
    return text
  }
}

async function raw(path: string, init: RequestInit = {}, token: string | null = null): Promise<Response> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  return fetch(`${apiBase()}${path}`, { ...init, headers })
}

function toError(status: number, body: unknown): ApiError {
  const b = (body ?? {}) as { code?: string; message?: string }
  return new ApiError(status, b.code ?? 'unknown', b.message ?? `HTTP ${status}`)
}

function accept(data: TokenResponse): Me {
  accessToken = data.access_token
  storageSet(data.refresh_token)
  return data.user
}

export async function login(username: string, password: string): Promise<Me> {
  const res = await raw('/auth/login', { method: 'POST', body: JSON.stringify({ username, password }) })
  const body = await parse(res)
  if (!res.ok) throw toError(res.status, body)
  return accept(body as TokenResponse)
}

/**
 * Runs `fn` while holding a lock shared by every tab of this origin, so two
 * tabs never present the same refresh token at once. Falls back to no lock
 * where Web Locks are missing (the server's 60 s reuse grace covers that).
 */
function withTabLock<T>(fn: () => Promise<T>): Promise<T> {
  const locks = typeof navigator !== 'undefined' ? (navigator as Navigator & { locks?: LockManager }).locks : undefined
  return locks ? (locks.request('rungud.refresh', fn) as Promise<T>) : fn()
}

/** Restores a session from the stored refresh token. Single-flight in this tab, serialised across tabs. */
export function refresh(): Promise<Me | null> {
  if (refreshing) return refreshing
  if (!storageGet()) return Promise.resolve(null)
  refreshing = withTabLock(async () => {
    // Read inside the lock: another tab may have rotated the token meanwhile.
    const token = storageGet()
    if (!token) return null
    try {
      const res = await raw('/auth/refresh', { method: 'POST', body: JSON.stringify({ refresh_token: token }) })
      const body = await parse(res)
      if (!res.ok) {
        if (res.status === 401 || res.status === 403) {
          accessToken = null
          storageSet(null)
          return null
        }
        throw toError(res.status, body)
      }
      return accept(body as TokenResponse)
    } finally {
      refreshing = null
    }
  })
  return refreshing
}

export async function logout(): Promise<void> {
  const token = accessToken
  accessToken = null
  storageSet(null)
  if (token) {
    await raw('/auth/logout', { method: 'POST' }, token).catch(() => undefined)
  }
}

/** Authenticated request. One transparent refresh on 401, then the session ends. */
export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  if (!accessToken) await refresh()
  let res = await raw(path, init, accessToken)
  if (res.status === 401) {
    const me = await refresh()
    if (!me) {
      onSessionEnd?.()
      throw new ApiError(401, 'rungud_invalid_token', 'Session ended')
    }
    res = await raw(path, init, accessToken)
  }
  const body = await parse(res)
  if (!res.ok) throw toError(res.status, body)
  return body as T
}

export function qs(params: Record<string, string | number | undefined | null>): string {
  const p = new URLSearchParams()
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== null && v !== '') p.set(k, String(v))
  const s = p.toString()
  return s ? `?${s}` : ''
}

/** Test seam. */
export function __resetForTests() {
  accessToken = null
  refreshing = null
  onSessionEnd = null
}
