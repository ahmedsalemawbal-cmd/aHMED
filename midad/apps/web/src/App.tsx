import React from 'react'
import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useApp } from './lib/store'
import { IcSpinner } from './ui/icons'

import SiteLayout from './pages/site/SiteLayout'
import Home from './pages/site/Home'
import ServiceTemplates from './pages/site/ServiceTemplates'
import ServiceNoor from './pages/site/ServiceNoor'
import Pricing from './pages/site/Pricing'
import Faq from './pages/site/Faq'
import Contact from './pages/site/Contact'
import Legal from './pages/site/Legal'

import ChooseType from './pages/auth/ChooseType'
import Signup from './pages/auth/Signup'
import Login from './pages/auth/Login'
import Forgot from './pages/auth/Forgot'
import Welcome from './pages/auth/Welcome'

import AppLayout from './pages/app/AppLayout'
import Dashboard from './pages/app/Dashboard'
import Library from './pages/app/Library'
import TemplateDetail from './pages/app/TemplateDetail'
import Editor from './pages/app/Editor'
import MyFiles from './pages/app/MyFiles'
import Classroom from './pages/app/Classroom'
import NoorList from './pages/app/NoorList'
import NoorKey from './pages/app/NoorKey'
import NoorTableView from './pages/app/NoorTableView'
import Team from './pages/app/Team'
import Account from './pages/app/Account'
import SubscriberSettings from './pages/app/SubscriberSettings'
import Subscription from './pages/app/Subscription'
import ChoosePlan from './pages/app/ChoosePlan'
import Checkout from './pages/app/Checkout'
import Invoices from './pages/app/Invoices'
import InvoiceView from './pages/app/InvoiceView'
import Paywall from './pages/app/Paywall'
import Suspended from './pages/app/Suspended'

import AdminLayout from './pages/admin/AdminLayout'
import AdminOverview from './pages/admin/Overview'
import AdminSubscribers from './pages/admin/Subscribers'
import AdminSubscriberDetail from './pages/admin/SubscriberDetail'
import AdminSubscriptions from './pages/admin/Subscriptions'
import AdminInvoices from './pages/admin/AdminInvoices'
import AdminPlans from './pages/admin/Plans'
import AdminRoles from './pages/admin/Roles'
import AdminTemplates from './pages/admin/Templates'
import AdminTemplateEditor from './pages/admin/TemplateEditor'
import AdminAi from './pages/admin/AiUsage'
import AdminSettings from './pages/admin/Settings'
import AdminLog from './pages/admin/AuditLog'

import NotFound from './pages/site/NotFound'

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
  return (
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
  )
}
