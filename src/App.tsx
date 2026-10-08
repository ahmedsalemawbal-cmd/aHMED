import { lazy, Suspense } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from '@/auth/AuthProvider';
import { RequireAuth } from '@/auth/RequireAuth';
import Login from '@/routes/Login';

const Home = lazy(() => import('@/routes/Home'));

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Suspense fallback={<div className="min-h-dvh bg-surface" aria-busy="true" />}>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route
              path="/"
              element={
                <RequireAuth>
                  <Home />
                </RequireAuth>
              }
            />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
    </AuthProvider>
  );
}
