import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown, Settings, LogOut, LayoutDashboard, Wallet, Plus, TrendingUp, AlertTriangle, XCircle } from 'lucide-react';

interface WalletData {
  balance: string;
  currency: string;
  status: string;
}

interface UserProfileProps {
  user: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
  apiUrl: string;
  nonce: string;
  showAvatar?: boolean;
  showName?: boolean;
  dashboardLink?: string;
  logoutRedirect?: string;
  className?: string;
  showWallet?: boolean;
  addFundsLink?: string;
}

export const UserProfile: React.FC<UserProfileProps> = ({
  user,
  apiUrl,
  nonce,
  showAvatar = true,
  showName = true,
  dashboardLink,
  logoutRedirect = '/',
  className = '',
  showWallet = true,
  addFundsLink,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [walletLoading, setWalletLoading] = useState(true);
  const [walletError, setWalletError] = useState<string | null>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const hoverTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Fetch wallet balance
  useEffect(() => {
    if (showWallet) {
      fetchWallet();
    }
  }, [showWallet]);

  const fetchWallet = async () => {
    setWalletError(null);
    setWalletLoading(true);
    try {
      // Use the my-wallet endpoint which doesn't require admin permissions
      const baseUrl = apiUrl.replace('/auth', '');
      const response = await fetch(`${baseUrl}/wallet/my-wallet`, {
        credentials: 'include',
        headers: {
          'X-WP-Nonce': nonce,
        },
      });
      if (response.ok) {
        const data = await response.json();
        setWallet(data);
      } else {
        const errorData = await response.json().catch(() => ({}));
        setWalletError(errorData.message || 'Failed to load wallet');
        setWallet(null);
      }
    } catch (error) {
      console.error('Failed to fetch wallet:', error);
      setWalletError('Network error');
      setWallet(null);
    } finally {
      setWalletLoading(false);
    }
  };

  const formatCurrency = (value: string | number | undefined | null, currency: string = 'USD') => {
    if (value === undefined || value === null || value === '') {
      return formatCurrencyValue(0, currency);
    }
    const num = typeof value === 'string' ? parseFloat(value) : value;
    if (isNaN(num)) {
      return formatCurrencyValue(0, currency);
    }
    return formatCurrencyValue(num, currency);
  };

  const formatCurrencyValue = (num: number, currency: string) => {
    try {
      return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
      }).format(num);
    } catch {
      // Fallback for unsupported currency codes
      return `${currency} ${num.toFixed(2)}`;
    }
  };

  // Handle mouse enter - show dropdown
  const handleMouseEnter = () => {
    if (hoverTimeoutRef.current) {
      clearTimeout(hoverTimeoutRef.current);
    }
    setIsOpen(true);
  };

  // Handle mouse leave - delay hiding dropdown
  const handleMouseLeave = () => {
    hoverTimeoutRef.current = setTimeout(() => {
      setIsOpen(false);
    }, 150);
  };

  // Close dropdown on escape
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setIsOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
      if (hoverTimeoutRef.current) {
        clearTimeout(hoverTimeoutRef.current);
      }
    };
  }, []);

  const handleLogout = async () => {
    setIsLoggingOut(true);
    try {
      await fetch(`${apiUrl}/logout`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
      });
      window.location.href = logoutRedirect;
    } catch (error) {
      console.error('Logout failed:', error);
      setIsLoggingOut(false);
    }
  };

  // Get initials for fallback avatar
  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2);
  };

  return (
    <div 
      className={`ak-user-profile ${className}`} 
      ref={dropdownRef}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      <button
        className="ak-user-trigger"
        onClick={() => setIsOpen(!isOpen)}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        {showAvatar && (
          <div className="ak-user-avatar">
            {user.avatar ? (
              <img src={user.avatar} alt={user.displayName} />
            ) : (
              <span>{getInitials(user.displayName)}</span>
            )}
          </div>
        )}
        <div className="ak-user-info-trigger">
          {showName && (
            <span className="ak-user-name">{user.displayName}</span>
          )}
          {showWallet && !walletLoading && wallet && !walletError && (
            <span className="ak-user-balance-inline">
              {formatCurrency(wallet.balance, wallet.currency)}
            </span>
          )}
          {showWallet && walletLoading && (
            <span className="ak-user-balance-inline ak-user-balance-loading">...</span>
          )}
        </div>
        <ChevronDown size={16} className={`ak-user-arrow ${isOpen ? 'rotated' : ''}`} />
      </button>

      <div className={`ak-user-dropdown ${isOpen ? 'open' : ''}`}>
        {/* User Info Header */}
        <div className="ak-dropdown-header">
          <div className="ak-dropdown-user">
            <div className="ak-dropdown-avatar">
              {user.avatar ? (
                <img src={user.avatar} alt={user.displayName} />
              ) : (
                <span>{getInitials(user.displayName)}</span>
              )}
            </div>
            <div className="ak-dropdown-info">
              <span className="ak-dropdown-name">{user.displayName}</span>
              <span className="ak-dropdown-email">{user.email}</span>
            </div>
          </div>
        </div>

        {/* Wallet Balance Section */}
        {showWallet && (
          <div className="ak-wallet-section">
            <div className={`ak-wallet-card ${walletError ? 'ak-wallet-error' : ''}`}>
              <div className="ak-wallet-header">
                <div className="ak-wallet-icon">
                  <Wallet size={20} />
                </div>
                <span className="ak-wallet-label">Wallet Balance</span>
              </div>
              <div className="ak-wallet-balance">
                {walletLoading ? (
                  <span className="ak-wallet-loading">Loading...</span>
                ) : walletError ? (
                  <div className="ak-wallet-error-state">
                    <span className="ak-wallet-error-text">{walletError}</span>
                    <button 
                      className="ak-wallet-retry-btn" 
                      onClick={(e) => { e.preventDefault(); e.stopPropagation(); fetchWallet(); }}
                    >
                      Retry
                    </button>
                  </div>
                ) : wallet ? (
                  <>
                    <span className="ak-wallet-amount">{formatCurrency(wallet.balance, wallet.currency)}</span>
                    {wallet.status === 'active' && (
                      <span className="ak-wallet-status ak-wallet-status-active">
                        <TrendingUp size={14} />
                        Active
                      </span>
                    )}
                    {wallet.status === 'suspended' && (
                      <span className="ak-wallet-status ak-wallet-status-suspended">
                        <AlertTriangle size={14} />
                        Suspended
                      </span>
                    )}
                    {wallet.status === 'closed' && (
                      <span className="ak-wallet-status ak-wallet-status-closed">
                        <XCircle size={14} />
                        Closed
                      </span>
                    )}
                  </>
                ) : null}
              </div>
              {addFundsLink && wallet && !walletError && (
                <a href={addFundsLink} className="ak-wallet-add-btn">
                  <Plus size={16} />
                  Add Funds
                </a>
              )}
            </div>
          </div>
        )}

        {/* Menu Items */}
        <nav className="ak-dropdown-menu">
          {dashboardLink && (
            <a href={dashboardLink} className="ak-dropdown-item">
              <LayoutDashboard size={18} />
              <span>Dashboard</span>
            </a>
          )}
          <a href="/wp-admin/profile.php" className="ak-dropdown-item">
            <Settings size={18} />
            <span>Account Settings</span>
          </a>
        </nav>

        {/* Logout */}
        <div className="ak-dropdown-footer">
          <button
            className="ak-dropdown-item ak-logout-item"
            onClick={handleLogout}
            disabled={isLoggingOut}
          >
            <LogOut size={18} />
            <span>{isLoggingOut ? 'Signing out...' : 'Sign Out'}</span>
          </button>
        </div>
      </div>
    </div>
  );
};

export default UserProfile;
