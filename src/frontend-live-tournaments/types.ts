/**
 * Types for the public live-tournaments frontend
 */

export interface GameRule {
  id: number;
  slug: string;
  game_name: string;
  game_icon: string;
  game_image: string;
  description: string;
  game_modes: GameMode[];
  all_maps: MapConfig[];
  all_team_modes: TeamMode[];
}

export interface GameMode {
  id: string;
  name: string;
  description?: string;
}

export interface MapConfig {
  id: string;
  name: string;
  image?: string;
}

export interface TeamMode {
  id: string;
  name: string;
  max_players?: number;
  playersPerTeam?: number;
}

export interface PlayerFieldConfig {
  id: string;
  name: string;
  type: 'text' | 'number' | 'email';
  placeholder: string;
  required: boolean;
  validation?: string;
}

export interface TournamentParticipant {
  user_id: number;
  display_name: string;
  team_name: string;
  status: string;
  slots: number;
  registered_at: string;
  players?: Record<string, string>[];
}

export interface Tournament {
  id: number;
  name: string;
  slug: string;
  description: string;
  game_type: string;
  status: string;
  start_date: string;
  end_date: string;
  max_participants: number;
  entry_fee: number;
  prize_pool: number;
  participant_count: number;
  settings: {
    game_mode?: string;
    map?: string;
    team_mode?: string;
    prize_per_kill?: number;
    winners?: {
      first?: string;
      second?: string;
      third?: string;
    } | null;
  };
  created_at: string;
  // Detail-only fields
  participants?: TournamentParticipant[];
  is_joined?: boolean;
  // Team mode & identity fields (from detail endpoint)
  team_mode_slots?: number;
  team_mode_name?: string;
  player_fields?: PlayerFieldConfig[];
}

export interface TournamentsResponse {
  success: boolean;
  tournaments: Tournament[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface TournamentDetailResponse {
  success: boolean;
  tournament: Tournament;
}

export interface JoinResponse {
  success: boolean;
  message: string;
  participant_id: number;
  entry_fee: number;
  new_balance: number;
  slots: number;
}

export interface WalletBalanceResponse {
  success: boolean;
  balance: number;
}

export interface PaymentGateway {
  id: string;
  title: string;
  description: string;
  enabled: boolean;
  supports: string[];
}

export interface DepositResponse {
  success: boolean;
  order_id?: number;
  requires_redirect?: boolean;
  redirect_url?: string;
  gateway_title?: string;
  message?: string;
  new_balance?: number;
  order_status?: string;
  wallet_credited?: boolean;
}

export interface RulesResponse {
  success: boolean;
  rules: GameRule[];
}
