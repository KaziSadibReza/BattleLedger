import React, { useState } from "react";

const Participants: React.FC = () => {
  const [participants] = useState([
    {
      id: 1,
      name: "Alex Thunder",
      email: "alex@example.com",
      wins: 15,
      losses: 3,
      tournaments: 5,
    },
    {
      id: 2,
      name: "Sarah Storm",
      email: "sarah@example.com",
      wins: 22,
      losses: 8,
      tournaments: 8,
    },
    {
      id: 3,
      name: "Mike Phoenix",
      email: "mike@example.com",
      wins: 10,
      losses: 5,
      tournaments: 4,
    },
    {
      id: 4,
      name: "Emma Blaze",
      email: "emma@example.com",
      wins: 18,
      losses: 6,
      tournaments: 6,
    },
  ]);

  const getWinRate = (wins: number, losses: number) => {
    const total = wins + losses;
    if (total === 0) return "0%";
    return ((wins / total) * 100).toFixed(1) + "%";
  };

  return (
    <div className="bl-participants">
      {/* Header Actions */}
      <div className="bl-page-header">
        <div className="bl-search-box">
          <span className="dashicons dashicons-search"></span>
          <input type="text" placeholder="Search participants..." />
        </div>
        <button className="bl-btn-primary">
          <span className="dashicons dashicons-plus-alt2"></span>
          Add Participant
        </button>
      </div>

      {/* Participants Grid */}
      <div className="bl-participants-grid">
        {participants.map((participant) => (
          <div key={participant.id} className="bl-participant-card">
            <div className="participant-avatar">
              <span className="dashicons dashicons-admin-users"></span>
            </div>
            <div className="participant-info">
              <h4>{participant.name}</h4>
              <p className="participant-email">{participant.email}</p>
            </div>
            <div className="participant-stats">
              <div className="stat-item">
                <span className="stat-value">{participant.wins}</span>
                <span className="stat-label">Wins</span>
              </div>
              <div className="stat-item">
                <span className="stat-value">{participant.losses}</span>
                <span className="stat-label">Losses</span>
              </div>
              <div className="stat-item">
                <span className="stat-value">
                  {getWinRate(participant.wins, participant.losses)}
                </span>
                <span className="stat-label">Win Rate</span>
              </div>
            </div>
            <div className="participant-actions">
              <button className="bl-btn-secondary">View Profile</button>
              <button className="bl-btn-icon" title="Edit">
                <span className="dashicons dashicons-edit"></span>
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Participants;
