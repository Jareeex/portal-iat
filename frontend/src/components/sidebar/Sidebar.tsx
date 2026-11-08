import { NavLink } from 'react-router-dom';
import { NavIcon } from '../common/NavIcon';

export interface NavItem {
    to: string;
    label: string;
    icon?: string;
    badge?: number;
}

interface SidebarProps {
    title: string;
    items: NavItem[];
    footerLink?: NavItem;
    open: boolean;
    onNavigate: () => void;
}

export function Sidebar({
    title,
    items,
    footerLink,
    open,
    onNavigate,
}: SidebarProps) {
    return (
        <aside className={open ? 'sidebar open' : 'sidebar'}>
            <div className="sidebar-brand">
                <img className="brand-mark-image" src="/logo-only.svg" alt="" />
                <span className="sidebar-brand-copy">
                    <strong>{title}</strong>
                    <span>AVIATION WORKSPACE</span>
                </span>
            </div>

            <nav className="sidebar-nav">
                {items.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        onClick={onNavigate}
                        className={({ isActive }) =>
                            isActive ? 'nav-link active' : 'nav-link'
                        }
                    >
                        <NavIcon name={item.icon ?? item.label} />
                        {item.label}
                        {item.badge !== undefined && item.badge > 0 && (
                            <span className="nav-badge">{item.badge}</span>
                        )}
                    </NavLink>
                ))}
            </nav>

            {footerLink && (
                <div className="sidebar-footer">
                    <NavLink
                        to={footerLink.to}
                        onClick={onNavigate}
                        className="nav-link"
                    >
                        <NavIcon name={footerLink.icon ?? footerLink.label} />
                        {footerLink.label}
                    </NavLink>
                </div>
            )}
        </aside>
    );
}
