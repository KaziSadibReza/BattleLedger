import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Toast from '../components/Toast';

interface AppearanceSettings {
  primaryColor: string;
  primaryHoverColor: string;
  successColor: string;
  warningColor: string;
  dangerColor: string;
  backgroundColor: string;
  surfaceColor: string;
  borderColor: string;
  textColor: string;
  textSecondaryColor: string;
  textMutedColor: string;
}

const defaultSettings: AppearanceSettings = {
  primaryColor: '#6366f1',
  primaryHoverColor: '#4f46e5',
  successColor: '#10b981',
  warningColor: '#f59e0b',
  dangerColor: '#ef4444',
  backgroundColor: '#f8fafc',
  surfaceColor: '#ffffff',
  borderColor: '#e2e8f0',
  textColor: '#0f172a',
  textSecondaryColor: '#475569',
  textMutedColor: '#94a3b8',
};

const colorPresets = [
  { name: 'Indigo',  primary: '#6366f1', hover: '#4f46e5' },
  { name: 'Blue',    primary: '#3b82f6', hover: '#2563eb' },
  { name: 'Emerald', primary: '#10b981', hover: '#059669' },
  { name: 'Rose',    primary: '#f43f5e', hover: '#e11d48' },
  { name: 'Orange',  primary: '#f97316', hover: '#ea580c' },
  { name: 'Violet',  primary: '#8b5cf6', hover: '#7c3aed' },
  { name: 'Cyan',    primary: '#06b6d4', hover: '#0891b2' },
  { name: 'Slate',   primary: '#475569', hover: '#334155' },
];

