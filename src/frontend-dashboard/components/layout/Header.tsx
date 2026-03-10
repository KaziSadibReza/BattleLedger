/**
 * Frontend Dashboard Header Component
 */

import React, { useState } from 'react';
import { LogOut } from 'lucide-react';
import NotificationBell from '../NotificationBell';

interface HeaderProps {
  currentUser: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
  activeTab: string;
  onLogout: () => void;
  isLoading: boolean;
}

const Header: React.FC<HeaderProps> = ({
  currentUser,
  activeTab,
  onLogout,
  isLoading,
}) => {
  const [showUserMenu, setShowUserMenu] = useState(false);

  const getPageTitle = () => {
    switch (activeTab) {
      case 'wallet':
        return 'My Wallet';
      case 'tournaments':
        return 'My Tournaments';
      case 'matches':
        return 'My Matches';
      case 'profile':
        return 'My Profile';
      default:
        return 'Dashboard';
    }
  };

  return (
    <header className="bl-header">
      <h3>{getPageTitle()}</h3>
      
      <div className="bl-header-actions">
        {/* Notifications */}
        <NotificationBell />

        {/* User Menu */}
        <div className="bl-user-menu">
          <button
            className="bl-user-menu-toggle"
            onClick={() => setShowUserMenu(!showUserMenu)}
          >
            <div className="bl-user-avatar-small">
              {currentUser.avatar ? (
                <img src={currentUser.avatar} alt={currentUser.displayName} />
              ) : (
                <span className="dashicons dashicons-admin-users"></span>
              )}
            </div>
            <span className="bl-user-name-header">{currentUser.displayName}</span>
          </button>

          {showUserMenu && (
            <div className="bl-user-dropdown">
              <div className="bl-user-dropdown-header">
                <div className="bl-user-dropdown-name">{currentUser.displayName}</div>
                <div className="bl-user-dropdown-email">{currentUser.email}</div>
              </div>
              <div className="bl-user-dropdown-divider"></div>
              <button
                className="bl-user-dropdown-item"
                onClick={onLogout}
                disabled={isLoading}
              >
                <LogOut size={16} />
                <span>{isLoading ? 'Logging out...' : 'Logout'}</span>
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
};

export default Header;
