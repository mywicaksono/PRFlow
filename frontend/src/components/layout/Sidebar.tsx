import { NavLink } from 'react-router-dom';

import { useAuth } from '@/features/auth/context/AuthContext';

const commonNavItems = [{ label: 'Dashboard', to: '/dashboard' }];
const staffNavItems = [{ label: 'Requests', to: '/requests' }];
const approverNavItems = [{ label: 'Pending Approvals', to: '/approvals/pending' }];

export function Sidebar() {
  const { user } = useAuth();
  const role = user?.role;

  const isApproverRole = role === 'admin' || role === 'supervisor' || role === 'manager' || role === 'finance';

  // Role-based menu is handled in one place so UI permissions stay predictable
  // before we introduce a richer permission system in later stages.
  const navItems = [
    ...commonNavItems,
    ...(role === 'staff' || role === 'admin' ? staffNavItems : []),
    ...(isApproverRole ? approverNavItems : []),
  ];

  return (
    <aside className="w-64 border-r border-slate-200 bg-white p-4">
      <div className="mb-1 text-xl font-bold text-slate-800">PRFlow</div>
      <div className="mb-8 text-xs uppercase tracking-wide text-slate-500">{role ?? 'guest'}</div>
      <nav className="space-y-1">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              `block rounded-md px-3 py-2 text-sm font-medium ${
                isActive ? 'bg-slate-100 text-slate-900' : 'text-slate-600 hover:bg-slate-50'
              }`
            }
          >
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  );
}
