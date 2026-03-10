/**
 * Settings Page — Admin Panel
 * Tabs: General, Diagnostics
 */
import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import SkeletonLoader from '../components/SkeletonLoader';

/* ══════════════════════════════════════════════════════════════
   Types
   ══════════════════════════════════════════════════════════════ */

interface GeneralSettings {
  theme_mode: string;
  enable_cache: boolean;
  cache_duration: number;
  enable_redis: boolean;
}

interface TableInfo {
  name: string;
  full_name: string;
  exists: boolean;
  rows: number | null;
}

interface OrphanedTx {
  transaction_id: number;
  user_id: number;
  display_name: string;
  amount: number;
  description: string;
  created_at: string;
}

interface EnvInfo {
  php_version: string;
  wp_version: string;
  mysql_version: string;
  wp_debug: boolean;
  wp_debug_log: boolean;
  woocommerce: string | false;
  db_prefix: string;
}

interface DiagnosticStatus {
  tables: TableInfo[];
  db_version: string;
  db_version_target: string;
  needs_migration: boolean;
  plugin_version: string;
  orphaned_withdrawals: OrphanedTx[];
  environment: EnvInfo;
  debug_log: string[];
}

type SettingsTab = 'general' | 'diagnostics';

/* ══════════════════════════════════════════════════════════════
   Main Component
   ══════════════════════════════════════════════════════════════ */

const Settings: React.FC = () => {
  const [activeTab, setActiveTab] = useState<SettingsTab>('general');

  return (
    <div className="bl-settings-page">
      {/* Tab bar */}
      <div className="bl-settings-tabs">
        <button
          className={activeTab === 'general' ? 'active' : ''}
          onClick={() => setActiveTab('general')}
        >
          <span className="dashicons dashicons-admin-settings" style={{ marginRight: 6 }}></span>
          General
        </button>
        <button
          className={activeTab === 'diagnostics' ? 'active' : ''}
          onClick={() => setActiveTab('diagnostics')}
        >
          <span className="dashicons dashicons-sos" style={{ marginRight: 6 }}></span>
          Diagnostics
        </button>
      </div>

      {/* Tab content */}
      <div className="bl-settings-content">
        {activeTab === 'general' && <GeneralTab />}
        {activeTab === 'diagnostics' && <DiagnosticsTab />}
      </div>
    </div>
  );
};

/* ══════════════════════════════════════════════════════════════
   General Settings Tab
   ══════════════════════════════════════════════════════════════ */

