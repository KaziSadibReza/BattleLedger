/**
 * Skeleton Component - Modern, Clean Loading States
 */

import React from 'react';

interface SkeletonProps {
  width?: string | number;
  height?: string | number;
  variant?: 'text' | 'circular' | 'rectangular' | 'rounded';
  animation?: 'pulse' | 'wave' | 'none';
  className?: string;
  count?: number;
}

const Skeleton: React.FC<SkeletonProps> = ({
  width = '100%',
  height = '1rem',
  variant = 'text',
  animation = 'pulse',
  className = '',
  count = 1,
}) => {
  const getVariantStyles = (): React.CSSProperties => {
    const baseRadius = {
      circular: '50%',
      rectangular: '0',
      rounded: '12px',
      text: '6px',
    };

    return {
      borderRadius: baseRadius[variant] || baseRadius.text,
    };
  };

  const baseStyles: React.CSSProperties = {
    display: 'block',
    width: typeof width === 'number' ? `${width}px` : width,
    height: typeof height === 'number' ? `${height}px` : height,
    position: 'relative',
    overflow: 'hidden',
    ...getVariantStyles(),
  };

  const animationStyles: React.CSSProperties = animation === 'pulse' 
    ? {
        animation: 'bl-skeleton-pulse 1.5s ease-in-out infinite',
      }
    : animation === 'wave'
    ? {
        animation: 'bl-skeleton-wave 1.5s ease-in-out infinite',
      }
    : {};

  const items = Array.from({ length: count }, (_, i) => (
    <span
      key={i}
      className={`bl-skeleton ${className}`}
      style={{
        ...baseStyles,
        ...animationStyles,
        marginBottom: count > 1 && i < count - 1 ? '0.5rem' : 0,
      }}
    />
  ));

  return <>{items}</>;
};

// Skeleton Group for common patterns
interface SkeletonGroupProps {
  type: 'card' | 'list-item' | 'form-field' | 'table-row' | 'stats-card';
  count?: number;
}

export const SkeletonGroup: React.FC<SkeletonGroupProps> = ({ type, count = 1 }) => {
  const items = Array.from({ length: count }, (_, i) => {
    switch (type) {
      case 'card':
        return (
          <div key={i} className="bl-skeleton-card" style={{ marginBottom: '1rem' }}>
            <Skeleton variant="rounded" height={200} />
            <div style={{ padding: '1.5rem' }}>
              <Skeleton width="60%" height={24} />
              <div style={{ marginTop: '0.75rem' }}>
                <Skeleton count={2} height={16} />
              </div>
            </div>
          </div>
        );
      
      case 'stats-card':
        return (
          <div 
            key={i} 
            className="bl-skeleton-stats-card"
          >
            <Skeleton variant="circular" width={56} height={56} />
            <div style={{ flex: 1 }}>
              <Skeleton width="40%" height={14} />
              <div style={{ marginTop: '0.5rem' }}>
                <Skeleton width="60%" height={24} />
              </div>
            </div>
          </div>
        );
      
      case 'list-item':
        return (
          <div 
            key={i} 
            className="bl-skeleton-list-item"
          >
            <Skeleton variant="circular" width={40} height={40} />
            <div style={{ flex: 1 }}>
              <Skeleton width="35%" height={16} />
              <div style={{ marginTop: '0.5rem' }}>
                <Skeleton width="60%" height={14} />
              </div>
            </div>
            <Skeleton width="80px" height={16} />
          </div>
        );

      case 'form-field':
        return (
          <div key={i} className="bl-skeleton-form-field" style={{ marginBottom: '1.5rem' }}>
            <Skeleton width="30%" height={14} />
            <div style={{ marginTop: '0.5rem' }}>
              <Skeleton height={44} variant="rounded" />
            </div>
          </div>
        );

      case 'table-row':
        return (
          <div 
            key={i} 
            className="bl-skeleton-table-row" 
            style={{ 
              gridTemplateColumns: '25% 35% 20% 20%',
            }}
          >
            <Skeleton height={16} />
            <Skeleton height={16} />
            <Skeleton height={16} />
            <Skeleton height={16} />
          </div>
        );

      default:
        return null;
    }
  });

  return <>{items}</>;
};

export default Skeleton;
