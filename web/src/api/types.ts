/** Response shapes of /wp-json/rungud/v1. Amounts are decimal strings; dates are YYYY-MM-DD. */

export type MembershipStatus = 'active' | 'ending' | 'free_access' | 'none'

export interface MembershipView {
  status: MembershipStatus
  plan: string | null
  until: string | null
  renews: boolean
  expiring: boolean
  days_left: number | null
  subscription_id: string | null
  customer_id: string | null
  free_access: { scope: string; until: string | null; reason: string; source: string } | null
}

export interface Paged<T> {
  items: T[]
  total: number
  page: number
  pages: number
}

export interface PersonRow {
  id: number
  name: string
  email: string
  membership: MembershipStatus
  plan: string | null
  orders: number
}

export interface OrderItem {
  id: number
  name: string
  quantity: number
  total: string | null
  product_id: number
  event_id: number | null
}

export interface Order {
  id: number
  number: string
  date: string | null
  status: string
  paid: boolean
  customer: { user_id: number | null; name: string | null; company: string | null; email: string | null }
  items: OrderItem[]
  total: string | null
  refunded: string | null
  currency: string
  invoice_pdf: string | null
  refunds?: { id: number; date: string | null; amount: string | null; reason: string }[]
}

export interface Licence {
  program_id?: number
  program_slug?: string
  program_title?: string
  status: 'not_submitted' | 'pending' | 'verified' | 'rejected'
  in_force?: boolean
  organisation?: string
  number?: string
  submitted_at?: string | null
  verified_at?: string | null
  valid_until?: string | null
  reason?: string
  source?: string
}

export interface Person {
  id: number
  name: string
  email: string
  registered: string
  professional: boolean
  address: Partial<Record<'company' | 'street' | 'postal_code' | 'city' | 'country' | 'phone', string>>
  membership: MembershipView | null
  licences: Licence[]
  orders: Order[]
  certificates: Record<string, unknown>[]
  education: Record<string, unknown>[]
  site_error: string | null
}

export interface AccessCourse {
  id: number
  title: string
  completed_steps: number
  total_steps: number
  percentage: number
}

export interface Access {
  membership: MembershipView
  learndash: boolean
  site: {
    user?: { professional?: boolean; roles?: string[] }
    licences?: Licence[]
    courses?: AccessCourse[]
    group_ids?: number[]
    paying?: boolean
  }
}

export interface StripeInvoice {
  id: string
  number: string | null
  date: string | null
  amount: string | null
  currency: string
  status: string
  pdf: string | null
}

export interface Documents {
  woo: Order[]
  stripe: StripeInvoice[]
  stripe_state: 'ok' | 'not_configured' | 'site_unavailable' | 'error'
  cms: Record<string, unknown>[]
  certificates: Record<string, unknown>[]
}

export interface EventSummary {
  id: number
  slug: string
  title: string
  status: string
  booking_status: string | null
  start: string | null
  end: string | null
  time: string | null
  time_end: string | null
  venue: string | null
  location: string | null
  audience: string | null
  price: string | null
  member_price: string | null
  currency: string
  seats_total: number | null
  seats_left: number | null
  booked: number | null
  waiting: number | null
  product_id: number | null
  url: string | null
}

export interface Participant {
  order_id: number
  order_number: string
  name: string
  company: string | null
  email: string
  quantity: number
  total: string | null
  currency: string | null
  order_status: string
  paid: boolean
  date: string | null
  user_id: number | null
  membership: MembershipStatus
  health_declaration: null | 'submitted' | 'missing'
  certificate: null | string
}

export interface AuditRow {
  id: number
  created_at: string
  actor_id: number | null
  actor_label: string
  entity: string
  entity_id: string
  action: string
  status: 'ok' | 'failed'
  error: string | null
  summary_en: string
  summary_de: string
  retry_of: number | null
  open?: boolean
}

export interface EventDetail {
  event: EventSummary
  participants: Participant[]
  participants_error: string | null
  history: AuditRow[]
}

export interface MembershipRow extends MembershipView {
  user_id: number
  name: string
  email: string
}

export interface Memberships {
  items: MembershipRow[]
  counts: Record<'expiring' | 'ending' | 'active' | 'free_access' | 'all', number>
  learndash: boolean
  errors: number
}

export interface AuditPage extends Paged<AuditRow> {
  open_failures: number
  last_72h: number
}

export interface TodayItem {
  key: string
  tone: 'attn' | 'bad' | 'calm'
  count: number
  params: Record<string, string | number>
  target: { screen: string; tab?: string }
}

export interface Today {
  date: string
  role: 'owner' | 'backoffice' | 'admin'
  items: TodayItem[]
  not_yet_available: string[]
}
