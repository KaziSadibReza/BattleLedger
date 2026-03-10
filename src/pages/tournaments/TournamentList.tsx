import React, { useState, useEffect, useCallback } from "react";
import {
  Search,
  Plus,
  Edit3,
  Trash2,
  Copy,
  ChevronLeft,
  ChevronRight,
  Trophy,
  Users,
  Calendar,
  DollarSign,
  MoreVertical,
  Filter,
  Zap,
  ZapOff,
} from "lucide-react";
import Toast from "../../components/Toast";
import Dropdown from "../../components/Dropdown";
import {
  fetchTournaments,
  deleteTournament,
  duplicateTournament,
  updateTournamentStatus,
} from "./api";
import type {
  Tournament,
  TournamentStatus,
  GameRule,
} from "./types";

interface Props {
  onEdit: (id: number) => void;
  onReactivate: (id: number) => void;
  onCreate: () => void;
  games: GameRule[];
}

const PER_PAGE = 10;

const TournamentList: React.FC<Props> = ({ onEdit, onReactivate, onCreate, games }) => {
  const [tournaments, setTournaments] = useState<Tournament[]>([]);
  const [loading, setLoading] = useState(true);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [actionMenuId, setActionMenuId] = useState<number | null>(null);
  const [toast, setToast] = useState<{
    message: string;
    type: "success" | "error" | "info";
  } | null>(null);

  /* ── Fetch ───────────────────────────────────────────────── */
  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchTournaments({
        page,
        per_page: PER_PAGE,
        search,
        status: statusFilter,
      });
      setTournaments(res.tournaments);
      setTotal(res.total);
      setTotalPages(res.total_pages);
    } catch {
      setToast({ message: "Failed to load tournaments", type: "error" });
    } finally {
      setLoading(false);
    }
  }, [page, search, statusFilter]);

  useEffect(() => {
    load();
  }, [load]);

  /* Debounced search */
  const [searchInput, setSearchInput] = useState("");
  useEffect(() => {
    const t = setTimeout(() => {
      setSearch(searchInput);
      setPage(1);
    }, 350);
    return () => clearTimeout(t);
  }, [searchInput]);

  /* ── Actions ─────────────────────────────────────────────── */
  const handleDelete = async (id: number, name: string) => {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
    try {
      await deleteTournament(id);
      setToast({ message: "Tournament deleted", type: "success" });
      load();
    } catch {
      setToast({ message: "Failed to delete tournament", type: "error" });
    }
    setActionMenuId(null);
  };

  const handleDuplicate = async (id: number) => {
    try {
      await duplicateTournament(id);
      setToast({ message: "Tournament duplicated", type: "success" });
      load();
    } catch {
      setToast({ message: "Failed to duplicate", type: "error" });
    }
    setActionMenuId(null);
  };

  const handleStatusChange = async (id: number, status: TournamentStatus) => {
    try {
      await updateTournamentStatus(id, status);
      setToast({ message: `Tournament ${status === "active" ? "activated" : "deactivated"}`, type: "success" });
      load();
    } catch {
      setToast({ message: "Failed to update status", type: "error" });
    }
  };

  /* Close action menu on outside click */
  useEffect(() => {
    const handler = () => setActionMenuId(null);
    if (actionMenuId !== null) {
      document.addEventListener("click", handler);
      return () => document.removeEventListener("click", handler);
    }
  }, [actionMenuId]);

  /* ── Game helpers ────────────────────────────────────────── */
  const getGameName = (slug: string) => {
    const g = games.find((r) => r.slug === slug);
    return g?.game_name || slug || "—";
  };
  const getGameIcon = (slug: string) => {
    const g = games.find((r) => r.slug === slug);
    return g?.game_icon || "";
  };

  /* ── Format ──────────────────────────────────────────────── */
  const fmtDate = (d: string | null) => {
    if (!d) return "—";
    return new Date(d).toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  };

  const fmtCurrency = (n: number) => {
    if (!n) return "Free";
    return "$" + n.toFixed(2);
  };

  /* skeleton rows while loading */
  const skeletonRows = Array.from({ length: 4 });

  /* ── Status filter options ───────────────────────────────── */
  const filterOptions = [
    { value: "all", label: "All statuses" },
    { value: "active", label: "Active" },
    { value: "deactive", label: "Deactive" },
    { value: "completed", label: "Completed" },
  ];

  return (
    <div className="bl-tournaments">
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}

      {/* ─── Header ──────────────────────────────────────── */}
      <div className="bl-t-header">
        <div className="bl-t-header-left">
          <div className="bl-t-search">
            <Search size={16} />
            <input
              type="text"
              placeholder="Search tournaments..."
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
            />
          </div>
          <div className="bl-t-filter">
            <Filter size={14} />
            <Dropdown
              value={statusFilter}
              onChange={(v) => {
                setStatusFilter(v);
                setPage(1);
              }}
              options={filterOptions}
              placeholder="Filter"
              className="bl-t-filter-dropdown"
            />
          </div>
        </div>
        <button className="bl-t-btn-create" onClick={onCreate}>
          <Plus size={16} />
          New Tournament
        </button>
      </div>

      {/* ─── Summary strip ───────────────────────────────── */}
      <div className="bl-t-summary">
        <span>{total} tournament{total !== 1 ? "s" : ""}</span>
        {statusFilter !== "all" && (
          <button
            className="bl-t-clear-filter"
            onClick={() => setStatusFilter("all")}
          >
            Clear filter
          </button>
        )}
      </div>

      {/* ─── Empty state ─────────────────────────────────── */}
      {!loading && tournaments.length === 0 && (
        <div className="bl-empty-state">
          <div className="bl-empty-state-ring">
            <Trophy size={28} />
          </div>
          <h3>No Tournaments Yet</h3>
          <p>Get started by creating your first tournament.<br/>Set up the game, prizes, and go live!</p>
          <button className="bl-t-btn-create" onClick={onCreate}>
            <Plus size={16} />
            New Tournament
          </button>
        </div>
      )}

      {/* ─── List ────────────────────────────────────────── */}
      {(loading || tournaments.length > 0) && (
        <div className="bl-t-list">
          {loading
            ? skeletonRows.map((_, i) => (
                <div className="bl-t-card bl-t-card-skeleton" key={i}>
                  <div className="bl-t-skel-top">
                    <div className="bl-t-skel-bar skel-icon" />
                    <div className="bl-t-skel-info">
                      <div className="bl-t-skel-bar skel-name" />
                      <div className="bl-t-skel-bar skel-game" />
                    </div>
                    <div className="bl-t-skel-bar skel-toggle" />
                  </div>
                  <div className="bl-t-skel-meta">
                    <div className="bl-t-skel-bar skel-pill" />
                    <div className="bl-t-skel-bar skel-pill" />
                    <div className="bl-t-skel-bar skel-pill" />
                    <div className="bl-t-skel-bar skel-pill" />
                  </div>
                </div>
              ))
            : tournaments.map((t) => {
                const icon = getGameIcon(t.game_type);
                return (
                  <div
                    className="bl-t-card"
                    key={t.id}
                    onClick={() => onEdit(t.id)}
                    style={{ cursor: "pointer" }}
                  >
                    {/* top row */}
                    <div className="bl-t-card-top">
                      <div className="bl-t-card-identity">
                        {icon ? (
                          <img
                            className="bl-t-card-game-icon"
                            src={icon}
                            alt=""
                          />
                        ) : (
                          <div className="bl-t-card-game-icon bl-t-card-game-placeholder">
                            <Trophy size={16} />
                          </div>
                        )}
                        <div>
                          <h4 className="bl-t-card-name">{t.name}</h4>
                          <span className="bl-t-card-game">
                            {getGameName(t.game_type)}
                          </span>
                        </div>
                      </div>

                      <div className="bl-t-card-status-row">
                        <button
                          className={`bl-t-card-toggle ${t.status === "active" || t.status === "registration" ? "on" : ""}`}
                          onClick={(e) => {
                            e.stopPropagation();
                            // Toggle always opens reactivate flow
                            onReactivate(t.id);
                          }}
                          title={t.status === "active" || t.status === "registration" ? "Active — Click to deactivate" : "Deactive — Click to edit & activate"}
                        >
                          {t.status === "active" || t.status === "registration" ? (
                            <><Zap size={13} /> Active</>
                          ) : t.status === "completed" ? (
                            <><Trophy size={13} /> Completed</>
                          ) : (
                            <><ZapOff size={13} /> Deactive</>
                          )}
                        </button>

                        {/* 3-dot menu */}
                        <div className="bl-t-card-actions-wrap">
                          <button
                            className="bl-t-btn-dots"
                            onClick={(e) => {
                              e.stopPropagation();
                              setActionMenuId(
                                actionMenuId === t.id ? null : t.id
                              );
                            }}
                          >
                            <MoreVertical size={16} />
                          </button>
                          {actionMenuId === t.id && (
                            <div className="bl-t-action-menu">
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  onEdit(t.id);
                                }}
                              >
                                <Edit3 size={14} /> Edit
                              </button>
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleDuplicate(t.id);
                                }}
                              >
                                <Copy size={14} /> Duplicate
                              </button>
                              <button
                                className="bl-t-action-danger"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleDelete(t.id, t.name);
                                }}
                              >
                                <Trash2 size={14} /> Delete
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>

                    {/* meta pills */}
                    <div className="bl-t-card-meta">
                      <span className="bl-t-meta-pill">
                        <Calendar size={13} />
                        {fmtDate(t.start_date)}
                      </span>
                      <span className="bl-t-meta-pill">
                        <Users size={13} />
                        {t.participant_count}
                        {t.max_participants > 0
                          ? ` / ${t.max_participants}`
                          : ""}
                      </span>
                      <span className="bl-t-meta-pill">
                        <DollarSign size={13} />
                        Entry {fmtCurrency(t.entry_fee)}
                      </span>
                      <span className="bl-t-meta-pill">
                        <Trophy size={13} />
                        Prize {fmtCurrency(t.prize_pool)}
                      </span>
                    </div>
                  </div>
                );
              })}
        </div>
      )}

      {/* ─── Pagination ──────────────────────────────────── */}
      {totalPages > 1 && (
        <div className="bl-t-pagination">
          <button
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            <ChevronLeft size={16} />
          </button>
          <span>
            Page {page} of {totalPages}
          </span>
          <button
            disabled={page >= totalPages}
            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
          >
            <ChevronRight size={16} />
          </button>
        </div>
      )}
    </div>
  );
};

export default TournamentList;
