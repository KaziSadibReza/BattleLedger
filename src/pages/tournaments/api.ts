import apiFetch from "@wordpress/api-fetch";
import eventBus from "../../lib/eventBus";
import type {
  Tournament,
  TournamentsResponse,
  TournamentStatus,
  FinishedTournament,
  GameRule,
  Participant,
  UserSearchResult,
} from "./types";

const BASE = "/battle-ledger/v1";

/* ── Tournament CRUD ─────────────────────────────────────────── */

export async function fetchTournaments(params: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}): Promise<TournamentsResponse> {
  const qs = new URLSearchParams();
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));
  if (params.search) qs.set("search", params.search);
  if (params.status && params.status !== "all") qs.set("status", params.status);

  return apiFetch({ path: `${BASE}/tournaments?${qs.toString()}` });
}

export async function fetchTournament(id: number): Promise<Tournament> {
  return apiFetch({ path: `${BASE}/tournaments/${id}` });
}

export async function createTournament(
  data: Record<string, unknown>
): Promise<{ success: boolean; tournament_id: number; message: string }> {
  const res = await apiFetch<{ success: boolean; tournament_id: number; message: string }>({
    path: `${BASE}/tournaments`,
    method: "POST",
    data,
  });
  eventBus.emit("tournaments:changed");
  return res;
}

export async function updateTournament(
  id: number,
  data: Record<string, unknown>
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/tournaments/${id}`,
    method: "PUT",
    data,
  });
  eventBus.emit("tournaments:changed");
  eventBus.emit("live:changed");
  return res;
}

export async function deleteTournament(
  id: number
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/tournaments/${id}`,
    method: "DELETE",
  });
  eventBus.emit("tournaments:changed");
  eventBus.emit("live:changed");
  return res;
}

export async function deleteFinishedTournament(
  id: number
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/finished-tournaments/${id}`,
    method: "DELETE",
  });
  eventBus.emit("finished:changed");
  return res;
}

export async function updateTournamentStatus(
  id: number,
  status: TournamentStatus
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/tournaments/${id}/status`,
    method: "POST",
    data: { status },
  });
  eventBus.emit("tournaments:changed");
  eventBus.emit("live:changed");
  return res;
}

export async function duplicateTournament(
  id: number
): Promise<{ success: boolean; tournament_id: number; message: string }> {
  const res = await apiFetch<{ success: boolean; tournament_id: number; message: string }>({
    path: `${BASE}/tournaments/${id}/duplicate`,
    method: "POST",
  });
  eventBus.emit("tournaments:changed");
  return res;
}

/* ── Live tournaments ────────────────────────────────────────── */

export async function fetchLiveTournaments(): Promise<Tournament[]> {
  const res = await apiFetch<{ tournaments: Tournament[] }>({
    path: `${BASE}/tournaments?live=1&per_page=100`,
  });
  return res.tournaments || [];
}

/* ── Finished tournaments ────────────────────────────────────── */

export async function fetchFinishedTournaments(): Promise<FinishedTournament[]> {
  const res = await apiFetch<{ tournaments: FinishedTournament[] }>({
    path: `${BASE}/finished-tournaments?per_page=200`,
  });
  return res.tournaments || [];
}

/* ── Winners ─────────────────────────────────────────────────── */

export async function saveWinners(
  tournamentId: number,
  winners: { first: string; second: string; third: string },
  killCounts?: Record<string, number>
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/tournaments/${tournamentId}/winners`,
    method: "POST",
    data: { winners, kill_counts: killCounts || {} },
  });
  // Tournament is deactivated & reset by the backend after winners saved
  eventBus.emit("tournaments:changed");
  eventBus.emit("live:changed");
  eventBus.emit("finished:changed");
  return res;
}

/* ── Rules Engine (read-only) ────────────────────────────────── */

export async function fetchActiveRules(): Promise<GameRule[]> {
  const res = await apiFetch<{ success: boolean; rules: GameRule[] }>({
    path: `${BASE}/rules/active`,
  });
  return Array.isArray(res) ? res : res.rules || [];
}

/* ── Participants ────────────────────────────────────────────── */

export async function fetchParticipants(
  tournamentId: number
): Promise<Participant[]> {
  const res = await apiFetch<{ success: boolean; participants: Participant[] }>({
    path: `${BASE}/tournaments/${tournamentId}/participants`,
  });
  return res.participants || [];
}

export async function addParticipant(
  tournamentId: number,
  userId: number,
  teamName?: string
): Promise<{ success: boolean; id: number; message: string }> {
  const res = await apiFetch<{ success: boolean; id: number; message: string }>({
    path: `${BASE}/tournaments/${tournamentId}/participants`,
    method: "POST",
    data: { user_id: userId, team_name: teamName || "" },
  });
  eventBus.emit("participants:changed");
  eventBus.emit("live:changed");
  return res;
}

export async function removeParticipant(
  tournamentId: number,
  participantId: number
): Promise<{ success: boolean; message: string }> {
  const res = await apiFetch<{ success: boolean; message: string }>({
    path: `${BASE}/tournaments/${tournamentId}/participants/${participantId}`,
    method: "DELETE",
  });
  eventBus.emit("participants:changed");
  eventBus.emit("live:changed");
  return res;
}

/* ── User search ─────────────────────────────────────────────── */

export async function searchUsers(q: string): Promise<UserSearchResult[]> {
  const res = await apiFetch<{ success: boolean; users: UserSearchResult[] }>({
    path: `${BASE}/users/search?q=${encodeURIComponent(q)}`,
  });
  return res.users || [];
}
