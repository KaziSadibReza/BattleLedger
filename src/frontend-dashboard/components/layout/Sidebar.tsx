/**
 * Frontend Dashboard Sidebar Component
 */

import React from 'react';
import { Wallet, Trophy, User, ChevronLeft, ChevronRight, LayoutDashboard } from 'lucide-react';

interface SidebarProps {
  activeTab: string;
  onNavigate: (tab: any) => void;
  isCollapsed: boolean;
  onToggleCollapse: () => void;
  currentUser: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
}

const Sidebar: React.FC<SidebarProps> = ({
  activeTab,
  onNavigate,
  isCollapsed,
  onToggleCollapse,
  currentUser,
}) => {
  return (
    <aside className={`bl-sidebar ${isCollapsed ? 'collapsed' : ''}`}>

      {/* User Info */}
      {!isCollapsed && (
        <div className="bl-user-info">
          <div className="bl-user-avatar">
            {currentUser.avatar ? (
              <img src={currentUser.avatar} alt={currentUser.displayName} />
            ) : (
              <span className="dashicons dashicons-admin-users"></span>
            )}
          </div>
          <div className="bl-user-details">
            <div className="bl-user-name">{currentUser.displayName}</div>
            <div className="bl-user-email">{currentUser.email}</div>
          </div>
        </div>
      )}

      {/* Navigation */}
      <nav className="bl-nav">
        <button
          className={activeTab === 'dashboard' ? 'active' : ''}
          onClick={() => onNavigate('dashboard')}
          title="Dashboard"
        >
          <LayoutDashboard size={20} />
          {!isCollapsed && <span>Dashboard</span>}
        </button>
        
        <button
          className={activeTab === 'wallet' ? 'active' : ''}
          onClick={() => onNavigate('wallet')}
          title="My Wallet"
        >
          <Wallet size={20} />
          {!isCollapsed && <span>My Wallet</span>}
        </button>
        
        <button
          className={activeTab === 'tournaments' ? 'active' : ''}
          onClick={() => onNavigate('tournaments')}
          title="My Tournaments"
        >
          <Trophy size={20} />
          {!isCollapsed && <span>My Tournaments</span>}
        </button>
        
        <button
          className={activeTab === 'profile' ? 'active' : ''}
          onClick={() => onNavigate('profile')}
          title="My Profile"
        >
          <User size={20} />
          {!isCollapsed && <span>My Profile</span>}
        </button>
      </nav>

      {/* Collapse Toggle */}
      <button
        className="bl-sidebar-toggle"
        onClick={onToggleCollapse}
        title={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
      >
        {isCollapsed ? <ChevronRight size={20} /> : <ChevronLeft size={20} />}
      </button>
    </aside>
  );
};

export default Sidebar;
