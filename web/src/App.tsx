import type { ReactNode } from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/auth/AuthProvider'
import { Shell } from '@/components/Shell'
import Login from '@/pages/Login'
import Today from '@/pages/Today'
import People from '@/pages/People'
import Person from '@/pages/Person'
import Events from '@/pages/Events'
import Event from '@/pages/Event'
import Invoices from '@/pages/Invoices'
import Members from '@/pages/Members'
import Sync from '@/pages/Sync'
import Later from '@/pages/Later'

function Protected({ children }: { children: ReactNode }) {
  const { status } = useAuth()
  const { t } = useTranslation()
  const location = useLocation()
  if (status === 'loading') return <div className="empty">{t('state.loading')}</div>
  if (status === 'anonymous') return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  return <Shell>{children}</Shell>
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/" element={<Navigate to="/today" replace />} />
      <Route path="/today" element={<Protected><Today /></Protected>} />
      <Route path="/people" element={<Protected><People /></Protected>} />
      <Route path="/people/:id" element={<Protected><Person /></Protected>} />
      <Route path="/events" element={<Protected><Events /></Protected>} />
      <Route path="/events/:id" element={<Protected><Event /></Protected>} />
      <Route path="/invoices" element={<Protected><Invoices /></Protected>} />
      <Route path="/members" element={<Protected><Members /></Protected>} />
      <Route path="/sync" element={<Protected><Sync /></Protected>} />
      <Route path="/certs" element={<Protected><Later titleKey="nav.certificates" phase={6} /></Protected>} />
      <Route path="/payments" element={<Protected><Later titleKey="common.payments" phase={5} /></Protected>} />
      <Route path="/codes" element={<Protected><Later titleKey="nav.codes" phase={4} /></Protected>} />
      <Route path="/insights" element={<Protected><Later titleKey="nav.insights" phase={7} /></Protected>} />
      <Route path="/video" element={<Protected><Later titleKey="nav.video" phase={7} /></Protected>} />
      <Route path="/messages" element={<Protected><Later titleKey="nav.messages" phase={7} /></Protected>} />
      <Route path="/help" element={<Protected><Later titleKey="common.help" phase={7} /></Protected>} />
      <Route path="*" element={<Navigate to="/today" replace />} />
    </Routes>
  )
}
