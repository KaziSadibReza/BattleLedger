/**
 * User Wallet Page - Frontend Dashboard (Enhanced)
 */

import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import { 
  DollarSign, 
  TrendingUp, 
  TrendingDown, 
  Clock, 
  RefreshCw, 
  Eye, 
  EyeOff,
  Plus,
  Minus,
  ArrowUpRight,
  ArrowDownRight,
  CreditCard,
  Lock
} from 'lucide-react';
import Skeleton from '../components/Skeleton';
import DepositModal from '../components/DepositModal';
import WithdrawalModal from '../components/WithdrawalModal';
import { showNotification } from '../components/Notifications';

interface WalletData {
  id: number;
  user_id: number;
  balance: string;
  available_balance: string;
  frozen_balance: string;
  currency: string;
  status: string;
  created_at: string;
  updated_at: string;
}

interface Transaction {
  id: number;
  type: string;
  amount: string;
  balance_after: string;
  description: string;
  reference_type: string | null;
  reference_id: number | null;
  created_at: string;
  created_by: number;
  metadata?: any;
}

interface WalletStats {
  total_credits: number;
  total_debits: number;
  total_transactions: number;
  pending_transactions: number;
  pending_withdrawals: number;
  completed_withdrawals: number;
}

interface UserWalletProps {
  currentUser: {
    id: number;
    email: string;
    displayName: string;
    avatar: string;
  };
}

