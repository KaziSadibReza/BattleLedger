import React from "react";
import { Gamepad2, Check } from "lucide-react";
import type { GameRule } from "./types";

interface Props {
  games: GameRule[];
  selected: string;
  onChange: (slug: string) => void;
}

/** Step 1 — choose which game the tournament is for */
const GamePicker: React.FC<Props> = ({ games, selected, onChange }) => {
  if (games.length === 0) {
    return (
      <div className="bl-t-step-empty">
        <Gamepad2 size={28} />
        <p>No active game rules found. Create a game in the Rules Engine first.</p>
      </div>
    );
  }

  return (
    <div className="bl-t-game-grid">
      {games.map((g) => {
        const active = selected === g.slug;
        return (
          <button
            key={g.slug}
            type="button"
            className={`bl-t-game-pick ${active ? "selected" : ""}`}
            onClick={() => onChange(g.slug)}
          >
            {g.game_icon ? (
              <img src={g.game_icon} alt="" className="bl-t-game-pick-icon" />
            ) : (
              <div className="bl-t-game-pick-icon bl-t-game-pick-placeholder">
                <Gamepad2 size={24} />
              </div>
            )}
            <span className="bl-t-game-pick-name">{g.game_name}</span>
            {active && (
              <span className="bl-t-game-pick-check">
                <Check size={14} />
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
};

export default GamePicker;
