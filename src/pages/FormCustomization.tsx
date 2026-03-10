import { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Toast from '../components/Toast';

// WordPress media frame type
declare global {
  interface Window {
    wp: {
      media: (options: {
        title: string;
        button: { text: string };
        multiple: boolean;
        library?: { type: string };
      }) => {
        on: (event: string, callback: () => void) => void;
        state: () => {
          get: (key: string) => {
            first: () => {
              toJSON: () => { url: string; id: number };
            };
          };
        };
        open: () => void;
      };
    };
  }
}

interface FormSettings {
  // Colors
  primaryColor: string;
  primaryDarkColor: string;
  accentColor: string;
  backgroundColor: string;
  surfaceColor: string;
  borderColor: string;
  textPrimaryColor: string;
  textSecondaryColor: string;
  textMutedColor: string;
  successColor: string;
  errorColor: string;
  
  // Typography
  fontFamily: string;
  headingFontSize: string;
  bodyFontSize: string;
  
  // Border Radius
  borderRadius: string;
  
  // Features
  showLogo: boolean;
  logoUrl: string;
  showSocialLogin: boolean;
  showOtpLogin: boolean;
  showPasswordLogin: boolean;
  defaultLoginMethod: 'otp' | 'password';
  
  // Labels
  signInTitle: string;
  signInSubtitle: string;
  signUpTitle: string;
  signUpSubtitle: string;
  emailLabel: string;
  passwordLabel: string;
  signInButtonText: string;
  signUpButtonText: string;
  
  // Redirects
  loginRedirect: string;
  logoutRedirect: string;
  registrationRedirect: string;
}

const defaultSettings: FormSettings = {
  primaryColor: '#6366f1',
  primaryDarkColor: '#4f46e5',
  accentColor: '#8b5cf6',
  backgroundColor: '#f8fafc',
  surfaceColor: '#ffffff',
  borderColor: '#e2e8f0',
  textPrimaryColor: '#0f172a',
  textSecondaryColor: '#475569',
  textMutedColor: '#94a3b8',
  successColor: '#10b981',
  errorColor: '#ef4444',
  fontFamily: 'system-ui, -apple-system, sans-serif',
  headingFontSize: '26px',
  bodyFontSize: '15px',
  borderRadius: '12px',
  showLogo: false,
  logoUrl: '',
  showSocialLogin: false,
  showOtpLogin: true,
  showPasswordLogin: true,
  defaultLoginMethod: 'otp',
  signInTitle: 'Welcome back',
  signInSubtitle: 'Sign in to your account',
  signUpTitle: 'Create an account',
  signUpSubtitle: 'Get started with BattleLedger',
  emailLabel: 'Email',
  passwordLabel: 'Password',
  signInButtonText: 'Sign In',
  signUpButtonText: 'Create Account',
  loginRedirect: '',
  logoutRedirect: '/',
  registrationRedirect: '',
};

const colorPresets = [
  { name: 'Indigo', primary: '#6366f1', accent: '#8b5cf6' },
  { name: 'Blue', primary: '#3b82f6', accent: '#6366f1' },
  { name: 'Emerald', primary: '#10b981', accent: '#14b8a6' },
  { name: 'Rose', primary: '#f43f5e', accent: '#ec4899' },
  { name: 'Orange', primary: '#f97316', accent: '#fb923c' },
  { name: 'Violet', primary: '#8b5cf6', accent: '#a78bfa' },
  { name: 'Cyan', primary: '#06b6d4', accent: '#22d3ee' },
  { name: 'Slate', primary: '#475569', accent: '#64748b' },
];

export default function FormCustomization() {
  const [settings, setSettings] = useState<FormSettings>(defaultSettings);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);
  const [activePreview, setActivePreview] = useState<'login' | 'signup'>('login');
  const [activeTab, setActiveTab] = useState<'colors' | 'typography' | 'labels' | 'features'>('colors');
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{
        colors?: Partial<FormSettings>;
        typography?: Partial<FormSettings>;
        labels?: Partial<FormSettings>;
        features?: Partial<FormSettings>;
      }>({
        path: '/battle-ledger/v1/settings/form',
      });
      if (response) {
        // Flatten the nested structure from API to our flat state
        const flatSettings: FormSettings = {
          ...defaultSettings,
          ...(response.colors || {}),
          ...(response.typography || {}),
          ...(response.labels || {}),
          ...(response.features || {}),
        };
        setSettings(flatSettings);
      }
    } catch (error) {
      console.error('Failed to load form settings:', error);
      setToast({ message: 'Failed to load settings', type: 'error' });
    } finally {
      setLoading(false);
    }
  };

  const saveSettings = async () => {
    setSaving(true);
    try {
      // Structure settings for API
      const apiSettings = {
        colors: {
          primaryColor: settings.primaryColor,
          primaryDarkColor: settings.primaryDarkColor,
          accentColor: settings.accentColor,
          backgroundColor: settings.backgroundColor,
          surfaceColor: settings.surfaceColor,
          borderColor: settings.borderColor,
          textPrimaryColor: settings.textPrimaryColor,
          textSecondaryColor: settings.textSecondaryColor,
          textMutedColor: settings.textMutedColor,
          successColor: settings.successColor,
          errorColor: settings.errorColor,
        },
        typography: {
          fontFamily: settings.fontFamily,
          headingFontSize: settings.headingFontSize,
          bodyFontSize: settings.bodyFontSize,
          borderRadius: settings.borderRadius,
        },
        labels: {
          signInTitle: settings.signInTitle,
          signInSubtitle: settings.signInSubtitle,
          signUpTitle: settings.signUpTitle,
          signUpSubtitle: settings.signUpSubtitle,
          emailLabel: settings.emailLabel,
          passwordLabel: settings.passwordLabel,
          signInButtonText: settings.signInButtonText,
          signUpButtonText: settings.signUpButtonText,
        },
        features: {
          showLogo: settings.showLogo,
          logoUrl: settings.logoUrl,
          showSocialLogin: settings.showSocialLogin,
          showOtpLogin: settings.showOtpLogin,
          showPasswordLogin: settings.showPasswordLogin,
          defaultLoginMethod: settings.defaultLoginMethod,
          loginRedirect: settings.loginRedirect,
          logoutRedirect: settings.logoutRedirect,
          registrationRedirect: settings.registrationRedirect,
        },
      };
      
      const result = await apiFetch<{ success: boolean; message?: string }>({
        path: '/battle-ledger/v1/settings/form',
        method: 'POST',
        data: apiSettings,
      });
      
      if (result?.success) {
        setSaved(true);
        setToast({ message: 'Settings saved successfully!', type: 'success' });
        setTimeout(() => setSaved(false), 3000);
      } else {
        throw new Error(result?.message || 'Failed to save');
      }
    } catch (error: any) {
      console.error('Failed to save settings:', error);
      setToast({ message: error?.message || 'Failed to save settings', type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const applyPreset = (preset: typeof colorPresets[0]) => {
    setSettings(prev => ({
      ...prev,
      primaryColor: preset.primary,
      accentColor: preset.accent,
      primaryDarkColor: adjustBrightness(preset.primary, -20),
    }));
  };

  const adjustBrightness = (hex: string, percent: number): string => {
    const num = parseInt(hex.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const R = Math.max(0, Math.min(255, (num >> 16) + amt));
    const G = Math.max(0, Math.min(255, ((num >> 8) & 0x00ff) + amt));
    const B = Math.max(0, Math.min(255, (num & 0x0000ff) + amt));
    return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
  };

  // Open WordPress Media Library
  const openMediaLibrary = useCallback(() => {
    if (!window.wp?.media) {
      setToast({ message: 'Media library not available', type: 'error' });
      return;
    }

    const frame = window.wp.media({
      title: 'Select Logo',
      button: { text: 'Use this image' },
      multiple: false,
      library: { type: 'image' },
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      setSettings(prev => ({ ...prev, logoUrl: attachment.url }));
    });

    frame.open();
  }, []);

  const renderColorInput = (label: string, key: keyof FormSettings, description?: string) => (
    <div className="bl-form-field">
      <label>{label}</label>
      <div className="bl-color-input">
        <input
          type="color"
          value={settings[key] as string}
          onChange={(e) => setSettings(prev => ({ ...prev, [key]: e.target.value }))}
        />
        <input
          type="text"
          value={settings[key] as string}
          onChange={(e) => setSettings(prev => ({ ...prev, [key]: e.target.value }))}
          placeholder="#000000"
        />
      </div>
      {description && <p className="bl-field-desc">{description}</p>}
    </div>
  );

  const renderTextInput = (label: string, key: keyof FormSettings, placeholder?: string) => (
    <div className="bl-form-field">
      <label>{label}</label>
      <input
        type="text"
        value={settings[key] as string}
        onChange={(e) => setSettings(prev => ({ ...prev, [key]: e.target.value }))}
        placeholder={placeholder}
      />
    </div>
  );

  const renderToggle = (label: string, key: keyof FormSettings, description?: string) => (
    <div className="bl-form-field bl-toggle-field">
      <div className="bl-toggle-header">
        <label>{label}</label>
        <button
          className={`bl-toggle ${settings[key] ? 'active' : ''}`}
          onClick={() => setSettings(prev => ({ ...prev, [key]: !prev[key] }))}
        >
          <span className="bl-toggle-slider" />
        </button>
      </div>
      {description && <p className="bl-field-desc">{description}</p>}
    </div>
  );

  // Generate CSS Variables for preview
  const previewStyles = {
    '--bl-primary': settings.primaryColor,
    '--bl-primary-dark': settings.primaryDarkColor,
    '--bl-accent': settings.accentColor,
    '--bl-bg': settings.backgroundColor,
    '--bl-surface': settings.surfaceColor,
    '--bl-border': settings.borderColor,
    '--bl-text-primary': settings.textPrimaryColor,
    '--bl-text-secondary': settings.textSecondaryColor,
    '--bl-text-muted': settings.textMutedColor,
    '--bl-success': settings.successColor,
    '--bl-error': settings.errorColor,
    '--bl-radius': settings.borderRadius,
    '--bl-font': settings.fontFamily,
  } as React.CSSProperties;

  if (loading) {
    return (
      <div className="bl-form-customization">
        <div className="bl-page-header">
          <div>
            <div className="bl-skeleton bl-skeleton-title" />
            <div className="bl-skeleton bl-skeleton-text" />
          </div>
          <div className="bl-skeleton bl-skeleton-button" />
        </div>
        
        <div className="bl-customization-layout">
          <div className="bl-settings-panel">
            <div className="bl-settings-tabs">
              <div className="bl-skeleton bl-skeleton-tab" />
              <div className="bl-skeleton bl-skeleton-tab" />
              <div className="bl-skeleton bl-skeleton-tab" />
              <div className="bl-skeleton bl-skeleton-tab" />
            </div>
            <div className="bl-tab-content">
              <div className="bl-form-section">
                <div className="bl-skeleton bl-skeleton-heading" />
                <div className="bl-skeleton-fields">
                  <div className="bl-skeleton bl-skeleton-field" />
                  <div className="bl-skeleton bl-skeleton-field" />
                  <div className="bl-skeleton bl-skeleton-field" />
                </div>
              </div>
              <div className="bl-form-section">
                <div className="bl-skeleton bl-skeleton-heading" />
                <div className="bl-skeleton-fields">
                  <div className="bl-skeleton bl-skeleton-field" />
                  <div className="bl-skeleton bl-skeleton-field" />
                </div>
              </div>
            </div>
          </div>
          
          <div className="bl-preview-panel">
            <div className="bl-preview-header">
              <div className="bl-skeleton bl-skeleton-heading" />
            </div>
            <div className="bl-preview-content">
              <div className="bl-skeleton bl-skeleton-preview" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bl-form-customization">
      {/* Toast Notification */}
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}
      
      <div className="bl-page-header">
        <div>
          <h1>Form Customization</h1>
          <p>Customize the appearance of your login and registration forms</p>
        </div>
        <button
          className={`bl-btn bl-btn-primary ${saving ? 'loading' : ''} ${saved ? 'saved' : ''}`}
          onClick={saveSettings}
          disabled={saving}
        >
          {saving ? (
            <>
              <span className="bl-spinner" />
              Saving...
            </>
          ) : saved ? (
            <>
              <span className="dashicons dashicons-yes-alt" />
              Saved!
            </>
          ) : (
            <>
              <span className="dashicons dashicons-cloud-saved" />
              Save Changes
            </>
          )}
        </button>
      </div>

      <div className="bl-customization-layout">
        {/* Settings Panel */}
        <div className="bl-settings-panel">
          {/* Tabs */}
          <div className="bl-settings-tabs">
            <button
              className={activeTab === 'colors' ? 'active' : ''}
              onClick={() => setActiveTab('colors')}
            >
              <span className="dashicons dashicons-art" />
              Colors
            </button>
            <button
              className={activeTab === 'typography' ? 'active' : ''}
              onClick={() => setActiveTab('typography')}
            >
              <span className="dashicons dashicons-editor-textcolor" />
              Typography
            </button>
            <button
              className={activeTab === 'labels' ? 'active' : ''}
              onClick={() => setActiveTab('labels')}
            >
              <span className="dashicons dashicons-edit" />
              Labels
            </button>
            <button
              className={activeTab === 'features' ? 'active' : ''}
              onClick={() => setActiveTab('features')}
            >
              <span className="dashicons dashicons-admin-settings" />
              Features
            </button>
          </div>

          {/* Tab Content */}
          <div className="bl-settings-content">
            {activeTab === 'colors' && (
              <div className="bl-tab-content">
                {/* Color Presets */}
                <div className="bl-color-presets">
                  <h4>Quick Presets</h4>
                  <div className="bl-presets-grid">
                    {colorPresets.map((preset) => (
                      <button
                        key={preset.name}
                        className="bl-preset-btn"
                        onClick={() => applyPreset(preset)}
                        style={{
                          background: `linear-gradient(135deg, ${preset.primary}, ${preset.accent})`,
                        }}
                        title={preset.name}
                      >
                        <span>{preset.name}</span>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="bl-form-section">
                  <h4>Primary Colors</h4>
                  {renderColorInput('Primary Color', 'primaryColor', 'Main brand color for buttons and accents')}
                  {renderColorInput('Primary Dark', 'primaryDarkColor', 'Darker shade for hover states')}
                  {renderColorInput('Accent Color', 'accentColor', 'Secondary accent color')}
                </div>

                <div className="bl-form-section">
                  <h4>Background Colors</h4>
                  {renderColorInput('Background', 'backgroundColor', 'Page background color')}
                  {renderColorInput('Surface', 'surfaceColor', 'Card/form background')}
                  {renderColorInput('Border', 'borderColor', 'Border and divider color')}
                </div>

                <div className="bl-form-section">
                  <h4>Text Colors</h4>
                  {renderColorInput('Primary Text', 'textPrimaryColor', 'Main text color')}
                  {renderColorInput('Secondary Text', 'textSecondaryColor', 'Subtitle text')}
                  {renderColorInput('Muted Text', 'textMutedColor', 'Placeholder and hint text')}
                </div>

                <div className="bl-form-section">
                  <h4>Status Colors</h4>
                  {renderColorInput('Success', 'successColor', 'Success messages')}
                  {renderColorInput('Error', 'errorColor', 'Error messages')}
                </div>
              </div>
            )}

            {activeTab === 'typography' && (
              <div className="bl-tab-content">
                <div className="bl-form-section">
                  <h4>Font Settings</h4>
                  <div className="bl-form-field">
                    <label>Font Family</label>
                    <select
                      value={settings.fontFamily}
                      onChange={(e) => setSettings(prev => ({ ...prev, fontFamily: e.target.value }))}
                    >
                      <option value="system-ui, -apple-system, sans-serif">System Default</option>
                      <option value="'Inter', sans-serif">Inter</option>
                      <option value="'Roboto', sans-serif">Roboto</option>
                      <option value="'Open Sans', sans-serif">Open Sans</option>
                      <option value="'Poppins', sans-serif">Poppins</option>
                      <option value="'Nunito', sans-serif">Nunito</option>
                    </select>
                  </div>
                  {renderTextInput('Heading Size', 'headingFontSize', '26px')}
                  {renderTextInput('Body Size', 'bodyFontSize', '15px')}
                </div>

                <div className="bl-form-section">
                  <h4>Shape</h4>
                  <div className="bl-form-field">
                    <label>Border Radius</label>
                    <div className="bl-range-input">
                      <input
                        type="range"
                        min="0"
                        max="24"
                        value={parseInt(settings.borderRadius)}
                        onChange={(e) => setSettings(prev => ({ ...prev, borderRadius: `${e.target.value}px` }))}
                      />
                      <span>{settings.borderRadius}</span>
                    </div>
                  </div>
                </div>
              </div>
            )}

            {activeTab === 'labels' && (
              <div className="bl-tab-content">
                <div className="bl-form-section">
                  <h4>Sign In Form</h4>
                  {renderTextInput('Title', 'signInTitle', 'Welcome back')}
                  {renderTextInput('Subtitle', 'signInSubtitle', 'Sign in to your account')}
                  {renderTextInput('Button Text', 'signInButtonText', 'Sign In')}
                </div>

                <div className="bl-form-section">
                  <h4>Sign Up Form</h4>
                  {renderTextInput('Title', 'signUpTitle', 'Create an account')}
                  {renderTextInput('Subtitle', 'signUpSubtitle', 'Get started with BattleLedger')}
                  {renderTextInput('Button Text', 'signUpButtonText', 'Create Account')}
                </div>

                <div className="bl-form-section">
                  <h4>Field Labels</h4>
                  {renderTextInput('Email Label', 'emailLabel', 'Email')}
                  {renderTextInput('Password Label', 'passwordLabel', 'Password')}
                </div>
              </div>
            )}

            {activeTab === 'features' && (
              <div className="bl-tab-content">
                <div className="bl-form-section">
                  <h4>Branding</h4>
                  {renderToggle('Show Logo', 'showLogo', 'Display your logo at the top of the form')}
                  {settings.showLogo && (
                    <div className="bl-form-field bl-logo-upload">
                      <label>Logo</label>
                      <div className="bl-logo-preview-wrapper">
                        {settings.logoUrl ? (
                          <div className="bl-logo-preview">
                            <img src={settings.logoUrl} alt="Logo preview" />
                            <button 
                              type="button" 
                              className="bl-logo-remove"
                              onClick={() => setSettings(prev => ({ ...prev, logoUrl: '' }))}
                              title="Remove logo"
                            >
                              ×
                            </button>
                          </div>
                        ) : (
                          <div className="bl-logo-placeholder">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                              <circle cx="8.5" cy="8.5" r="1.5"/>
                              <polyline points="21,15 16,10 5,21"/>
                            </svg>
                            <span>No logo selected</span>
                          </div>
                        )}
                      </div>
                      <button 
                        type="button" 
                        className="bl-media-button"
                        onClick={openMediaLibrary}
                      >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                          <polyline points="17 8 12 3 7 8"/>
                          <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        {settings.logoUrl ? 'Change Logo' : 'Upload Logo'}
                      </button>
                      <p className="bl-field-desc">Select an image from your WordPress Media Library</p>
                    </div>
                  )}
                </div>

                <div className="bl-form-section">
                  <h4>Login Methods</h4>
                  {renderToggle('Email Code (OTP)', 'showOtpLogin', 'Allow passwordless login via email code')}
                  {renderToggle('Password', 'showPasswordLogin', 'Allow traditional password login')}
                  {renderToggle('Social Login', 'showSocialLogin', 'Show Google login button')}
                  
                  {settings.showOtpLogin && settings.showPasswordLogin && (
                    <div className="bl-form-field">
                      <label>Default Login Method</label>
                      <select
                        value={settings.defaultLoginMethod}
                        onChange={(e) => setSettings(prev => ({ ...prev, defaultLoginMethod: e.target.value as 'otp' | 'password' }))}
                      >
                        <option value="otp">Email Code</option>
                        <option value="password">Password</option>
                      </select>
                    </div>
                  )}
                </div>

                <div className="bl-form-section">
                  <h4>Redirects</h4>
                  {renderTextInput('After Login', 'loginRedirect', '/my-account')}
                  {renderTextInput('After Logout', 'logoutRedirect', '/')}
                  {renderTextInput('After Registration', 'registrationRedirect', '/welcome')}
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Live Preview */}
        <div className="bl-preview-panel">
          <div className="bl-preview-header">
            <h4>Live Preview</h4>
            <div className="bl-preview-toggle">
              <button
                className={activePreview === 'login' ? 'active' : ''}
                onClick={() => setActivePreview('login')}
              >
                Sign In
              </button>
              <button
                className={activePreview === 'signup' ? 'active' : ''}
                onClick={() => setActivePreview('signup')}
              >
                Sign Up
              </button>
            </div>
          </div>
          
          <div className="bl-preview-container" style={previewStyles}>
            <div className="bl-preview-form">
              {/* Form accent bar */}
              <div 
                className="bl-preview-accent"
                style={{ background: `linear-gradient(90deg, ${settings.primaryColor}, ${settings.accentColor})` }}
              />
              
              {/* Logo */}
              {settings.showLogo && settings.logoUrl && (
                <div className="bl-preview-logo">
                  <img src={settings.logoUrl} alt="Logo" />
                </div>
              )}
              
              {/* Header */}
              <div className="bl-preview-header-text">
                <h2 style={{ 
                  fontSize: settings.headingFontSize, 
                  color: settings.textPrimaryColor,
                  fontFamily: settings.fontFamily 
                }}>
                  {activePreview === 'login' ? settings.signInTitle : settings.signUpTitle}
                </h2>
                <p style={{ 
                  fontSize: settings.bodyFontSize, 
                  color: settings.textMutedColor,
                  fontFamily: settings.fontFamily 
                }}>
                  {activePreview === 'login' ? settings.signInSubtitle : settings.signUpSubtitle}
                </p>
              </div>

              {/* Social Button */}
              {settings.showSocialLogin && (
                <>
                  <button 
                    className="bl-preview-social-btn"
                    style={{ 
                      borderColor: settings.borderColor, 
                      borderRadius: settings.borderRadius,
                      color: settings.textPrimaryColor,
                      fontFamily: settings.fontFamily
                    }}
                  >
                    <svg viewBox="0 0 24 24" width="20" height="20">
                      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continue with Google
                  </button>
                  <div className="bl-preview-divider" style={{ color: settings.textMutedColor }}>
                    <span>or</span>
                  </div>
                </>
              )}

              {/* Method Toggle */}
              {settings.showOtpLogin && settings.showPasswordLogin && activePreview === 'login' && (
                <div 
                  className="bl-preview-method-toggle"
                  style={{ background: settings.backgroundColor, borderRadius: settings.borderRadius }}
                >
                  <button 
                    className="active"
                    style={{ 
                      background: settings.surfaceColor, 
                      color: settings.primaryColor,
                      borderRadius: `calc(${settings.borderRadius} - 4px)`
                    }}
                  >
                    Email Code
                  </button>
                  <button style={{ color: settings.textMutedColor }}>
                    Password
                  </button>
                </div>
              )}

              {/* Form Field */}
              <div className="bl-preview-field">
                <label style={{ 
                  color: settings.textPrimaryColor, 
                  fontFamily: settings.fontFamily,
                  fontSize: '14px'
                }}>
                  {settings.emailLabel}
                </label>
                <div 
                  className="bl-preview-input"
                  style={{ 
                    borderColor: settings.borderColor, 
                    borderRadius: settings.borderRadius,
                    background: settings.surfaceColor
                  }}
                >
                  <span style={{ color: settings.textMutedColor }}>✉</span>
                  <span style={{ color: settings.textMutedColor, fontFamily: settings.fontFamily }}>
                    you@example.com
                  </span>
                </div>
              </div>

              {/* Submit Button */}
              <button 
                className="bl-preview-submit"
                style={{ 
                  background: `linear-gradient(135deg, ${settings.primaryColor}, ${settings.primaryDarkColor})`,
                  borderRadius: settings.borderRadius,
                  fontFamily: settings.fontFamily
                }}
              >
                {activePreview === 'login' ? settings.signInButtonText : settings.signUpButtonText}
                <span>→</span>
              </button>

              {/* Footer */}
              <p className="bl-preview-footer" style={{ color: settings.textMutedColor, fontFamily: settings.fontFamily }}>
                {activePreview === 'login' ? "Don't have an account? " : "Already have an account? "}
                <span style={{ color: settings.primaryColor, fontWeight: 600 }}>
                  {activePreview === 'login' ? 'Sign Up' : 'Sign In'}
                </span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