const UserWallet: React.FC<UserWalletProps> = ({ currentUser }) => {
  const [wallet, setWallet] = useState<WalletData | null>(null);
  const [stats, setStats] = useState<WalletStats | null>(null);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [showBalance, setShowBalance] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalTransactions, setTotalTransactions] = useState(0);
  const [showDepositModal, setShowDepositModal] = useState(false);
  const [showWithdrawalModal, setShowWithdrawalModal] = useState(false);
  const perPage = 10;

  // Fetch wallet data
  const fetchWallet = useCallback(async () => {
    try {
      const response = await apiFetch<WalletData>({
        path: `/battle-ledger/v1/wallet/my-wallet`,
      });
      setWallet(response);
    } catch (error) {
      console.error('Error fetching wallet:', error);
      showNotification('Failed to load wallet data', 'error');
    }
  }, [currentUser.id]);

  // Fetch wallet stats
  const fetchStats = useCallback(async () => {
    try {
      const response = await apiFetch<WalletStats>({
        path: `/battle-ledger/v1/wallet/user/${currentUser.id}/stats`,
      });
      setStats(response);
    } catch (error) {
      console.error('Error fetching stats:', error);
    }
  }, [currentUser.id]);

  // Fetch transactions
  const fetchTransactions = useCallback(async () => {
    try {
      const response = await apiFetch<{ transactions: Transaction[]; total: number }>({
        path: `/battle-ledger/v1/wallet/my-transactions?page=${currentPage}&per_page=${perPage}`,
      });
      setTransactions(response.transactions);
      setTotalTransactions(response.total);
    } catch (error) {
      console.error('Error fetching transactions:', error);
      showNotification('Failed to load transactions', 'error');
    }
  }, [currentUser.id, currentPage, perPage]);

  // Initial load
  useEffect(() => {
    const loadData = async () => {
      await Promise.all([fetchWallet(), fetchStats(), fetchTransactions()]);
      setLoading(false);
    };
    loadData();
  }, [fetchWallet, fetchStats, fetchTransactions]);

  // Refresh data
  const handleRefresh = async () => {
    setRefreshing(true);
    await Promise.all([fetchWallet(), fetchStats(), fetchTransactions()]);
    setRefreshing(false);
    showNotification('Wallet data refreshed', 'success');
  };

  const handleDepositSuccess = () => {
    fetchWallet();
    fetchStats();
    fetchTransactions();
  };

  const handleWithdrawalSuccess = () => {
    fetchWallet();
    fetchStats();
    fetchTransactions();
  };

  // Format currency
  const formatCurrency = (amount: string | number, currency: string = 'USD') => {
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(value);
  };

  // Format date
  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  // Get transaction icon and color
  const getTransactionStyle = (type: string, description?: string) => {
    const styles: Record<string, { icon: JSX.Element; color: string; label: string }> = {
      credit: { icon: <TrendingUp size={16} />, color: 'success', label: 'Credit' },
      admin_credit: { icon: <TrendingUp size={16} />, color: 'success', label: 'Admin Credit' },
      woocommerce: { icon: <CreditCard size={16} />, color: 'success', label: 'Deposit' },
      deposit_pending: { icon: <Clock size={16} />, color: 'warning', label: 'Pending Deposit' },
      prize: { icon: <TrendingUp size={16} />, color: 'success', label: 'Prize' },
      debit: { icon: <TrendingDown size={16} />, color: 'danger', label: 'Debit' },
      entry_fee: { icon: <TrendingDown size={16} />, color: 'warning', label: 'Entry Fee' },
      withdrawal: { icon: <TrendingDown size={16} />, color: 'danger', label: 'Withdrawal' },
      refund: { icon: <TrendingUp size={16} />, color: 'info', label: 'Refund' },
      purchase: { icon: <TrendingDown size={16} />, color: 'warning', label: 'Purchase' },
    };

    // Distinguish pending / completed / cancelled withdrawals
    if (type === 'withdrawal' && description) {
      if (description.includes('(pending)')) {
        return { icon: <Clock size={16} />, color: 'warning', label: 'Pending Withdrawal' };
      }
      if (description.includes('(cancelled')) {
        return { icon: <TrendingDown size={16} />, color: 'secondary', label: 'Withdrawal (Cancelled)' };
      }
      // completed or unrecognised fall through to default "Withdrawal"
    }

    return styles[type] || { icon: <Clock size={16} />, color: 'secondary', label: type };
  };

  // Calculate pagination
  const totalPages = Math.ceil(totalTransactions / perPage);

  if (loading) {
    return (
      <div className="bl-user-wallet">
        <div className="bl-wallet-balance-card">
          <Skeleton variant="rounded" height={200} />
        </div>
        <div className="bl-wallet-stats">
          {[1, 2, 3].map((i) => (
            <div key={i} className="bl-stat-card">
              <Skeleton variant="circular" width={56} height={56} />
              <div style={{ flex: 1 }}>
                <Skeleton width="40%" height={14} />
                <Skeleton width="60%" height={24} />
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  }

  if (!wallet) {
    return (
      <div className="bl-card">
        <p>Wallet not found. Please contact support.</p>
      </div>
    );
  }

  return (
    <div className="bl-user-wallet">
      {/* Wallet Balance Card */}
      <div className="bl-wallet-balance-card">
        {/* Dark Top Section */}
        <div className="bl-wallet-card-top">
          <div className="bl-wallet-header">
            <div className="bl-wallet-icon">
              <DollarSign size={24} />
            </div>
            <div className="bl-wallet-actions">
              <button
                className="bl-btn-icon"
                onClick={handleRefresh}
                disabled={refreshing}
                title="Refresh"
              >
                <RefreshCw size={18} className={refreshing ? 'spinning' : ''} />
              </button>
            </div>
          </div>
          
          <div className="bl-wallet-balance">
            <label>Available Balance</label>
            <div className="bl-balance-display">
              {showBalance ? (
                <h1>{formatCurrency(wallet.available_balance, wallet.currency)}</h1>
              ) : (
                <h1>••••••</h1>
              )}
              <button
                className="bl-btn-icon"
                onClick={() => setShowBalance(!showBalance)}
                title={showBalance ? 'Hide balance' : 'Show balance'}
              >
                {showBalance ? <Eye size={18} /> : <EyeOff size={18} />}
              </button>
            </div>
          </div>

          {parseFloat(wallet.frozen_balance) > 0 && showBalance && (
            <div className="bl-wallet-frozen">
              <Lock size={14} />
              <span>{formatCurrency(wallet.frozen_balance, wallet.currency)} frozen</span>
              <span className="bl-frozen-total">Total: {formatCurrency(wallet.balance, wallet.currency)}</span>
            </div>
          )}

          <div className="bl-wallet-status">
            <span className={`bl-badge bl-badge-${wallet.status === 'active' ? 'success' : 'warning'}`}>
              {wallet.status.charAt(0).toUpperCase() + wallet.status.slice(1)}
            </span>
            <span className="bl-wallet-currency">{wallet.currency}</span>
          </div>
        </div>

        {/* White Bottom Section — Action Buttons */}
        <div className="bl-wallet-card-bottom">
          <div className="bl-wallet-action-buttons">
            <button
              className="bl-btn-success"
              onClick={() => setShowDepositModal(true)}
            >
              <Plus size={18} />
              <span>Deposit</span>
            </button>
            <button
              className="bl-btn-danger"
              onClick={() => setShowWithdrawalModal(true)}
            >
              <Minus size={18} />
              <span>Withdraw</span>
            </button>
          </div>
        </div>
      </div>

      {/* Quick Stats */}
      <div className="bl-wallet-stats">
        <div className="bl-stat-card">
          <div className="bl-stat-icon success">
            <ArrowDownRight size={24} />
          </div>
          <div className="bl-stat-content">
            <label>Total Credits</label>
            <h3>
              {formatCurrency(stats?.total_credits || 0, wallet.currency)}
            </h3>
          </div>
        </div>

        <div className="bl-stat-card">
          <div className="bl-stat-icon warning">
            <ArrowUpRight size={24} />
          </div>
          <div className="bl-stat-content">
            <label>Withdrawals</label>
            <h3>
              {formatCurrency(stats?.completed_withdrawals || 0, wallet.currency)}
            </h3>
          </div>
        </div>

        <div className="bl-stat-card">
          <div className="bl-stat-icon info">
            <Clock size={24} />
          </div>
          <div className="bl-stat-content">
            <label>Total Transactions</label>
            <h3>{totalTransactions}</h3>
            {stats && stats.pending_transactions > 0 && (
              <span className="bl-stat-subtitle">{stats.pending_transactions} pending</span>
            )}
          </div>
        </div>
      </div>

      {/* Transaction History */}
      <div className="bl-card">
        <div className="bl-card-header">
          <h3>Transaction History</h3>
        </div>

        {transactions.length === 0 ? (
          <div className="bl-empty-state">
            <Clock size={48} />
            <p>No transactions yet</p>
          </div>
        ) : (
          <>
            <div className="bl-transaction-list">
              {transactions.map((transaction) => {
                const style = getTransactionStyle(transaction.type, transaction.description);
                return (
                  <div key={transaction.id} className="bl-transaction-item">
                    <div className={`bl-transaction-icon ${style.color}`}>
                      {style.icon}
                    </div>
                    <div className="bl-transaction-details">
                      <div className="bl-transaction-type">{style.label}</div>
                      <div className="bl-transaction-description">
                        {transaction.description || 'No description'}
                      </div>
                      {transaction.reference_type === 'order' && transaction.reference_id && (
                        <div className="bl-transaction-reference">
                          Order #{transaction.reference_id}
                        </div>
                      )}
                      <div className="bl-transaction-date">{formatDate(transaction.created_at)}</div>
                    </div>
                    <div className="bl-transaction-amount">
                      {transaction.type === 'deposit_pending' ? (
                        <>
                          <div className="bl-amount warning">
                            {formatCurrency(transaction.amount, wallet.currency)}
                          </div>
                          <div className="bl-pending-badge">Pending</div>
                        </>
                      ) : (
                        <>
                          <div className={`bl-amount ${style.color}`}>
                            {['credit', 'admin_credit', 'prize', 'refund', 'woocommerce'].includes(transaction.type) ? '+' : '-'}
                            {formatCurrency(Math.abs(parseFloat(String(transaction.amount))), wallet.currency)}
                          </div>
                          <div className="bl-balance-after">
                            Balance: {formatCurrency(transaction.balance_after, wallet.currency)}
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="bl-pagination">
                <button
                  className="bl-btn-secondary"
                  onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
                  disabled={currentPage === 1}
                >
                  Previous
                </button>
                <span className="bl-pagination-info">
                  Page {currentPage} of {totalPages}
                </span>
                <button
                  className="bl-btn-secondary"
                  onClick={() => setCurrentPage((prev) => Math.min(totalPages, prev + 1))}
                  disabled={currentPage === totalPages}
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Modals */}
      {wallet && (
        <>
          <DepositModal
            isOpen={showDepositModal}
            onClose={() => setShowDepositModal(false)}
            onSuccess={handleDepositSuccess}
            currentBalance={parseFloat(wallet.balance)}
            currency={wallet.currency}
          />

          <WithdrawalModal
            isOpen={showWithdrawalModal}
            onClose={() => setShowWithdrawalModal(false)}
            onSuccess={handleWithdrawalSuccess}
            currentBalance={parseFloat(wallet.available_balance)}
            currency={wallet.currency}
          />
        </>
      )}
    </div>
  );
};

export default UserWallet;
