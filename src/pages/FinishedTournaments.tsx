import React, { useState, useEffect, useCallback } from "react";
import {
  Trophy,
  Users,
  Calendar,
  Clock,
  Medal,
  Award,
  Loader2,
  RefreshCw,
  Gamepad2,
  ChevronDown,
  ChevronUp,
  DollarSign,
  CheckCircle2,
  Search,
  Trash2,
  AlertTriangle,
  X,
  Crosshair,
} from "lucide-react";
import {
  fetchFinishedTournaments,
  deleteFinishedTournament,
} from "./tournaments/api";
import type { FinishedTournament } from "./tournaments/types";
import eventBus from "../lib/eventBus";
import { useGameRules } from "../lib/useGameRules";

/* ── helpers ────────────────────────────────────────────────── */
const fmtDate = (d: string | null) => {
  if (!d) return "—";
  return new Date(d).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
};

const fmtFull = (d: string | null) => {
  if (!d) return "—";
  return new Date(d).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
};

/* ── Main component ─────────────────────────────────────────── */
const FinishedTournaments: React.FC = () => {
  const [tournaments, setTournaments] = useState<FinishedTournament[]>([]);
  const [games] = useGameRules();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [search, setSearch] = useState("");
  const [deleteConfirm, setDeleteConfirm] = useState<{ id: number; name: string } | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [toast, setToast] = useState<{ message: string; type: "success" | "error" } | null>(null);

  const load = useCallback(async () => {
    try {
      const finished = await fetchFinishedTournaments();
      setTournaments(Array.isArray(finished) ? finished : []);
    } catch {
      /* silent */
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  /* auto-refresh when winners are saved from the Live tab */
  useEffect(() => {
    eventBus.on("finished:changed", load);
    return () => eventBus.off("finished:changed", load);
  }, [load]);

  const handleRefresh = () => {
    setRefreshing(true);
    load();
  };

  const getGameName = (slug: string) =>
    games.find((g) => g.slug === slug)?.game_name || slug || "—";
  const getGameIcon = (slug: string) =>
    games.find((g) => g.slug === slug)?.game_icon || "";

  const toggle = (id: number) =>
    setExpandedId((prev) => (prev === id ? null : id));

  const handleDelete = async () => {
    if (!deleteConfirm) return;
    setDeleting(true);
    try {
      await deleteFinishedTournament(deleteConfirm.id);
      setToast({ message: `"${deleteConfirm.name}" deleted successfully`, type: "success" });
      setDeleteConfirm(null);
      setExpandedId(null);
      load();
    } catch {
      setToast({ message: "Failed to delete tournament", type: "error" });
    } finally {
      setDeleting(false);
    }
  };

  // Auto-dismiss toast
  useEffect(() => {
    if (!toast) return;
    const t = setTimeout(() => setToast(null), 3500);
    return () => clearTimeout(t);
  }, [toast]);

  /* filter by search */
  const filtered = tournaments.filter((t) => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (
      t.name.toLowerCase().includes(q) ||
      getGameName(t.game_type).toLowerCase().includes(q)
    );
  });

  /* loading skeleton */
  if (loading) {
    return (
      <div className="bl-finished">
        <div className="bl-finished-header">
          <div className="bl-finished-title">
            <CheckCircle2 size={20} />
            <h2>Finished Tournaments</h2>
          </div>
        </div>
        <div className="bl-finished-skeleton-grid">
          {[1, 2, 3, 4].map((i) => (
            <div className="bl-finished-card-skeleton" key={i}>
              <div className="bl-finished-skel-bar skel-status" />
              <div className="bl-finished-skel-identity">
                <div className="bl-finished-skel-bar skel-icon" />
                <div className="bl-finished-skel-identity-text">
                  <div className="bl-finished-skel-bar skel-name" />
                  <div className="bl-finished-skel-bar skel-game" />
                </div>
              </div>
              <div className="bl-finished-skel-stats">
                <div className="bl-finished-skel-bar skel-stat" />
                <div className="bl-finished-skel-bar skel-stat" />
                <div className="bl-finished-skel-bar skel-stat" />
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="bl-finished">
      {/* header */}
      <div className="bl-finished-header">
        <div className="bl-finished-title">
          <CheckCircle2 size={20} />
          <h2>Finished Tournaments</h2>
          <span className="bl-finished-count">{tournaments.length}</span>
        </div>
        <div className="bl-finished-actions">
          <div className="bl-finished-search">
            <Search size={14} />
            <input
              type="text"
              placeholder="Search tournaments..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <button
            className="bl-finished-refresh"
            onClick={handleRefresh}
            disabled={refreshing}
          >
            <RefreshCw size={15} className={refreshing ? "bl-spin" : ""} />
            Refresh
          </button>
        </div>
      </div>

      {/* empty state */}
      {tournaments.length === 0 && (
        <div className="bl-empty-state">
          <div className="bl-empty-state-ring finished">
            <CheckCircle2 size={28} />
          </div>
          <h3>No Finished Tournaments</h3>
          <p>When you assign winners to a tournament,<br/>a snapshot is saved here as a finished record.</p>
        </div>
      )}

      {/* no search results */}
      {tournaments.length > 0 && filtered.length === 0 && (
        <div className="bl-empty-state">
          <div className="bl-empty-state-ring">
            <Search size={24} />
          </div>
          <h3>No Results</h3>
          <p>No tournaments match "{search}"</p>
        </div>
      )}

      {/* card list */}
      <div className="bl-finished-list">
        {filtered.map((t) => {
          const icon = getGameIcon(t.game_type);
          const winners = t.winners;
          const isExpanded = expandedId === t.id;

          return (
            <div
              className={`bl-finished-card ${isExpanded ? "expanded" : ""}`}
              key={t.id}
            >
              {/* Card top — always visible, clickable */}
              <div
                className="bl-finished-card-top"
                onClick={() => toggle(t.id)}
              >
                <div className="bl-finished-card-identity">
                  {icon ? (
                    <img
                      className="bl-finished-game-icon"
                      src={icon}
                      alt=""
                    />
                  ) : (
                    <div className="bl-finished-game-icon bl-finished-game-placeholder">
                      <Gamepad2 size={18} />
                    </div>
                  )}
                  <div>
                    <h3 className="bl-finished-card-name">{t.name}</h3>
                    <span className="bl-finished-card-game">
                      {getGameName(t.game_type)}
                    </span>
                  </div>
                </div>

                {/* Winners preview (compact) */}
                {winners?.first && (
                  <div className="bl-finished-winner-preview">
                    <Trophy size={14} />
                    <span>{winners.first}</span>
                  </div>
                )}

                {/* stats pills */}
                <div className="bl-finished-card-pills">
                  <span className="bl-finished-pill">
                    <Users size={12} /> {t.participant_count}
                  </span>
                  <span className="bl-finished-pill">
                    <DollarSign size={12} />
                    {t.prize_pool > 0 ? `$${t.prize_pool.toFixed(2)}` : "—"}
                  </span>
                  <span className="bl-finished-pill">
                    <Calendar size={12} /> {fmtDate(t.finished_at)}
                  </span>
                </div>

                <button className="bl-finished-expand-btn">
                  {isExpanded ? (
                    <ChevronUp size={16} />
                  ) : (
                    <ChevronDown size={16} />
                  )}
                </button>
              </div>

              {/* Expanded detail */}
              {isExpanded && (
                <div className="bl-finished-card-detail">
                  {/* date range */}
                  <div className="bl-finished-dates">
                    <div className="bl-finished-date">
                      <Calendar size={14} />
                      <div>
                        <span className="bl-finished-date-label">Started</span>
                        <span className="bl-finished-date-value">
                          {fmtFull(t.start_date)}
                        </span>
                      </div>
                    </div>
                    <div className="bl-finished-date">
                      <Clock size={14} />
                      <div>
                        <span className="bl-finished-date-label">Ended</span>
                        <span className="bl-finished-date-value">
                          {fmtFull(t.end_date)}
                        </span>
                      </div>
                    </div>
                    <div className="bl-finished-date">
                      <CheckCircle2 size={14} />
                      <div>
                        <span className="bl-finished-date-label">Finished</span>
                        <span className="bl-finished-date-value">
                          {fmtFull(t.finished_at)}
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* stats row */}
                  <div className="bl-finished-stats-row">
                    <div className="bl-finished-stat">
                      <Users size={16} />
                      <div>
                        <strong>
                          {t.participant_count}
                          {t.max_participants > 0
                            ? ` / ${t.max_participants}`
                            : ""}
                        </strong>
                        <span>Participants</span>
                      </div>
                    </div>
                    <div className="bl-finished-stat">
                      <Trophy size={16} />
                      <div>
                        <strong>
                          {t.prize_pool > 0
                            ? `$${t.prize_pool.toFixed(2)}`
                            : "—"}
                        </strong>
                        <span>Prize Pool</span>
                      </div>
                    </div>
                    <div className="bl-finished-stat">
                      <DollarSign size={16} />
                      <div>
                        <strong>
                          {t.entry_fee > 0
                            ? `$${t.entry_fee.toFixed(2)}`
                            : "Free"}
                        </strong>
                        <span>Entry Fee</span>
                      </div>
                    </div>
                    {t.settings?.prize_per_kill && Number(t.settings.prize_per_kill) > 0 && (
                      <div className="bl-finished-stat">
                        <Crosshair size={16} />
                        <div>
                          <strong>${Number(t.settings.prize_per_kill).toFixed(2)}</strong>
                          <span>Per Kill</span>
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Winners section */}
                  <div className="bl-finished-winners">
                    <h4>
                      <Trophy size={15} /> Winners
                    </h4>
                    <div className="bl-finished-winner-list">
                      {winners?.first && (
                        <div className="bl-finished-winner gold">
                          <div className="bl-finished-winner-badge">
                            <Medal size={16} /> <span>1st</span>
                          </div>
                          <strong>{winners.first}</strong>
                        </div>
                      )}
                      {winners?.second && (
                        <div className="bl-finished-winner silver">
                          <div className="bl-finished-winner-badge">
                            <Medal size={16} /> <span>2nd</span>
                          </div>
                          <strong>{winners.second}</strong>
                        </div>
                      )}
                      {winners?.third && (
                        <div className="bl-finished-winner bronze">
                          <div className="bl-finished-winner-badge">
                            <Award size={16} /> <span>3rd</span>
                          </div>
                          <strong>{winners.third}</strong>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Kill stats — only if prize_per_kill was enabled */}
                  {t.settings?.prize_per_kill && Number(t.settings.prize_per_kill) > 0 && t.participants && t.participants.some(p => (p.kills ?? 0) > 0) && (
                    <div className="bl-finished-kills">
                      <h4>
                        <Crosshair size={15} /> Kill Stats
                      </h4>
                      <div className="bl-finished-kill-list">
                        {t.participants
                          .filter(p => (p.kills ?? 0) > 0)
                          .sort((a, b) => (b.kills ?? 0) - (a.kills ?? 0))
                          .map((p, i) => (
                            <div className="bl-finished-kill-row" key={i}>
                              <span className="bl-finished-kill-name">
                                {p.display_name === winners?.first && <Trophy size={12} />}
                                {p.display_name}
                              </span>
                              <span className="bl-finished-kill-count">{p.kills} kills</span>
                              <span className="bl-finished-kill-earning">
                                +${((p.kills ?? 0) * Number(t.settings.prize_per_kill)).toFixed(2)}
                              </span>
                            </div>
                          ))}
                      </div>
                    </div>
                  )}

                  {/* description / notes */}
                  {t.description && (
                    <div className="bl-finished-desc">
                      <p>{t.description}</p>
                    </div>
                  )}

                  {/* Delete action */}
                  <div className="bl-finished-card-actions">
                    <button
                      className="bl-finished-delete-btn"
                      onClick={(e) => {
                        e.stopPropagation();
                        setDeleteConfirm({ id: t.id, name: t.name });
                      }}
                    >
                      <Trash2 size={14} />
                      Delete Tournament
                    </button>
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>

      {/* Delete confirm dialog */}
      {deleteConfirm && (
        <div className="bl-modal-overlay" onClick={() => !deleting && setDeleteConfirm(null)}>
          <div className="bl-modal bl-finished-confirm-modal" onClick={(e) => e.stopPropagation()}>
            <div className="bl-modal-header">
              <h3><AlertTriangle size={18} /> Delete Finished Tournament</h3>
              <button className="bl-modal-close" onClick={() => !deleting && setDeleteConfirm(null)}>
                <X size={16} />
              </button>
            </div>
            <div className="bl-modal-body" style={{ padding: "1.25rem 1.5rem" }}>
              <p style={{ margin: "0 0 1rem", color: "var(--bl-text-200)", lineHeight: 1.6 }}>
                Are you sure you want to delete <strong>"{deleteConfirm.name}"</strong>?
                This will permanently remove this finished tournament record.
                The original tournament is not affected. This action cannot be undone.
              </p>
              <div style={{ display: "flex", gap: "0.75rem", justifyContent: "flex-end" }}>
                <button
                  className="bl-btn bl-btn-secondary"
                  onClick={() => setDeleteConfirm(null)}
                  disabled={deleting}
                  style={{ padding: "0.5rem 1rem", borderRadius: "8px", border: "1px solid var(--bl-border)", background: "var(--bl-white)", cursor: "pointer", fontSize: "13px" }}
                >
                  Cancel
                </button>
                <button
                  className="bl-btn bl-btn-danger bl-finished-delete-confirm-btn"
                  onClick={handleDelete}
                  disabled={deleting}
                >
                  {deleting ? (
                    <>
                      <Loader2 size={14} className="bl-spin" /> Deleting...
                    </>
                  ) : (
                    <>
                      <Trash2 size={14} /> Delete
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Toast */}
      {toast && (
        <div className={`bl-toast bl-toast-${toast.type}`}>
          {toast.type === "success" ? <CheckCircle2 size={16} /> : <AlertTriangle size={16} />}
          <span>{toast.message}</span>
        </div>
      )}
    </div>
  );
};

export default FinishedTournaments;
