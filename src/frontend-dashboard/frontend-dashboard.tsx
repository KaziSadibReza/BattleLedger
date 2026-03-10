/**
 * Frontend Dashboard Entry Point
 */

import React from 'react';
import { createRoot } from 'react-dom/client';
import { DashboardLayout } from './components/layout/DashboardLayout';
import './styles/dashboard.scss';

interface DashboardProps {
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

/**
 * Initialize all dashboard containers on the page
 */
export function initializeDashboardContainers(): void {
  const containers = document.querySelectorAll('.battleledger-dashboard-container');
  
  containers.forEach((container) => {
    const propsAttr = container.getAttribute('data-props');
    
    if (!propsAttr) {
      console.error('BattleLedger Dashboard: Missing data-props attribute');
      return;
    }

    try {
      const props: DashboardProps = JSON.parse(propsAttr);
      const root = createRoot(container);
      
      root.render(
        <React.StrictMode>
          <DashboardLayout
            apiUrl={props.apiUrl}
            nonce={props.nonce}
            currentUser={props.currentUser}
            logoutRedirect={props.logoutRedirect}
          />
        </React.StrictMode>
      );
    } catch (error) {
      console.error('BattleLedger Dashboard: Failed to initialize', error);
    }
  });
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeDashboardContainers);
} else {
  initializeDashboardContainers();
}

