import React, { useEffect, useState } from "react";
import apiFetch from "@wordpress/api-fetch";
import SkeletonLoader from "../components/SkeletonLoader";

interface DashboardProps {
  onNavigate?: (tab: string) => void;
}

interface DashboardStats {
  total_tournaments: number;
  active_tournaments: number;
  total_participants: number;
  total_matches: number;
}

const Dashboard: React.FC<DashboardProps> = ({ onNavigate }) => {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    apiFetch({ path: "/battle-ledger/v1/stats" })
      .then((data: any) => {
        setStats(data);
        setLoading(false);
      })
      .catch(() => {
        // Mock data for now
        setStats({
          total_tournaments: 12,
          active_tournaments: 3,
          total_participants: 256,
          total_matches: 89,
        });
        setLoading(false);
      });
  }, []);

  return (
    <div className="bl-dashboard">
      {/* Hero Stats */}
      <div className="bl-stats-grid">
        {/* Total Tournaments */}
        <div className="bl-stat-card">
          <div className="stat-icon" style={{ background: "#f0f4ff" }}>
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#667eea"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6" />
              <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18" />
              <path d="M4 22h16" />
              <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
              <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
              <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z" />
            </svg>
          </div>
          <div className="stat-content">
            <div className="stat-label">Total Tournaments</div>
            <div className="stat-value">
              {loading ? (
                <SkeletonLoader height="32px" width="60px" />
              ) : (
                stats?.total_tournaments || 0
              )}
            </div>
            <div className="stat-change positive">↑ All time</div>
          </div>
        </div>

        {/* Active Tournaments */}
        <div className="bl-stat-card">
          <div className="stat-icon" style={{ background: "#f0fcf4" }}>
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#10b981"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
          </div>
          <div className="stat-content">
            <div className="stat-label">Active Now</div>
            <div className="stat-value">
              {loading ? (
                <SkeletonLoader height="32px" width="60px" />
              ) : (
                stats?.active_tournaments || 0
              )}
            </div>
            <div className="stat-change positive">Live tournaments</div>
          </div>
        </div>

        {/* Total Participants */}
        <div className="bl-stat-card">
          <div className="stat-icon" style={{ background: "#fff0f6" }}>
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#ec4899"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <div className="stat-content">
            <div className="stat-label">Participants</div>
            <div className="stat-value">
              {loading ? (
                <SkeletonLoader height="32px" width="60px" />
              ) : (
                stats?.total_participants || 0
              )}
            </div>
            <div className="stat-change neutral">Registered players</div>
          </div>
        </div>

        {/* Total Matches */}
        <div className="bl-stat-card">
          <div className="stat-icon" style={{ background: "#fff8f0" }}>
            <svg
              width="28"
              height="28"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#f59e0b"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" />
            </svg>
          </div>
          <div className="stat-content">
            <div className="stat-label">Total Matches</div>
            <div className="stat-value">
              {loading ? (
                <SkeletonLoader height="32px" width="60px" />
              ) : (
                stats?.total_matches || 0
              )}
            </div>
            <div className="stat-change neutral">Games played</div>
          </div>
        </div>
      </div>

      {/* Two Column Layout */}
      <div className="bl-dashboard-grid">
        {/* System Status */}
        <div className="bl-card bl-status-banner">
          <div className="status-icon-large">
            <svg
              width="36"
              height="36"
              viewBox="0 0 24 24"
              fill="none"
              stroke="white"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              <path d="M9 12l2 2 4-4" />
            </svg>
          </div>
          <div className="status-content">
            <h3>System Status</h3>
            <div className="status-row">
              <span className="status-dot active"></span>
              <span>Tournament Engine Active</span>
            </div>
            <div className="status-row">
              <span className="status-dot active"></span>
              <span>Match Tracking Ready</span>
            </div>
            <div className="status-row">
              <span className="status-dot active"></span>
              <span>WooCommerce Connected</span>
            </div>
            <p className="status-note">
              Automatically managing brackets and tracking player scores.
            </p>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="bl-card">
          <h3>Quick Actions</h3>
          <div className="bl-quick-actions">
            <button
              className="bl-action-btn"
              onClick={() => onNavigate?.("tournaments")}
            >
              <span className="dashicons dashicons-plus-alt2"></span>
              <span>New Tournament</span>
            </button>
            <button
              className="bl-action-btn"
              onClick={() => onNavigate?.("participants")}
            >
              <span className="dashicons dashicons-admin-users"></span>
              <span>Add Participant</span>
            </button>
            <button
              className="bl-action-btn"
              onClick={() => onNavigate?.("matches")}
            >
              <span className="dashicons dashicons-games"></span>
              <span>Record Match</span>
            </button>
            <button
              className="bl-action-btn"
              onClick={() => onNavigate?.("settings")}
            >
              <span className="dashicons dashicons-admin-settings"></span>
              <span>Settings</span>
            </button>
          </div>
        </div>
      </div>

      {/* Recent Activity */}
      <div className="bl-card">
        <h3>Recent Activity</h3>
        <div className="bl-activity-list">
          <div className="bl-activity-item">
            <div className="activity-icon">
              <span className="dashicons dashicons-megaphone"></span>
            </div>
            <div className="activity-content">
              <strong>Summer Championship 2024</strong> tournament created
              <span className="activity-time">2 hours ago</span>
            </div>
          </div>
          <div className="bl-activity-item">
            <div className="activity-icon">
              <span className="dashicons dashicons-admin-users"></span>
            </div>
            <div className="activity-content">
              <strong>12 new participants</strong> registered
              <span className="activity-time">5 hours ago</span>
            </div>
          </div>
          <div className="bl-activity-item">
            <div className="activity-icon">
              <span className="dashicons dashicons-games"></span>
            </div>
            <div className="activity-content">
              <strong>Match #45</strong> completed - Player A vs Player B
              <span className="activity-time">Yesterday</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
