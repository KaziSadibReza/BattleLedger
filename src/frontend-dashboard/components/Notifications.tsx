/**
 * Frontend Dashboard Notifications Component
 */

import React, { useState, useEffect } from 'react';
import { X, AlertCircle, CheckCircle, Info, AlertTriangle } from 'lucide-react';

interface Notification {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  message: string;
  duration?: number;
}

const Notifications: React.FC = () => {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  useEffect(() => {
    // Listen for custom notification events
    const handleNotification = (event: CustomEvent<Omit<Notification, 'id'>>) => {
      const notification: Notification = {
        id: Date.now().toString(),
        ...event.detail,
        duration: event.detail.duration || 5000,
      };

      setNotifications((prev) => [...prev, notification]);

      // Auto-remove after duration
      if (notification.duration) {
        setTimeout(() => {
          removeNotification(notification.id);
        }, notification.duration);
      }
    };

    window.addEventListener('bl-notification' as any, handleNotification);
    return () => {
      window.removeEventListener('bl-notification' as any, handleNotification);
    };
  }, []);

  const removeNotification = (id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  };

  const getIcon = (type: Notification['type']) => {
    switch (type) {
      case 'success':
        return <CheckCircle size={20} />;
      case 'error':
        return <AlertCircle size={20} />;
      case 'warning':
        return <AlertTriangle size={20} />;
      case 'info':
        return <Info size={20} />;
      default:
        return <Info size={20} />;
    }
  };

  if (notifications.length === 0) return null;

  return (
    <div className="bl-notifications-container">
      {notifications.map((notification) => (
        <div
          key={notification.id}
          className={`bl-notification bl-notification-${notification.type}`}
        >
          <div className="bl-notification-icon">{getIcon(notification.type)}</div>
          <div className="bl-notification-content">
            <p>{notification.message}</p>
          </div>
          <button
            className="bl-notification-close"
            onClick={() => removeNotification(notification.id)}
          >
            <X size={16} />
          </button>
        </div>
      ))}
    </div>
  );
};

export default Notifications;

// Helper function to trigger notifications
export const showNotification = (
  message: string,
  type: 'success' | 'error' | 'warning' | 'info' = 'info',
  duration?: number
) => {
  const event = new CustomEvent('bl-notification', {
    detail: { message, type, duration },
  });
  window.dispatchEvent(event);
};
