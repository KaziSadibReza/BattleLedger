/**
 * Live Tournaments App — root component
 *
 * Mounted by the shortcode [battleledger_live_tournaments]
 */

import React, { useState, useEffect, useCallback, useMemo, useRef } from 'react';

import type { Tournament, GameRule } from './types';
import { fetchLiveTournaments, fetchActiveRules } from './api';

import GameTabs from './components/GameTabs';
import TournamentCard from './components/TournamentCard';
import TournamentModal from './components/TournamentModal';

import { Search, Trophy } from 'lucide-react';

export interface LiveTournamentsProps {
  apiUrl: string;
  nonce: string;
  isLoggedIn: boolean;
  loginUrl: string;
  userId: number;
}

const LiveTournamentsApp: React.FC<LiveTournamentsProps> = ({
  apiUrl,
  nonce,
  isLoggedIn,
  loginUrl,
  userId,
}) => {
  const [tournaments, setTournaments] = useState<Tournament[]>([]);
  const [rules, setRules] = useState<GameRule[]>([]);
  const [activeGame, setActiveGame] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [rulesLoading, setRulesLoading] = useState(true);
  const [selectedId, setSelectedId] = useState<number | null>(null);

  // Prevent duplicate fetches (React StrictMode / hot-reload)
  const mountedRef = useRef(false);

  // Load rules (once)
  useEffect(() => {
    if (mountedRef.current) return;
    mountedRef.current = true;
    (async () => {
      setRulesLoading(true);
      try {
        const res = await fetchActiveRules();
        setRules(res.rules);
      } catch { /* ignore */ }
      setRulesLoading(false);
    })();
  }, []);

  // Load tournaments (reacts to filter changes)
  const loadTournaments = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchLiveTournaments({
        game_type: activeGame || undefined,
        search: search || undefined,
        per_page: 50,
      });
      setTournaments(res.tournaments);
    } catch { /* ignore */ }
    setLoading(false);
  }, [activeGame, search]);

  useEffect(() => {
    loadTournaments();
  }, [loadTournaments]);

  /* Refresh when user returns to the tab (no polling) */
  useEffect(() => {
    const onVisibility = () => {
      if (!document.hidden) loadTournaments();
    };
    document.addEventListener('visibilitychange', onVisibility);
    return () => document.removeEventListener('visibilitychange', onVisibility);
  }, [loadTournaments]);

  /* ── Client-side "awaiting_results" — flip status the instant end_date passes ── */
  const [tick, setTick] = useState(0);

  // Compute the effective list: override status client-side when end_date has passed
  const effectiveTournaments = useMemo(() => {
    void tick; // depend on tick to re-run
    const now = Date.now();
    return tournaments.map((t) => {
      if (t.status === 'active' && t.end_date) {
        const end = new Date(t.end_date.replace(' ', 'T')).getTime();
        if (end <= now) {
          return { ...t, status: 'awaiting_results' };
        }
      }
      return t;
    });
  }, [tournaments, tick]);

  // Set a timer for the next tournament that's about to end
  useEffect(() => {
    const now = Date.now();
    let nearest = Infinity;
    for (const t of tournaments) {
      if (t.status === 'active' && t.end_date) {
        const end = new Date(t.end_date.replace(' ', 'T')).getTime();
        if (end > now && end < nearest) nearest = end;
      }
    }
    if (nearest === Infinity) return;
    const delay = nearest - now + 500; // +500ms buffer
    const id = setTimeout(() => setTick((n) => n + 1), delay);
    return () => clearTimeout(id);
  }, [tournaments, tick]);

  // Debounced search
  const [searchInput, setSearchInput] = useState('');
  useEffect(() => {
    const t = setTimeout(() => setSearch(searchInput), 350);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Build game-rule map
  const rulesMap = Object.fromEntries(rules.map((r) => [r.slug, r]));

  // Find rule for selected tournament
  const selectedTournament = effectiveTournaments.find((t) => t.id === selectedId);
  const selectedRule = selectedTournament ? rulesMap[selectedTournament.game_type] : undefined;

  return (
    <div className="bl-lt-root">
      {/* Header */}
      <div className="bl-lt-header">
        <div className="bl-lt-header-title">
          <Trophy size={22} />
          <h2>Live Tournaments</h2>
        </div>

        {/* Search */}
        <div className="bl-lt-search">
          <Search size={16} />
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search tournaments..."
          />
        </div>
      </div>

      {/* Game Rule Tabs */}
      <GameTabs
        rules={rules}
        activeSlug={activeGame}
        onSelect={setActiveGame}
        loading={rulesLoading}
      />

      {/* Grid */}
      {loading ? (
        <div className="bl-lt-grid">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="bl-lt-skeleton-card">
              <div className="bl-lt-skeleton-card-header" />
              <div className="bl-lt-skeleton-card-body">
                <div className="bl-lt-skeleton-line w40 h16" />
                <div className="bl-lt-skeleton-line w80 h20" />
                <div className="bl-lt-skeleton-line w60" />
                <div className="bl-lt-skeleton-line w100 h5" />
              </div>
            </div>
          ))}
        </div>
      ) : effectiveTournaments.length === 0 ? (
        <div className="bl-lt-empty">
          <Trophy size={44} />
          <h3>No Live Tournaments</h3>
          <p>
            {activeGame
              ? 'No active tournaments for this game right now.'
              : 'There are no active tournaments at the moment. Check back soon!'}
          </p>
        </div>
      ) : (
        <div className="bl-lt-grid">
          {effectiveTournaments.map((t) => (
            <TournamentCard
              key={t.id}
              tournament={t}
              gameRule={rulesMap[t.game_type]}
              onClick={() => setSelectedId(t.id)}
            />
          ))}
        </div>
      )}

      {/* Modal */}
      {selectedId !== null && (
        <TournamentModal
          tournamentId={selectedId}
          gameRule={selectedRule}
          isLoggedIn={isLoggedIn}
          loginUrl={loginUrl}
          onClose={() => setSelectedId(null)}
          onJoined={loadTournaments}
        />
      )}
    </div>
  );
};

export default LiveTournamentsApp;
