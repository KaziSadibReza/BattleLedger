/**
 * Tournament Card — displays one tournament in the grid
 */

import React from 'react';
import type { Tournament, GameRule } from '../types';
import { Users, Trophy, DollarSign, Calendar, Clock, Crosshair } from 'lucide-react';

interface TournamentCardProps {
  tournament: Tournament;
  gameRule?: GameRule;
  onClick: () => void;
}

const TournamentCard: React.FC<TournamentCardProps> = ({ tournament, gameRule, onClick }) => {
  const t = tournament;
  const gameName = gameRule?.game_name || (t.game_type ? t.game_type.charAt(0).toUpperCase() + t.game_type.slice(1) : 'Game');
  const gameIcon = gameRule?.game_icon || '';
  const fillPct = t.max_participants > 0 ? Math.min(100, Math.round((t.participant_count / t.max_participants) * 100)) : 0;
  const isFull = t.max_participants > 0 && t.participant_count >= t.max_participants;
  const isAwaitingResults = t.status === 'awaiting_results';

  const startDate = t.start_date ? new Date(t.start_date) : null;
  const now = new Date();
  const isStarted = startDate ? startDate <= now : false;

  const formatDate = (d: Date) =>
    d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
    ' ' +
    d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

  return (
    <div className="bl-lt-card" onClick={onClick} role="button" tabIndex={0} onKeyDown={(e) => e.key === 'Enter' && onClick()}>
      {/* Game image/header strip */}
      <div className="bl-lt-card-header">
        {gameRule?.game_image ? (
          <img src={gameRule.game_image} alt="" className="bl-lt-card-bg" />
        ) : (
          <div className="bl-lt-card-bg bl-lt-card-bg-gradient" />
        )}
        <div className="bl-lt-card-header-overlay">
          {isAwaitingResults ? (
            <span className="bl-lt-awaiting-badge">
              <Clock size={10} />
              Awaiting Results
            </span>
          ) : (
            <span className="bl-lt-live-badge">
              <span className="bl-lt-pulse" />
              Live
            </span>
          )}
          {isFull && <span className="bl-lt-full-chip">Full</span>}
        </div>
      </div>

      {/* Body */}
      <div className="bl-lt-card-body">
        {/* Game tag */}
        <div className="bl-lt-game-tag">
          {gameIcon ? (
            <img src={gameIcon} alt="" className="bl-lt-game-tag-icon" />
          ) : null}
          <span>{gameName}</span>
          {t.settings.game_mode && <span className="bl-lt-mode-chip">{t.settings.game_mode}</span>}
        </div>

        {/* Title */}
        <h3 className="bl-lt-card-title">{t.name}</h3>

        {/* Description */}
        {t.description && (
          <p className="bl-lt-card-desc">{t.description.length > 80 ? t.description.slice(0, 80) + '...' : t.description}</p>
        )}

        {/* Stats row */}
        <div className="bl-lt-card-stats">
          <div className="bl-lt-stat" title="Participants">
            <Users size={14} />
            <span>
              {t.participant_count}
              {t.max_participants > 0 ? `/${t.max_participants}` : ''}
            </span>
          </div>

          {t.entry_fee > 0 && (
            <div className="bl-lt-stat" title="Entry Fee">
              <DollarSign size={14} />
              <span>{t.entry_fee.toFixed(2)}</span>
            </div>
          )}

          {t.entry_fee === 0 && (
            <div className="bl-lt-stat bl-lt-stat-free" title="Free Entry">
              <span>Free</span>
            </div>
          )}

          {t.prize_pool > 0 && (
            <div className="bl-lt-stat bl-lt-stat-prize" title="Prize Pool">
              <Trophy size={14} />
              <span>${t.prize_pool.toFixed(2)}</span>
            </div>
          )}

          {t.settings.prize_per_kill && t.settings.prize_per_kill > 0 && (
            <div className="bl-lt-stat bl-lt-stat-kill" title="Prize per Kill">
              <Crosshair size={14} />
              <span>${t.settings.prize_per_kill.toFixed(2)}/kill</span>
            </div>
          )}
        </div>

        {/* Capacity bar */}
        {t.max_participants > 0 && (
          <div className="bl-lt-capacity">
            <div className="bl-lt-capacity-track">
              <div
                className={`bl-lt-capacity-fill ${isFull ? 'full' : fillPct > 75 ? 'high' : ''}`}
                style={{ width: `${fillPct}%` }}
              />
            </div>
            <span className="bl-lt-capacity-label">{fillPct}%</span>
          </div>
        )}

        {/* Date */}
        {startDate && (
          <div className="bl-lt-card-date">
            {isStarted ? <Clock size={12} /> : <Calendar size={12} />}
            <span>{isStarted ? 'Started' : 'Starts'} {formatDate(startDate)}</span>
          </div>
        )}
      </div>
    </div>
  );
};

export default TournamentCard;
