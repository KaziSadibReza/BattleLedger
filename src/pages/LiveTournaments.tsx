import React, { useState, useEffect, useCallback, useRef } from "react";
import {
  Radio,
  Users,
  Calendar,
  Clock,
  Trophy,
  Medal,
  Award,
  Loader2,
  RefreshCw,
  Gamepad2,
  Check,
  Save,
  TimerOff,
  X,
  UserPlus,
  Search,
  Trash2,
  ChevronRight,
  Mail,
  Phone,
  Hash,
  Ban,
  AlertTriangle,
  Crosshair,
} from "lucide-react";
import {
  fetchLiveTournaments,
  saveWinners,
  fetchParticipants,
  addParticipant,
  removeParticipant,
  searchUsers,
  deleteTournament,
  updateTournamentStatus,
} from "./tournaments/api";
import type {
  Tournament,
  WinnerPlacement,
  Participant,
  UserSearchResult,
} from "./tournaments/types";
import { STATUS_META } from "./tournaments/types";
import eventBus from "../lib/eventBus";
import { useGameRules } from "../lib/useGameRules";

/* ── helpers ────────────────────────────────────────────────── */
const fmtDateTime = (d: string | null) => {
  if (!d) return "—";
  return new Date(d).toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
};

const fmtDate = (d: string | null) => {
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

const timeRemaining = (end: string | null): string => {
  if (!end) return "No end date";
  const diff = new Date(end).getTime() - Date.now();
  if (diff <= 0) return "Ended";
  const h = Math.floor(diff / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  if (h > 24) {
    const days = Math.floor(h / 24);
    return `${days}d ${h % 24}h left`;
  }
  return `${h}h ${m}m left`;
};

const isEnded = (t: Tournament): boolean => {
  if (!t.end_date) return false;
  return new Date(t.end_date).getTime() <= Date.now();
};

const hasWinners = (t: Tournament): boolean => {
  const w = t.settings?.winners;
  return !!(w && (w.first || w.second || w.third));
};

/* ── User Search Dropdown ───────────────────────────────────── */
interface UserSearchProps {
  tournamentId: number;
  onAdded: () => void;
}

const UserSearchAdd: React.FC<UserSearchProps> = ({
  tournamentId,
  onAdded,
}) => {
  const [query, setQuery] = useState("");
  const [results, setResults] = useState<UserSearchResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [adding, setAdding] = useState<number | null>(null);
  const [open, setOpen] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout>>();
  const wrapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (query.length < 2) {
      setResults([]);
      setOpen(false);
      return;
    }
    clearTimeout(timerRef.current);
    timerRef.current = setTimeout(async () => {
      setSearching(true);
      try {
        const users = await searchUsers(query);
        setResults(users);
        setOpen(users.length > 0);
      } catch {
        /* silent */
      } finally {
        setSearching(false);
      }
    }, 300);
    return () => clearTimeout(timerRef.current);
  }, [query]);

  /* close on outside click */
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener("mousedown", handler);
    return () => document.removeEventListener("mousedown", handler);
  }, []);

  const handleAdd = async (userId: number) => {
    setAdding(userId);
    try {
      await addParticipant(tournamentId, userId);
      setQuery("");
      setResults([]);
      setOpen(false);
      onAdded();
    } catch {
      /* silent */
    } finally {
      setAdding(null);
    }
  };

  return (
    <div className="bl-live-user-search" ref={wrapRef}>
      <div className="bl-live-user-search-input">
        <Search size={14} />
        <input
          type="text"
          placeholder="Search users by name or email..."
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
        />
        {searching && <Loader2 size={14} className="bl-spin" />}
      </div>
      {open && (
        <div className="bl-live-user-results">
          {results.map((u) => (
            <div className="bl-live-user-result" key={u.id}>
              <img src={u.avatar} alt="" className="bl-live-user-avatar" />
              <div className="bl-live-user-info">
                <span className="bl-live-user-name">{u.display_name}</span>
                <span className="bl-live-user-email">{u.user_email}</span>
                {u.phone && <span className="bl-live-user-phone"><Phone size={10} /> {u.phone}</span>}
              </div>
              <button
                className="bl-live-user-add-btn"
                onClick={() => handleAdd(u.id)}
                disabled={adding === u.id}
              >
                {adding === u.id ? (
                  <Loader2 size={13} className="bl-spin" />
                ) : (
                  <UserPlus size={13} />
                )}
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

/* ── Detail Panel ───────────────────────────────────────────── */
interface DetailProps {
  tournament: Tournament;
  getGameName: (slug: string) => string;
  getGameIcon: (slug: string) => string;
  onClose: () => void;
  onRefresh: () => void;
}

const TournamentDetail: React.FC<DetailProps> = ({
  tournament: t,
  getGameName,
  getGameIcon,
  onClose,
  onRefresh,
}) => {
  const [participants, setParticipants] = useState<Participant[]>([]);
  const [loadingP, setLoadingP] = useState(true);
  const [removing, setRemoving] = useState<number | null>(null);
  const ended = isEnded(t);
  const winnersSet = hasWinners(t);
  const icon = getGameIcon(t.game_type);
  const winners = t.settings?.winners;

  // Winner form state
  const [first, setFirst] = useState(winners?.first || "");
  const [second, setSecond] = useState(winners?.second || "");
  const [third, setThird] = useState(winners?.third || "");
  const [killCounts, setKillCounts] = useState<Record<string, number>>({});
  const [savingW, setSavingW] = useState(false);
  const [savedW, setSavedW] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const prizePerKill = t.settings?.prize_per_kill ? Number(t.settings.prize_per_kill) : 0;

  const loadParticipants = useCallback(async () => {
    setLoadingP(true);
    try {
      const p = await fetchParticipants(t.id);
      setParticipants(p);
    } catch {
      /* silent */
    } finally {
      setLoadingP(false);
    }
  }, [t.id]);

  useEffect(() => {
    loadParticipants();
  }, [loadParticipants]);

  const handleRemove = async (pid: number) => {
    if (!confirm("Remove this participant?")) return;
    setRemoving(pid);
    try {
      await removeParticipant(t.id, pid);
      loadParticipants();
      onRefresh();
    } catch {
      /* silent */
    } finally {
      setRemoving(null);
    }
  };

  const handleSaveWinners = async () => {
    setSavingW(true);
    try {
      await saveWinners(
        t.id,
        { first, second, third },
        prizePerKill > 0 ? killCounts : undefined
      );
      setSavedW(true);
      // Tournament is now deactivated by the backend.
      // Close detail and refresh after a short delay.
      setTimeout(() => {
        setSavedW(false);
        onClose();
        onRefresh();
      }, 1500);
    } catch {
      /* silent */
    } finally {
      setSavingW(false);
    }
  };

  const handleCancel = async () => {
    if (!confirm("Cancel this tournament? It will be moved out of the Live view.")) return;
    setCancelling(true);
    try {
      await updateTournamentStatus(t.id, "cancelled");
      onClose();
      onRefresh();
    } catch {
      /* silent */
    } finally {
      setCancelling(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm("Permanently delete this tournament? This cannot be undone.")) return;
    setDeleting(true);
    try {
      await deleteTournament(t.id);
      onClose();
      onRefresh();
    } catch {
      /* silent */
    } finally {
      setDeleting(false);
    }
  };

  const winnersDirty =
    first !== (winners?.first || "") ||
    second !== (winners?.second || "") ||
    third !== (winners?.third || "");

  const meta = STATUS_META[t.status];
  const fillPct =
    t.max_participants > 0
      ? Math.min(100, (t.participant_count / t.max_participants) * 100)
      : 0;

  return (
    <div className="bl-live-detail-overlay" onClick={onClose}>
      <div
        className="bl-live-detail"
        onClick={(e) => e.stopPropagation()}
      >
        {/* header */}
        <div className="bl-live-detail-header">
          <div className="bl-live-detail-identity">
            {icon ? (
              <img className="bl-live-detail-icon" src={icon} alt="" />
            ) : (
              <div className="bl-live-detail-icon bl-live-detail-placeholder">
                <Gamepad2 size={22} />
              </div>
            )}
            <div>
              <h2>{t.name}</h2>
              <span className="bl-live-detail-game">
                {getGameName(t.game_type)}
              </span>
            </div>
          </div>
          <button className="bl-live-detail-close" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        {/* status bar */}
        <div className="bl-live-detail-status">
          <span
            className="bl-live-dot"
            style={{ background: ended ? "#9ca3af" : meta.dot }}
          />
          <span>
            {ended
              ? winnersSet
                ? "Completed"
                : "Ended — Awaiting Results"
              : meta.label}
          </span>
          <span className="bl-live-time-badge">
            <Clock size={12} />
            {timeRemaining(t.end_date)}
          </span>
        </div>

        {/* stats grid */}
        <div className="bl-live-detail-stats">
          <div className="bl-live-detail-stat">
            <Users size={16} />
            <div>
              <strong>
                {t.participant_count}
                {t.max_participants > 0 ? ` / ${t.max_participants}` : ""}
              </strong>
              <span>Participants</span>
            </div>
          </div>
          <div className="bl-live-detail-stat">
            <Trophy size={16} />
            <div>
              <strong>
                {t.prize_pool > 0 ? `$${t.prize_pool.toFixed(2)}` : "—"}
              </strong>
              <span>Prize Pool</span>
            </div>
          </div>
          <div className="bl-live-detail-stat">
            <Calendar size={16} />
            <div>
              <strong>{fmtDate(t.start_date)}</strong>
              <span>Started</span>
            </div>
          </div>
          <div className="bl-live-detail-stat">
            <Clock size={16} />
            <div>
              <strong>{fmtDate(t.end_date)}</strong>
              <span>End Date</span>
            </div>
          </div>
          {prizePerKill > 0 && (
            <div className="bl-live-detail-stat">
              <Crosshair size={16} />
              <div>
                <strong>${prizePerKill.toFixed(2)}</strong>
                <span>Per Kill</span>
              </div>
            </div>
          )}
        </div>

        {/* progress bar */}
        {t.max_participants > 0 && (
          <div className="bl-live-detail-progress">
            <div className="bl-live-detail-progress-label">
              <span>Capacity</span>
              <span>{Math.round(fillPct)}%</span>
            </div>
            <div className="bl-live-detail-progress-track">
              <div
                className="bl-live-detail-progress-bar"
                style={{ width: `${fillPct}%` }}
              />
            </div>
          </div>
        )}

        {/* ── Participants section ─────────────────────────── */}
        <div className="bl-live-detail-section">
          <div className="bl-live-detail-section-header">
            <Users size={15} />
            <h3>Participants</h3>
            <span className="bl-live-detail-badge">
              {participants.length}
            </span>
          </div>

          {/* Add user search */}
          <UserSearchAdd
            tournamentId={t.id}
            onAdded={() => {
              loadParticipants();
              onRefresh();
            }}
          />

          {/* Participant list */}
          {loadingP ? (
            <div className="bl-live-detail-loading">
              <Loader2 size={18} className="bl-spin" />
            </div>
          ) : participants.length === 0 ? (
            <div className="bl-live-detail-empty-p">
              <Users size={20} />
              <span>No participants yet</span>
            </div>
          ) : (
            <div className="bl-live-detail-participants">
              {participants.map((p, i) => (
                <div className="bl-live-detail-participant" key={p.id}>
                  <span className="bl-live-detail-p-num">{i + 1}</span>
                  <div className="bl-live-detail-p-info">
                    <span className="bl-live-detail-p-name">
                      {p.display_name}
                    </span>
                    <span className="bl-live-detail-p-email">
                      <Mail size={11} /> {p.user_email}
                    </span>
                    {p.phone && (
                      <span className="bl-live-detail-p-phone">
                        <Phone size={11} /> {p.phone}
                      </span>
                    )}
                  </div>
                  {p.team_name && (
                    <span className="bl-live-detail-p-team">{p.team_name}</span>
                  )}
                  <button
                    className="bl-live-detail-p-remove"
                    onClick={() => handleRemove(p.id)}
                    disabled={removing === p.id}
                    title="Remove participant"
                  >
                    {removing === p.id ? (
                      <Loader2 size={13} className="bl-spin" />
                    ) : (
                      <Trash2 size={13} />
                    )}
                  </button>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* ── Winners section (only if ended) ─────────────── */}
        {ended && (
          <div className="bl-live-detail-section">
            <div className="bl-live-detail-section-header">
              <Trophy size={15} />
              <h3>Winners</h3>
            </div>

            {participants.length === 0 ? (
              <div className="bl-live-detail-empty-p">
                <Users size={20} />
                <span>No participants to assign as winners</span>
              </div>
            ) : (
              <div className="bl-live-winner-form">
                <div className="bl-live-winner-row gold">
                  <div className="bl-live-winner-badge">
                    <Medal size={16} /> <span>1st</span>
                  </div>
                  <select
                    value={first}
                    onChange={(e) => setFirst(e.target.value)}
                    className="bl-live-winner-select"
                  >
                    <option value="">— Select 1st place —</option>
                    {participants
                      .filter((p) => {
                        const name = p.display_name;
                        return name !== second && name !== third;
                      })
                      .map((p) => (
                        <option key={p.id} value={p.display_name}>
                          {p.display_name} ({p.user_email})
                        </option>
                      ))}
                  </select>
                </div>
                <div className="bl-live-winner-row silver">
                  <div className="bl-live-winner-badge">
                    <Medal size={16} /> <span>2nd</span>
                  </div>
                  <select
                    value={second}
                    onChange={(e) => setSecond(e.target.value)}
                    className="bl-live-winner-select"
                  >
                    <option value="">— Select 2nd place —</option>
                    {participants
                      .filter((p) => {
                        const name = p.display_name;
                        return name !== first && name !== third;
                      })
                      .map((p) => (
                        <option key={p.id} value={p.display_name}>
                          {p.display_name} ({p.user_email})
                        </option>
                      ))}
                  </select>
                </div>
                <div className="bl-live-winner-row bronze">
                  <div className="bl-live-winner-badge">
                    <Award size={16} /> <span>3rd</span>
                  </div>
                  <select
                    value={third}
                    onChange={(e) => setThird(e.target.value)}
                    className="bl-live-winner-select"
                  >
                    <option value="">— Select 3rd place —</option>
                    {participants
                      .filter((p) => {
                        const name = p.display_name;
                        return name !== first && name !== second;
                      })
                      .map((p) => (
                        <option key={p.id} value={p.display_name}>
                          {p.display_name} ({p.user_email})
                        </option>
                      ))}
                  </select>
                </div>

                {/* Kill counts — only if prize_per_kill is enabled */}
                {prizePerKill > 0 && participants.length > 0 && (
                  <div className="bl-live-kill-section">
                    <div className="bl-live-kill-header">
                      <Crosshair size={15} />
                      <h4>Kill Counts</h4>
                      <span className="bl-live-kill-rate">${prizePerKill.toFixed(2)} per kill</span>
                    </div>
                    <div className="bl-live-kill-list">
                      {participants.map((p) => {
                        const kills = killCounts[p.display_name] || 0;
                        const earning = kills * prizePerKill;
                        const isWinner = p.display_name === first;
                        return (
                          <div className={`bl-live-kill-row${isWinner ? " winner" : ""}`} key={p.id}>
                            <span className="bl-live-kill-name">
                              {isWinner && <Trophy size={12} />}
                              {p.display_name}
                            </span>
                            <input
                              type="number"
                              min={0}
                              className="bl-live-kill-input"
                              placeholder="0"
                              value={kills || ""}
                              onChange={(e) =>
                                setKillCounts((prev) => ({
                                  ...prev,
                                  [p.display_name]: Math.max(0, parseInt(e.target.value) || 0),
                                }))
                              }
                            />
                            {earning > 0 && (
                              <span className="bl-live-kill-earning">
                                +${earning.toFixed(2)}
                              </span>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                <button
                  className="bl-live-winner-save"
                  onClick={handleSaveWinners}
                  disabled={savingW || savedW || !winnersDirty}
                >
                  {savingW ? (
                    <>
                      <Loader2 size={14} className="bl-spin" /> Saving & Distributing...
                    </>
                  ) : savedW ? (
                    <>
                      <Check size={14} /> Saved & Distributed!
                    </>
                  ) : (
                    <>
                      <Save size={14} /> Save Winners{prizePerKill > 0 ? " & Distribute Prizes" : ""}
                    </>
                  )}
                </button>
              </div>
            )}
          </div>
        )}

        {/* ── Cancel / Delete actions (0 participants) ─────── */}
        {!loadingP && participants.length === 0 && (
          <div className="bl-live-detail-section">
            <div className="bl-live-detail-section-header">
              <AlertTriangle size={15} />
              <h3>Tournament Actions</h3>
            </div>
            <p className="bl-live-detail-action-hint">
              This tournament has no participants. You can cancel or delete it.
            </p>
            <div className="bl-live-detail-actions">
              <button
                className="bl-live-action-cancel"
                onClick={handleCancel}
                disabled={cancelling || deleting}
              >
                {cancelling ? (
                  <><Loader2 size={14} className="bl-spin" /> Cancelling...</>
                ) : (
                  <><Ban size={14} /> Cancel Tournament</>
                )}
              </button>
              <button
                className="bl-live-action-delete"
                onClick={handleDelete}
                disabled={cancelling || deleting}
              >
                {deleting ? (
                  <><Loader2 size={14} className="bl-spin" /> Deleting...</>
                ) : (
                  <><Trash2 size={14} /> Delete Tournament</>
                )}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

/* ── Main component ─────────────────────────────────────────── */
const LiveTournaments: React.FC = () => {
  const [tournaments, setTournaments] = useState<Tournament[]>([]);
  const [games] = useGameRules();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  /* tick forces re-render every 5s so isEnded / timeRemaining update fast */
  const [, setTick] = useState(0);
  useEffect(() => {
    const t = setInterval(() => setTick((n) => n + 1), 5000);
    return () => clearInterval(t);
  }, []);

  const load = useCallback(async () => {
    try {
      const live = await fetchLiveTournaments();
      setTournaments(Array.isArray(live) ? live : []);
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

  /* re-fetch when another tab mutates live data or participants */
  useEffect(() => {
    eventBus.on("live:changed", load);
    eventBus.on("participants:changed", load);
    return () => {
      eventBus.off("live:changed", load);
      eventBus.off("participants:changed", load);
    };
  }, [load]);

  const handleRefresh = () => {
    setRefreshing(true);
    load();
  };

  const getGameName = (slug: string) =>
    games.find((g) => g.slug === slug)?.game_name || slug || "—";
  const getGameIcon = (slug: string) =>
    games.find((g) => g.slug === slug)?.game_icon || "";

  if (loading) {
    return (
      <div className="bl-live">
        <div className="bl-live-header">
          <div className="bl-live-title">
            <span className="bl-live-pulse" />
            <h2>Live Tournaments</h2>
          </div>
        </div>
        <div className="bl-live-skeleton-grid">
          {[1, 2, 3].map((i) => (
            <div className="bl-live-card-skeleton" key={i}>
              <div className="bl-live-skel-bar skel-status" />
              <div className="bl-live-skel-identity">
                <div className="bl-live-skel-bar skel-icon" />
                <div className="bl-live-skel-identity-text">
                  <div className="bl-live-skel-bar skel-name" />
                  <div className="bl-live-skel-bar skel-game" />
                </div>
              </div>
              <div className="bl-live-skel-stats">
                <div className="bl-live-skel-bar skel-stat" />
                <div className="bl-live-skel-bar skel-stat" />
                <div className="bl-live-skel-bar skel-stat" />
              </div>
              <div className="bl-live-skel-bar skel-progress" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  /* Split into live vs ended */
  const liveTournaments = tournaments.filter((t) => !isEnded(t));
  const endedTournaments = tournaments.filter((t) => isEnded(t));
  const totalCount = tournaments.length;
  const selectedTournament = tournaments.find((t) => t.id === selectedId);

  /* Card renderer */
  const renderCard = (t: Tournament) => {
    const icon = getGameIcon(t.game_type);
    const ended = isEnded(t);
    const meta = STATUS_META[t.status];
    const winnersSet = hasWinners(t);

    return (
      <div
        className={`bl-live-card ${ended ? "ended" : ""} ${
          selectedId === t.id ? "selected" : ""
        }`}
        key={t.id}
        onClick={() => setSelectedId(t.id)}
      >
        {/* status bar */}
        <div className="bl-live-card-status">
          <span
            className="bl-live-dot"
            style={{ background: ended ? "#9ca3af" : meta.dot }}
          />
          <span>
            {ended
              ? winnersSet
                ? "Completed"
                : "Awaiting Results"
              : meta.label}
          </span>
          <ChevronRight size={14} className="bl-live-card-arrow" />
        </div>

        {/* identity */}
        <div className="bl-live-card-identity">
          {icon ? (
            <img className="bl-live-game-icon" src={icon} alt="" />
          ) : (
            <div className="bl-live-game-icon bl-live-game-placeholder">
              <Gamepad2 size={18} />
            </div>
          )}
          <div>
            <h3 className="bl-live-card-name">{t.name}</h3>
            <span className="bl-live-card-game">
              {getGameName(t.game_type)}
            </span>
          </div>
        </div>

        {/* compact stats */}
        <div className="bl-live-card-stats-row">
          <span>
            <Users size={13} /> {t.participant_count}
            {t.max_participants > 0 ? `/${t.max_participants}` : ""}
          </span>
          <span>
            <Trophy size={13} />{" "}
            {t.prize_pool > 0 ? `$${t.prize_pool.toFixed(2)}` : "—"}
          </span>
          <span>
            <Clock size={13} /> {timeRemaining(t.end_date)}
          </span>
        </div>

        {/* progress bar */}
        {t.max_participants > 0 && (
          <div className="bl-live-progress">
            <div
              className="bl-live-progress-bar"
              style={{
                width: `${Math.min(
                  100,
                  (t.participant_count / t.max_participants) * 100
                )}%`,
              }}
            />
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="bl-live">
      {/* header */}
      <div className="bl-live-header">
        <div className="bl-live-title">
          <span className="bl-live-pulse" />
          <h2>Live Tournaments</h2>
          <span className="bl-live-count">{totalCount}</span>
        </div>
        <button
          className="bl-live-refresh"
          onClick={handleRefresh}
          disabled={refreshing}
        >
          <RefreshCw size={15} className={refreshing ? "bl-spin" : ""} />
          Refresh
        </button>
      </div>

      {/* empty state */}
      {totalCount === 0 && (
        <div className="bl-empty-state">
          <div className="bl-empty-state-ring live">
            <Radio size={28} />
          </div>
          <h3>No Live Tournaments</h3>
          <p>When you set a tournament to <strong>Active</strong>, it will appear<br/>here in real-time. Ended tournaments stay until you assign winners.</p>
          <div className="bl-empty-state-hint">
            <Gamepad2 size={14} />
            Go to <strong>Tournaments</strong> to create one
          </div>
        </div>
      )}

      {/* ── Live Now section ──────────────────────────────── */}
      {liveTournaments.length > 0 && (
        <>
          <div className="bl-live-section-header">
            <span className="bl-live-pulse" />
            <h3>Live Now</h3>
            <span className="bl-live-section-count">
              {liveTournaments.length}
            </span>
          </div>
          <div className="bl-live-grid">
            {liveTournaments.map(renderCard)}
          </div>
        </>
      )}

      {/* ── Ended section ─────────────────────────────────── */}
      {endedTournaments.length > 0 && (
        <>
          <div className="bl-live-section-header ended">
            <TimerOff size={16} />
            <h3>Ended — Set Winners</h3>
            <span className="bl-live-section-count">
              {endedTournaments.length}
            </span>
          </div>
          <div className="bl-live-grid">
            {endedTournaments.map(renderCard)}
          </div>
        </>
      )}

      {/* ── Detail panel ──────────────────────────────────── */}
      {selectedTournament && (
        <TournamentDetail
          tournament={selectedTournament}
          getGameName={getGameName}
          getGameIcon={getGameIcon}
          onClose={() => setSelectedId(null)}
          onRefresh={load}
        />
      )}
    </div>
  );
};

export default LiveTournaments;
