import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import * as client from '@/api/client'
import type { Me } from '@/api/client'

interface AuthState {
  status: 'loading' | 'anonymous' | 'authenticated'
  me: Me | null
  login: (username: string, password: string) => Promise<Me>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [me, setMe] = useState<Me | null>(null)
  const [status, setStatus] = useState<AuthState['status']>('loading')

  useEffect(() => {
    client.setSessionEndHandler(() => {
      setMe(null)
      setStatus('anonymous')
      queryClient.clear()
    })
    client
      .refresh()
      .then((user) => {
        setMe(user)
        setStatus(user ? 'authenticated' : 'anonymous')
      })
      .catch(() => setStatus('anonymous'))
    return () => client.setSessionEndHandler(null)
  }, [queryClient])

  const login = useCallback(async (username: string, password: string) => {
    const user = await client.login(username, password)
    setMe(user)
    setStatus('authenticated')
    return user
  }, [])

  const logout = useCallback(async () => {
    await client.logout()
    queryClient.clear()
    setMe(null)
    setStatus('anonymous')
  }, [queryClient])

  const value = useMemo(() => ({ status, me, login, logout }), [status, me, login, logout])
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth outside AuthProvider')
  return ctx
}

/** Robert: reads everything, changes nothing except promises and claims (enforced by the API). */
// eslint-disable-next-line react-refresh/only-export-components
export function useCan() {
  const { me } = useAuth()
  return (cap: keyof Me['capabilities']) => Boolean(me?.capabilities[cap])
}
