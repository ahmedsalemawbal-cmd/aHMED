import { lazy, Suspense, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { QueryClientProvider } from '@tanstack/react-query';
import { AuthProvider } from '@/auth/AuthProvider';
import { RequireAuth } from '@/auth/RequireAuth';
import { ToastProvider } from '@/components/app/toast/ToastProvider';
import { queryClient } from '@/lib/queryClient';
import Login from '@/routes/Login';

const Today = lazy(() => import('@/routes/Today'));
const NewLead = lazy(() => import('@/features/new-lead/NewLead'));
const Message = lazy(() => import('@/features/message/Message'));
const Leads = lazy(() => import('@/routes/Leads'));
const Lead = lazy(() => import('@/routes/Lead'));
const LeadEdit = lazy(() => import('@/routes/LeadEdit'));
const NewVisit = lazy(() => import('@/routes/NewVisit'));
const Pipeline = lazy(() => import('@/routes/Pipeline'));
const Tasks = lazy(() => import('@/routes/Tasks'));
const Quotes = lazy(() => import('@/routes/Quotes'));
const QuoteEditor = lazy(() => import('@/routes/QuoteEditor'));
const QuotePreview = lazy(() => import('@/routes/QuotePreview'));
const More = lazy(() => import('@/routes/More'));
const Settings = lazy(() => import('@/routes/Settings'));
const DevUi = lazy(() => import('@/routes/dev/DevUi'));

/** /dev/ui exists only in development, or in test builds with VITE_DEV_UI=1. Never in production. */
const devUiEnabled = import.meta.env.DEV || import.meta.env.VITE_DEV_UI === '1';

function Private({ children }: { children: ReactNode }) {
  return <RequireAuth>{children}</RequireAuth>;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ToastProvider>
          <BrowserRouter basename={import.meta.env.BASE_URL}>
            <Suspense fallback={<div className="min-h-dvh bg-surface" aria-busy="true" />}>
              <Routes>
                <Route path="/login" element={<Login />} />
                {devUiEnabled ? <Route path="/dev/ui" element={<DevUi />} /> : null}
                <Route path="/" element={<Private><Today /></Private>} />
                <Route path="/leads/new" element={<Private><NewLead /></Private>} />
                <Route path="/leads/:id/message" element={<Private><Message /></Private>} />
                <Route path="/leads/:id/edit" element={<Private><LeadEdit /></Private>} />
                <Route path="/leads/:id/visit" element={<Private><NewVisit /></Private>} />
                <Route path="/leads/:id/quotes/new" element={<Private><QuoteEditor /></Private>} />
                <Route path="/leads/:id" element={<Private><Lead /></Private>} />
                <Route path="/leads" element={<Private><Leads /></Private>} />
                <Route path="/pipeline" element={<Private><Pipeline /></Private>} />
                <Route path="/tasks" element={<Private><Tasks /></Private>} />
                <Route path="/quotes" element={<Private><Quotes /></Private>} />
                <Route path="/quotes/:id" element={<Private><QuoteEditor /></Private>} />
                <Route path="/quotes/:id/preview" element={<Private><QuotePreview /></Private>} />
                <Route path="/more" element={<Private><More /></Private>} />
                <Route path="/settings/*" element={<Private><Settings /></Private>} />
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </Suspense>
          </BrowserRouter>
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
