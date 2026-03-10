/**
 * API functions for public live-tournaments frontend
 */

import apiFetch from '@wordpress/api-fetch';
import type {
  TournamentsResponse,
  TournamentDetailResponse,
  JoinResponse,
  WalletBalanceResponse,
  RulesResponse,
  PaymentGateway,
  DepositResponse,
} from './types';

/* ── Tournaments ─────────────────────────────────────────── */

export async function fetchLiveTournaments(params?: {
  game_type?: string;
  search?: string;
  page?: number;
  per_page?: number;
}): Promise<TournamentsResponse> {
  const q = new URLSearchParams();
  if (params?.game_type) q.set('game_type', params.game_type);
  if (params?.search) q.set('search', params.search);
  if (params?.page) q.set('page', String(params.page));
  if (params?.per_page) q.set('per_page', String(params.per_page));

  const qs = q.toString();
  return apiFetch({ path: `/battle-ledger/v1/public/tournaments${qs ? '?' + qs : ''}` });
}

export async function fetchTournamentDetail(id: number): Promise<TournamentDetailResponse> {
  return apiFetch({ path: `/battle-ledger/v1/public/tournaments/${id}` });
}

/* ── Join ────────────────────────────────────────────────── */

export async function joinTournament(
  id: number,
  teamName?: string,
  playerData?: Record<string, string>[],
): Promise<JoinResponse> {
  return apiFetch({
    path: `/battle-ledger/v1/public/tournaments/${id}/join`,
    method: 'POST',
    data: {
      team_name: teamName || '',
      player_data: playerData || undefined,
    },
  });
}

/* ── Wallet ──────────────────────────────────────────────── */

export async function fetchWalletBalance(): Promise<WalletBalanceResponse> {
  return apiFetch({ path: '/battle-ledger/v1/public/wallet-balance' });
}

/* ── Game Rules ──────────────────────────────────────────── */

export async function fetchActiveRules(): Promise<RulesResponse> {
  return apiFetch({ path: '/battle-ledger/v1/rules/active' });
}

/* ── Deposit (reuse existing wallet endpoints) ───────────── */

export async function fetchPaymentGateways(): Promise<{ gateways: PaymentGateway[] }> {
  return apiFetch({ path: '/battle-ledger/v1/wallet/payment-gateways' });
}

export async function processDeposit(amount: number, paymentMethod: string): Promise<DepositResponse> {
  return apiFetch({
    path: '/battle-ledger/v1/wallet/deposit',
    method: 'POST',
    data: { amount, payment_method: paymentMethod },
  });
}

export async function checkOrderStatus(orderId: number): Promise<{
  success: boolean;
  status: string;
  is_paid: boolean;
  new_balance: number;
}> {
  return apiFetch({ path: `/battle-ledger/v1/wallet/order-status/${orderId}` });
}