const GeneralTab: React.FC = () => {
  const [settings, setSettings] = useState<GeneralSettings>({
    theme_mode: 'light',
    enable_cache: true,
    cache_duration: 3600,
    enable_redis: false,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const res = await apiFetch<GeneralSettings>({ path: '/battle-ledger/v1/settings' });
        setSettings(res);
      } catch (err) {
        console.error('Failed to load settings', err);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    try {
      await apiFetch({
        path: '/battle-ledger/v1/settings',
        method: 'POST',
        data: settings,
      });
      setMessage({ text: 'Settings saved successfully.', type: 'success' });
    } catch (err: any) {
      setMessage({ text: err?.message || 'Failed to save settings.', type: 'error' });
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="bl-settings-general">
        <div className="bl-card">
          <div className="bl-card-header"><SkeletonLoader width="120px" height="20px" /></div>
          <div style={{ padding: '1rem 1.25rem', display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            <SkeletonLoader width="160px" height="18px" />
            <SkeletonLoader width="100px" height="14px" />
            <SkeletonLoader width="300px" height="36px" borderRadius="6px" />
            <SkeletonLoader width="160px" height="18px" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="bl-settings-general">
      {message && (
        <div className={`bl-alert bl-alert-${message.type}`} style={{ marginBottom: '1rem' }}>
          <p>{message.text}</p>
          <button className="bl-alert-close" onClick={() => setMessage(null)}>&times;</button>
        </div>
      )}

      <div className="bl-card">
        <div className="bl-card-header">
          <h3>
            <span className="dashicons dashicons-performance" style={{ marginRight: 8 }}></span>
            Performance
          </h3>
        </div>

        <div className="bl-settings-field">
          <label>
            <input
              type="checkbox"
              checked={settings.enable_cache}
              onChange={(e) => setSettings({ ...settings, enable_cache: e.target.checked })}
            />
            Enable Cache
          </label>
          <span className="bl-field-hint">Cache API responses to improve performance</span>
        </div>

        <div className="bl-settings-field">
          <label>Cache Duration (seconds)</label>
          <input
            type="number"
            min={60}
            max={86400}
            value={settings.cache_duration}
            onChange={(e) => setSettings({ ...settings, cache_duration: parseInt(e.target.value) || 3600 })}
          />
          <span className="bl-field-hint">How long to cache data (default: 3600 = 1 hour)</span>
        </div>

        <div className="bl-settings-field">
          <label>
            <input
              type="checkbox"
              checked={settings.enable_redis}
              onChange={(e) => setSettings({ ...settings, enable_redis: e.target.checked })}
            />
            Enable Redis
          </label>
          <span className="bl-field-hint">Use Redis for object caching (requires Redis server)</span>
        </div>
      </div>

      <div className="bl-settings-actions">
        <button className="bl-btn-primary" onClick={handleSave} disabled={saving}>
          {saving ? (
            <><span className="dashicons dashicons-update spinning" style={{ marginRight: 6 }}></span>Saving…</>
          ) : (
            <><span className="dashicons dashicons-saved" style={{ marginRight: 6 }}></span>Save Settings</>
          )}
        </button>
      </div>
    </div>
  );
};

/* ══════════════════════════════════════════════════════════════
   Diagnostics Tab
   ══════════════════════════════════════════════════════════════ */

const DiagnosticsTab: React.FC = () => {
  const [status, setStatus] = useState<DiagnosticStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [migrating, setMigrating] = useState(false);
  const [fixingOrphans, setFixingOrphans] = useState(false);
  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' | 'info' } | null>(null);

  const fetchStatus = useCallback(async () => {
    try {
      const res = await apiFetch<DiagnosticStatus>({ path: '/battle-ledger/v1/diagnostic/status' });
      setStatus(res);
    } catch (err) {
      console.error('Diagnostic fetch error', err);
      setMessage({ text: 'Failed to load diagnostic data.', type: 'error' });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchStatus(); }, [fetchStatus]);

  const handleMigrate = async () => {
    setMigrating(true);
    setMessage(null);
    try {
      const res = await apiFetch<{ success: boolean; message: string }>({
        path: '/battle-ledger/v1/diagnostic/migrate',
        method: 'POST',
      });
      setMessage({ text: res.message, type: res.success ? 'success' : 'error' });
      await fetchStatus();
    } catch (err: any) {
      setMessage({ text: err?.message || 'Migration failed.', type: 'error' });
    } finally {
      setMigrating(false);
    }
  };

  const handleFixOrphans = async () => {
    if (!confirm('This will refund all orphaned withdrawal transactions back to user wallets. Continue?')) return;
    setFixingOrphans(true);
    setMessage(null);
    try {
      const res = await apiFetch<{ success: boolean; message: string; refunded: number }>({
        path: '/battle-ledger/v1/diagnostic/fix-orphaned',
        method: 'POST',
      });
      setMessage({ text: res.message, type: res.success ? 'success' : 'error' });
      await fetchStatus();
    } catch (err: any) {
      setMessage({ text: err?.message || 'Fix failed.', type: 'error' });
    } finally {
      setFixingOrphans(false);
    }
  };

  const formatDate = (d: string) =>
    new Date(d).toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });

  if (loading) {
    return (
      <div className="bl-diagnostics">
        {/* Migration skeleton */}
        <div className="bl-card">
          <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <SkeletonLoader width="180px" height="22px" />
            <SkeletonLoader width="130px" height="36px" borderRadius="6px" />
          </div>
          <div style={{ display: 'flex', gap: '2rem', padding: '1rem 0' }}>
            {[1,2,3,4].map(i => (
              <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
                <SkeletonLoader width="90px" height="12px" />
                <SkeletonLoader width="70px" height="18px" />
              </div>
            ))}
          </div>
        </div>
        {/* Tables skeleton */}
        <div className="bl-card">
          <div className="bl-card-header"><SkeletonLoader width="150px" height="22px" /></div>
          <div style={{ padding: '0.5rem 1rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
            {[1,2,3,4,5,6,7].map(i => (
              <div key={i} style={{ display: 'flex', gap: '1.5rem', alignItems: 'center' }}>
                <SkeletonLoader width="220px" height="16px" />
                <SkeletonLoader width="70px" height="22px" borderRadius="12px" />
                <SkeletonLoader width="30px" height="16px" />
              </div>
            ))}
          </div>
        </div>
        {/* Orphans skeleton */}
        <div className="bl-card">
          <div className="bl-card-header"><SkeletonLoader width="250px" height="22px" /></div>
          <div style={{ padding: '1rem' }}><SkeletonLoader width="300px" height="16px" /></div>
        </div>
        {/* Environment skeleton */}
        <div className="bl-card">
          <div className="bl-card-header"><SkeletonLoader width="120px" height="22px" /></div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '1rem', padding: '1rem' }}>
            {[1,2,3,4,5,6,7].map(i => (
              <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: '0.3rem', padding: '0.75rem', background: 'var(--bl-bg-secondary, #f8fafc)', borderRadius: 8 }}>
                <SkeletonLoader width="60px" height="11px" />
                <SkeletonLoader width="90px" height="16px" />
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bl-card bl-alert-error">
        <p>Could not load diagnostic data. Check your REST API and PHP error log.</p>
      </div>
    );
  }

  const missingTables = status.tables.filter(t => !t.exists);
  const allTablesOk = missingTables.length === 0;

  return (
    <div className="bl-diagnostics">
      {message && (
        <div className={`bl-alert bl-alert-${message.type}`} style={{ marginBottom: '1rem' }}>
          <p>{message.text}</p>
          <button className="bl-alert-close" onClick={() => setMessage(null)}>&times;</button>
        </div>
      )}

      {/* Migration */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-database" style={{ marginRight: 8 }}></span>
            Database Migration
          </h3>
          <button className="bl-btn-primary" onClick={handleMigrate} disabled={migrating}>
            {migrating ? (
              <><span className="dashicons dashicons-update spinning" style={{ marginRight: 6 }}></span>Running…</>
            ) : (
              <><span className="dashicons dashicons-migrate" style={{ marginRight: 6 }}></span>Run Migration</>
            )}
          </button>
        </div>
        <div className="bl-diagnostic-versions" style={{ display: 'flex', gap: '2rem', padding: '1rem 0' }}>
          <div>
            <label>Plugin Version</label>
            <strong>{status.plugin_version}</strong>
          </div>
          <div>
            <label>DB Version (stored)</label>
            <strong className={status.needs_migration ? 'bl-text-warning' : 'bl-text-success'}>
              {status.db_version || 'Not set'}
            </strong>
          </div>
          <div>
            <label>DB Version (target)</label>
            <strong>{status.db_version_target}</strong>
          </div>
          <div>
            <label>Status</label>
            {status.needs_migration ? (
              <span className="bl-badge bl-badge-warning">Migration Needed</span>
            ) : (
              <span className="bl-badge bl-badge-success">Up to Date</span>
            )}
          </div>
        </div>
      </div>

      {/* Tables */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>
            <span className="dashicons dashicons-list-view" style={{ marginRight: 8 }}></span>
            Database Tables
          </h3>
        </div>
        <table className="bl-table">
          <thead>
            <tr><th>Table</th><th>Status</th><th>Rows</th></tr>
          </thead>
          <tbody>
            {status.tables.map((t) => (
              <tr key={t.name}>
                <td><code>{t.full_name}</code></td>
                <td>
                  {t.exists ? (
                    <span className="bl-badge bl-badge-success">
                      <span className="dashicons dashicons-yes" style={{ fontSize: 14, width: 14, height: 14 }}></span> Exists
                    </span>
                  ) : (
                    <span className="bl-badge bl-badge-danger">
                      <span className="dashicons dashicons-no" style={{ fontSize: 14, width: 14, height: 14 }}></span> Missing
                    </span>
                  )}
                </td>
                <td>{t.exists ? t.rows : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {!allTablesOk && (
          <div className="bl-alert bl-alert-warning" style={{ marginTop: '1rem' }}>
            <strong>{missingTables.length} table(s) missing.</strong> Click "Run Migration" above to create them.
          </div>
        )}
      </div>

      {/* Orphaned Withdrawals */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-warning" style={{ marginRight: 8 }}></span>
            Orphaned Withdrawal Transactions
          </h3>
          {status.orphaned_withdrawals.length > 0 && (
            <button className="bl-btn-warning" onClick={handleFixOrphans} disabled={fixingOrphans}>
              {fixingOrphans ? (
                <><span className="dashicons dashicons-update spinning" style={{ marginRight: 6 }}></span>Refunding…</>
              ) : (
                <><span className="dashicons dashicons-undo" style={{ marginRight: 6 }}></span>Refund All ({status.orphaned_withdrawals.length})</>
              )}
            </button>
          )}
        </div>
        {status.orphaned_withdrawals.length === 0 ? (
          <p style={{ padding: '1rem', color: 'var(--bl-text-secondary)' }}>
            No orphaned transactions found. Everything looks good.
          </p>
        ) : (
          <>
            <p style={{ padding: '0.5rem 1rem', color: 'var(--bl-text-secondary)', fontSize: '0.85rem' }}>
              These are wallet debit transactions of type "withdrawal" that have no matching record in the withdrawal requests table.
              Refunding will credit the amount back to each user's wallet.
            </p>
            <table className="bl-table">
              <thead>
                <tr><th>TX #</th><th>User</th><th>Amount</th><th>Description</th><th>Date</th></tr>
              </thead>
              <tbody>
                {status.orphaned_withdrawals.map((o) => (
                  <tr key={o.transaction_id}>
                    <td>#{o.transaction_id}</td>
                    <td>{o.display_name} (ID: {o.user_id})</td>
                    <td className="bl-text-danger">{Math.abs(o.amount).toFixed(2)}</td>
                    <td>{o.description}</td>
                    <td>{formatDate(o.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </>
        )}
      </div>

      {/* Environment */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>
            <span className="dashicons dashicons-info-outline" style={{ marginRight: 8 }}></span>
            Environment
          </h3>
        </div>
        <div className="bl-diagnostic-grid">
          <div className="bl-diagnostic-item"><label>PHP</label><span>{status.environment.php_version}</span></div>
          <div className="bl-diagnostic-item"><label>WordPress</label><span>{status.environment.wp_version}</span></div>
          <div className="bl-diagnostic-item"><label>MySQL</label><span>{status.environment.mysql_version}</span></div>
          <div className="bl-diagnostic-item"><label>WooCommerce</label><span>{status.environment.woocommerce || 'Not active'}</span></div>
          <div className="bl-diagnostic-item">
            <label>WP_DEBUG</label>
            <span className={status.environment.wp_debug ? 'bl-text-success' : 'bl-text-secondary'}>
              {status.environment.wp_debug ? 'Enabled' : 'Disabled'}
            </span>
          </div>
          <div className="bl-diagnostic-item">
            <label>WP_DEBUG_LOG</label>
            <span className={status.environment.wp_debug_log ? 'bl-text-success' : 'bl-text-secondary'}>
              {status.environment.wp_debug_log ? 'Enabled' : 'Disabled'}
            </span>
          </div>
          <div className="bl-diagnostic-item"><label>DB Prefix</label><span>{status.environment.db_prefix}</span></div>
        </div>
      </div>

      {/* Debug Log */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-editor-code" style={{ marginRight: 8 }}></span>
            Debug Log (last 30 lines)
          </h3>
          <button className="bl-btn-secondary" onClick={fetchStatus}>
            <span className="dashicons dashicons-update" style={{ marginRight: 6 }}></span>Refresh
          </button>
        </div>
        {status.debug_log.length === 0 ? (
          <p style={{ padding: '1rem', color: 'var(--bl-text-secondary)' }}>
            Debug log is empty or WP_DEBUG_LOG is disabled.
          </p>
        ) : (
          <pre className="bl-debug-log">{status.debug_log.join('\n')}</pre>
        )}
      </div>
    </div>
  );
};

export default Settings;
