import { useAuth } from '../../hooks/useAuth';

export function Navbar({ onMenu }: { onMenu: () => void }) {
  const { user, logout } = useAuth();

  return (
    <header className="navbar">
      <button type="button" className="icon-btn menu-btn" onClick={onMenu} aria-label="Buka menu">
        ☰
      </button>

      <div className="navbar-spacer" />

      {user && (
        <div className="navbar-user">
          <span className="user-avatar" aria-hidden="true">
            {user.name.trim().charAt(0).toUpperCase()}
          </span>
          <div className="navbar-user-text">
            <strong>{user.name}</strong>
            <span className="muted">
              {user.is_master ? 'Master' : user.department?.code ?? 'User'}
              {user.full_access && !user.is_master ? ' · Full access' : ''}
            </span>
          </div>
          <button type="button" className="btn btn-ghost" onClick={() => void logout()}>
            Logout
          </button>
        </div>
      )}
    </header>
  );
}
