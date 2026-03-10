import React, { useMemo } from "react";
import {
  MapPin,
  Users,
  Crosshair,
  SlidersHorizontal,
  Check,
  UserCheck,
  KeyRound,
  Hash,
} from "lucide-react";
import type {
  GameRule,
  TournamentSettings,
  GameModeConfig,
  MapConfig,
  TeamModeConfig,
  GameSettingConfig,
} from "./types";

interface Props {
  game: GameRule | null;
  settings: TournamentSettings;
  maxParticipants: number;
  highlightErrors?: boolean;
  onMaxParticipantsChange: (val: number) => void;
  onChange: (patch: Partial<TournamentSettings>) => void;
}

/** Step 3 — game mode, map, team mode, player count, max participants, settings (all from rules engine) */
const GameConfig: React.FC<Props> = ({ game, settings, maxParticipants, highlightErrors, onMaxParticipantsChange, onChange }) => {
  if (!game) {
    return (
      <div className="bl-t-step-empty">
        <SlidersHorizontal size={28} />
        <p>Select a game first to configure its settings.</p>
      </div>
    );
  }

  /* ── Derived lists based on selected game mode ──────────── */
  const selectedMode: GameModeConfig | undefined = useMemo(
    () => game.game_modes.find((m) => m.id === settings.game_mode),
    [game.game_modes, settings.game_mode]
  );

  const availableMaps: MapConfig[] = useMemo(() => {
    if (!selectedMode) return game.all_maps;
    return game.all_maps.filter((m) => selectedMode.allowedMaps.includes(m.id));
  }, [selectedMode, game.all_maps]);

  const availableTeamModes: TeamModeConfig[] = useMemo(() => {
    if (!selectedMode) return game.all_team_modes;
    return game.all_team_modes.filter((tm) =>
      selectedMode.allowedTeamModes.includes(tm.id)
    );
  }, [selectedMode, game.all_team_modes]);

  const availablePlayerCounts: number[] = useMemo(() => {
    if (!selectedMode) return game.all_player_counts;
    return selectedMode.allowedPlayerCounts;
  }, [selectedMode, game.all_player_counts]);

  const modeSettings: GameSettingConfig[] = useMemo(() => {
    if (!selectedMode) return game.available_settings;
    return selectedMode.settings;
  }, [selectedMode, game.available_settings]);

  /* Toggle helper for custom_settings */
  const toggleSetting = (id: string) => {
    const prev = settings.custom_settings || {};
    onChange({
      custom_settings: { ...prev, [id]: !prev[id] },
    });
  };

  return (
    <div className="bl-t-step-fields">
      {/* Game Mode */}
      <div className={`bl-t-field${highlightErrors && !settings.game_mode ? " bl-t-field--error" : ""}`}>
        <label>
          <Crosshair size={14} /> Game Mode *
        </label>
        <div className="bl-t-chip-grid">
          {game.game_modes.map((m) => (
            <button
              key={m.id}
              type="button"
              className={`bl-t-chip ${
                settings.game_mode === m.id ? "active" : ""
              }`}
              onClick={() =>
                onChange({
                  game_mode: settings.game_mode === m.id ? undefined : m.id,
                  // reset downstream when mode changes
                  map: undefined,
                  team_mode: undefined,
                  player_count: undefined,
                  custom_settings: {},
                })
              }
            >
              {m.name}
              {settings.game_mode === m.id && <Check size={12} />}
            </button>
          ))}
        </div>
      </div>

      {/* Map */}
      <div className={`bl-t-field${highlightErrors && !settings.map ? " bl-t-field--error" : ""}`}>
        <label>
          <MapPin size={14} /> Map *
        </label>
        <div className="bl-t-chip-grid">
          {availableMaps.map((m) => (
            <button
              key={m.id}
              type="button"
              className={`bl-t-chip ${settings.map === m.id ? "active" : ""}`}
              onClick={() =>
                onChange({ map: settings.map === m.id ? undefined : m.id })
              }
            >
              {m.image && (
                <img src={m.image} alt="" className="bl-t-chip-img" />
              )}
              {m.name}
              {settings.map === m.id && <Check size={12} />}
            </button>
          ))}
        </div>
      </div>

      {/* Team mode + Player count row */}
      <div className="bl-t-field-row">
        <div className={`bl-t-field${highlightErrors && !settings.team_mode ? " bl-t-field--error" : ""}`}>
          <label>
            <Users size={14} /> Team Mode *
          </label>
          <div className="bl-t-chip-grid">
            {availableTeamModes.map((tm) => (
              <button
                key={tm.id}
                type="button"
                className={`bl-t-chip ${
                  settings.team_mode === tm.id ? "active" : ""
                }`}
                onClick={() =>
                  onChange({
                    team_mode:
                      settings.team_mode === tm.id ? undefined : tm.id,
                  })
                }
              >
                {tm.name}
                {settings.team_mode === tm.id && <Check size={12} />}
              </button>
            ))}
          </div>
        </div>

        <div className={`bl-t-field${highlightErrors && !settings.player_count ? " bl-t-field--error" : ""}`}>
          <label>
            <Hash size={14} /> Player Count *
          </label>
          <div className="bl-t-chip-grid">
            {availablePlayerCounts.map((pc) => (
              <button
                key={pc}
                type="button"
                className={`bl-t-chip ${
                  settings.player_count === pc ? "active" : ""
                }`}
                onClick={() =>
                  onChange({
                    player_count:
                      settings.player_count === pc ? undefined : pc,
                  })
                }
              >
                {pc}
                {settings.player_count === pc && <Check size={12} />}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Max Participants — from rules engine player counts */}
      <div className={`bl-t-field${highlightErrors && !maxParticipants ? " bl-t-field--error" : ""}`}>
        <label>
          <UserCheck size={14} /> Max Participants *
        </label>
        <div className="bl-t-chip-grid">
          {game.all_player_counts.map((pc) => (
            <button
              key={pc}
              type="button"
              className={`bl-t-chip ${maxParticipants === pc ? "active" : ""}`}
              onClick={() =>
                onMaxParticipantsChange(maxParticipants === pc ? 0 : pc)
              }
            >
              {pc}
              {maxParticipants === pc && <Check size={12} />}
            </button>
          ))}
        </div>
      </div>

      {/* Settings toggles */}
      {modeSettings.length > 0 && (
        <div className="bl-t-field">
          <label>
            <SlidersHorizontal size={14} /> Game Settings
          </label>
          <div className="bl-t-toggle-list">
            {modeSettings.map((s) => {
              const on = settings.custom_settings?.[s.id] ?? s.enabled;
              return (
                <label key={s.id} className="bl-t-toggle-item">
                  <input
                    type="checkbox"
                    checked={on}
                    onChange={() => toggleSetting(s.id)}
                  />
                  <span className="bl-t-toggle-track">
                    <span className="bl-t-toggle-knob" />
                  </span>
                  <span>{s.name}</span>
                </label>
              );
            })}
          </div>
        </div>
      )}

      {/* Room info */}
      <div className="bl-t-field-row">
        <div className={`bl-t-field${highlightErrors && !settings.room_id?.trim() ? " bl-t-field--error" : ""}`}>
          <label><Hash size={14} /> Room ID *</label>
          <input
            type="text"
            placeholder="Enter room ID..."
            value={settings.room_id || ""}
            onChange={(e) => onChange({ room_id: e.target.value })}
          />
        </div>
        <div className={`bl-t-field${highlightErrors && !settings.room_password?.trim() ? " bl-t-field--error" : ""}`}>
          <label><KeyRound size={14} /> Room Password *</label>
          <input
            type="text"
            placeholder="Enter room password..."
            value={settings.room_password || ""}
            onChange={(e) => onChange({ room_password: e.target.value })}
          />
        </div>
      </div>
    </div>
  );
};

export default GameConfig;
