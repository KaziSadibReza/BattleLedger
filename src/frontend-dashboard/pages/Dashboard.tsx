/**
 * Dashboard Overview Page - Frontend
 */

import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { Wallet, Trophy, TrendingUp, Clock } from 'lucide-react';
import StatCard from '../components/StatCard';
import Skeleton from '../components/Skeleton';
import { showNotification } from '../components/Notifications';

interface DashboardStats {
  wallet_balance: number;
  currency: string;
  total_tournaments: number;
  active_tournaments: number;
  recent_transactions: number;
}

interface CurrentUser {
  id: number;
  email: string;
  displayName: string;
  avatar: string;
}

interface DashboardProps {
  currentUser: CurrentUser;
  onNavigate?: (tab: string) => void;
}

const Dashboard: React.FC<DashboardProps> = ({ currentUser, onNavigate }) => {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  const fetchDashboardStats = useCallback(async () => {
    try {
      setLoading(true);
      const response = await apiFetch<DashboardStats>({
        path: '/battle-ledger/v1/user/dashboard-stats',
      });
      setStats(response);
    } catch (error) {
      console.error('Error fetching dashboard stats:', error);
      showNotification('Failed to load dashboard data', 'error');
      // Set default stats on error
      setStats({
        wallet_balance: 0,
        currency: 'USD',
        total_tournaments: 0,
        active_tournaments: 0,
        recent_transactions: 0,
      });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboardStats();
  }, [fetchDashboardStats]);

  const formatCurrency = (amount: number, currency: string = 'USD') => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(amount);
  };

  return (
    <div className="bl-dashboard-page">
      {/* Welcome Banner */}
      <div className="bl-welcome-banner">
        <div className="bl-welcome-content">
          <h2>Welcome back, {currentUser.displayName}!</h2>
          <p>Here's what's happening with your account</p>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="bl-stats-grid">
        {loading ? (
          <>
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="bl-stat-card">
                <Skeleton variant="circular" width={56} height={56} />
                <div style={{ flex: 1 }}>
                  <Skeleton width="40%" height={14} />
                  <Skeleton width="60%" height={24} />
                </div>
              </div>
            ))}
          </>
        ) : (
          <>
            <div
              className="bl-stat-card-clickable"
              onClick={() => onNavigate?.('wallet')}
            >
              <StatCard
                icon={<Wallet size={24} />}
                label="Wallet Balance"
                value={formatCurrency(stats?.wallet_balance || 0, stats?.currency)}
                color="success"
                subtitle="Click to view details"
              />
            </div>

            <StatCard
              icon={<Trophy size={24} />}
              label="My Tournaments"
              value={stats?.total_tournaments || 0}
              color="primary"
              subtitle={`${stats?.active_tournaments || 0} active`}
            />

            <StatCard
              icon={<TrendingUp size={24} />}
              label="Recent Activity"
              value={stats?.recent_transactions || 0}
              color="info"
              subtitle="Last 7 days"
            />
          </>
        )}
      </div>

      {/* Quick Actions */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>Quick Actions</h3>
        </div>
        <div className="bl-quick-actions">
          <button
            className="bl-quick-action-btn"
            onClick={() => onNavigate?.('wallet')}
          >
            <Wallet size={20} />
            <span>Manage Wallet</span>
          </button>
          <button
            className="bl-quick-action-btn"
            onClick={() => onNavigate?.('tournaments')}
          >
            <Trophy size={20} />
            <span>My Tournaments</span>
          </button>
          <button
            className="bl-quick-action-btn"
            onClick={() => onNavigate?.('profile')}
          >
            <Clock size={20} />
            <span>Edit Profile</span>
          </button>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>Recent Activity</h3>
        </div>
        <div className="bl-empty-state">
          <Clock size={48} />
          <p>No recent activity</p>
          <span className="bl-empty-subtitle">
            Your recent transactions and matches will appear here
          </span>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
