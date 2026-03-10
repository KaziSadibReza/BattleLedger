import React, { useState } from "react";

const Matches: React.FC = () => {
  const [matches] = useState([
    {
      id: 1,
      tournament: "Summer Championship 2024",
      player1: "Alex Thunder",
      player2: "Sarah Storm",
      score1: 3,
      score2: 2,
      status: "completed",
      date: "2024-06-16",
    },
    {
      id: 2,
      tournament: "Summer Championship 2024",
      player1: "Mike Phoenix",
      player2: "Emma Blaze",
      score1: 0,
      score2: 0,
      status: "scheduled",
      date: "2024-06-17",
    },
    {
      id: 3,
      tournament: "Weekly League #12",
      player1: "Alex Thunder",
      player2: "Mike Phoenix",
      score1: 2,
      score2: 1,
      status: "in_progress",
      date: "2024-06-15",
    },
  ]);

  const getStatusBadge = (status: string) => {
    const statusMap: Record<string, { class: string; label: string }> = {
      completed: { class: "bl-badge bl-badge-success", label: "Completed" },
      scheduled: { class: "bl-badge bl-badge-warning", label: "Scheduled" },
      in_progress: { class: "bl-badge bl-badge-primary", label: "In Progress" },
    };
    return statusMap[status] || { class: "bl-badge", label: status };
  };

  return (
    <div className="bl-matches">
      {/* Header Actions */}
      <div className="bl-page-header">
        <div className="bl-search-box">
          <span className="dashicons dashicons-search"></span>
          <input type="text" placeholder="Search matches..." />
        </div>
        <button className="bl-btn-primary">
          <span className="dashicons dashicons-plus-alt2"></span>
          Record Match
        </button>
      </div>

      {/* Matches List */}
      <div className="bl-matches-list">
        {matches.map((match) => (
          <div key={match.id} className="bl-match-card">
            <div className="match-header">
              <span className="match-tournament">{match.tournament}</span>
              <span className={getStatusBadge(match.status).class}>
                {getStatusBadge(match.status).label}
              </span>
            </div>
            <div className="match-players">
              <div className={`player ${match.score1 > match.score2 ? "winner" : ""}`}>
                <span className="player-name">{match.player1}</span>
                <span className="player-score">{match.score1}</span>
              </div>
              <div className="vs">VS</div>
              <div className={`player ${match.score2 > match.score1 ? "winner" : ""}`}>
                <span className="player-score">{match.score2}</span>
                <span className="player-name">{match.player2}</span>
              </div>
            </div>
            <div className="match-footer">
              <span className="match-date">
                <span className="dashicons dashicons-calendar-alt"></span>
                {match.date}
              </span>
              <div className="match-actions">
                <button className="bl-btn-icon" title="View Details">
                  <span className="dashicons dashicons-visibility"></span>
                </button>
                <button className="bl-btn-icon" title="Edit">
                  <span className="dashicons dashicons-edit"></span>
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Matches;
