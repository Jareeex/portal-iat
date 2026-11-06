import { NavLink } from 'react-router-dom';

export interface NavItem {
  to: string;
  label: string;
}

interface SidebarProps {
  title: string;
  items: NavItem[];
  footerLink?: NavItem;
  open: boolean;
  onNavigate: () => void;
}

export function Sidebar({ title, items, footerLink, open, onNavigate }: SidebarProps) {
  return (
    <aside className={open ? 'sidebar open' : 'sidebar'}>
      <div className="sidebar-brand">
        <img className="brand-mark-image" src="/logo-only.svg" alt="" />
        <span>{title}</span>
      </div>

      <nav className="sidebar-nav">
        {items.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            onClick={onNavigate}
            className={({ isActive }) => (isActive ? 'nav-link active' : 'nav-link')}
          >
            <span className="nav-dot">{item.label.charAt(0).toUpperCase()}</span>
            {item.label}
          </NavLink>
        ))}
      </nav>

      {footerLink && (
        <div className="sidebar-footer">
          <NavLink to={footerLink.to} onClick={onNavigate} className="nav-link">
            {footerLink.label}
          </NavLink>
        </div>
      )}
    </aside>
  );
}
