/**
 * BattleLedger Frontend Auth Entry Point
 * 
 * This file initializes React auth components from shortcode containers
 */

// Declare jQuery as a global (WordPress includes it)
declare global {
  interface Window {
    jQuery?: (selector: Document | string) => {
      on: (event: string, callback: () => void) => void;
    };
  }
}

import React from 'react';
import { createRoot } from 'react-dom/client';
import AuthContainer from './components/AuthContainer';
import './styles/auth.scss';

interface AuthProps {
  component: 'auth' | 'login' | 'signup' | 'profile' | 'logout';
  apiUrl: string;
  nonce: string;
  currentUser?: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  } | null;
  mode?: 'login' | 'signup' | 'both';
  popup?: boolean;
  buttonText?: string;
  redirect?: string;
  googleEnabled?: boolean;
  otpEnabled?: boolean;
  className?: string;
  showAvatar?: boolean;
  showName?: boolean;
  dashboardLink?: string;
  text?: string;
}

/**
 * Initialize all auth containers on the page
 */
function initializeAuthContainers() {
  const containers = document.querySelectorAll<HTMLDivElement>('.battleledger-auth-container');

  containers.forEach((container) => {
    const propsData = container.dataset.props;
    const component = container.dataset.component as AuthProps['component'];

    if (!propsData || !component) {
      console.warn('BattleLedger Auth: Missing props or component type', container);
      return;
    }

    try {
      const props: AuthProps = JSON.parse(propsData);
      props.component = component;

      // Create React root and render
      const root = createRoot(container);
      root.render(
        <React.StrictMode>
          <AuthContainer {...props} />
        </React.StrictMode>
      );
    } catch (error) {
      console.error('BattleLedger Auth: Failed to parse props', error);
    }
  });
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeAuthContainers);
} else {
  initializeAuthContainers();
}

// Re-initialize on AJAX complete (for dynamic content)
if (typeof window.jQuery !== 'undefined') {
  window.jQuery(document).on('ajaxComplete', () => {
    // Small delay to allow DOM updates
    setTimeout(initializeAuthContainers, 100);
  });
}

// Export for manual initialization
export { initializeAuthContainers, AuthContainer };
