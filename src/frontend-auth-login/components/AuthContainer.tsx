import React, { useState } from 'react';
import { User } from 'lucide-react';
import AuthModal from './AuthModal';
import UserProfile from './UserProfile';

interface FormSettings {
  colors?: Record<string, string>;
  typography?: Record<string, string>;
  labels?: Record<string, string>;
  features?: Record<string, boolean | string>;
}

interface AuthContainerProps {
  component: 'auth' | 'login' | 'signup' | 'profile' | 'logout';
  apiUrl: string;
  nonce: string;
  currentUser?: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  } | null;
  // Auth modal props
  mode?: 'login' | 'signup' | 'both';
  popup?: boolean;
  buttonText?: string;
  redirect?: string;
  googleEnabled?: boolean;
  otpEnabled?: boolean;
  className?: string;
  // Profile props
  showAvatar?: boolean;
  showName?: boolean;
  dashboardLink?: string;
  // Logout props
  text?: string;
  // Form customization
  formSettings?: FormSettings;
}

/**
 * Main Auth Container component that renders the appropriate auth component
 * based on the component prop and user state
 */
export const AuthContainer: React.FC<AuthContainerProps> = ({
  component,
  apiUrl,
  nonce,
  currentUser,
  mode = 'both',
  popup = false,
  buttonText = 'Sign In',
  redirect,
  googleEnabled = false,
  otpEnabled = true,
  className = '',
  showAvatar = true,
  showName = true,
  dashboardLink,
  text = 'Sign Out',
  formSettings,
}) => {
  const [isModalOpen, setIsModalOpen] = useState(false);
  const isLoggedIn = !!currentUser;

  // Profile component - only show when logged in
  if (component === 'profile') {
    if (!isLoggedIn || !currentUser) {
      return (
        <button
          className={`ak-auth-trigger-btn ${className}`}
          onClick={() => setIsModalOpen(true)}
        >
          <User size={20} />
          <span>{buttonText}</span>
        </button>
      );
    }

    return (
      <>
        <UserProfile
          user={currentUser}
          apiUrl={apiUrl}
          nonce={nonce}
          showAvatar={showAvatar}
          showName={showName}
          dashboardLink={dashboardLink}
          logoutRedirect={redirect}
          className={className}
        />
      </>
    );
  }

  // Logout component
  if (component === 'logout') {
    if (!isLoggedIn) return null;

    const handleLogout = async () => {
      try {
        await fetch(`${apiUrl}/logout`, {
          method: 'POST',
          credentials: 'include',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
          },
        });
        window.location.href = redirect || '/';
      } catch (error) {
        console.error('Logout failed:', error);
      }
    };

    return (
      <button className={`ak-logout-btn ${className}`} onClick={handleLogout}>
        {text}
      </button>
    );
  }

  // If user is logged in, show profile dropdown for auth components
  if (isLoggedIn && currentUser) {
    return (
      <UserProfile
        user={currentUser}
        apiUrl={apiUrl}
        nonce={nonce}
        showAvatar={true}
        showName={true}
        dashboardLink={dashboardLink}
        logoutRedirect={redirect}
        className={className}
      />
    );
  }

  // Auth components (login, signup, auth)
  const getMode = (): 'login' | 'signup' | 'both' => {
    if (component === 'login') return 'login';
    if (component === 'signup') return 'signup';
    return mode;
  };

  // Popup mode - show button that triggers modal
  if (popup) {
    return (
      <>
        <button
          className={`ak-auth-trigger-btn ${className}`}
          onClick={() => setIsModalOpen(true)}
        >
          <User size={20} />
          <span>{buttonText}</span>
        </button>

        <AuthModal
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          mode={getMode()}
          apiUrl={apiUrl}
          nonce={nonce}
          googleEnabled={googleEnabled}
          otpEnabled={otpEnabled}
          redirect={redirect}
          formSettings={formSettings}
        />
      </>
    );
  }

  // Inline mode - show form directly
  return (
    <div className={`ak-auth-inline ${className}`}>
      <AuthModal
        isOpen={true}
        onClose={() => {}}
        mode={getMode()}
        apiUrl={apiUrl}
        nonce={nonce}
        googleEnabled={googleEnabled}
        otpEnabled={otpEnabled}
        redirect={redirect}
        inline={true}
        formSettings={formSettings}
      />
    </div>
  );
};

export default AuthContainer;
