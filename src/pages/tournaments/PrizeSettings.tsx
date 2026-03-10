import React from "react";
import { Coins, Trophy, ScrollText, Zap, ZapOff, AlertCircle, Crosshair } from "lucide-react";
import type { TournamentStatus } from "./types";

interface Props {
  entryFee: number;
  prizePool: number;
  rulesText: string;
  prizePerKill: number;
  status: TournamentStatus;
  allFieldsValid: boolean;
  highlightErrors?: boolean;
  onStatusChange: (s: TournamentStatus) => void;
  onChange: (patch: {
    entry_fee?: number;
    prize_pool?: number;
    rules_text?: string;
    prize_per_kill?: number;
  }) => void;
}

/** Step 4 — entry fee, prize pool, rules text, status toggle */
const PrizeSettings: React.FC<Props> = ({
  entryFee,
  prizePool,
  rulesText,
  prizePerKill,
  status,
  allFieldsValid,
  highlightErrors,
  onStatusChange,
  onChange,
}) => {
  const isActive = status === "active";
  const killPrizeEnabled = prizePerKill > 0;
  return (
    <div className="bl-t-step-fields">
      <div className="bl-t-field-row">
        <div className={`bl-t-field${highlightErrors && (!entryFee || entryFee <= 0) ? " bl-t-field--error" : ""}`}>
          <label>
            <Coins size={14} /> Entry Fee *
          </label>
          <div className="bl-t-input-prefix">
            <span>$</span>
            <input
              type="number"
              min={0}
              step={0.01}
              placeholder="0.00"
              value={entryFee || ""}
              onChange={(e) =>
                onChange({ entry_fee: parseFloat(e.target.value) || 0 })
              }
            />
          </div>
        </div>
        <div className={`bl-t-field${highlightErrors && (!prizePool || prizePool <= 0) ? " bl-t-field--error" : ""}`}>
          <label>
            <Trophy size={14} /> Prize Pool *
          </label>
          <div className="bl-t-input-prefix">
            <span>$</span>
            <input
              type="number"
              min={0}
              step={0.01}
              placeholder="0.00"
              value={prizePool || ""}
              onChange={(e) =>
                onChange({ prize_pool: parseFloat(e.target.value) || 0 })
              }
            />
          </div>
        </div>
      </div>

      {/* Prize per Kill toggle + field */}
      <div className="bl-t-field">
        <label>
          <Crosshair size={14} /> Prize per Kill
        </label>
        <div className="bl-t-status-toggle-wrap">
          <button
            type="button"
            className={`bl-t-status-toggle ${killPrizeEnabled ? "active" : ""}`}
            onClick={() =>
              onChange({ prize_per_kill: killPrizeEnabled ? 0 : 1 })
            }
          >
            <span className="bl-t-status-toggle-track">
              <span className="bl-t-status-toggle-knob" />
            </span>
            <span className="bl-t-status-toggle-label">
              {killPrizeEnabled ? "Enabled" : "Disabled"}
            </span>
          </button>
        </div>
        {killPrizeEnabled && (
          <div className="bl-t-input-prefix" style={{ marginTop: 8 }}>
            <span>$</span>
            <input
              type="number"
              min={0}
              step={0.01}
              placeholder="Amount per kill"
              value={prizePerKill || ""}
              onChange={(e) =>
                onChange({ prize_per_kill: parseFloat(e.target.value) || 0 })
              }
            />
          </div>
        )}
      </div>

      <div className="bl-t-field">
        <label>
          <ScrollText size={14} /> Additional Rules / Notes
        </label>
        <textarea
          rows={5}
          placeholder="Any additional rules, instructions, or notes for participants..."
          value={rulesText}
          onChange={(e) => onChange({ rules_text: e.target.value })}
        />
      </div>

      {/* Status toggle — Active / Deactive */}
      <div className="bl-t-field">
        <label>Status</label>
        <div className="bl-t-status-toggle-wrap">
          <button
            type="button"
            className={`bl-t-status-toggle ${isActive ? "active" : ""}`}
            onClick={() => {
              if (!isActive && !allFieldsValid) return; // can't activate with incomplete fields
              onStatusChange(isActive ? "deactive" : "active");
            }}
            style={!isActive && !allFieldsValid ? { opacity: 0.5, cursor: "not-allowed" } : undefined}
          >
            <span className="bl-t-status-toggle-track">
              <span className="bl-t-status-toggle-knob" />
            </span>
            <span className="bl-t-status-toggle-label">
              {isActive ? <><Zap size={14} /> Active — Live Now</> : <><ZapOff size={14} /> Deactive</>}
            </span>
          </button>
          {isActive && (
            <span className="bl-t-status-hint live">
              Tournament is live and visible to players
            </span>
          )}
          {!isActive && !allFieldsValid && (
            <span className="bl-t-status-hint">
              <AlertCircle size={12} /> Fill all required fields to activate
            </span>
          )}
          {!isActive && allFieldsValid && (
            <span className="bl-t-status-hint">
              All fields are valid — toggle to go live!
            </span>
          )}
        </div>
      </div>
    </div>
  );
};

export default PrizeSettings;
