/**
 * Reusable Stat Card Component
 */

import React from 'react';

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  color?: 'success' | 'danger' | 'warning' | 'info' | 'primary';
  subtitle?: string;
}

const StatCard: React.FC<StatCardProps> = ({
  icon,
  label,
  value,
  color = 'primary',
  subtitle,
}) => {
  return (
    <div className="bl-stat-card">
      <div className={`bl-stat-icon ${color}`}>{icon}</div>
      <div className="bl-stat-content">
        <label>{label}</label>
        <h3>{value}</h3>
        {subtitle && <span className="bl-stat-subtitle">{subtitle}</span>}
      </div>
    </div>
  );
};

export default StatCard;
