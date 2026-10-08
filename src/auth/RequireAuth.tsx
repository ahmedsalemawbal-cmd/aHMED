import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';

export function RequireAuth({ children }: { children: ReactNode }) {
  const { session } = useAuth();
  const location = useLocation();
  if (session === undefined) {
    return (
      <div className="min-h-dvh bg-surface" aria-busy="true" aria-label="جارٍ التحميل">
        <div className="mx-4 mt-12 h-8 w-40 rounded-md bg-surface-sunken" />
        <div className="mx-4 mt-6 h-24 rounded-lg bg-surface-sunken" />
      </div>
    );
  }
  if (!session) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }
  return <>{children}</>;
}
