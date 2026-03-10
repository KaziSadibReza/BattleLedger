/**
 * Tournament Detail Modal — full-screen popup with all details + join flow
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import type { Tournament, GameRule, PaymentGateway } from '../types';
import {
  fetchTournamentDetail,
  joinTournament,
  fetchWalletBalance,
  fetchPaymentGateways,
  processDeposit,
  checkOrderStatus,
} from '../api';
import {
  X, Users, Trophy, DollarSign, Calendar, Clock, MapPin,
  Swords, Shield, CheckCircle, AlertTriangle,
  Wallet, CreditCard, ExternalLink, ChevronRight, UserPlus, Crosshair
} from 'lucide-react';

interface TournamentModalProps {
  tournamentId: number;
  gameRule?: GameRule;
  isLoggedIn: boolean;
  loginUrl: string;
  onClose: () => void;
  onJoined: () => void;
}

type ModalView =
  | 'loading'
  | 'details'
  | 'player-fields'
  | 'join-confirm'
  | 'joining'
  | 'join-success'
  | 'insufficient-balance'
  | 'deposit'
  | 'deposit-processing'
  | 'deposit-success'
  | 'deposit-pending'
  | 'deposit-external'
  | 'error';

const TournamentModal: React.FC<TournamentModalProps> = ({
  tournamentId,
  gameRule,
  isLoggedIn,
  loginUrl,
  onClose,
  onJoined,
}) => {
  const [view, setView] = useState<ModalView>('loading');
  const [tournament, setTournament] = useState<Tournament | null>(null);
  const [error, setError] = useState('');
  const [walletBalance, setWalletBalance] = useState<number>(0);
  const [newBalance, setNewBalance] = useState<number | null>(null);

  // Deposit state
  const [depositAmount, setDepositAmount] = useState('');
  const [gateways, setGateways] = useState<PaymentGateway[]>([]);
  const [selectedGw, setSelectedGw] = useState('');
  const [gatewaysLoading, setGatewaysLoading] = useState(false);
  const [pendingOrderId, setPendingOrderId] = useState<number | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Player identity fields state
  const [playerData, setPlayerData] = useState<Record<string, string>[]>([]);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>[]>([]);

  // Load tournament detail
  useEffect(() => {
    loadTournament();
    return () => stopPolling();
  }, [tournamentId]);

  // Lock scroll
  useEffect(() => {
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = ''; };
  }, []);

  // Escape key
  useEffect(() => {
    const h = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, [onClose]);

  // Visibility change for polling
  useEffect(() => {
    const handler = () => {
      if (document.visibilityState === 'visible' && pendingOrderId && view === 'deposit-external') {
        pollOrderStatus(pendingOrderId);
      }
    };
    document.addEventListener('visibilitychange', handler);
    return () => document.removeEventListener('visibilitychange', handler);
  }, [pendingOrderId, view]);

  const stopPolling = () => {
    if (pollRef.current) { clearInterval(pollRef.current); pollRef.current = null; }
  };

  const startPolling = (orderId: number) => {
    stopPolling();
    pollRef.current = setInterval(() => pollOrderStatus(orderId), 5000);
  };

  const pollOrderStatus = useCallback(async (orderId: number) => {
    try {
      const res = await checkOrderStatus(orderId);
      if (res.is_paid) {
        stopPolling();
        setWalletBalance(res.new_balance);
        setNewBalance(res.new_balance);
        setView('deposit-success');
      }
    } catch { /* continue polling */ }
  }, []);

  const loadTournament = async () => {
    try {
      setView('loading');
      const res = await fetchTournamentDetail(tournamentId);
      setTournament(res.tournament);

      if (isLoggedIn) {
        const wb = await fetchWalletBalance();
        setWalletBalance(wb.balance);
      }

      setView('details');
    } catch (err: any) {
      setError(err.message || 'Failed to load tournament');
      setView('error');
    }
  };

  /* ── Join Flow ──────────────────────────────────────────── */

  const handleJoinClick = async () => {
    if (!tournament) return;

    // Block if tournament is awaiting results
    if (tournament.status === 'awaiting_results') return;

    // Check balance first
    if (tournament.entry_fee > 0 && walletBalance < tournament.entry_fee) {
      const shortfall = tournament.entry_fee - walletBalance;
      setDepositAmount(shortfall.toFixed(2));
      setView('insufficient-balance');
      return;
    }

    // If the tournament requires player identity fields, show that step first
    const fields = tournament.player_fields ?? [];
    const slots = tournament.team_mode_slots ?? 1;
    if (fields.length > 0) {
      // Initialise empty player data for each slot
      const empty: Record<string, string>[] = Array.from({ length: slots }, () => {
        const obj: Record<string, string> = {};
        fields.forEach(f => { obj[f.id] = ''; });
        return obj;
      });
      setPlayerData(empty);
      setFieldErrors(Array.from({ length: slots }, () => ({})));
      setView('player-fields');
      return;
    }

    setView('join-confirm');
  };

  const confirmJoin = async () => {
    if (!tournament) return;
    setView('joining');
    try {
      const fields = tournament.player_fields ?? [];
      const res = await joinTournament(
        tournament.id,
        undefined,
        fields.length > 0 ? playerData : undefined,
      );
      const slots = tournament.team_mode_slots ?? 1;
      setNewBalance(res.new_balance);
      setTournament(prev => prev ? { ...prev, is_joined: true, participant_count: prev.participant_count + slots } : prev);
      setView('join-success');
      onJoined();
    } catch (err: any) {
      const data = err?.data;
      if (err?.code === 'insufficient_balance' || data?.status === 400) {
        const shortfall = (data?.shortfall ?? tournament.entry_fee) as number;
        setDepositAmount(shortfall.toFixed(2));
        setView('insufficient-balance');
      } else {
        setError(err.message || 'Failed to join tournament');
        setView('error');
      }
    }
  };

  /* ── Player Identity Fields validation ─────────────────── */

  const updatePlayerField = (slotIndex: number, fieldId: string, value: string) => {
    setPlayerData(prev => {
      const copy = [...prev];
      copy[slotIndex] = { ...copy[slotIndex], [fieldId]: value };
      return copy;
    });
    // Clear error for this field
    setFieldErrors(prev => {
      const copy = [...prev];
      if (copy[slotIndex]?.[fieldId]) {
        copy[slotIndex] = { ...copy[slotIndex] };
        delete copy[slotIndex][fieldId];
      }
      return copy;
    });
  };

  const validatePlayerFields = (): boolean => {
    if (!tournament) return false;
    const fields = tournament.player_fields ?? [];
    const errors: Record<string, string>[] = playerData.map(() => ({}));
    let valid = true;

    playerData.forEach((player, idx) => {
      fields.forEach(field => {
        const value = (player[field.id] ?? '').trim();
        if (field.required && !value) {
          errors[idx][field.id] = `${field.name} is required`;
          valid = false;
        } else if (value && field.validation) {
          try {
            const re = new RegExp(field.validation);
            if (!re.test(value)) {
              errors[idx][field.id] = `Invalid ${field.name} format`;
              valid = false;
            }
          } catch { /* skip broken regex */ }
        }
      });
    });

    setFieldErrors(errors);
    return valid;
  };

  const handlePlayerFieldsSubmit = () => {
    if (validatePlayerFields()) {
      setView('join-confirm');
    }
  };

  /* ── Deposit Flow ───────────────────────────────────────── */

  const openDeposit = async () => {
    setView('deposit');
    if (gateways.length === 0) {
      setGatewaysLoading(true);
      try {
        const res = await fetchPaymentGateways();
        const enabled = res.gateways.filter(g => g.enabled);
        setGateways(enabled);
        if (enabled.length) setSelectedGw(enabled[0].id);
      } catch { /* ignore */ }
      setGatewaysLoading(false);
    }
  };

  const handleDeposit = async () => {
    const amt = parseFloat(depositAmount);
    if (!amt || amt <= 0 || !selectedGw) return;

    setView('deposit-processing');
    try {
      const res = await processDeposit(amt, selectedGw);
      if (res.success) {
        if (res.requires_redirect && res.redirect_url) {
          setPendingOrderId(res.order_id || null);
          setView('deposit-external');
          startPolling(res.order_id!);
          window.open(res.redirect_url, '_blank');
        } else if (res.wallet_credited) {
          setWalletBalance(res.new_balance ?? 0);
          setNewBalance(res.new_balance ?? null);
          setView('deposit-success');
        } else {
          setView('deposit-pending');
        }
      } else {
        setError(res.message || 'Deposit failed');
        setView('error');
      }
    } catch (err: any) {
      setError(err.message || 'Deposit failed');
      setView('error');
    }
  };

  /* ── Helpers ────────────────────────────────────────────── */

  const fmt = (n: number) => n.toFixed(2);
  const fmtDate = (d: string) => {
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
      ' ' + dt.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
  };

  const t = tournament;
  const gameName = gameRule?.game_name || (t?.game_type || 'Game');
  const isFull = t ? (t.max_participants > 0 && t.participant_count >= t.max_participants) : false;

  // Client-side awaiting-results: also check end_date locally so status
  // flips immediately even if the server still returns "active"
  const [localEnded, setLocalEnded] = useState(false);
  useEffect(() => {
    if (!t?.end_date) { setLocalEnded(false); return; }
    const endMs = new Date(t.end_date).getTime();
    if (endMs <= Date.now()) { setLocalEnded(true); return; }
    const timer = setTimeout(() => setLocalEnded(true), endMs - Date.now());
    return () => clearTimeout(timer);
  }, [t?.end_date]);

  const isAwaitingResults = t?.status === 'awaiting_results' || (t?.status === 'active' && localEnded);

  const quickAmounts = [100, 250, 500, 1000, 2500, 5000];

  /* ── Render ─────────────────────────────────────────────── */

  return (
    <div className="bl-lt-modal-overlay" onClick={onClose}>
      <div className="bl-lt-modal" onClick={(e) => e.stopPropagation()}>

        {/* Close button */}
        <button className="bl-lt-modal-close" onClick={onClose}>
          <X size={20} />
        </button>

        {/* ─────── Loading ─────── */}
        {view === 'loading' && (
          <>
            <div className="bl-lt-skeleton-modal-hero" />
            <div className="bl-lt-skeleton-modal-body">
              <div className="bl-lt-skeleton-info-grid">
                <div className="bl-lt-skeleton-info-card" />
                <div className="bl-lt-skeleton-info-card" />
                <div className="bl-lt-skeleton-info-card" />
              </div>
              <div className="bl-lt-skeleton-line w60" />
              <div className="bl-lt-skeleton-line w80" />
              <div className="bl-lt-skeleton-line w100" />
              <div className="bl-lt-skeleton-line w40" />
              <div className="bl-lt-skeleton-btn" />
            </div>
          </>
        )}

        {/* ─────── Error ─────── */}
        {view === 'error' && (
          <div className="bl-lt-modal-center">
            <AlertTriangle size={40} className="bl-lt-text-danger" />
            <h3>Something went wrong</h3>
            <p>{error}</p>
            <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>
              Go Back
            </button>
          </div>
        )}

        {/* ─────── Tournament Details ─────── */}
        {view === 'details' && t && (
          <>
            {/* Header */}
            <div className="bl-lt-modal-hero">
              {gameRule?.game_image ? (
                <img src={gameRule.game_image} alt="" className="bl-lt-modal-hero-bg" />
              ) : (
                <div className="bl-lt-modal-hero-bg bl-lt-modal-hero-gradient" />
              )}
              <div className="bl-lt-modal-hero-content">
                {isAwaitingResults ? (
                  <span className="bl-lt-awaiting-badge"><Clock size={12} /> Awaiting Results</span>
                ) : (
                  <span className="bl-lt-live-badge"><span className="bl-lt-pulse" />Live</span>
                )}
                <h2>{t.name}</h2>
                <div className="bl-lt-modal-game-tag">
                  {gameRule?.game_icon && <img src={gameRule.game_icon} alt="" />}
                  <span>{gameName}</span>
                </div>
              </div>
            </div>

            {/* Content */}
            <div className="bl-lt-modal-body">
              {/* Info grid */}
              <div className="bl-lt-info-grid">
                <div className="bl-lt-info-card">
                  <Users size={18} />
                  <div>
                    <span className="bl-lt-info-label">Participants</span>
                    <span className="bl-lt-info-value">
                      {t.participant_count}{t.max_participants > 0 ? ` / ${t.max_participants}` : ''}
                    </span>
                  </div>
                </div>
                <div className="bl-lt-info-card">
                  <DollarSign size={18} />
                  <div>
                    <span className="bl-lt-info-label">Entry Fee</span>
                    <span className="bl-lt-info-value">{t.entry_fee > 0 ? `$${fmt(t.entry_fee)}` : 'Free'}</span>
                  </div>
                </div>
                <div className="bl-lt-info-card bl-lt-info-prize">
                  <Trophy size={18} />
                  <div>
                    <span className="bl-lt-info-label">Prize Pool</span>
                    <span className="bl-lt-info-value">{t.prize_pool > 0 ? `$${fmt(t.prize_pool)}` : '—'}</span>
                  </div>
                </div>
                {t.settings.prize_per_kill && t.settings.prize_per_kill > 0 && (
                  <div className="bl-lt-info-card bl-lt-info-kill">
                    <Crosshair size={18} />
                    <div>
                      <span className="bl-lt-info-label">Per Kill</span>
                      <span className="bl-lt-info-value">${fmt(t.settings.prize_per_kill)}</span>
                    </div>
                  </div>
                )}
                {t.settings.game_mode && (
                  <div className="bl-lt-info-card">
                    <Swords size={18} />
                    <div>
                      <span className="bl-lt-info-label">Game Mode</span>
                      <span className="bl-lt-info-value">{t.settings.game_mode}</span>
                    </div>
                  </div>
                )}
                {t.settings.map && (
                  <div className="bl-lt-info-card">
                    <MapPin size={18} />
                    <div>
                      <span className="bl-lt-info-label">Map</span>
                      <span className="bl-lt-info-value">{t.settings.map}</span>
                    </div>
                  </div>
                )}
                {t.settings.team_mode && (
                  <div className="bl-lt-info-card">
                    <Shield size={18} />
                    <div>
                      <span className="bl-lt-info-label">Team Mode</span>
                      <span className="bl-lt-info-value">{t.settings.team_mode}</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Dates */}
              <div className="bl-lt-dates-row">
                {t.start_date && (
                  <div className="bl-lt-date-item">
                    <Calendar size={14} />
                    <span>Starts: {fmtDate(t.start_date)}</span>
                  </div>
                )}
                {t.end_date && (
                  <div className="bl-lt-date-item">
                    <Clock size={14} />
                    <span>Ends: {fmtDate(t.end_date)}</span>
                  </div>
                )}
              </div>

              {/* Description */}
              {t.description && (
                <div className="bl-lt-section">
                  <h4>About</h4>
                  <p className="bl-lt-description">{t.description}</p>
                </div>
              )}

              {/* Participants list — expand team registrations into individual players */}
              {t.participants && t.participants.length > 0 && (() => {
                // Flatten: for each registration, if it has player data (team mode), show each player;
                // otherwise show the registering user (solo mode)
                const flatList: { name: string; teamName: string; regKey: string }[] = [];
                const fields = t.player_fields ?? [];
                // Find the first field to use as the display name (preferring 'name' or 'username', else first field)
                const nameField = fields.find(f => /name|username/i.test(f.id)) ?? fields[0];

                for (const p of t.participants) {
                  if (p.players && p.players.length > 0 && nameField) {
                    p.players.forEach((player, pi) => {
                      const displayVal = player[nameField.id] || `Player ${pi + 1}`;
                      flatList.push({ name: displayVal, teamName: p.team_name, regKey: `${p.user_id}-${pi}` });
                    });
                  } else if (p.slots > 1 && !p.players?.length) {
                    // Team registration but no player data — show N entries with registrant name
                    for (let s = 0; s < p.slots; s++) {
                      flatList.push({ name: p.display_name, teamName: p.team_name, regKey: `${p.user_id}-${s}` });
                    }
                  } else {
                    flatList.push({ name: p.display_name, teamName: p.team_name, regKey: `${p.user_id}` });
                  }
                }

                return (
                  <div className="bl-lt-section">
                    <h4>Participants ({flatList.length})</h4>
                    <div className="bl-lt-participants-list">
                      {flatList.map((entry, i) => (
                        <div key={entry.regKey} className="bl-lt-participant">
                          <span className="bl-lt-participant-num">{i + 1}</span>
                          <span className="bl-lt-participant-name">{entry.name}</span>
                          {entry.teamName && <span className="bl-lt-participant-team">{entry.teamName}</span>}
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })()}

              {/* ── Action Button ── */}
              <div className="bl-lt-modal-action">
                {isAwaitingResults ? (
                  <div className="bl-lt-awaiting-banner">
                    <Clock size={20} />
                    <span>Awaiting Results</span>
                  </div>
                ) : !isLoggedIn ? (
                  <a href={loginUrl} className="bl-lt-btn bl-lt-btn-primary bl-lt-btn-block">
                    Log in to Join
                  </a>
                ) : t.is_joined ? (
                  <div className="bl-lt-joined-banner">
                    <CheckCircle size={20} />
                    <span>You've joined this tournament</span>
                  </div>
                ) : isFull ? (
                  <div className="bl-lt-full-banner">Tournament is Full</div>
                ) : (
                  <button className="bl-lt-btn bl-lt-btn-primary bl-lt-btn-block" onClick={handleJoinClick}>
                    {t.entry_fee > 0 ? `Join Tournament — $${fmt(t.entry_fee)}` : 'Join Tournament — Free'}
                    <ChevronRight size={18} />
                  </button>
                )}

                {isLoggedIn && !t.is_joined && !isFull && !isAwaitingResults && (
                  <div className="bl-lt-wallet-hint">
                    <Wallet size={14} />
                    <span>Wallet Balance: ${fmt(walletBalance)}</span>
                  </div>
                )}
              </div>
            </div>
          </>
        )}

        {/* ─────── Player Identity Fields ─────── */}
        {view === 'player-fields' && t && (
          <div className="bl-lt-player-fields-view">
            <div className="bl-lt-pf-header">
              <UserPlus size={24} />
              <div>
                <h3>Player Information</h3>
                <p>
                  {(t.team_mode_slots ?? 1) > 1
                    ? `${t.team_mode_name || t.settings.team_mode || 'Team'} Mode — ${t.team_mode_slots} Players`
                    : 'Enter your player details'}
                </p>
              </div>
            </div>

            <div className="bl-lt-pf-slots">
              {playerData.map((player, slotIdx) => (
                <div key={slotIdx} className="bl-lt-pf-slot">
                  {(t.team_mode_slots ?? 1) > 1 && (
                    <div className="bl-lt-pf-slot-header">
                      <Users size={14} />
                      <span>Player {slotIdx + 1}</span>
                    </div>
                  )}
                  <div className="bl-lt-pf-fields">
                    {(t.player_fields ?? []).map((field) => {
                      const errMsg = fieldErrors[slotIdx]?.[field.id];
                      return (
                        <div key={field.id} className={`bl-lt-pf-field ${errMsg ? 'bl-lt-pf-field-error' : ''}`}>
                          <label>
                            {field.name}
                            {field.required && <span className="bl-lt-pf-required">*</span>}
                          </label>
                          <input
                            type={field.type === 'number' ? 'text' : field.type}
                            inputMode={field.type === 'number' ? 'numeric' : undefined}
                            placeholder={field.placeholder || ''}
                            value={player[field.id] || ''}
                            onChange={(e) => updatePlayerField(slotIdx, field.id, e.target.value)}
                          />
                          {errMsg && <span className="bl-lt-pf-error-msg">{errMsg}</span>}
                        </div>
                      );
                    })}
                  </div>
                </div>
              ))}
            </div>

            <div className="bl-lt-confirm-actions">
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>Back</button>
              <button className="bl-lt-btn bl-lt-btn-primary" onClick={handlePlayerFieldsSubmit}>
                Continue
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        )}

        {/* ─────── Join Confirm ─────── */}
        {view === 'join-confirm' && t && (
          <div className="bl-lt-modal-center bl-lt-confirm-view">
            <Swords size={44} className="bl-lt-text-primary" />
            <h3>Join Tournament</h3>
            <p>You're about to join <strong>{t.name}</strong></p>

            {t.entry_fee > 0 && (
              <div className="bl-lt-confirm-summary">
                <div className="bl-lt-summary-row"><span>Entry Fee</span><span>${fmt(t.entry_fee)}</span></div>
                <div className="bl-lt-summary-row"><span>Wallet Balance</span><span>${fmt(walletBalance)}</span></div>
                <div className="bl-lt-summary-row bl-lt-summary-total">
                  <span>After Joining</span>
                  <span>${fmt(walletBalance - t.entry_fee)}</span>
                </div>
              </div>
            )}

            <div className="bl-lt-confirm-actions">
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>Cancel</button>
              <button className="bl-lt-btn bl-lt-btn-primary" onClick={confirmJoin}>
                Confirm & Join
              </button>
            </div>
          </div>
        )}

        {/* ─────── Joining ─────── */}
        {view === 'joining' && (
          <div className="bl-lt-modal-center">
            <div className="bl-lt-processing-dots"><span /><span /><span /></div>
            <h3>Joining Tournament...</h3>
            <p>Please wait, processing your entry</p>
          </div>
        )}

        {/* ─────── Join Success ─────── */}
        {view === 'join-success' && t && (
          <div className="bl-lt-modal-center bl-lt-success-view">
            <div className="bl-lt-success-icon">
              <CheckCircle size={52} />
            </div>
            <h3>You're In!</h3>
            <p>Successfully joined <strong>{t.name}</strong></p>
            {newBalance !== null && t.entry_fee > 0 && (
              <div className="bl-lt-balance-update">
                <span>New Wallet Balance</span>
                <strong>${fmt(newBalance)}</strong>
              </div>
            )}
            <button className="bl-lt-btn bl-lt-btn-primary" onClick={onClose}>Done</button>
          </div>
        )}

        {/* ─────── Insufficient Balance ─────── */}
        {view === 'insufficient-balance' && t && (
          <div className="bl-lt-modal-center bl-lt-insufficient-view">
            <AlertTriangle size={44} className="bl-lt-text-warning" />
            <h3>Insufficient Balance</h3>
            <p>
              You need <strong>${fmt(t.entry_fee)}</strong> to join. Your wallet has <strong>${fmt(walletBalance)}</strong>.
            </p>
            <p className="bl-lt-shortfall">
              You need at least <strong>${fmt(t.entry_fee - walletBalance)}</strong> more.
            </p>
            <div className="bl-lt-confirm-actions">
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>Go Back</button>
              <button className="bl-lt-btn bl-lt-btn-primary" onClick={openDeposit}>
                <Wallet size={16} /> Add Funds
              </button>
            </div>
          </div>
        )}

        {/* ─────── Deposit Form ─────── */}
        {view === 'deposit' && (
          <div className="bl-lt-deposit-view">
            <h3><Wallet size={20} /> Add Funds to Wallet</h3>

            {/* Amount */}
            <div className="bl-lt-field">
              <label>Amount</label>
              <div className="bl-lt-amount-input">
                <span className="bl-lt-currency">$</span>
                <input
                  type="text"
                  value={depositAmount}
                  onChange={(e) => {
                    const v = e.target.value;
                    if (v === '' || /^\d*\.?\d{0,2}$/.test(v)) setDepositAmount(v);
                  }}
                  placeholder="0.00"
                  autoFocus
                />
              </div>
            </div>

            {/* Quick amounts */}
            <div className="bl-lt-quick-amounts">
              {quickAmounts.map((v) => (
                <button key={v} className={`bl-lt-quick-btn ${depositAmount === String(v) ? 'active' : ''}`} onClick={() => setDepositAmount(String(v))}>
                  ${v}
                </button>
              ))}
            </div>

            {/* Payment gateways */}
            <div className="bl-lt-field">
              <label>Payment Method</label>
              {gatewaysLoading ? (
                <div>
                  <div className="bl-lt-skeleton-gateway" />
                  <div className="bl-lt-skeleton-gateway" />
                  <div className="bl-lt-skeleton-gateway" />
                </div>
              ) : gateways.length === 0 ? (
                <p className="bl-lt-text-muted">No payment methods available</p>
              ) : (
                <div className="bl-lt-gateways">
                  {gateways.map((gw) => (
                    <div
                      key={gw.id}
                      className={`bl-lt-gateway ${selectedGw === gw.id ? 'active' : ''}`}
                      onClick={() => setSelectedGw(gw.id)}
                    >
                      <CreditCard size={18} />
                      <span>{gw.title}</span>
                      <input type="radio" name="bl-gw" checked={selectedGw === gw.id} readOnly />
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Summary */}
            {depositAmount && parseFloat(depositAmount) > 0 && (
              <div className="bl-lt-confirm-summary">
                <div className="bl-lt-summary-row"><span>Current Balance</span><span>${fmt(walletBalance)}</span></div>
                <div className="bl-lt-summary-row"><span>Deposit</span><span className="bl-lt-text-success">+${fmt(parseFloat(depositAmount))}</span></div>
                <div className="bl-lt-summary-row bl-lt-summary-total">
                  <span>New Balance</span>
                  <strong>${fmt(walletBalance + parseFloat(depositAmount))}</strong>
                </div>
              </div>
            )}

            <div className="bl-lt-confirm-actions">
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('insufficient-balance')}>Back</button>
              <button
                className="bl-lt-btn bl-lt-btn-primary"
                disabled={!depositAmount || parseFloat(depositAmount) <= 0 || !selectedGw}
                onClick={handleDeposit}
              >
                Deposit ${depositAmount && parseFloat(depositAmount) > 0 ? fmt(parseFloat(depositAmount)) : ''}
              </button>
            </div>
          </div>
        )}

        {/* ─────── Deposit Processing ─────── */}
        {view === 'deposit-processing' && (
          <div className="bl-lt-modal-center">
            <div className="bl-lt-processing-dots"><span /><span /><span /></div>
            <h3>Processing Payment...</h3>
          </div>
        )}

        {/* ─────── Deposit Success ─────── */}
        {view === 'deposit-success' && (
          <div className="bl-lt-modal-center bl-lt-success-view">
            <div className="bl-lt-success-icon"><CheckCircle size={48} /></div>
            <h3>Funds Added!</h3>
            <p>Your wallet has been credited.</p>
            {newBalance !== null && (
              <div className="bl-lt-balance-update">
                <span>New Balance</span><strong>${fmt(newBalance)}</strong>
              </div>
            )}
            <button className="bl-lt-btn bl-lt-btn-primary" onClick={() => {
              setWalletBalance(newBalance ?? walletBalance);
              setView('details');
            }}>
              Continue to Join
            </button>
          </div>
        )}

        {/* ─────── Deposit Pending ─────── */}
        {view === 'deposit-pending' && (
          <div className="bl-lt-modal-center">
            <Clock size={44} className="bl-lt-text-warning" />
            <h3>Payment Pending</h3>
            <p>Your wallet will be credited once payment is confirmed.</p>
            <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>OK</button>
          </div>
        )}

        {/* ─────── Deposit External Redirect ─────── */}
        {view === 'deposit-external' && (
          <div className="bl-lt-modal-center">
            <ExternalLink size={40} className="bl-lt-text-primary" />
            <h3>Complete Your Payment</h3>
            <p>A new tab has been opened. Complete payment there.</p>
            <div className="bl-lt-awaiting-status">
              <div className="bl-lt-processing-dots"><span /><span /><span /></div>
              <span>Waiting for confirmation...</span>
            </div>
            <div className="bl-lt-confirm-actions">
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => { if (pendingOrderId) pollOrderStatus(pendingOrderId); }}>
                Check Status
              </button>
              <button className="bl-lt-btn bl-lt-btn-secondary" onClick={() => setView('details')}>Close</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default TournamentModal;
