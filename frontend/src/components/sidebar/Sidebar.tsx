import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { NavIcon } from '../common/NavIcon';

export interface NavItem {
    to: string;
    label: string;
    icon?: string;
    badge?: number;
}

export interface NavGroup {
    label: string;
    icon?: string;
    children: NavItem[];
}

export type NavEntry = NavItem | NavGroup;

interface SidebarProps {
    title: string;
    items: NavEntry[];
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
    const { pathname } = useLocation();
    const [expandedGroups, setExpandedGroups] = useState<Record<string, boolean>>(
        {},
    );

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
                {items.map((item) => {
                    if ('children' in item) {
                        const isActive = item.children.some(
                            (child) =>
                                pathname === child.to ||
                                pathname.startsWith(`${child.to}/`),
                        );
                        const isExpanded =
                            expandedGroups[item.label] ?? isActive;

                        return (
                            <div
                                key={item.label}
                                className={
                                    isActive
                                        ? 'nav-group nav-group-active'
                                        : 'nav-group'
                                }
                            >
                                <button
                                    type="button"
                                    className={
                                        isActive
                                            ? 'nav-link nav-group-toggle active'
                                            : 'nav-link nav-group-toggle'
                                    }
                                    aria-expanded={isExpanded}
                                    onClick={() =>
                                        setExpandedGroups((groups) => ({
                                            ...groups,
                                            [item.label]: !isExpanded,
                                        }))
                                    }
                                >
                                    <NavIcon
                                        name={item.icon ?? item.label}
                                    />
                                    {item.label}
                                    <span
                                        className="nav-group-chevron"
                                        aria-hidden="true"
                                    >
                                        ›
                                    </span>
                                </button>
                                {isExpanded && (
                                    <div className="nav-submenu">
                                        {item.children.map((child) => (
                                            <NavLink
                                                key={child.to}
                                                to={child.to}
                                                end
                                                onClick={onNavigate}
                                                className={({ isActive }) => {
                                                    const isTrainingRecordSubpage =
                                                        child.to ===
                                                            '/training' &&
                                                        pathname.startsWith(
                                                            '/training/',
                                                        ) &&
                                                        !pathname.startsWith(
                                                            '/training/program',
                                                        );
                                                    return isActive ||
                                                        isTrainingRecordSubpage
                                                        ? 'nav-link nav-sublink active'
                                                        : 'nav-link nav-sublink'
                                                }}
                                            >
                                                <NavIcon
                                                    name={
                                                        child.icon ??
                                                        child.label
                                                    }
                                                />
                                                {child.label}
                                                {child.badge !== undefined &&
                                                    child.badge > 0 && (
                                                        <span className="nav-badge">
                                                            {child.badge}
                                                        </span>
                                                    )}
                                            </NavLink>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    }

                    return (
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
                    );
                })}
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
