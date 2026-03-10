/**
 * Frontend Dashboard Loader Component
 */

import React from 'react';

interface LoaderProps {
  size?: 'small' | 'medium' | 'large';
  text?: string;
  fullScreen?: boolean;
}

const Loader: React.FC<LoaderProps> = ({
  size = 'medium',
  text,
  fullScreen = false,
}) => {
  const sizeClasses = {
    small: 'bl-loader-small',
    medium: 'bl-loader-medium',
    large: 'bl-loader-large',
  };

  const loader = (
    <div className={`bl-loader ${sizeClasses[size]}`}>
      <div className="bl-loader-spinner"></div>
      {text && <p className="bl-loader-text">{text}</p>}
    </div>
  );

  if (fullScreen) {
    return (
      <div className="bl-loader-fullscreen">
        {loader}
      </div>
    );
  }

  return loader;
};

export default Loader;
