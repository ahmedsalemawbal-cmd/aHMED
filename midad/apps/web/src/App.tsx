import React from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useApp } from './lib/store'
import { IcSpinner } from './ui/icons'

/* ═══════════ الصفحات تُحمَّل عند طلبها ═══════════
   كانت كلُّها مستوردةً استيرادًا ثابتًا، فتدخل حزمةً واحدة: يفتح المشترك
   المكتبة فيُنزّل معها المحرّرَ ولوحةَ الإدارة وشاشةَ الدفع — ٤٥٢ كيلوبايتًا
   من TipTap وحده في صفحةٍ لا محرّر فيها.

   فما يُرى أوّلًا يبقى ثابتًا (الهيكل وصفحة الهبوط والدخول)، وما سواه
   يُجلب حين يُطلب. والانتظار الذي يوفّره أضعافُ الذي يُحدثه: الشبكة تجلب
   ثلاثين كيلوبايتًا في لمحة، والمتصفّح يُفسّر ميغابايتًا في ثانية. */

import SiteLayout from './pages/site/SiteLayout'
import Home from './pages/site/Home'
import ChooseType from './pages/auth/ChooseType'
import Signup from './pages/auth/Signup'
import Login from './pages/auth/Login'
import AppLayout from './pages/app/AppLayout'
import AdminLayout from './pages/admin/AdminLayout'
import NotFound from './pages/site/NotFound'

const ServiceTemplates = React.lazy(() => import('./pages/site/ServiceTemplates'))
const ServiceNoor = React.lazy(() => import('./pages/site/ServiceNoor'))
const Pricing = React.lazy(() => import('./pages/site/Pricing'))
const Faq = React.lazy(() => import('./pages/site/Faq'))
const Contact = React.lazy(() => import('./pages/site/Contact'))
const Legal = React.lazy(() => import('./pages/site/Legal'))
const Forgot = React.lazy(() => import('./pages/auth/Forgot'))
const Welcome = React.lazy(() => import('./pages/auth/Welcome'))
const Dashboard = React.lazy(() => import('./pages/app/Dashboard'))
const Library = React.lazy(() => import('./pages/app/Library'))
const TemplateDetail = React.lazy(() => import('./pages/app/TemplateDetail'))
const Editor = React.lazy(() => import('./pages/app/Editor'))
const MyFiles = React.lazy(() => import('./pages/app/MyFiles'))
const Classroom = React.lazy(() => import('./pages/app/Classroom'))
const NoorList = React.lazy(() => import('./pages/app/NoorList'))
const NoorKey = React.lazy(() => import('./pages/app/NoorKey'))
const NoorTableView = React.lazy(() => import('./pages/app/NoorTableView'))
const Team = React.lazy(() => import('./pages/app/Team'))
const Account = React.lazy(() => import('./pages/app/Account'))
const SubscriberSettings = React.lazy(() => import('./pages/app/SubscriberSettings'))
const Subscription = React.lazy(() => import('./pages/app/Subscription'))
const ChoosePlan = React.lazy(() => import('./pages/app/ChoosePlan'))
const Checkout = React.lazy(() => import('./pages/app/Checkout'))
const Invoices = React.lazy(() => import('./pages/app/Invoices'))
const InvoiceView = React.lazy(() => import('./pages/app/InvoiceView'))
const Paywall = React.lazy(() => import('./pages/app/Paywall'))
const Suspended = React.lazy(() => import('./pages/app/Suspended'))
const AdminOverview = React.lazy(() => import('./pages/admin/Overview'))
const AdminSubscribers = React.lazy(() => import('./pages/admin/Subscribers'))
const AdminSubscriberDetail = React.lazy(() => import('./pages/admin/SubscriberDetail'))
const AdminSubscriptions = React.lazy(() => import('./pages/admin/Subscriptions'))
const AdminInvoices = React.lazy(() => import('./pages/admin/AdminInvoices'))
const AdminPlans = React.lazy(() => import('./pages/admin/Plans'))
const AdminRoles = React.lazy(() => import('./pages/admin/Roles'))
const AdminTemplates = React.lazy(() => import('./pages/admin/Templates'))
const AdminTemplateEditor = React.lazy(() => import('./pages/admin/TemplateEditor'))
const AdminAi = React.lazy(() => import('./pages/admin/AiUsage'))
const AdminSettings = React.lazy(() => import('./pages/admin/Settings'))
const AdminLog = React.lazy(() => import('./pages/admin/AuditLog'))

function FullLoader() {
  return (
    <div style={{
      minHeight: '100vh', display: 'grid', placeItems: 'center',
      gap: 14, alignContent: 'center', color: 'var(--mdd-accent)',
    }}>
      <IcSpinner size={34} />
      <span style={{ fontSize: 13, color: 'var(--mdd-text-3)' }}>جارٍ فتح مِداد…</span>
    </div>
  )
}

