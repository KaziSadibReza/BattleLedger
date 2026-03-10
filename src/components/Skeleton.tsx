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
  const getVariantStyles = () => {
    switch (variant) {
      case 'circular':
        return { borderRadius: '50%' };
      case 'rectangular':
        return { borderRadius: '0' };
      case 'rounded':
        return { borderRadius: '8px' };
      case 'text':
      default:
        return { borderRadius: '4px' };
    }
  };

  const baseStyles: React.CSSProperties = {
    display: 'block',
    backgroundColor: 'var(--skeleton-bg, rgba(0, 0, 0, 0.08))',
    width: typeof width === 'number' ? `${width}px` : width,
    height: typeof height === 'number' ? `${height}px` : height,
    ...getVariantStyles(),
  };

  const items = Array.from({ length: count }, (_, i) => (
    <span
      key={i}
      className={`skeleton skeleton--${animation} ${className}`}
      style={{
        ...baseStyles,
        marginBottom: count > 1 && i < count - 1 ? '0.5rem' : 0,
      }}
    />
  ));

  return <>{items}</>;
};

// Skeleton Group for common patterns
interface SkeletonGroupProps {
  type: 'card' | 'list-item' | 'form-field' | 'table-row' | 'page-item';
  count?: number;
}

export const SkeletonGroup: React.FC<SkeletonGroupProps> = ({ type, count = 1 }) => {
  const items = Array.from({ length: count }, (_, i) => {
    switch (type) {
      case 'card':
        return (
          <div key={i} className="skeleton-card">
            <Skeleton variant="rounded" height={200} />
            <div style={{ padding: '1rem' }}>
              <Skeleton width="60%" height={24} />
              <Skeleton count={2} height={16} />
            </div>
          </div>
        );
      
      case 'list-item':
        return (
          <div key={i} className="skeleton-list-item" style={{ display: 'flex', alignItems: 'center', gap: '1rem', padding: '0.75rem 0' }}>
            <Skeleton variant="circular" width={40} height={40} />
            <div style={{ flex: 1 }}>
              <Skeleton width="40%" height={16} />
              <Skeleton width="70%" height={14} />
            </div>
          </div>
        );

      case 'form-field':
        return (
          <div key={i} className="skeleton-form-field" style={{ marginBottom: '1.5rem' }}>
            <Skeleton width="30%" height={14} />
            <Skeleton height={40} variant="rounded" />
          </div>
        );

      case 'table-row':
        return (
          <div key={i} className="skeleton-table-row" style={{ display: 'flex', gap: '1rem', padding: '0.75rem 0', borderBottom: '1px solid var(--border-color, #eee)' }}>
            <Skeleton width="25%" height={16} />
            <Skeleton width="35%" height={16} />
            <Skeleton width="20%" height={16} />
            <Skeleton width="20%" height={16} />
          </div>
        );

      case 'page-item':
        return (
          <div key={i} className="skeleton-page-item" style={{ 
            display: 'flex', 
            justifyContent: 'space-between', 
            alignItems: 'center',
            padding: '1.25rem',
            backgroundColor: 'var(--card-bg, #fff)',
            borderRadius: '12px',
            marginBottom: '1rem',
            border: '1px solid var(--border-color, #eee)'
          }}>
            <div style={{ flex: 1 }}>
              <Skeleton width="40%" height={20} />
              <Skeleton width="60%" height={14} />
            </div>
            <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
              <Skeleton width={80} height={24} variant="rounded" />
              <Skeleton width={150} height={36} variant="rounded" />
              <Skeleton width={70} height={32} variant="rounded" />
              <Skeleton width={60} height={32} variant="rounded" />
            </div>
          </div>
        );

      default:
        return null;
    }
  });

  return <>{items}</>;
};

export default Skeleton;
