/**
 * My Tournaments Page — Frontend Dashboard
 *
 * Shows tournaments the user has joined (active & finished) with
 * status badges, winners, and pagination.
 */

import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import {
  Trophy, Clock, Users, DollarSign, MapPin, Shield,
  RefreshCw, Crown, Medal, Award, ChevronRight,
} from 'lucide-react';
import Skeleton from '../components/Skeleton';
import { showNotification } from '../components/Notifications';

interface MyTournament {
  id: number;
  type: 'active' | 'finished';
  name: string;
  game_type: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  entry_fee: number;
  prize_pool: number;
  max_participants: number;
  participant_count: number;
  joined_at: string;
  my_slots: number;
  my_team_name: string;
  settings: {
    game_mode?: string;
    map?: string;
    team_mode?: string;
  };
  winners: { first?: string; second?: string; third?: string } | null;
  finished_at: string | null;
}

interface MyTournamentsProps {
  currentUser: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
}

type TabFilter = 'all' | 'active' | 'finished';

const MyTournaments: React.FC<MyTournamentsProps> = ({ currentUser: _currentUser }) => {
  const [tournaments, setTournaments] = useState<MyTournament[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [tab, setTab] = useState<TabFilter>('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const perPage = 10;

  const fetchTournaments = useCallback(async () => {
    try {
      const res = await apiFetch<{
        tournaments: MyTournament[];
        total: number;
        page: number;
        total_pages: number;
      }>({
        path: `/battle-ledger/v1/user/my-tournaments?tab=${tab}&page=${currentPage}&per_page=${perPage}`,
      });
      setTournaments(res.tournaments);
      setTotal(res.total);
    } catch {
      showNotification('Failed to load tournaments', 'error');
    }
  }, [tab, currentPage]);

  useEffect(() => {
    setLoading(true);
    fetchTournaments().finally(() => setLoading(false));
  }, [fetchTournaments]);

  // Reset page when tab changes
  useEffect(() => {
    setCurrentPage(1);
  }, [tab]);

  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchTournaments();
    setRefreshing(false);
    showNotification('Tournaments refreshed', 'success');
  };

  const fmt = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n);

  const fmtDate = (d: string | null) => {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const statusBadge = (status: string) => {
    const map: Record<string, { cls: string; label: string }> = {
      active:            { cls: 'bl-badge-success',   label: 'Active' },
      awaiting_results:  { cls: 'bl-badge-warning',   label: 'Awaiting Results' },
      finished:          { cls: 'bl-badge-secondary',  label: 'Finished' },
      deactive:          { cls: 'bl-badge-danger',    label: 'Inactive' },
    };
    const s = map[status] ?? { cls: 'bl-badge-info', label: status };
    return <span className={`bl-badge ${s.cls}`}>{s.label}</span>;
  };

  const uniqueKey = (t: MyTournament) => `${t.type}-${t.id}`;

  const totalPages = Math.ceil(total / perPage);

  /* ── Loading skeleton ──────────────────────────────────── */
  if (loading) {
    return (
      <div className="bl-my-tournaments">
        <div className="bl-card">
          <div className="bl-card-header">
            <Skeleton width="40%" height={24} />
          </div>
          {[1, 2, 3, 4].map((i) => (
            <div key={i} style={{ marginBottom: 16 }}>
              <Skeleton height={80} variant="rounded" />
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="bl-my-tournaments">
      {/* Header */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>My Tournaments</h3>
          <button
            className="bl-btn-icon"
            onClick={handleRefresh}
            disabled={refreshing}
            title="Refresh"
          >
            <RefreshCw size={18} className={refreshing ? 'spinning' : ''} />
          </button>
        </div>

        {/* Tabs */}
        <div className="bl-mt-tabs">
          {(['all', 'active', 'finished'] as TabFilter[]).map((t) => (
            <button
              key={t}
              className={`bl-mt-tab ${tab === t ? 'active' : ''}`}
              onClick={() => setTab(t)}
            >
              {t === 'all' ? 'All' : t === 'active' ? 'Active' : 'Finished'}
            </button>
          ))}
        </div>

        {/* List */}
        {tournaments.length === 0 ? (
          <div className="bl-empty-state">
            <Trophy size={48} />
            <p>No tournaments found</p>
            <span className="bl-empty-subtitle">
              {tab === 'active'
                ? 'You have no active tournament registrations.'
                : tab === 'finished'
                ? 'No finished tournaments yet.'
                : 'Join a tournament to see it here!'}
            </span>
          </div>
        ) : (
          <div className="bl-mt-list">
            {tournaments.map((t) => {
              const key = uniqueKey(t);
              const isExpanded = expandedId === key;

              return (
                <div
                  key={key}
                  className={`bl-mt-item ${isExpanded ? 'expanded' : ''}`}
                >
                  {/* Row summary */}
                  <div
                    className="bl-mt-item-header"
                    onClick={() => setExpandedId(isExpanded ? null : key)}
                  >
                    <div className="bl-mt-item-left">
                      <div className="bl-mt-item-icon">
                        <Trophy size={20} />
                      </div>
                      <div className="bl-mt-item-info">
                        <div className="bl-mt-item-name">{t.name}</div>
                        <div className="bl-mt-item-meta">
                          <span className="bl-mt-game">{t.game_type}</span>
                          {t.settings.game_mode && (
                            <>
                              <span className="bl-mt-sep">·</span>
                              <span>{t.settings.game_mode}</span>
                            </>
                          )}
                          {t.settings.team_mode && (
                            <>
                              <span className="bl-mt-sep">·</span>
                              <span>{t.settings.team_mode}</span>
                            </>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="bl-mt-item-right">
                      {statusBadge(t.status)}
                      <ChevronRight
                        size={18}
                        className={`bl-mt-chevron ${isExpanded ? 'rotated' : ''}`}
                      />
                    </div>
                  </div>

                  {/* Expanded details */}
                  {isExpanded && (
                    <div className="bl-mt-item-body">
                      <div className="bl-mt-detail-grid">
                        <div className="bl-mt-detail">
                          <Users size={14} />
                          <span className="bl-mt-detail-label">Participants</span>
                          <span className="bl-mt-detail-value">
                            {t.participant_count}
                            {t.max_participants > 0 ? ` / ${t.max_participants}` : ''}
                          </span>
                        </div>
                        <div className="bl-mt-detail">
                          <DollarSign size={14} />
                          <span className="bl-mt-detail-label">Entry Fee</span>
                          <span className="bl-mt-detail-value">
                            {t.entry_fee > 0 ? fmt(t.entry_fee) : 'Free'}
                          </span>
                        </div>
                        <div className="bl-mt-detail">
                          <Trophy size={14} />
                          <span className="bl-mt-detail-label">Prize Pool</span>
                          <span className="bl-mt-detail-value">
                            {t.prize_pool > 0 ? fmt(t.prize_pool) : '—'}
                          </span>
                        </div>
                        {t.settings.map && (
                          <div className="bl-mt-detail">
                            <MapPin size={14} />
                            <span className="bl-mt-detail-label">Map</span>
                            <span className="bl-mt-detail-value">{t.settings.map}</span>
                          </div>
                        )}
                        {t.my_team_name && (
                          <div className="bl-mt-detail">
                            <Shield size={14} />
                            <span className="bl-mt-detail-label">Team</span>
                            <span className="bl-mt-detail-value">{t.my_team_name}</span>
                          </div>
                        )}
                        {t.my_slots > 1 && (
                          <div className="bl-mt-detail">
                            <Users size={14} />
                            <span className="bl-mt-detail-label">Players</span>
                            <span className="bl-mt-detail-value">{t.my_slots}</span>
                          </div>
                        )}
                      </div>

                      {/* Dates */}
                      <div className="bl-mt-dates">
                        {t.start_date && (
                          <div className="bl-mt-date">
                            <Clock size={13} />
                            <span>Started: {fmtDate(t.start_date)}</span>
                          </div>
                        )}
                        {t.end_date && (
                          <div className="bl-mt-date">
                            <Clock size={13} />
                            <span>Ended: {fmtDate(t.end_date)}</span>
                          </div>
                        )}
                        {t.finished_at && (
                          <div className="bl-mt-date">
                            <Trophy size={13} />
                            <span>Finished: {fmtDate(t.finished_at)}</span>
                          </div>
                        )}
                        <div className="bl-mt-date">
                          <Clock size={13} />
                          <span>Joined: {fmtDate(t.joined_at)}</span>
                        </div>
                      </div>

                      {/* Winners */}
                      {t.winners && (t.winners.first || t.winners.second || t.winners.third) && (
                        <div className="bl-mt-winners">
                          <h4>Winners</h4>
                          <div className="bl-mt-winners-list">
                            {t.winners.first && (
                              <div className="bl-mt-winner bl-mt-winner-gold">
                                <Crown size={16} />
                                <span className="bl-mt-winner-place">1st</span>
                                <span className="bl-mt-winner-name">{t.winners.first}</span>
                              </div>
                            )}
                            {t.winners.second && (
                              <div className="bl-mt-winner bl-mt-winner-silver">
                                <Medal size={16} />
                                <span className="bl-mt-winner-place">2nd</span>
                                <span className="bl-mt-winner-name">{t.winners.second}</span>
                              </div>
                            )}
                            {t.winners.third && (
                              <div className="bl-mt-winner bl-mt-winner-bronze">
                                <Award size={16} />
                                <span className="bl-mt-winner-place">3rd</span>
                                <span className="bl-mt-winner-name">{t.winners.third}</span>
                              </div>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="bl-pagination">
            <button
              className="bl-btn-secondary"
              onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
              disabled={currentPage === 1}
            >
              Previous
            </button>
            <span className="bl-pagination-info">
              Page {currentPage} of {totalPages}
            </span>
            <button
              className="bl-btn-secondary"
              onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
              disabled={currentPage === totalPages}
            >
              Next
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default MyTournaments;