/** الشاشات التي تبقى مفتوحة لمن انتهت تجربته. */
const OPEN_WHEN_EXPIRED = ['/app/subscription', '/app/plans', '/app/checkout', '/app/invoices', '/app/invoice', '/app/account']

function Protected({ children }: { children: React.ReactNode }) {
  const { access, ready } = useApp()
  const loc = useLocation()
  if (!ready || access === 'loading') return <FullLoader />
  if (access === 'anon') return <Navigate to="/login" replace state={{ from: loc.pathname }} />
  if (access === 'suspended' || access === 'member_suspended') return <Navigate to="/suspended" replace />
  if (access === 'expired' && !OPEN_WHEN_EXPIRED.some((p) => loc.pathname.startsWith(p))) {
    return <Navigate to="/paywall" replace />
  }
  return <>{children}</>
}

function AdminOnly({ children }: { children: React.ReactNode }) {
  const { isAdmin, ready, access } = useApp()
  if (!ready || access === 'loading') return <FullLoader />
  if (access === 'anon') return <Navigate to="/login" replace />
  if (!isAdmin) return <Navigate to="/app" replace />
  return <>{children}</>
}

export default function App() {
  /* والصفحة المؤجَّلة تحتاج حدًّا ينتظرها. و`FullLoader` هو نفسه الذي
     ينتظر جلسة المستخدم، فلا يرى المشترك شكلين لانتظارٍ واحد. */
  return (
    <React.Suspense fallback={<FullLoader />}>
    <Routes>
      <Route element={<SiteLayout />}>
        <Route path="/" element={<Home />} />
        <Route path="/service/templates" element={<ServiceTemplates />} />
        <Route path="/service/noor" element={<ServiceNoor />} />
        <Route path="/pricing" element={<Pricing />} />
        <Route path="/faq" element={<Faq />} />
        <Route path="/contact" element={<Contact />} />
        <Route path="/terms" element={<Legal kind="terms" />} />
        <Route path="/privacy" element={<Legal kind="privacy" />} />
      </Route>

      <Route path="/join" element={<ChooseType />} />
      <Route path="/join/school" element={<Signup type="school" />} />
      <Route path="/join/teacher" element={<Signup type="teacher" />} />
      <Route path="/login" element={<Login />} />
      <Route path="/forgot" element={<Forgot />} />
      <Route path="/welcome" element={<Welcome />} />

      <Route path="/paywall" element={<Paywall />} />
      <Route path="/suspended" element={<Suspended />} />

      <Route path="/app/doc/:id" element={<Protected><Editor /></Protected>} />

      <Route path="/app" element={<Protected><AppLayout /></Protected>}>
        <Route index element={<Dashboard />} />
        <Route path="library" element={<Library />} />
        <Route path="library/:slug" element={<Library />} />
        <Route path="template/:slug" element={<TemplateDetail />} />
        <Route path="files" element={<MyFiles />} />
        <Route path="classroom" element={<Classroom />} />
        <Route path="noor" element={<NoorList />} />
        <Route path="noor/key" element={<NoorKey />} />
        <Route path="noor/:id" element={<NoorTableView />} />
        <Route path="team" element={<Team />} />
        <Route path="account" element={<Account />} />
        <Route path="settings" element={<SubscriberSettings />} />
        <Route path="subscription" element={<Subscription />} />
        <Route path="plans" element={<ChoosePlan />} />
        <Route path="checkout" element={<Checkout />} />
        <Route path="checkout/:invoiceId" element={<Checkout />} />
        <Route path="invoices" element={<Invoices />} />
        <Route path="invoice/:id" element={<InvoiceView />} />
      </Route>

      <Route path="/admin" element={<AdminOnly><AdminLayout /></AdminOnly>}>
        <Route index element={<AdminOverview />} />
        <Route path="subscribers" element={<AdminSubscribers />} />
        <Route path="subscriber/:id" element={<AdminSubscriberDetail />} />
        <Route path="subscriptions" element={<AdminSubscriptions />} />
        <Route path="invoices" element={<AdminInvoices />} />
        <Route path="plans" element={<AdminPlans />} />
        <Route path="roles" element={<AdminRoles />} />
        <Route path="templates" element={<AdminTemplates />} />
        <Route path="template/:id" element={<AdminTemplateEditor />} />
        <Route path="ai" element={<AdminAi />} />
        <Route path="settings" element={<AdminSettings />} />
        <Route path="log" element={<AdminLog />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
    </React.Suspense>
  )
}
