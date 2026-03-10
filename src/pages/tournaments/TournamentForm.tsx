import React, { useState, useEffect } from "react";
import {
  ArrowLeft,
  ArrowRight,
  Save,
  Loader2,
  Gamepad2,
  ClipboardList,
  SlidersHorizontal,
  Trophy,
  Check,
  X,
} from "lucide-react";
import Toast from "../../components/Toast";
import GamePicker from "./GamePicker";
import BasicInfo from "./BasicInfo";
import GameConfig from "./GameConfig";
import PrizeSettings from "./PrizeSettings";
import {
  fetchTournament,
  createTournament,
  updateTournament,
} from "./api";
import type {
  GameRule,
  TournamentStatus,
  TournamentSettings,
} from "./types";

interface Props {
  tournamentId: number | null; // null = create mode
  games: GameRule[];
  reactivate?: boolean;        // true = opened via toggle button
  onClose: () => void;
  onSaved: () => void;
}

/* ── Step metadata ─────────────────────────────────────────── */
const STEPS = [
  { key: "game", label: "Select Game", icon: Gamepad2 },
  { key: "info", label: "Basic Info", icon: ClipboardList },
  { key: "config", label: "Game Config", icon: SlidersHorizontal },
  { key: "prize", label: "Prize & Rules", icon: Trophy },
] as const;

