import React, { useState, useEffect } from 'react';
import { Eye, EyeOff, Save, RefreshCw, ExternalLink, Shield, Mail, Clock, Users, AlertCircle, CheckCircle, FileText, Plus, Edit } from 'lucide-react';
import apiFetch from '@wordpress/api-fetch';
import Toast from '../components/Toast';
import Skeleton from '../components/Skeleton';

interface AuthSettings {
  google_client_id: string;
  google_client_secret: string;
  otp_enabled: boolean;
  otp_expiry_minutes: number;
  otp_length: number;
  otp_max_attempts: number;
  rate_limit_attempts: number;
  rate_limit_window: number;
  session_duration: number;
  login_redirect: string;
  logout_redirect: string;
  dashboard_url: string;
}

interface PageInfo {
  id: number;
  title: string;
  url: string;
  edit_url: string;
  exists: boolean;
}

interface PagesConfig {
  login: PageInfo;
}

interface AvailablePage {
  id: number;
  title: string;
  status: string;
  url: string;
}

const defaultSettings: AuthSettings = {
  google_client_id: '',
  google_client_secret: '',
  otp_enabled: true,
  otp_expiry_minutes: 10,
  otp_length: 6,
  otp_max_attempts: 3,
  rate_limit_attempts: 5,
  rate_limit_window: 15,
  session_duration: 14,
  login_redirect: '',
  logout_redirect: '',
  dashboard_url: '',
};

