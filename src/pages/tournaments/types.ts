/* ── Tournament Types ─────────────────────────────────────────── */

/** Status values matching the backend */
export type TournamentStatus =
  | "active"
  | "deactive"
  | "completed"
  /* legacy — kept for backward compat with old data */
  | "draft"
  | "registration"
  | "cancelled";

/** Shape returned by the REST API */
export interface Tournament {
  id: number;
  name: string;
  slug: string;
  description: string;
  game_type: string; // slug of the game rule
  status: TournamentStatus;
  start_date: string | null;
  end_date: string | null;
  max_participants: number;
  entry_fee: number;
  prize_pool: number;
  settings: TournamentSettings;
  created_by: number;
  created_at: string;
  updated_at: string;
  participant_count: number;
}

/** Winner placement */
export interface WinnerPlacement {
  first: string;
  second: string;
  third: string;
}

/** Kill counts per participant (keyed by display_name) */
export type KillCounts = Record<string, number>;

/** Participant in a tournament */
export interface Participant {
  id: number;
  tournament_id: number;
  user_id: number;
  display_name: string;
  user_email: string;
  phone: string;
  team_name: string;
  status: string;
  rank: number | null;
  score: number;
  registered_at: string;
}

/** User from search endpoint */
export interface UserSearchResult {
  id: number;
  display_name: string;
  user_email: string;
  phone: string;
  avatar: string;
}

/** JSON stored in the `settings` column */
export interface TournamentSettings {
  game_mode?: string;
  map?: string;
  team_mode?: string;
  player_count?: number;
  custom_settings?: Record<string, boolean>;
  player_fields?: string[];       // ids of required player fields
  room_id?: string;
  room_password?: string;
  rules_text?: string;
  prize_per_kill?: number;
  winners?: WinnerPlacement;
  [key: string]: unknown;
}

/** Participant snapshot stored inside a finished tournament */
export interface FinishedParticipant {
  user_id: number;
  display_name: string;
  user_email: string;
  phone: string;
  team_name: string;
  status: string;
  rank: number | null;
  score: number;
  slots: number;
  kills: number;
  registered_at: string;
}

/** Immutable snapshot created each time winners are assigned */
export interface FinishedTournament {
  id: number;
  tournament_id: number;
  name: string;
  slug: string;
  description: string;
  game_type: string;
  start_date: string | null;
  end_date: string | null;
  max_participants: number;
  entry_fee: number;
  prize_pool: number;
  settings: TournamentSettings;
  participants: FinishedParticipant[];
  participant_count: number;
  winners: WinnerPlacement;
  finished_by: number;
  finished_at: string;
}

/** Paginated response from GET /tournaments */
export interface TournamentsResponse {
  tournaments: Tournament[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

/* ── Rules Engine types (read-only, from /rules/active) ──────── */

export interface MapConfig {
  id: string;
  name: string;
  image?: string;
}

export interface TeamModeConfig {
  id: string;
  name: string;
  playersPerTeam: number;
}

export interface PlayerFieldConfig {
  id: string;
  name: string;
  type: "text" | "number" | "email";
  placeholder: string;
  required: boolean;
  validation?: string;
}

export interface GameSettingConfig {
  id: string;
  name: string;
  enabled: boolean;
}

export interface GameModeConfig {
  id: string;
  name: string;
  allowedMaps: string[];
  allowedTeamModes: string[];
  allowedPlayerCounts: number[];
  settings: GameSettingConfig[];
}

export interface GameRule {
  id: number;
  game_name: string;
  slug: string;
  game_icon: string;
  game_image: string;
  is_active: boolean;
  sort_order: number;
  all_maps: MapConfig[];
  all_team_modes: TeamModeConfig[];
  all_player_counts: number[];
  player_fields: PlayerFieldConfig[];
  available_settings: GameSettingConfig[];
  game_modes: GameModeConfig[];
}

/* ── Helpers ─────────────────────────────────────────────────── */

export const STATUS_OPTIONS: { value: TournamentStatus; label: string }[] = [
  { value: "active", label: "Active" },
  { value: "deactive", label: "Deactive" },
];

export const STATUS_META: Record<
  string,
  { label: string; dot: string }
> = {
  active: { label: "Active", dot: "#10b981" },
  deactive: { label: "Deactive", dot: "#9ca3af" },
  completed: { label: "Completed", dot: "#8b5cf6" },
  /* legacy statuses map to new labels */
  draft: { label: "Deactive", dot: "#9ca3af" },
  registration: { label: "Active", dot: "#10b981" },
  cancelled: { label: "Deactive", dot: "#9ca3af" },
};

/** Empty tournament for the create form */
export function emptyTournament(): Omit<Tournament, "id" | "created_at" | "updated_at" | "created_by" | "participant_count"> {
  return {
    name: "",
    slug: "",
    description: "",
    game_type: "",
    status: "deactive",
    start_date: null,
    end_date: null,
    max_participants: 0,
    entry_fee: 0,
    prize_pool: 0,
    settings: {},
  };
}
