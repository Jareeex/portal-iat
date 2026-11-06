import { createBrowserRouter, Navigate } from 'react-router-dom';
import AuthLayout from '../layouts/AuthLayout';
import UserLayout from '../layouts/UserLayout';
import MasterLayout from '../layouts/MasterLayout';
import { RequireAuth, RequireMaster } from '../components/common/guards';
import LoginPage from '../features/auth/LoginPage';
import DashboardPage from '../features/dashboard/DashboardPage';
import PortalPage from '../features/portal/PortalPage';
import UsersPage from '../features/users/UsersPage';
import DepartmentsPage from '../features/departments/DepartmentsPage';
import PagesPage from '../features/pages/PagesPage';
import AccessPage from '../features/access-management/AccessPage';

export const router = createBrowserRouter([
  {
    element: <AuthLayout />,
    children: [{ path: '/login', element: <LoginPage /> }],
  },
  {
    element: <RequireAuth />,
    children: [
      // Portal untuk semua user: dashboard + halaman yang di-assign (dinamis dari backend).
      {
        element: <UserLayout />,
        children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          { path: '*', element: <PortalPage /> },
        ],
      },
      // Panel master.
      {
        element: <RequireMaster />,
        children: [
          {
            path: '/master',
            element: <MasterLayout />,
            children: [
              { index: true, element: <Navigate to="users" replace /> },
              { path: 'users', element: <UsersPage /> },
              { path: 'departments', element: <DepartmentsPage /> },
              { path: 'pages', element: <PagesPage /> },
              { path: 'access', element: <AccessPage /> },
            ],
          },
        ],
      },
    ],
  },
]);
