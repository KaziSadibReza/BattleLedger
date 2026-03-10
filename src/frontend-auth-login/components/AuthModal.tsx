import React, { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import LoginForm from './LoginForm';
import SignupForm from './SignupForm';
import { transformUser } from '../hooks/useAuth';

interface FormSettings {
  colors?: Record<string, string>;
  typography?: Record<string, string>;
  labels?: Record<string, string>;
  features?: Record<string, boolean | string>;
}

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  mode?: 'login' | 'signup' | 'both';
  apiUrl: string;
  nonce: string;
  googleEnabled: boolean;
  otpEnabled: boolean;
  redirect?: string;
  onSuccess?: (user: any) => void;
  inline?: boolean; // Render inline without overlay
  formSettings?: FormSettings;
}

export const AuthModal: React.FC<AuthModalProps> = ({
  isOpen,
  onClose,
  mode = 'both',
  apiUrl,
  nonce,
  googleEnabled,
  otpEnabled,
  redirect,
  onSuccess,
  inline = false,
  formSettings,
}) => {
  const [currentView, setCurrentView] = useState<'login' | 'signup'>(
    mode === 'signup' ? 'signup' : 'login'
  );
  const [error, setError] = useState<string | null>(null);

  // Reset view when modal opens
  useEffect(() => {
    if (isOpen) {
      setCurrentView(mode === 'signup' ? 'signup' : 'login');
      setError(null);
    }
  }, [isOpen, mode]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  // Prevent body scroll when modal is open (only for popup mode)
  useEffect(() => {
    if (isOpen && !inline) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen, inline]);

  const handleSuccess = (apiUser: any) => {
    const user = transformUser(apiUser);
    onSuccess?.(user);
    onClose();

    if (redirect) {
      window.location.href = redirect;
    } else {
      window.location.reload();
    }
  };

  const handleError = (message: string) => {
    setError(message);
    setTimeout(() => setError(null), 5000);
  };

  if (!isOpen) return null;

  // Inline mode - render form directly without overlay
  if (inline) {
    return (
      <div className="ak-auth-inline-wrapper">
        {/* Error Message */}
        {error && (
          <div className="ak-error-message">
            <span>{error}</span>
            <button onClick={() => setError(null)} aria-label="Dismiss">
              <X size={16} />
            </button>
          </div>
        )}

        {/* Tab Switcher (if both modes enabled) */}
        {mode === 'both' && (
          <div className="ak-auth-tabs">
            <button
              className={currentView === 'login' ? 'active' : ''}
              onClick={() => {
                setCurrentView('login');
                setError(null);
              }}
            >
              Sign In
            </button>
            <button
              className={currentView === 'signup' ? 'active' : ''}
              onClick={() => {
                setCurrentView('signup');
                setError(null);
              }}
            >
              Sign Up
            </button>
          </div>
        )}

        {/* Forms */}
        {currentView === 'login' ? (
          <LoginForm
            apiUrl={apiUrl}
            nonce={nonce}
            googleEnabled={googleEnabled}
            otpEnabled={otpEnabled}
            onSuccess={handleSuccess}
            onError={handleError}
            onSwitchToSignup={mode === 'both' ? () => setCurrentView('signup') : undefined}
            formSettings={formSettings}
          />
        ) : (
          <SignupForm
            apiUrl={apiUrl}
            nonce={nonce}
            googleEnabled={googleEnabled}
            onSuccess={handleSuccess}
            onError={handleError}
            onSwitchToLogin={mode === 'both' ? () => setCurrentView('login') : undefined}
            formSettings={formSettings}
          />
        )}
      </div>
    );
  }

  // Modal mode - render with overlay
  return (
    <div className="ak-auth-overlay" onClick={onClose}>
      <div className="ak-auth-modal" onClick={(e) => e.stopPropagation()}>
        <button className="ak-modal-close" onClick={onClose} aria-label="Close">
          <X size={24} />
        </button>

        <div className="ak-modal-content">
          {/* Error Message */}
          {error && (
            <div className="ak-error-message">
              <span>{error}</span>
              <button onClick={() => setError(null)} aria-label="Dismiss">
                <X size={16} />
              </button>
            </div>
          )}

          {/* Tab Switcher (if both modes enabled) */}
          {mode === 'both' && (
            <div className="ak-auth-tabs">
              <button
                className={currentView === 'login' ? 'active' : ''}
                onClick={() => {
                  setCurrentView('login');
                  setError(null);
                }}
              >
                Sign In
              </button>
              <button
                className={currentView === 'signup' ? 'active' : ''}
                onClick={() => {
                  setCurrentView('signup');
                  setError(null);
                }}
              >
                Sign Up
              </button>
            </div>
          )}

          {/* Forms */}
          {currentView === 'login' ? (
            <LoginForm
              apiUrl={apiUrl}
              nonce={nonce}
              googleEnabled={googleEnabled}
              otpEnabled={otpEnabled}
              onSuccess={handleSuccess}
              onError={handleError}
              onSwitchToSignup={mode === 'both' ? () => setCurrentView('signup') : undefined}
              formSettings={formSettings}
            />
          ) : (
            <SignupForm
              apiUrl={apiUrl}
              nonce={nonce}
              googleEnabled={googleEnabled}
              onSuccess={handleSuccess}
              onError={handleError}
              onSwitchToLogin={mode === 'both' ? () => setCurrentView('login') : undefined}
              formSettings={formSettings}
            />
          )}
        </div>
      </div>
    </div>
  );
};

export default AuthModal;
