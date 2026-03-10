/**
 * Diagnostics & Migration Page — Admin Panel
 */
import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';

/* ── Types ─────────────────────────────────────────────────── */

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

/* ── Component ─────────────────────────────────────────────── */

const Diagnostics: React.FC = () => {
  const [status, setStatus] = useState<DiagnosticStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [migrating, setMigrating] = useState(false);
  const [fixingOrphans, setFixingOrphans] = useState(false);
  const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' | 'info' } | null>(null);

  /* Fetch diagnostic status */
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

  /* Run migration */
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

  /* Fix orphaned withdrawals */
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

  /* ── Render helpers ──────────────────────────────────────── */

  const formatDate = (d: string) =>
    new Date(d).toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });

  if (loading) {
    return (
      <div className="bl-diagnostics">
        <div className="bl-card" style={{ padding: '2rem', textAlign: 'center' }}>
          <span className="dashicons dashicons-update spinning" style={{ fontSize: 32 }}></span>
          <p>Running diagnostics…</p>
        </div>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bl-diagnostics">
        <div className="bl-card bl-alert-error">
          <p>Could not load diagnostic data. Check your REST API and PHP error log.</p>
        </div>
      </div>
    );
  }

  const missingTables = status.tables.filter(t => !t.exists);
  const allTablesOk = missingTables.length === 0;

  return (
    <div className="bl-diagnostics">

      {/* Notification Banner */}
      {message && (
        <div className={`bl-alert bl-alert-${message.type}`} style={{ marginBottom: '1rem' }}>
          <p>{message.text}</p>
          <button className="bl-alert-close" onClick={() => setMessage(null)}>&times;</button>
        </div>
      )}

      {/* ── Migration Card ──────────────────────────────────── */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-database" style={{ marginRight: 8 }}></span>
            Database Migration
          </h3>
          <button
            className="bl-btn-primary"
            onClick={handleMigrate}
            disabled={migrating}
          >
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

      {/* ── Tables Status ───────────────────────────────────── */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>
            <span className="dashicons dashicons-list-view" style={{ marginRight: 8 }}></span>
            Database Tables
          </h3>
        </div>
        <table className="bl-table">
          <thead>
            <tr>
              <th>Table</th>
              <th>Status</th>
              <th>Rows</th>
            </tr>
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

      {/* ── Orphaned Withdrawals ────────────────────────────── */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-warning" style={{ marginRight: 8 }}></span>
            Orphaned Withdrawal Transactions
          </h3>
          {status.orphaned_withdrawals.length > 0 && (
            <button
              className="bl-btn-warning"
              onClick={handleFixOrphans}
              disabled={fixingOrphans}
            >
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
              The wallet was debited but the withdrawal request was never saved (likely because the table didn't exist at the time).
              Refunding will credit the amount back to each user's wallet.
            </p>
            <table className="bl-table">
              <thead>
                <tr>
                  <th>TX #</th>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Description</th>
                  <th>Date</th>
                </tr>
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

      {/* ── Environment ─────────────────────────────────────── */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>
            <span className="dashicons dashicons-info-outline" style={{ marginRight: 8 }}></span>
            Environment
          </h3>
        </div>
        <div className="bl-diagnostic-grid">
          <div className="bl-diagnostic-item">
            <label>PHP</label>
            <span>{status.environment.php_version}</span>
          </div>
          <div className="bl-diagnostic-item">
            <label>WordPress</label>
            <span>{status.environment.wp_version}</span>
          </div>
          <div className="bl-diagnostic-item">
            <label>MySQL</label>
            <span>{status.environment.mysql_version}</span>
          </div>
          <div className="bl-diagnostic-item">
            <label>WooCommerce</label>
            <span>{status.environment.woocommerce || 'Not active'}</span>
          </div>
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
          <div className="bl-diagnostic-item">
            <label>DB Prefix</label>
            <span>{status.environment.db_prefix}</span>
          </div>
        </div>
      </div>

      {/* ── Debug Log ───────────────────────────────────────── */}
      <div className="bl-card">
        <div className="bl-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <h3>
            <span className="dashicons dashicons-editor-code" style={{ marginRight: 8 }}></span>
            Debug Log (last 30 lines)
          </h3>
          <button className="bl-btn-secondary" onClick={fetchStatus}>
            <span className="dashicons dashicons-update" style={{ marginRight: 6 }}></span>
            Refresh
          </button>
        </div>
        {status.debug_log.length === 0 ? (
          <p style={{ padding: '1rem', color: 'var(--bl-text-secondary)' }}>
            Debug log is empty or WP_DEBUG_LOG is disabled.
          </p>
        ) : (
          <pre className="bl-debug-log">
            {status.debug_log.join('\n')}
          </pre>
        )}
      </div>
    </div>
  );
};

export default Diagnostics;