const AuthSettingsPage: React.FC = () => {
  const [settings, setSettings] = useState<AuthSettings>(defaultSettings);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showSecret, setShowSecret] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);
  const [activeTab, setActiveTab] = useState<'pages' | 'google' | 'otp' | 'security'>('pages');
  
  // Pages state
  const [pages, setPages] = useState<PagesConfig | null>(null);
  const [availablePages, setAvailablePages] = useState<AvailablePage[]>([]);
  const [recreatingPages, setRecreatingPages] = useState(false);
  const [pagesLoading, setPagesLoading] = useState(true);

  useEffect(() => {
    fetchSettings();
    fetchPages();
  }, []);

  const fetchSettings = async () => {
    try {
      const response = await apiFetch<{ settings: AuthSettings }>({
        path: '/battle-ledger/v1/settings/auth',
        method: 'GET',
      });
      setSettings({ ...defaultSettings, ...response.settings });
    } catch (error) {
      setToast({ message: 'Failed to load settings', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const fetchPages = async () => {
    setPagesLoading(true);
    try {
      const [pagesResponse, availableResponse] = await Promise.all([
        apiFetch<{ pages: PagesConfig }>({ path: '/battle-ledger/v1/pages' }),
        apiFetch<{ pages: AvailablePage[] }>({ path: '/battle-ledger/v1/pages/available' }),
      ]);
      setPages(pagesResponse.pages);
      setAvailablePages(availableResponse.pages);
    } catch (error) {
      console.error('Failed to load pages:', error);
      setToast({ message: 'Failed to load pages. Please refresh the page.', type: 'error' });
    } finally {
      setPagesLoading(false);
    }
  };

  const handlePageChange = async (key: string, pageId: number) => {
    try {
      const response = await apiFetch<{ pages: PagesConfig }>({
        path: `/battle-ledger/v1/pages/${key}`,
        method: 'POST',
        data: { page_id: pageId },
      });
      setPages(response.pages);
      setToast({ message: 'Page updated successfully', type: 'success' });
    } catch (error) {
      setToast({ message: 'Failed to update page', type: 'error' });
    }
  };

  const handleRecreateMissingPages = async () => {
    setRecreatingPages(true);
    try {
      const response = await apiFetch<{ pages: PagesConfig; message: string }>({
        path: '/battle-ledger/v1/pages/recreate',
        method: 'POST',
      });
      setPages(response.pages);
      setToast({ message: 'Missing pages have been created', type: 'success' });
      // Refresh available pages list
      const availableResponse = await apiFetch<{ pages: AvailablePage[] }>({ 
        path: '/battle-ledger/v1/pages/available' 
      });
      setAvailablePages(availableResponse.pages);
    } catch (error) {
      setToast({ message: 'Failed to create pages', type: 'error' });
    } finally {
      setRecreatingPages(false);
    }
  };

  const saveSettings = async () => {
    setSaving(true);
    try {
      await apiFetch({
        path: '/battle-ledger/v1/settings/auth',
        method: 'POST',
        data: { settings },
      });
      setToast({ message: 'Settings saved successfully', type: 'success' });
    } catch (error) {
      setToast({ message: 'Failed to save settings', type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const updateSetting = <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => {
    setSettings(prev => ({ ...prev, [key]: value }));
  };

  const isGoogleConfigured = settings.google_client_id && settings.google_client_secret;

  // Skeleton for loading state
  const renderPageSkeleton = () => (
    <div className="pages-list">
      {[1, 2, 3].map((i) => (
        <div key={i} className="page-item skeleton-page-container">
          <div className="page-item-header">
            <div className="page-item-info">
              <Skeleton width="40%" height={20} variant="text" animation="wave" />
              <Skeleton width="60%" height={14} variant="text" animation="wave" />
            </div>
            <Skeleton width={80} height={24} variant="rounded" animation="wave" />
          </div>
          <div className="page-item-content">
            <Skeleton width="100%" height={40} variant="rounded" animation="wave" />
            <div className="page-actions">
              <Skeleton width={70} height={32} variant="rounded" animation="wave" />
              <Skeleton width={60} height={32} variant="rounded" animation="wave" />
            </div>
          </div>
          <Skeleton width="50%" height={14} variant="text" animation="wave" />
        </div>
      ))}
    </div>
  );

  if (loading) {
    return (
      <div className="auth-settings-page">
        <div className="auth-settings-header">
          <div className="auth-settings-title">
            <Skeleton variant="circular" width={28} height={28} animation="wave" />
            <div>
              <Skeleton width={200} height={28} animation="wave" />
              <Skeleton width={350} height={16} animation="wave" />
            </div>
          </div>
          <Skeleton width={140} height={42} variant="rounded" animation="wave" />
        </div>
        
        <div className="auth-settings-tabs">
          {[1, 2, 3, 4].map(i => (
            <Skeleton key={i} width={100} height={44} variant="rounded" animation="wave" />
          ))}
        </div>
        
        <div className="auth-settings-content">
          <div className="settings-section">
            <div className="section-header">
              <Skeleton width={150} height={24} animation="wave" />
              <Skeleton width="80%" height={14} animation="wave" />
            </div>
            {renderPageSkeleton()}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="auth-settings-page">
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}

      <div className="auth-settings-header">
        <div className="auth-settings-title">
          <Shield size={28} />
          <div>
            <h1>Authentication Settings</h1>
            <p>Configure login methods and security options for your customers</p>
          </div>
        </div>
        <button 
          className="btn-primary"
          onClick={saveSettings}
          disabled={saving}
        >
          {saving ? <RefreshCw className="spin" size={16} /> : <Save size={16} />}
          {saving ? 'Saving...' : 'Save Settings'}
        </button>
      </div>

      <div className="auth-settings-tabs">
        <button 
          className={`tab ${activeTab === 'pages' ? 'active' : ''}`}
          onClick={() => setActiveTab('pages')}
        >
          <FileText size={18} />
          Pages
          {pages && Object.values(pages).every(p => p.exists) && <CheckCircle size={14} className="tab-check" />}
        </button>
        <button 
          className={`tab ${activeTab === 'google' ? 'active' : ''}`}
          onClick={() => setActiveTab('google')}
        >
          <svg viewBox="0 0 24 24" width="18" height="18">
            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Google OAuth
          {isGoogleConfigured && <CheckCircle size={14} className="tab-check" />}
        </button>
        <button 
          className={`tab ${activeTab === 'otp' ? 'active' : ''}`}
          onClick={() => setActiveTab('otp')}
        >
          <Mail size={18} />
          Email OTP
          {settings.otp_enabled && <CheckCircle size={14} className="tab-check" />}
        </button>
        <button 
          className={`tab ${activeTab === 'security' ? 'active' : ''}`}
          onClick={() => setActiveTab('security')}
        >
          <Shield size={18} />
          Security & Redirects
        </button>
      </div>

      <div className="auth-settings-content">
        {activeTab === 'pages' && (
          <div className="settings-section">
            <div className="section-header">
              <h2>Page Setup</h2>
              <p>Manage the pages created by BattleLedger. These pages are compatible with Elementor, Gutenberg, and other page builders.</p>
            </div>

            <div className="info-box">
              <AlertCircle size={18} />
              <div>
                <strong>Auto-Created Pages:</strong>
                <p style={{ margin: '8px 0 0' }}>
                  These pages were automatically created when the plugin was activated. 
                  You can edit them with any page builder (Elementor, Gutenberg, etc.) or assign different pages below.
                </p>
              </div>
            </div>

            {pagesLoading ? (
              renderPageSkeleton()
            ) : pages ? (
              <div className="pages-list">
                {/* Login Page */}
                <div className="page-item">
                  <div className="page-item-header">
                    <div className="page-item-info">
                      <h4>Login Page</h4>
                      <p>Where customers can sign in or register</p>
                    </div>
                    <div className={`page-status ${pages.login.exists ? 'active' : 'missing'}`}>
                      {pages.login.exists ? (
                        <><CheckCircle size={14} /> Active</>
                      ) : (
                        <><AlertCircle size={14} /> Missing</>
                      )}
                    </div>
                  </div>
                  <div className="page-item-content">
                    <select
                      value={pages.login.id || ''}
                      onChange={(e) => handlePageChange('login', parseInt(e.target.value) || 0)}
                      className="page-select"
                    >
                      <option value="">— Select a page —</option>
                      {availablePages.map(page => (
                        <option key={page.id} value={page.id}>
                          {page.title} {page.status !== 'publish' ? `(${page.status})` : ''}
                        </option>
                      ))}
                    </select>
                    {pages.login.exists && (
                      <div className="page-actions">
                        <a href={pages.login.url} target="_blank" rel="noopener noreferrer" className="page-action-btn">
                          <ExternalLink size={14} /> View
                        </a>
                        <a href={pages.login.edit_url} target="_blank" rel="noopener noreferrer" className="page-action-btn">
                          <Edit size={14} /> Edit
                        </a>
                      </div>
                    )}
                  </div>
                  <p className="page-shortcode">Shortcode: <code>[battleledger_auth]</code></p>
                </div>

                {/* Dashboard Page */}
                <div className="page-item">
                  <div className="page-item-header">
                    <div className="page-item-info">
                      <h4>Dashboard Page</h4>
                      <p>User dashboard with profile and wallet management</p>
                    </div>
                    <div className={`page-status ${pages.dashboard?.exists ? 'active' : 'missing'}`}>
                      {pages.dashboard?.exists ? (
                        <><CheckCircle size={14} /> Active</>
                      ) : (
                        <><AlertCircle size={14} /> Missing</>
                      )}
                    </div>
                  </div>
                  <div className="page-item-content">
                    <select
                      value={pages.dashboard?.id || ''}
                      onChange={(e) => handlePageChange('dashboard', parseInt(e.target.value) || 0)}
                      className="page-select"
                    >
                      <option value="">— Select a page —</option>
                      {availablePages.map(page => (
                        <option key={page.id} value={page.id}>
                          {page.title} {page.status !== 'publish' ? `(${page.status})` : ''}
                        </option>
                      ))}
                    </select>
                    {pages.dashboard?.exists && (
                      <div className="page-actions">
                        <a href={pages.dashboard.url} target="_blank" rel="noopener noreferrer" className="page-action-btn">
                          <ExternalLink size={14} /> View
                        </a>
                        <a href={pages.dashboard.edit_url} target="_blank" rel="noopener noreferrer" className="page-action-btn">
                          <Edit size={14} /> Edit
                        </a>
                      </div>
                    )}
                  </div>
                  <p className="page-shortcode">Shortcode: <code>[battleledger_dashboard]</code></p>
                </div>
              </div>
            ) : (
              <div className="pages-empty">
                <AlertCircle size={24} />
                <p>Unable to load pages. Click "Recreate Missing Pages" to create them.</p>
              </div>
            )}

            <div className="pages-actions">
              <button
                type="button"
                className="btn-secondary"
                onClick={handleRecreateMissingPages}
                disabled={recreatingPages}
              >
                {recreatingPages ? (
                  <><RefreshCw className="spin" size={16} /> Creating...</>
                ) : (
                  <><Plus size={16} /> Recreate Missing Pages</>
                )}
              </button>
            </div>
          </div>
        )}

        {activeTab === 'google' && (
          <div className="settings-section">
            <div className="section-header">
              <h2>Google OAuth 2.0 Configuration</h2>
              <p>Enable customers to sign in with their Google accounts</p>
            </div>

            <div className="info-box">
              <AlertCircle size={18} />
              <div>
                <strong>Setup Instructions:</strong>
                <ol>
                  <li>Go to the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">Google Cloud Console <ExternalLink size={12} /></a></li>
                  <li>Create a new project or select existing one</li>
                  <li>Enable the Google+ API and Google Identity API</li>
                  <li>Create OAuth 2.0 credentials (Web application)</li>
                  <li>Add your authorized redirect URI: <code>{window.location.origin}/wp-json/battle-ledger/v1/auth/google/callback</code></li>
                  <li>Copy the Client ID and Client Secret below</li>
                </ol>
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="google_client_id">Client ID</label>
              <input
                type="text"
                id="google_client_id"
                value={settings.google_client_id}
                onChange={(e) => updateSetting('google_client_id', e.target.value)}
                placeholder="xxxxxxxxxx.apps.googleusercontent.com"
              />
            </div>

            <div className="form-group">
              <label htmlFor="google_client_secret">Client Secret</label>
              <div className="input-with-icon">
                <input
                  type={showSecret ? 'text' : 'password'}
                  id="google_client_secret"
                  value={settings.google_client_secret}
                  onChange={(e) => updateSetting('google_client_secret', e.target.value)}
                  placeholder="GOCSPX-xxxxxxxxxxxxxx"
                />
                <button 
                  type="button" 
                  className="icon-btn"
                  onClick={() => setShowSecret(!showSecret)}
                >
                  {showSecret ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            <div className="status-box">
              {isGoogleConfigured ? (
                <>
                  <CheckCircle size={20} className="success" />
                  <span>Google OAuth is configured and ready</span>
                </>
              ) : (
                <>
                  <AlertCircle size={20} className="warning" />
                  <span>Google OAuth is not configured</span>
                </>
              )}
            </div>
          </div>
        )}

        {activeTab === 'otp' && (
          <div className="settings-section">
            <div className="section-header">
              <h2>Email OTP Configuration</h2>
              <p>Allow customers to login with one-time passwords sent to their email</p>
            </div>

            <div className="form-group">
              <label className="toggle-label">
                <span>Enable Email OTP Login</span>
                <div className="toggle-switch">
                  <input
                    type="checkbox"
                    checked={settings.otp_enabled}
                    onChange={(e) => updateSetting('otp_enabled', e.target.checked)}
                  />
                  <span className="toggle-slider"></span>
                </div>
              </label>
              <p className="field-description">Allow users to login by receiving a code via email</p>
            </div>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="otp_length">
                  <Clock size={16} />
                  OTP Length
                </label>
                <input
                  type="number"
                  id="otp_length"
                  value={settings.otp_length}
                  onChange={(e) => updateSetting('otp_length', parseInt(e.target.value) || 6)}
                  min={4}
                  max={8}
                />
                <p className="field-description">Number of digits in OTP code (4-8)</p>
              </div>

              <div className="form-group">
                <label htmlFor="otp_expiry">
                  <Clock size={16} />
                  OTP Expiry (minutes)
                </label>
                <input
                  type="number"
                  id="otp_expiry"
                  value={settings.otp_expiry_minutes}
                  onChange={(e) => updateSetting('otp_expiry_minutes', parseInt(e.target.value) || 10)}
                  min={1}
                  max={60}
                />
                <p className="field-description">How long the OTP code remains valid</p>
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="otp_max_attempts">
                <Users size={16} />
                Max Verification Attempts
              </label>
              <input
                type="number"
                id="otp_max_attempts"
                value={settings.otp_max_attempts}
                onChange={(e) => updateSetting('otp_max_attempts', parseInt(e.target.value) || 3)}
                min={1}
                max={10}
              />
              <p className="field-description">Maximum wrong attempts before OTP is invalidated</p>
            </div>
          </div>
        )}

        {activeTab === 'security' && (
          <div className="settings-section">
            <div className="section-header">
              <h2>Security & Redirect Settings</h2>
              <p>Configure rate limiting and redirect URLs</p>
            </div>

            <h3 className="subsection-title">Rate Limiting</h3>

            <div className="form-row">
              <div className="form-group">
                <label htmlFor="rate_limit_attempts">Max Login Attempts</label>
                <input
                  type="number"
                  id="rate_limit_attempts"
                  value={settings.rate_limit_attempts}
                  onChange={(e) => updateSetting('rate_limit_attempts', parseInt(e.target.value) || 5)}
                  min={1}
                  max={20}
                />
                <p className="field-description">Failed attempts before temporary lockout</p>
              </div>

              <div className="form-group">
                <label htmlFor="rate_limit_window">Lockout Duration (minutes)</label>
                <input
                  type="number"
                  id="rate_limit_window"
                  value={settings.rate_limit_window}
                  onChange={(e) => updateSetting('rate_limit_window', parseInt(e.target.value) || 15)}
                  min={1}
                  max={60}
                />
                <p className="field-description">How long users are locked out after max attempts</p>
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="session_duration">Session Duration (days)</label>
              <input
                type="number"
                id="session_duration"
                value={settings.session_duration}
                onChange={(e) => updateSetting('session_duration', parseInt(e.target.value) || 14)}
                min={1}
                max={365}
              />
              <p className="field-description">How long users stay logged in with "Remember Me"</p>
            </div>

            <h3 className="subsection-title">Redirect URLs</h3>

            <div className="form-group">
              <label htmlFor="login_redirect">After Login Redirect</label>
              <input
                type="url"
                id="login_redirect"
                value={settings.login_redirect}
                onChange={(e) => updateSetting('login_redirect', e.target.value)}
                placeholder={window.location.origin + '/dashboard'}
              />
              <p className="field-description">Where to redirect users after successful login (leave empty for homepage)</p>
            </div>

            <div className="form-group">
              <label htmlFor="logout_redirect">After Logout Redirect</label>
              <input
                type="url"
                id="logout_redirect"
                value={settings.logout_redirect}
                onChange={(e) => updateSetting('logout_redirect', e.target.value)}
                placeholder={window.location.origin}
              />
              <p className="field-description">Where to redirect users after logout (leave empty for homepage)</p>
            </div>

            <div className="form-group">
              <label htmlFor="dashboard_url">Dashboard URL</label>
              <input
                type="url"
                id="dashboard_url"
                value={settings.dashboard_url}
                onChange={(e) => updateSetting('dashboard_url', e.target.value)}
                placeholder={window.location.origin + '/my-account'}
              />
              <p className="field-description">User dashboard/account page URL</p>
            </div>
          </div>
        )}
      </div>

      <div className="auth-settings-footer">
        <div className="shortcode-info">
          <h3>Available Shortcodes</h3>
          <div className="shortcode-list">
            <div className="shortcode-item">
              <code>[battleledger_auth]</code>
              <span>Full auth modal (login/signup)</span>
            </div>
            <div className="shortcode-item">
              <code>[battleledger_auth popup="true"]</code>
              <span>Button that opens auth modal</span>
            </div>
            <div className="shortcode-item">
              <code>[battleledger_user_profile]</code>
              <span>User avatar/name with dropdown</span>
            </div>
            <div className="shortcode-item">
              <code>[battleledger_login]</code>
              <span>Login form only</span>
            </div>
            <div className="shortcode-item">
              <code>[battleledger_signup]</code>
              <span>Signup form only</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AuthSettingsPage;
