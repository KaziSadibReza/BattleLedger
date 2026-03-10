/**
 * Notifications Dropdown — Admin Panel
 *
 * Fetches real notifications from the REST API (bl_notifications table).
 * Polls for unread count every 30 seconds for near real-time badge updates.
 *
 * Notification types displayed:
 *  ─ tournament  (trophy icon)   — Tournament created / activated / starting soon
 *  ─ participant (users icon)    — New registration / participant removed / tournament full
 *  ─ success     (check icon)    — Match completed / tournament completed / deposit / withdrawal approved
 *  ─ alert       (alert icon)    — Payment pending / withdrawal requested / rejected / cancelled
 *  ─ system      (bell icon)     — System info / warnings / errors
 */
import { useState, useRef, useEffect, useCallback } from "react";
import { Bell, X, Trophy, Users, AlertCircle, CheckCircle, ChevronRight, Info } from "lucide-react";
import apiFetch from "@wordpress/api-fetch";

/* ── Types ─────────────────────────────────────────── */

interface Notification {
  id: number;
  type: "tournament" | "participant" | "alert" | "success" | "system";
  title: string;
  message: string;
  icon: string;
  link: string;
  is_read: boolean;
  metadata: Record<string, unknown> | null;
  created_at: string;
}

interface NotificationsResponse {
  notifications: Notification[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

/* ── Helpers ───────────────────────────────────────── */

/** Relative time label (e.g. "2 hours ago", "3 days ago") */
function timeAgo(dateStr: string): string {
  const now = Date.now();
  // Server stores UTC — append Z so JS parses as UTC
  const then = new Date(dateStr.endsWith("Z") ? dateStr : dateStr + "Z").getTime();
  const diff = Math.max(0, now - then);
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "Just now";
  if (mins < 60) return `${mins} min ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  const days = Math.floor(hours / 24);
  if (days < 30) return `${days} day${days > 1 ? "s" : ""} ago`;
  const months = Math.floor(days / 30);
  return `${months} month${months > 1 ? "s" : ""} ago`;
}

/* ── Polling interval (ms) ─────────────────────────── */
const POLL_INTERVAL = 30_000;

/* ── Component ─────────────────────────────────────── */

function Notifications() {
  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [selectedNotification, setSelectedNotification] = useState<Notification | null>(null);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  /* ── Fetch unread count (lightweight polling) ─── */
  const fetchUnreadCount = useCallback(async () => {
    try {
      const res = await apiFetch<{ count: number }>({ path: "/battle-ledger/v1/notifications/unread-count" });
      setUnreadCount(res.count);
    } catch {
      // silently ignore
    }
  }, []);

  /* ── Fetch notification list ─── */
  const fetchNotifications = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<NotificationsResponse>({ path: "/battle-ledger/v1/notifications?per_page=20" });
      setNotifications(res.notifications);
      setUnreadCount(res.notifications.filter((n) => !n.is_read).length);
    } catch {
      // silently ignore
    } finally {
      setLoading(false);
    }
  }, []);

  /* ── Initial load + polling ─── */
  useEffect(() => {
    fetchUnreadCount();
    const timer = setInterval(fetchUnreadCount, POLL_INTERVAL);
    return () => clearInterval(timer);
  }, [fetchUnreadCount]);

  /* ── Load full list when dropdown opens ─── */
  useEffect(() => {
    if (isOpen) fetchNotifications();
  }, [isOpen, fetchNotifications]);

  /* ── Close on outside click ─── */
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setSelectedNotification(null);
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  /* ── Close on Escape ─── */
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        if (selectedNotification) setSelectedNotification(null);
        else setIsOpen(false);
      }
    };
    document.addEventListener("keydown", handleEscape);
    return () => document.removeEventListener("keydown", handleEscape);
  }, [selectedNotification]);

  /* ── Icon by type ─── */
  const getIcon = (type: Notification["type"]) => {
    switch (type) {
      case "tournament":
        return <Trophy size={16} />;
      case "participant":
        return <Users size={16} />;
      case "alert":
        return <AlertCircle size={16} />;
      case "success":
        return <CheckCircle size={16} />;
      case "system":
        return <Info size={16} />;
      default:
        return <Bell size={16} />;
    }
  };

  /* ── Mark single as read ─── */
  const markAsRead = async (id: number) => {
    setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)));
    setUnreadCount((c) => Math.max(0, c - 1));
    try {
      await apiFetch({ path: "/battle-ledger/v1/notifications/mark-read", method: "POST", data: { ids: [id] } });
    } catch {
      // ignore
    }
  };

  /* ── Mark all as read ─── */
  const markAllAsRead = async () => {
    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    setUnreadCount(0);
    try {
      await apiFetch({ path: "/battle-ledger/v1/notifications/mark-all-read", method: "POST" });
    } catch {
      // ignore
    }
  };

  /* ── Dismiss single notification ─── */
  const dismissNotification = async (id: number) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
    setSelectedNotification(null);
    try {
      await apiFetch({ path: `/battle-ledger/v1/notifications/${id}`, method: "DELETE" });
    } catch {
      // ignore
    }
  };

  /* ── Click handler ─── */
  const handleNotificationClick = (notification: Notification) => {
    if (!notification.is_read) markAsRead(notification.id);
    setSelectedNotification(notification);
  };

  return (
    <div className="bl-notifications" ref={dropdownRef}>
      <button
        className={`bl-notification-trigger ${isOpen ? "active" : ""}`}
        onClick={() => {
          setIsOpen(!isOpen);
          setSelectedNotification(null);
        }}
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="bl-notification-badge">{unreadCount > 99 ? "99+" : unreadCount}</span>
        )}
      </button>

      {isOpen && (
        <div className={`bl-notification-dropdown ${selectedNotification ? "expanded" : ""}`}>
          {!selectedNotification ? (
            <>
              {/* Header */}
              <div className="bl-notification-header">
                <h4>Notifications</h4>
                {unreadCount > 0 && (
                  <button className="bl-mark-read" onClick={markAllAsRead}>
                    Mark all read
                  </button>
                )}
              </div>

              {/* Notifications List */}
              <div className="bl-notification-list">
                {loading && notifications.length === 0 ? (
                  <div className="bl-notification-skeleton">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="bl-notification-item">
                        <div className="bl-skeleton" style={{ width: 36, height: 36, borderRadius: 10, flexShrink: 0 }} />
                        <div className="bl-notification-content">
                          <div className="bl-skeleton" style={{ width: "60%", height: 14, borderRadius: 4 }} />
                          <div className="bl-skeleton" style={{ width: "90%", height: 12, borderRadius: 4 }} />
                          <div className="bl-skeleton" style={{ width: "30%", height: 11, borderRadius: 4 }} />
                        </div>
                      </div>
                    ))}
                  </div>
                ) : notifications.length === 0 ? (
                  <div className="bl-notification-empty">
                    <Bell size={32} />
                    <p>No notifications yet</p>
                  </div>
                ) : (
                  notifications.map((notification) => (
                    <div
                      key={notification.id}
                      className={`bl-notification-item ${!notification.is_read ? "unread" : ""}`}
                      onClick={() => handleNotificationClick(notification)}
                    >
                      <div className={`bl-notification-icon ${notification.type}`}>
                        {getIcon(notification.type)}
                      </div>
                      <div className="bl-notification-content">
                        <span className="bl-notification-title">{notification.title}</span>
                        <span className="bl-notification-message">{notification.message}</span>
                        <span className="bl-notification-time">{timeAgo(notification.created_at)}</span>
                      </div>
                      <ChevronRight size={16} className="bl-notification-arrow" />
                    </div>
                  ))
                )}
              </div>

              {/* Footer */}
              <div className="bl-notification-footer">
                <button className="bl-view-all" onClick={() => setIsOpen(false)}>
                  View All Notifications
                </button>
              </div>
            </>
          ) : (
            <>
              {/* Expanded View */}
              <div className="bl-notification-expanded">
                <div className="bl-notification-expanded-header">
                  <button
                    className="bl-notification-back"
                    onClick={() => setSelectedNotification(null)}
                  >
                    <ChevronRight size={18} className="rotated" />
                    Back
                  </button>
                  <button
                    className="bl-notification-close"
                    onClick={() => {
                      setIsOpen(false);
                      setSelectedNotification(null);
                    }}
                  >
                    <X size={18} />
                  </button>
                </div>

                <div className="bl-notification-expanded-content">
                  <div className={`bl-notification-expanded-icon ${selectedNotification.type}`}>
                    {getIcon(selectedNotification.type)}
                  </div>
                  <h3>{selectedNotification.title}</h3>
                  <span className="bl-notification-expanded-time">
                    {timeAgo(selectedNotification.created_at)}
                  </span>
                  <p>{selectedNotification.message}</p>
                </div>

                <div className="bl-notification-expanded-actions">
                  {selectedNotification.link && (
                    <button
                      className="bl-btn bl-btn-primary"
                      onClick={() => {
                        window.location.hash = selectedNotification.link.replace("#", "");
                        setIsOpen(false);
                        setSelectedNotification(null);
                      }}
                    >
                      View Details
                    </button>
                  )}
                  <button
                    className="bl-btn bl-btn-secondary"
                    onClick={() => dismissNotification(selectedNotification.id)}
                  >
                    Dismiss
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      )}
    </div>
  );
}

export default Notifications;
