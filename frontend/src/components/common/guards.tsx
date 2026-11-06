import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { Loader } from './Feedback';

export function RequireAuth() {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) return <Loader fullPage />;
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />;

  return <Outlet />;
}

export function RequireMaster() {
  const { user } = useAuth();

  if (!user?.is_master) return <Navigate to="/dashboard" replace />;

  return <Outlet />;
}
