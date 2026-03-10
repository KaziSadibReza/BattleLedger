/**
 * NotificationBell — User Dashboard Notification Dropdown
 *
 * Uses the /user/notifications REST API endpoints (scoped to current logged-in user).
 * Polls for unread count every 30 seconds.
 *
 * Notification types:
 *  - success   (check icon)  — Deposit confirmed, Withdrawal approved, Tournament joined
 *  - alert     (alert icon)  — Withdrawal rejected
 *  - tournament(trophy icon) — Tournament completed / results
 *  - system    (info icon)   — Withdrawal submitted / general info
 */
import { useState, useRef, useEffect, useCallback } from "react";
import {
  Bell,
  BellRing,
  X,
  Trophy,
  AlertCircle,
  CheckCircle,
  ChevronRight,
  Info,
} from "lucide-react";
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

function timeAgo(dateStr: string): string {
  const now = Date.now();
  const then = new Date(
    dateStr.endsWith("Z") ? dateStr : dateStr + "Z"
  ).getTime();
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

const POLL_INTERVAL = 30_000;
const API_BASE = "/battle-ledger/v1/user/notifications";

/* ── Component ─────────────────────────────────────── */

function NotificationBell() {
  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [selectedNotification, setSelectedNotification] =
    useState<Notification | null>(null);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  /* ── Push notification state ─── */
  const [pushSupported, setPushSupported] = useState(false);
  const [pushEnabled, setPushEnabled] = useState(false);
  const [pushLoading, setPushLoading] = useState(false);

  /* ── Fetch unread count (lightweight polling) ─── */
  const fetchUnreadCount = useCallback(async () => {
    try {
      const res = await apiFetch<{ count: number }>({
        path: `${API_BASE}/unread-count`,
      });
      setUnreadCount(res.count);
    } catch {
      /* silently ignore */
    }
  }, []);

  /* ── Fetch notification list ─── */
  const fetchNotifications = useCallback(async () => {
    setLoading(true);
    try {
      const res = await apiFetch<NotificationsResponse>({
        path: `${API_BASE}?per_page=20`,
      });
      setNotifications(res.notifications);
      setUnreadCount(res.notifications.filter((n) => !n.is_read).length);
    } catch {
      /* silently ignore */
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
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node)
      ) {
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

  /* ── Push notification setup ─── */
  useEffect(() => {
    if ("serviceWorker" in navigator && "PushManager" in window) {
      setPushSupported(true);
      // Check if already subscribed
      navigator.serviceWorker.ready.then((reg) => {
        reg.pushManager.getSubscription().then((sub) => {
          setPushEnabled(!!sub);
        });
      });
      // Register the service worker
      navigator.serviceWorker.register(
        "/wp-content/plugins/BattleLedger/assets/bl-sw.js"
      ).catch(() => { /* SW registration failed — push won't work */ });
    }
  }, []);

  const togglePush = async () => {
    if (pushLoading) return;
    setPushLoading(true);

    try {
      const reg = await navigator.serviceWorker.ready;

      if (pushEnabled) {
        // Unsubscribe
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
          await sub.unsubscribe();
          await apiFetch({
            path: "/battle-ledger/v1/push/unsubscribe",
            method: "POST",
            data: { endpoint: sub.endpoint },
          });
        }
        setPushEnabled(false);
      } else {
        // Get VAPID public key
        const { publicKey } = await apiFetch<{ publicKey: string }>({
          path: "/battle-ledger/v1/push/vapid-key",
        });

        // Convert base64url to Uint8Array
        const raw = atob(publicKey.replace(/-/g, "+").replace(/_/g, "/"));
        const key = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) key[i] = raw.charCodeAt(i);

        // Subscribe
        const sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: key,
        });

        const json = sub.toJSON();
        await apiFetch({
          path: "/battle-ledger/v1/push/subscribe",
          method: "POST",
          data: {
            endpoint: sub.endpoint,
            keys: {
              p256dh: json.keys?.p256dh ?? "",
              auth: json.keys?.auth ?? "",
            },
          },
        });
        setPushEnabled(true);
      }
    } catch {
      /* permission denied or API error */
    } finally {
      setPushLoading(false);
    }
  };

  /* ── Icon by type ─── */
  const getIcon = (type: Notification["type"]) => {
    switch (type) {
      case "tournament":
        return <Trophy size={16} />;
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
    setNotifications((prev) =>
      prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
    );
    setUnreadCount((c) => Math.max(0, c - 1));
    try {
      await apiFetch({
        path: `${API_BASE}/mark-read`,
        method: "POST",
        data: { ids: [id] },
      });
    } catch {
      /* ignore */
    }
  };

  /* ── Mark all as read ─── */
  const markAllAsRead = async () => {
    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
    setUnreadCount(0);
    try {
      await apiFetch({ path: `${API_BASE}/mark-all-read`, method: "POST" });
    } catch {
      /* ignore */
    }
  };

  /* ── Dismiss single notification ─── */
  const dismissNotification = async (id: number) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
    setSelectedNotification(null);
    try {
      await apiFetch({ path: `${API_BASE}/${id}`, method: "DELETE" });
    } catch {
      /* ignore */
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
        className={`bl-btn-icon ${isOpen ? "active" : ""}`}
        title="Notifications"
        onClick={() => {
          setIsOpen(!isOpen);
          setSelectedNotification(null);
        }}
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="bl-notification-badge">
            {unreadCount > 99 ? "99+" : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div
          className={`bl-notification-dropdown ${selectedNotification ? "expanded" : ""}`}
        >
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

              {/* List */}
              <div className="bl-notification-list">
                {loading && notifications.length === 0 ? (
                  <div className="bl-notification-skeleton">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="bl-notification-item">
                        <div
                          className="bl-skeleton"
                          style={{
                            width: 36,
                            height: 36,
                            borderRadius: 10,
                            flexShrink: 0,
                          }}
                        />
                        <div className="bl-notification-content">
                          <div
                            className="bl-skeleton"
                            style={{
                              width: "60%",
                              height: 14,
                              borderRadius: 4,
                            }}
                          />
                          <div
                            className="bl-skeleton"
                            style={{
                              width: "90%",
                              height: 12,
                              borderRadius: 4,
                            }}
                          />
                          <div
                            className="bl-skeleton"
                            style={{
                              width: "30%",
                              height: 11,
                              borderRadius: 4,
                            }}
                          />
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
                      <div
                        className={`bl-notification-icon ${notification.type}`}
                      >
                        {getIcon(notification.type)}
                      </div>
                      <div className="bl-notification-content">
                        <span className="bl-notification-title">
                          {notification.title}
                        </span>
                        <span className="bl-notification-message">
                          {notification.message}
                        </span>
                        <span className="bl-notification-time">
                          {timeAgo(notification.created_at)}
                        </span>
                      </div>
                      <ChevronRight
                        size={16}
                        className="bl-notification-arrow"
                      />
                    </div>
                  ))
                )}
              </div>

              {/* Footer */}
              <div className="bl-notification-footer">
                {pushSupported && (
                  <button
                    className={`bl-push-toggle ${pushEnabled ? "active" : ""}`}
                    onClick={togglePush}
                    disabled={pushLoading}
                    title={pushEnabled ? "Disable push notifications" : "Enable push notifications"}
                  >
                    <BellRing size={14} />
                    {pushLoading
                      ? "..."
                      : pushEnabled
                        ? "Push On"
                        : "Enable Push"}
                  </button>
                )}
                <button
                  className="bl-view-all"
                  onClick={() => setIsOpen(false)}
                >
                  Close
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
                    <ChevronRight size={18} className="rotated" /> Back
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
                  <div
                    className={`bl-notification-expanded-icon ${selectedNotification.type}`}
                  >
                    {getIcon(selectedNotification.type)}
                  </div>
                  <h3>{selectedNotification.title}</h3>
                  <span className="bl-notification-expanded-time">
                    {timeAgo(selectedNotification.created_at)}
                  </span>
                  <p>{selectedNotification.message}</p>
                </div>

                <div className="bl-notification-expanded-actions">
                  <button
                    className="bl-btn bl-btn-secondary"
                    onClick={() =>
                      dismissNotification(selectedNotification.id)
                    }
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

export default NotificationBell;
