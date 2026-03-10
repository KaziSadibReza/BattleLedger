/**
 * Frontend Dashboard Layout Component
 */

import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Sidebar from './Sidebar';
import Header from './Header';
import Notifications from '../Notifications';
import Dashboard from '../../pages/Dashboard';
import UserWallet from '../../pages/UserWallet';
import MyTournaments from '../../pages/MyTournaments';
import Profile from '../../pages/Profile';

interface DashboardLayoutProps {
  apiUrl: string;
  nonce: string;
  currentUser: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
  logoutRedirect: string;
}

type Tab = 'dashboard' | 'wallet' | 'tournaments' | 'profile';

const DashboardLayout: React.FC<DashboardLayoutProps> = ({
  apiUrl,
  nonce,
  currentUser,
  logoutRedirect,
}) => {
  const [activeTab, setActiveTab] = useState<Tab>('dashboard');
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState(false);

  // Configure API Fetch
  useEffect(() => {
    if (nonce) {
      apiFetch.use(apiFetch.createNonceMiddleware(nonce));
    }
    if (apiUrl) {
      apiFetch.use(apiFetch.createRootURLMiddleware(apiUrl));
    }
  }, [apiUrl, nonce]);

  // Listen to hash changes for navigation
  useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash.replace('#', '');
      if (hash === 'dashboard' || hash === 'wallet' || hash === 'tournaments' || hash === 'profile') {
        setActiveTab(hash as Tab);
      }
    };
    window.addEventListener('hashchange', handleHashChange);
    return () => window.removeEventListener('hashchange', handleHashChange);
  }, []);

  const handleNavigate = (tab: Tab) => {
    setActiveTab(tab);
    window.location.hash = tab;
  };

  const handleNavigateFromDashboard = (tab: string) => {
    if (tab === 'dashboard' || tab === 'wallet' || tab === 'tournaments' || tab === 'profile') {
      handleNavigate(tab as Tab);
    }
  };

  const handleLogout = async () => {
    try {
      setIsLoading(true);
      await apiFetch({
        path: '/battle-ledger/v1/auth/logout',
        method: 'POST',
      });
      window.location.href = logoutRedirect;
    } catch (error) {
      console.error('Logout failed:', error);
      setIsLoading(false);
    }
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard':
        return <Dashboard currentUser={currentUser} onNavigate={handleNavigateFromDashboard} />;
      case 'wallet':
        return <UserWallet currentUser={currentUser} />;
      case 'tournaments':
        return <MyTournaments currentUser={currentUser} />;
      case 'profile':
        return <Profile currentUser={currentUser} />;
      default:
        return <Dashboard currentUser={currentUser} onNavigate={handleNavigateFromDashboard} />;
    }
  };

  return (
    <div className="battle-ledger-dashboard">
      <Sidebar
        activeTab={activeTab}
        onNavigate={handleNavigate}
        isCollapsed={isSidebarCollapsed}
        onToggleCollapse={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
        currentUser={currentUser}
      />

      <main className="bl-main">
        <Header
          currentUser={currentUser}
          activeTab={activeTab}
          onLogout={handleLogout}
          isLoading={isLoading}
        />
        
        <div className="bl-content-wrapper">
          {renderContent()}
        </div>
      </main>
      
      <Notifications />
    </div>
  );
};

export default DashboardLayout;
export { DashboardLayout };