const TournamentForm: React.FC<Props> = ({
  tournamentId,
  games,
  reactivate = false,
  onClose,
  onSaved,
}) => {
  const isEdit = tournamentId !== null;

  /* ── Form state ──────────────────────────────────────────── */
  const [step, setStep] = useState<number>(0);
  const [loading, setLoading] = useState(isEdit);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState<{
    message: string;
    type: "success" | "error" | "info";
  } | null>(null);

  // Top-level fields
  const [gameType, setGameType] = useState("");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [status, setStatus] = useState<TournamentStatus>("deactive");
  // Auto-set end date to current date/time for new tournaments
  // Admin MUST change this — validation blocks save if still ≤ now
  const [endDate, setEndDate] = useState(() => {
    if (isEdit) return "";
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  });
  const [maxParticipants, setMaxParticipants] = useState(0);
  const [entryFee, setEntryFee] = useState(0);
  const [prizePool, setPrizePool] = useState(0);
  const [settings, setSettings] = useState<TournamentSettings>({});
  const [showErrors, setShowErrors] = useState(false);

  /* ── Load existing tournament ────────────────────────────── */
  useEffect(() => {
    if (!isEdit) return;
    (async () => {
      try {
        const t = await fetchTournament(tournamentId!);
        setGameType(t.game_type);
        setName(t.name);
        setDescription(t.description);
        // Map legacy statuses to new ones
        const s = t.status;
        if (s === "draft" || s === "cancelled") setStatus("deactive");
        else if (s === "registration") setStatus("active");
        else setStatus(s as TournamentStatus);
        setEndDate(
          t.end_date ? t.end_date.replace(" ", "T").slice(0, 16) : ""
        );
        setMaxParticipants(t.max_participants);
        setEntryFee(t.entry_fee);
        setPrizePool(t.prize_pool);
        setSettings(t.settings || {});
      } catch {
        setToast({ message: "Failed to load tournament", type: "error" });
      } finally {
        setLoading(false);
      }
    })();
  }, [tournamentId, isEdit]);

  /* Smart navigate on reactivate — go to first step with problems */
  useEffect(() => {
    if (!isEdit || loading || !reactivate) return;
    setShowErrors(true);
    const endBad = !endDate || new Date(endDate).getTime() <= Date.now();
    if (!name.trim() || endBad) { setStep(1); return; }
    const ag = games.find((g) => g.slug === gameType) || null;
    if (ag) {
      const noMode = ag.game_modes.length > 0 && !settings.game_mode;
      const noRoom = !settings.room_id?.trim() || !settings.room_password?.trim();
      if (noMode || noRoom) { setStep(2); return; }
    }
    if (!maxParticipants || !settings.room_id?.trim() || !settings.room_password?.trim()) { setStep(2); return; }
    if (!entryFee || entryFee <= 0 || !prizePool || prizePool <= 0) { setStep(3); return; }
    setStep(1); // all good — show date so admin can extend
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading]);

  /* ── Active game rule ────────────────────────────────────── */
  const activeGame =
    games.find((g) => g.slug === gameType) || null;

  /* ── Navigation ──────────────────────────────────────────── */
  const canPrev = step > 0;
  const canNext = step < STEPS.length - 1;

  /** Check whether every field is valid (used for auto-active) */
  const formComplete = (() => {
    if (!gameType || !name.trim()) return false;
    if (!endDate || new Date(endDate).getTime() <= Date.now()) return false;
    if (activeGame) {
      if (activeGame.game_modes.length > 0 && !settings.game_mode) return false;
      const selMode = activeGame.game_modes.find((m) => m.id === settings.game_mode);
      const maps = selMode ? activeGame.all_maps.filter((m) => selMode.allowedMaps.includes(m.id)) : activeGame.all_maps;
      if (maps.length > 0 && !settings.map) return false;
      const teams = selMode ? activeGame.all_team_modes.filter((tm) => selMode.allowedTeamModes.includes(tm.id)) : activeGame.all_team_modes;
      if (teams.length > 0 && !settings.team_mode) return false;
      const pCounts = selMode ? selMode.allowedPlayerCounts : activeGame.all_player_counts;
      if (pCounts.length > 0 && !settings.player_count) return false;
    }
    if (!maxParticipants || maxParticipants <= 0) return false;
    if (!settings.room_id?.trim() || !settings.room_password?.trim()) return false;
    if (!entryFee || entryFee <= 0) return false;
    if (!prizePool || prizePool <= 0) return false;
    return true;
  })();

  /* Auto-activate when all fields are valid, auto-deactivate when not */
  useEffect(() => {
    if (loading) return;
    setStatus(formComplete ? "active" : "deactive");
  }, [formComplete, loading]);

  const validate = (): { step: number; message: string } | null => {
    // Step 0 — Game
    if (!gameType) return { step: 0, message: "Please select a game." };
    // Step 1 — Basic Info
    if (!name.trim()) return { step: 1, message: "Tournament name is required." };
    if (!endDate) return { step: 1, message: "End date is required." };
    if (new Date(endDate).getTime() <= Date.now()) {
      return { step: 1, message: "End date must be in the future." };
    }
    // Step 2 — Game Config
    if (activeGame) {
      if (activeGame.game_modes.length > 0 && !settings.game_mode) {
        return { step: 2, message: "Please select a game mode." };
      }
      const selMode = activeGame.game_modes.find((m) => m.id === settings.game_mode);
      const maps = selMode
        ? activeGame.all_maps.filter((m) => selMode.allowedMaps.includes(m.id))
        : activeGame.all_maps;
      if (maps.length > 0 && !settings.map) {
        return { step: 2, message: "Please select a map." };
      }
      const teams = selMode
        ? activeGame.all_team_modes.filter((tm) => selMode.allowedTeamModes.includes(tm.id))
        : activeGame.all_team_modes;
      if (teams.length > 0 && !settings.team_mode) {
        return { step: 2, message: "Please select a team mode." };
      }
      const pCounts = selMode ? selMode.allowedPlayerCounts : activeGame.all_player_counts;
      if (pCounts.length > 0 && !settings.player_count) {
        return { step: 2, message: "Please select a player count." };
      }
    }
    if (!maxParticipants || maxParticipants <= 0) {
      return { step: 2, message: "Max participants is required." };
    }
    if (!settings.room_id?.trim()) return { step: 2, message: "Room ID is required." };
    if (!settings.room_password?.trim()) return { step: 2, message: "Room Password is required." };
    // Step 3 — Prize
    if (!entryFee || entryFee <= 0) return { step: 3, message: "Entry fee is required." };
    if (!prizePool || prizePool <= 0) return { step: 3, message: "Prize pool is required." };
    return null;
  };

  /* ── Save ────────────────────────────────────────────────── */
  const handleSave = async () => {
    const err = validate();
    if (err) {
      setShowErrors(true);
      setStep(err.step);
      setToast({ message: err.message, type: "error" });
      return;
    }

    /* Auto-set start date to now */
    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, "0");
    const autoStart = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:00`;

    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        name,
        description,
        game_type: gameType,
        status,
        start_date: autoStart,
        end_date: endDate ? endDate.replace("T", " ") + ":00" : "",
        max_participants: maxParticipants,
        entry_fee: entryFee,
        prize_pool: prizePool,
        settings,
      };

      if (isEdit) {
        await updateTournament(tournamentId!, payload);
        setToast({ message: "Tournament updated!", type: "success" });
      } else {
        await createTournament(payload);
        setToast({ message: "Tournament created!", type: "success" });
      }

      // short delay so toast is visible
      setTimeout(() => onSaved(), 800);
    } catch {
      setToast({ message: "Failed to save tournament", type: "error" });
    } finally {
      setSaving(false);
    }
  };

  /* ── Render ──────────────────────────────────────────────── */
  const currentStep = STEPS[step];

  return (
    <div className="bl-t-modal-overlay" onClick={onClose}>
      <div
        className="bl-t-modal"
        onClick={(e) => e.stopPropagation()}
      >
        {toast && (
          <Toast
            message={toast.message}
            type={toast.type}
            onClose={() => setToast(null)}
          />
        )}

        {/* ─── Modal header ───────────────────────────────── */}
        <div className="bl-t-modal-header">
          <h2>{isEdit ? "Edit Tournament" : "New Tournament"}</h2>
          <button className="bl-t-modal-close" onClick={onClose}>
            <X size={18} />
          </button>
        </div>

        {loading ? (
          <div className="bl-t-form-loading">
            <Loader2 size={28} className="bl-spin" />
            <p>Loading tournament...</p>
          </div>
        ) : (
          <>
            {/* ─── Step indicator ─────────────────────────── */}
            <div className="bl-t-stepper">
              {STEPS.map((s, i) => {
                const Icon = s.icon;
                const done = i < step;
                const active = i === step;
                return (
                  <button
                    key={s.key}
                    type="button"
                    className={`bl-t-step ${active ? "active" : ""} ${
                      done ? "done" : ""
                    }`}
                    onClick={() => setStep(i)}
                  >
                    <span className="bl-t-step-dot">
                      {done ? <Check size={14} /> : <Icon size={14} />}
                    </span>
                    <span className="bl-t-step-label">{s.label}</span>
                  </button>
                );
              })}
            </div>

            {/* ─── Step content ───────────────────────────── */}
            <div className="bl-t-modal-body">
              <div className="bl-t-form-card">
                <div className="bl-t-form-card-header">
                  <h3>
                    {React.createElement(currentStep.icon, { size: 18 })}
                    {currentStep.label}
                  </h3>
                </div>

                {step === 0 && (
                  <GamePicker
                    games={games}
                    selected={gameType}
                    onChange={setGameType}
                  />
                )}

                {step === 1 && (
                  <BasicInfo
                    data={{
                      name,
                      description,
                      end_date: endDate,
                    }}
                    highlightErrors={showErrors}
                    autoOpenDate={reactivate && (!endDate || new Date(endDate).getTime() <= Date.now())}
                    onChange={(patch) => {
                      if (patch.name !== undefined) setName(patch.name);
                      if (patch.description !== undefined)
                        setDescription(patch.description);
                      if (patch.end_date !== undefined) setEndDate(patch.end_date);
                    }}
                  />
                )}

                {step === 2 && (
                  <GameConfig
                    game={activeGame}
                    settings={settings}
                    maxParticipants={maxParticipants}
                    highlightErrors={showErrors}
                    onMaxParticipantsChange={setMaxParticipants}
                    onChange={(patch) =>
                      setSettings((prev) => ({ ...prev, ...patch }))
                    }
                  />
                )}

                {step === 3 && (
                  <PrizeSettings
                    entryFee={entryFee}
                    prizePool={prizePool}
                    rulesText={settings.rules_text || ""}
                    prizePerKill={settings.prize_per_kill || 0}
                    status={status}
                    allFieldsValid={formComplete}
                    highlightErrors={showErrors}
                    onStatusChange={setStatus}
                    onChange={(patch) => {
                      if (patch.entry_fee !== undefined) setEntryFee(patch.entry_fee);
                      if (patch.prize_pool !== undefined)
                        setPrizePool(patch.prize_pool);
                      if (patch.rules_text !== undefined)
                        setSettings((prev) => ({
                          ...prev,
                          rules_text: patch.rules_text,
                        }));
                      if (patch.prize_per_kill !== undefined)
                        setSettings((prev) => ({
                          ...prev,
                          prize_per_kill: patch.prize_per_kill,
                        }));
                    }}
                  />
                )}
              </div>
            </div>

            {/* ─── Footer nav ─────────────────────────────── */}
            <div className="bl-t-modal-footer">
              <button
                className="bl-t-btn-secondary"
                disabled={!canPrev}
                onClick={() => setStep((s) => s - 1)}
              >
                <ArrowLeft size={14} />
                Previous
              </button>

              <div className="bl-t-form-footer-right">
                <button
                  className="bl-t-btn-save"
                  onClick={handleSave}
                  disabled={saving}
                >
                  {saving ? (
                    <Loader2 size={14} className="bl-spin" />
                  ) : (
                    <Save size={14} />
                  )}
                  {isEdit ? "Update" : "Create"}
                </button>

                {canNext && (
                  <button
                    className="bl-t-btn-primary"
                    onClick={() => setStep((s) => s + 1)}
                  >
                    Next
                    <ArrowRight size={14} />
                  </button>
                )}
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default TournamentForm;