export default function FrontendAppearance() {
  const [settings, setSettings] = useState<AppearanceSettings>(defaultSettings);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    setLoading(true);
    try {
      const response = await apiFetch<AppearanceSettings>({
        path: '/battle-ledger/v1/settings/frontend-appearance',
      });
      if (response) {
        setSettings({ ...defaultSettings, ...response });
      }
    } catch (error) {
      console.error('Failed to load frontend appearance settings:', error);
    } finally {
      setLoading(false);
    }
  };

  const saveSettings = async () => {
    setSaving(true);
    try {
      const result = await apiFetch<{ success: boolean; message?: string }>({
        path: '/battle-ledger/v1/settings/frontend-appearance',
        method: 'POST',
        data: settings,
      });
      if (result?.success) {
        setSaved(true);
        setToast({ message: 'Appearance settings saved!', type: 'success' });
        setTimeout(() => setSaved(false), 3000);
      } else {
        throw new Error(result?.message || 'Failed to save');
      }
    } catch (error: any) {
      setToast({ message: error?.message || 'Failed to save settings', type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  const resetToDefaults = () => {
    setSettings(defaultSettings);
    setToast({ message: 'Reset to defaults — save to apply', type: 'info' });
  };

  const applyPreset = (preset: typeof colorPresets[0]) => {
    setSettings(prev => ({
      ...prev,
      primaryColor: preset.primary,
      primaryHoverColor: preset.hover,
    }));
  };

  const renderColorInput = (label: string, key: keyof AppearanceSettings, description?: string) => (
    <div className="bl-form-field">
      <label>{label}</label>
      <div className="bl-color-input">
        <input
          type="color"
          value={settings[key]}
          onChange={(e) => setSettings(prev => ({ ...prev, [key]: e.target.value }))}
        />
        <input
          type="text"
          value={settings[key]}
          onChange={(e) => setSettings(prev => ({ ...prev, [key]: e.target.value }))}
          placeholder="#000000"
        />
      </div>
      {description && <p className="bl-field-desc">{description}</p>}
    </div>
  );

  /* ── Preview Styles ── */
  const ps: React.CSSProperties & Record<string, string> = {
    '--prev-primary': settings.primaryColor,
    '--prev-primary-hover': settings.primaryHoverColor,
    '--prev-success': settings.successColor,
    '--prev-warning': settings.warningColor,
    '--prev-danger': settings.dangerColor,
    '--prev-bg': settings.backgroundColor,
    '--prev-surface': settings.surfaceColor,
    '--prev-border': settings.borderColor,
    '--prev-text': settings.textColor,
    '--prev-text-sec': settings.textSecondaryColor,
    '--prev-text-muted': settings.textMutedColor,
  };

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
            <div className="bl-tab-content">
              {[...Array(6)].map((_, i) => (
                <div key={i} className="bl-skeleton bl-skeleton-field" />
              ))}
            </div>
          </div>
          <div className="bl-preview-panel">
            <div className="bl-skeleton bl-skeleton-preview" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bl-form-customization">
      {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

      {/* ── Header ── */}
      <div className="bl-page-header">
        <div>
          <h1>Frontend Appearance</h1>
          <p>Customize colors for your Dashboard &amp; Live Tournaments shortcodes</p>
        </div>
        <div style={{ display: 'flex', gap: '8px' }}>
          <button className="bl-btn bl-btn-secondary" onClick={resetToDefaults}>
            <span className="dashicons dashicons-image-rotate" />
            Reset
          </button>
          <button
            className={`bl-btn bl-btn-primary ${saving ? 'loading' : ''} ${saved ? 'saved' : ''}`}
            onClick={saveSettings}
            disabled={saving}
          >
            {saving ? (
              <><span className="bl-spinner" /> Saving...</>
            ) : saved ? (
              <><span className="dashicons dashicons-yes-alt" /> Saved!</>
            ) : (
              <><span className="dashicons dashicons-cloud-saved" /> Save Changes</>
            )}
          </button>
        </div>
      </div>

      <div className="bl-customization-layout">
        {/* ── Settings Panel ── */}
        <div className="bl-settings-panel">
          <div className="bl-settings-content">
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
                      style={{ background: `linear-gradient(135deg, ${preset.primary}, ${preset.hover})` }}
                      title={preset.name}
                    >
                      <span>{preset.name}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Primary Colors */}
              <div className="bl-form-section">
                <h4>Primary Colors</h4>
                {renderColorInput('Primary Color', 'primaryColor', 'Main brand color for buttons, links, accents')}
                {renderColorInput('Primary Hover', 'primaryHoverColor', 'Darker shade for hover states')}
              </div>

              {/* Status Colors */}
              <div className="bl-form-section">
                <h4>Status Colors</h4>
                {renderColorInput('Success', 'successColor', 'Positive actions & live badges')}
                {renderColorInput('Warning', 'warningColor', 'Alerts & prize pool highlights')}
                {renderColorInput('Danger', 'dangerColor', 'Errors & full badges')}
              </div>

              {/* Backgrounds */}
              <div className="bl-form-section">
                <h4>Backgrounds &amp; Surfaces</h4>
                {renderColorInput('Page Background', 'backgroundColor', 'Main page background')}
                {renderColorInput('Card / Surface', 'surfaceColor', 'Panels, cards, modals')}
                {renderColorInput('Border Color', 'borderColor', 'Dividers & outlines')}
              </div>

              {/* Typography Colors */}
              <div className="bl-form-section">
                <h4>Text Colors</h4>
                {renderColorInput('Primary Text', 'textColor', 'Headings & main text')}
                {renderColorInput('Secondary Text', 'textSecondaryColor', 'Descriptions & meta')}
                {renderColorInput('Muted Text', 'textMutedColor', 'Hints & placeholder text')}
              </div>
            </div>
          </div>
        </div>

        {/* ── Preview Panel ── */}
        <div className="bl-preview-panel">
          <div className="bl-preview-header">
            <h3>Preview</h3>
          </div>
          <div className="bl-preview-content" style={ps}>
            <div className="bl-appearance-preview">
              {/* Mini Dashboard Card Preview */}
              <div className="bl-ap-section-label">Dashboard</div>
              <div className="bl-ap-dash" style={{ background: 'var(--prev-bg)', padding: 16, borderRadius: 12 }}>
                <div style={{ display: 'flex', gap: 10, marginBottom: 12 }}>
                  {[
                    { label: 'Tournaments', val: '12', color: 'var(--prev-primary)' },
                    { label: 'Won', val: '4', color: 'var(--prev-success)' },
                    { label: 'Prize', val: '$520', color: 'var(--prev-warning)' },
                  ].map((s) => (
                    <div key={s.label} style={{ flex: 1, background: 'var(--prev-surface)', border: '1px solid var(--prev-border)', borderRadius: 8, padding: '10px 12px' }}>
                      <div style={{ fontSize: 11, color: 'var(--prev-text-muted)', marginBottom: 4 }}>{s.label}</div>
                      <div style={{ fontSize: 18, fontWeight: 700, color: s.color }}>{s.val}</div>
                    </div>
                  ))}
                </div>
                <div style={{ background: 'var(--prev-primary)', color: '#fff', borderRadius: 8, padding: '10px 16px', textAlign: 'center', fontWeight: 600, fontSize: 13 }}>
                  View All Tournaments
                </div>
              </div>

              {/* Mini Live Tournament Card Preview */}
              <div className="bl-ap-section-label" style={{ marginTop: 20 }}>Live Tournaments</div>
              <div className="bl-ap-card" style={{ background: 'var(--prev-surface)', border: '1px solid var(--prev-border)', borderRadius: 12, overflow: 'hidden' }}>
                <div style={{ height: 60, background: `linear-gradient(135deg, ${settings.primaryColor}, ${settings.primaryHoverColor})`, position: 'relative' }}>
                  <span style={{ position: 'absolute', top: 8, left: 10, fontSize: 10, fontWeight: 700, color: '#fff', background: `${settings.successColor}cc`, padding: '3px 10px', borderRadius: 12, display: 'inline-flex', alignItems: 'center', gap: 4 }}>
                    <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#fff', display: 'inline-block' }} />
                    LIVE
                  </span>
                </div>
                <div style={{ padding: 12 }}>
                  <div style={{ fontSize: 10, fontWeight: 600, color: 'var(--prev-primary)', marginBottom: 4 }}>Battle Royale</div>
                  <div style={{ fontSize: 14, fontWeight: 700, color: 'var(--prev-text)', marginBottom: 6 }}>Daily Championship</div>
                  <div style={{ display: 'flex', gap: 12, fontSize: 11, color: 'var(--prev-text-sec)', marginBottom: 8 }}>
                    <span>24/32 Players</span>
                    <span style={{ color: 'var(--prev-success)', fontWeight: 600 }}>Free</span>
                    <span style={{ color: 'var(--prev-warning)', fontWeight: 600 }}>$500</span>
                  </div>
                  {/* Capacity bar */}
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <div style={{ flex: 1, height: 4, borderRadius: 2, background: 'var(--prev-bg)' }}>
                      <div style={{ width: '75%', height: '100%', borderRadius: 2, background: 'var(--prev-primary)' }} />
                    </div>
                    <span style={{ fontSize: 10, color: 'var(--prev-text-muted)' }}>75%</span>
                  </div>
                </div>
              </div>

              {/* Button samples */}
              <div style={{ display: 'flex', gap: 8, marginTop: 16, flexWrap: 'wrap' }}>
                <span style={{ padding: '6px 14px', borderRadius: 6, fontSize: 12, fontWeight: 600, background: 'var(--prev-primary)', color: '#fff' }}>Primary</span>
                <span style={{ padding: '6px 14px', borderRadius: 6, fontSize: 12, fontWeight: 600, background: 'var(--prev-success)', color: '#fff' }}>Success</span>
                <span style={{ padding: '6px 14px', borderRadius: 6, fontSize: 12, fontWeight: 600, background: 'var(--prev-warning)', color: '#fff' }}>Warning</span>
                <span style={{ padding: '6px 14px', borderRadius: 6, fontSize: 12, fontWeight: 600, background: 'var(--prev-danger)', color: '#fff' }}>Danger</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
