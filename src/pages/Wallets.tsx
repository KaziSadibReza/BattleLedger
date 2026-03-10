import React, { useState, useEffect, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Toast from '../components/Toast';
import SkeletonLoader from '../components/SkeletonLoader';
import Dropdown from '../components/Dropdown';
import { 
  Wallet, 
  Search, 
  Plus, 
  Minus, 
  RefreshCw, 
  ChevronLeft, 
  ChevronRight,
  User,
  Ban,
  CheckCircle,
  Clock,
  TrendingUp,
  TrendingDown,
  DollarSign,
  Users,
  X,
  AlertCircle,
  ArrowDownCircle,
  Settings,
  Trash2,
  XCircle,
  Save,
  PlusCircle,
  ToggleLeft,
  ToggleRight,
  FileText,
  ChevronDown,
  ChevronUp
} from 'lucide-react';

interface WalletStats {
  total_balance: number;
  total_wallets: number;
  active_wallets: number;
  today_transactions: number;
  today_credits: number;
  today_debits: number;
  currency: string;
}

interface WalletData {
  id: number;
  user_id: number;
  balance: string;
  currency: string;
  status: string;
  created_at: string;
  updated_at: string;
  user_email: string;
  display_name: string;
  phone: string;
}

interface Transaction {
  id: number;
  wallet_id: number;
  user_id: number;
  type: string;
  amount: string;
  balance_after: string;
  description: string;
  reference_type: string | null;
  reference_id: number | null;
  created_by: number;
  created_at: string;
  user_email?: string;
  display_name?: string;
}

interface UserSearch {
  ID: number;
  user_email: string;
  display_name: string;
  wallet_balance: number;
  wallet_status: string;
}

interface WithdrawalField {
  key: string;
  label: string;
  type: string;
  placeholder: string;
  required: boolean;
}

interface WithdrawalMethod {
  id: string;
  name: string;
  enabled: boolean;
  instructions: string;
  fields: WithdrawalField[];
}

interface WithdrawalRequest {
  id: number;
  user_id: number;
  display_name: string;
  user_email: string;
  phone: string;
  amount: number;
  method_id: string;
  method_name: string;
  method_details: Record<string, string>;
  status: string;
  admin_note: string | null;
  processed_by: number | null;
  processed_at: string | null;
  created_at: string;
}

const Wallets: React.FC = () => {
  const [stats, setStats] = useState<WalletStats | null>(null);
  const [wallets, setWallets] = useState<WalletData[]>([]);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'wallets' | 'transactions' | 'withdrawals' | 'methods'>('wallets');
  
  // Pagination
  const [currentPage, setCurrentPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [perPage] = useState(15);
  
  // Search & Filter
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  
  // Modal states
  const [showAddFundsModal, setShowAddFundsModal] = useState(false);
  const [showDeductFundsModal, setShowDeductFundsModal] = useState(false);
  const [selectedWallet, setSelectedWallet] = useState<WalletData | null>(null);
  const [amount, setAmount] = useState('');
  const [description, setDescription] = useState('');
  const [processing, setProcessing] = useState(false);
  
  // User search for quick add
  const [showUserSearch, setShowUserSearch] = useState(false);
  const [userSearchQuery, setUserSearchQuery] = useState('');
  const [userSearchResults, setUserSearchResults] = useState<UserSearch[]>([]);
  const [searchingUsers, setSearchingUsers] = useState(false);
  
  // Toast
  const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'info' } | null>(null);

  // Withdrawal requests
  const [withdrawalRequests, setWithdrawalRequests] = useState<WithdrawalRequest[]>([]);
  const [withdrawalStatusFilter, setWithdrawalStatusFilter] = useState('');
  
  // Withdrawal methods builder
  const [withdrawalMethods, setWithdrawalMethods] = useState<WithdrawalMethod[]>([]);
  const [methodsLoading, setMethodsLoading] = useState(false);
  const [methodsSaving, setMethodsSaving] = useState(false);
  
  // Action modal for approve/cancel
  const [actionModal, setActionModal] = useState<{ type: 'complete' | 'cancel'; request: WithdrawalRequest } | null>(null);
  const [actionNote, setActionNote] = useState('');
  const [actionProcessing, setActionProcessing] = useState(false);
  
  // Expanded withdrawal details
  const [expandedRequest, setExpandedRequest] = useState<number | null>(null);

  // Dropdown options for status filter
  const statusOptions = [
    { value: '', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'closed', label: 'Closed' },
  ];

  // Fetch statistics
  const fetchStats = useCallback(async () => {
    try {
      const response = await apiFetch<WalletStats>({
        path: '/battle-ledger/v1/wallet/stats',
      });
      setStats(response);
    } catch (error) {
      console.error('Error fetching wallet stats:', error);
      setToast({ message: 'Failed to load wallet statistics', type: 'error' });
    }
  }, []);

  // Fetch wallets
  const fetchWallets = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{ wallets: WalletData[]; total: number }>({
        path: `/battle-ledger/v1/wallets?page=${currentPage}&per_page=${perPage}&search=${searchQuery}&status=${statusFilter}`,
      });
      setWallets(response.wallets);
      setTotalItems(response.total);
    } catch (error) {
      console.error('Error fetching wallets:', error);
      setToast({ message: 'Failed to load wallets', type: 'error' });
    } finally {
      setLoading(false);
    }
  }, [currentPage, perPage, searchQuery, statusFilter]);

  // Fetch transactions
  const fetchTransactions = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{ transactions: Transaction[]; total: number }>({
        path: `/battle-ledger/v1/wallet/transactions?page=${currentPage}&per_page=${perPage}`,
      });
      setTransactions(response.transactions);
      setTotalItems(response.total);
    } catch (error) {
      console.error('Error fetching transactions:', error);
      setToast({ message: 'Failed to load transactions', type: 'error' });
    } finally {
      setLoading(false);
    }
  }, [currentPage, perPage]);

  // Search users
  const searchUsers = useCallback(async (query: string) => {
    if (query.length < 2) {
      setUserSearchResults([]);
      return;
    }
    
    setSearchingUsers(true);
    try {
      const response = await apiFetch<UserSearch[]>({
        path: `/battle-ledger/v1/wallet/search-users?search=${encodeURIComponent(query)}`,
      });
      setUserSearchResults(response);
    } catch (error) {
      console.error('Error searching users:', error);
    } finally {
      setSearchingUsers(false);
    }
  }, []);

  // Fetch withdrawal requests
  const fetchWithdrawalRequests = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<{ requests: WithdrawalRequest[]; total: number }>({
        path: `/battle-ledger/v1/wallet/withdrawal-requests?page=${currentPage}&per_page=${perPage}&status=${withdrawalStatusFilter}`,
      });
      setWithdrawalRequests(response.requests);
      setTotalItems(response.total);
    } catch (error) {
      console.error('Error fetching withdrawal requests:', error);
      setToast({ message: 'Failed to load withdrawal requests', type: 'error' });
    } finally {
      setLoading(false);
    }
  }, [currentPage, perPage, withdrawalStatusFilter]);

  // Fetch withdrawal methods
  const fetchWithdrawalMethods = useCallback(async () => {
    setMethodsLoading(true);
    try {
      const response = await apiFetch<{ methods: WithdrawalMethod[] }>({
        path: '/battle-ledger/v1/wallet/withdrawal-methods',
      });
      setWithdrawalMethods(response.methods);
    } catch (error) {
      console.error('Error fetching withdrawal methods:', error);
      setToast({ message: 'Failed to load withdrawal methods', type: 'error' });
    } finally {
      setMethodsLoading(false);
    }
  }, []);

  // Save withdrawal methods
  const saveWithdrawalMethods = async () => {
    setMethodsSaving(true);
    try {
      const response = await apiFetch<{ success: boolean; methods: WithdrawalMethod[]; message: string }>({
        path: '/battle-ledger/v1/wallet/withdrawal-methods',
        method: 'POST',
        data: { methods: withdrawalMethods },
      });
      setWithdrawalMethods(response.methods);
      setToast({ message: response.message, type: 'success' });
    } catch (error: any) {
      setToast({ message: error?.message || 'Failed to save methods', type: 'error' });
    } finally {
      setMethodsSaving(false);
    }
  };

  // Complete withdrawal
  const handleCompleteWithdrawal = async () => {
    if (!actionModal) return;
    setActionProcessing(true);
    try {
      await apiFetch({
        path: `/battle-ledger/v1/wallet/withdrawal-requests/${actionModal.request.id}/complete`,
        method: 'POST',
        data: { note: actionNote },
      });
      setActionModal(null);
      setActionNote('');
      fetchWithdrawalRequests();
      fetchStats();
      setToast({ message: 'Withdrawal marked as completed', type: 'success' });
    } catch (error: any) {
      setToast({ message: error?.message || 'Failed to complete withdrawal', type: 'error' });
    } finally {
      setActionProcessing(false);
    }
  };

  // Cancel withdrawal
  const handleCancelWithdrawal = async () => {
    if (!actionModal) return;
    setActionProcessing(true);
    try {
      await apiFetch({
        path: `/battle-ledger/v1/wallet/withdrawal-requests/${actionModal.request.id}/cancel`,
        method: 'POST',
        data: { note: actionNote },
      });
      setActionModal(null);
      setActionNote('');
      fetchWithdrawalRequests();
      fetchStats();
      setToast({ message: 'Withdrawal cancelled and funds refunded', type: 'success' });
    } catch (error: any) {
      setToast({ message: error?.message || 'Failed to cancel withdrawal', type: 'error' });
    } finally {
      setActionProcessing(false);
    }
  };

  // Methods builder helpers
  const addMethod = () => {
    setWithdrawalMethods(prev => [...prev, {
      id: `method_${Date.now()}`,
      name: '',
      enabled: true,
      instructions: '',
      fields: [],
    }]);
  };

  const removeMethod = (index: number) => {
    setWithdrawalMethods(prev => prev.filter((_, i) => i !== index));
  };

  const updateMethod = (index: number, updates: Partial<WithdrawalMethod>) => {
    setWithdrawalMethods(prev => prev.map((m, i) => i === index ? { ...m, ...updates } : m));
  };

  const addField = (methodIndex: number) => {
    setWithdrawalMethods(prev => prev.map((m, i) => {
      if (i !== methodIndex) return m;
      return {
        ...m,
        fields: [...m.fields, { key: '', label: '', type: 'text', placeholder: '', required: true }],
      };
    }));
  };

  const removeField = (methodIndex: number, fieldIndex: number) => {
    setWithdrawalMethods(prev => prev.map((m, i) => {
      if (i !== methodIndex) return m;
      return {
        ...m,
        fields: m.fields.filter((_, fi) => fi !== fieldIndex),
      };
    }));
  };

  const updateField = (methodIndex: number, fieldIndex: number, updates: Partial<WithdrawalField>) => {
    setWithdrawalMethods(prev => prev.map((m, i) => {
      if (i !== methodIndex) return m;
      return {
        ...m,
        fields: m.fields.map((f, fi) => fi === fieldIndex ? { ...f, ...updates } : f),
      };
    }));
  };

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  useEffect(() => {
    if (activeTab === 'wallets') {
      fetchWallets();
    } else if (activeTab === 'transactions') {
      fetchTransactions();
    } else if (activeTab === 'withdrawals') {
      fetchWithdrawalRequests();
    } else if (activeTab === 'methods') {
      fetchWithdrawalMethods();
    }
  }, [activeTab, fetchWallets, fetchTransactions, fetchWithdrawalRequests, fetchWithdrawalMethods]);

  useEffect(() => {
    const debounce = setTimeout(() => {
      searchUsers(userSearchQuery);
    }, 300);
    return () => clearTimeout(debounce);
  }, [userSearchQuery, searchUsers]);

  // Credit wallet
  const handleCredit = async () => {
    if (!selectedWallet || !amount) return;
    
    setProcessing(true);
    try {
      await apiFetch({
        path: '/battle-ledger/v1/wallet/credit',
        method: 'POST',
        data: {
          user_id: selectedWallet.user_id,
          amount: parseFloat(amount),
          description: description || 'Admin credit',
        },
      });
      
      setShowAddFundsModal(false);
      setAmount('');
      setDescription('');
      setSelectedWallet(null);
      fetchWallets();
      fetchStats();
      setToast({ message: `Successfully added ${formatCurrency(parseFloat(amount))} to wallet`, type: 'success' });
    } catch (error: any) {
      console.error('Error crediting wallet:', error);
      setToast({ message: error?.message || 'Failed to add funds', type: 'error' });
    } finally {
      setProcessing(false);
    }
  };

  // Debit wallet
  const handleDebit = async () => {
    if (!selectedWallet || !amount) return;
    
    setProcessing(true);
    try {
      await apiFetch({
        path: '/battle-ledger/v1/wallet/debit',
        method: 'POST',
        data: {
          user_id: selectedWallet.user_id,
          amount: parseFloat(amount),
          description: description || 'Admin debit',
        },
      });
      
      setShowDeductFundsModal(false);
      setAmount('');
      setDescription('');
      setSelectedWallet(null);
      fetchWallets();
      fetchStats();
      setToast({ message: `Successfully deducted ${formatCurrency(parseFloat(amount))} from wallet`, type: 'success' });
    } catch (error: any) {
      console.error('Error debiting wallet:', error);
      setToast({ message: error?.message || 'Failed to deduct funds. Check balance.', type: 'error' });
    } finally {
      setProcessing(false);
    }
  };

  // Update wallet status
  const updateWalletStatus = async (userId: number, status: string) => {
    try {
      await apiFetch({
        path: `/battle-ledger/v1/wallet/${userId}/status`,
        method: 'PUT',
        data: { status },
      });
      fetchWallets();
      setToast({ message: `Wallet status updated to ${status}`, type: 'success' });
    } catch (error) {
      console.error('Error updating wallet status:', error);
      setToast({ message: 'Failed to update wallet status', type: 'error' });
    }
  };

  // Quick add funds from user search
  const handleQuickAddFunds = (user: UserSearch) => {
    setSelectedWallet({
      id: 0,
      user_id: user.ID,
      balance: String(user.wallet_balance),
      currency: stats?.currency || 'USD',
      status: user.wallet_status,
      created_at: '',
      updated_at: '',
      user_email: user.user_email,
      display_name: user.display_name,
    });
    setShowUserSearch(false);
    setUserSearchQuery('');
    setUserSearchResults([]);
    setShowAddFundsModal(true);
  };

  const formatCurrency = (value: number | string) => {
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: stats?.currency || 'USD',
    }).format(num);
  };

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getStatusBadge = (status: string) => {
    const badges: Record<string, { className: string; icon: React.ReactNode }> = {
      active: { className: 'bl-badge bl-badge-success', icon: <CheckCircle size={14} /> },
      suspended: { className: 'bl-badge bl-badge-warning', icon: <Clock size={14} /> },
      closed: { className: 'bl-badge bl-badge-danger', icon: <Ban size={14} /> },
    };
    const badge = badges[status] || badges.active;
    return (
      <span className={badge.className}>
        {badge.icon}
        {status}
      </span>
    );
  };

  const getTransactionTypeBadge = (type: string, amount: string) => {
    const isCredit = parseFloat(amount) > 0;
    return (
      <span className={`bl-badge ${isCredit ? 'bl-badge-success' : 'bl-badge-danger'}`}>
        {isCredit ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
        {type.replace('_', ' ')}
      </span>
    );
  };

  const getWithdrawalStatusBadge = (status: string) => {
    const badges: Record<string, { className: string; icon: React.ReactNode; label: string }> = {
      pending: { className: 'bl-badge bl-badge-warning', icon: <Clock size={14} />, label: 'Pending' },
      completed: { className: 'bl-badge bl-badge-success', icon: <CheckCircle size={14} />, label: 'Completed' },
      cancelled: { className: 'bl-badge bl-badge-danger', icon: <XCircle size={14} />, label: 'Cancelled' },
    };
    const badge = badges[status] || badges.pending;
    return (
      <span className={badge.className}>
        {badge.icon}
        {badge.label}
      </span>
    );
  };

  // Dropdown options for withdrawal status filter
  const withdrawalStatusOptions = [
    { value: '', label: 'All Status' },
    { value: 'pending', label: 'Pending' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
  ];

  const totalPages = Math.ceil(totalItems / perPage);

  const closeModal = () => {
    setShowAddFundsModal(false);
    setShowDeductFundsModal(false);
    setShowUserSearch(false);
    setAmount('');
    setDescription('');
    setSelectedWallet(null);
    setUserSearchQuery('');
    setUserSearchResults([]);
    setActionModal(null);
    setActionNote('');
  };

  // Skeleton loader for table rows
  const renderTableSkeleton = (columns: number) => (
    <>
      {[...Array(5)].map((_, i) => (
        <tr key={i}>
          {[...Array(columns)].map((_, j) => (
            <td key={j}>
              <SkeletonLoader height="20px" width={j === 0 ? "180px" : "80px"} />
            </td>
          ))}
        </tr>
      ))}
    </>
  );

  // Skeleton loader for stats
  const renderStatsSkeleton = () => (
    <div className="bl-stats-row">
      {[...Array(4)].map((_, i) => (
        <div className="bl-stat-card" key={i}>
          <SkeletonLoader width="48px" height="48px" borderRadius="12px" />
          <div className="bl-stat-info">
            <SkeletonLoader width="80px" height="12px" />
            <SkeletonLoader width="100px" height="24px" />
          </div>
        </div>
      ))}
    </div>
  );

  return (
    <div className="bl-wallets-page">
      {/* Toast Notification */}
      {toast && (
        <Toast
          message={toast.message}
          type={toast.type}
          onClose={() => setToast(null)}
        />
      )}

      {/* Header */}
      <div className="bl-page-header">
        <div className="bl-title-section">
          <Wallet size={28} className="bl-page-icon" />
          <h1>Wallet Management</h1>
        </div>
        <button 
          className="bl-btn-primary"
          onClick={() => setShowUserSearch(true)}
        >
          <Plus size={18} />
          Add Funds to User
        </button>
      </div>

      {/* Statistics Cards */}
      {!stats ? renderStatsSkeleton() : (
        <div className="bl-stats-row">
          <div className="bl-stat-card">
            <div className="bl-stat-icon blue">
              <DollarSign size={24} />
            </div>
            <div className="bl-stat-info">
              <span className="bl-stat-label">Total Balance</span>
              <span className="bl-stat-value">{formatCurrency(stats.total_balance)}</span>
            </div>
          </div>
          
          <div className="bl-stat-card">
            <div className="bl-stat-icon purple">
              <Users size={24} />
            </div>
            <div className="bl-stat-info">
              <span className="bl-stat-label">Active Wallets</span>
              <span className="bl-stat-value">{stats.active_wallets} / {stats.total_wallets}</span>
            </div>
          </div>
          
          <div className="bl-stat-card">
            <div className="bl-stat-icon green">
              <TrendingUp size={24} />
            </div>
            <div className="bl-stat-info">
              <span className="bl-stat-label">Today's Credits</span>
              <span className="bl-stat-value">{formatCurrency(stats.today_credits)}</span>
            </div>
          </div>
          
          <div className="bl-stat-card">
            <div className="bl-stat-icon red">
              <TrendingDown size={24} />
            </div>
            <div className="bl-stat-info">
              <span className="bl-stat-label">Today's Debits</span>
              <span className="bl-stat-value">{formatCurrency(stats.today_debits)}</span>
            </div>
          </div>
        </div>
      )}

      {/* Tabs */}
      <div className="bl-tabs-bar">
        <button 
          className={`bl-tab-btn ${activeTab === 'wallets' ? 'active' : ''}`}
          onClick={() => { setActiveTab('wallets'); setCurrentPage(1); }}
        >
          <Wallet size={18} />
          Wallets
        </button>
        <button 
          className={`bl-tab-btn ${activeTab === 'transactions' ? 'active' : ''}`}
          onClick={() => { setActiveTab('transactions'); setCurrentPage(1); }}
        >
          <RefreshCw size={18} />
          Transactions
        </button>
        <button 
          className={`bl-tab-btn ${activeTab === 'withdrawals' ? 'active' : ''}`}
          onClick={() => { setActiveTab('withdrawals'); setCurrentPage(1); }}
        >
          <ArrowDownCircle size={18} />
          Withdrawals
        </button>
        <button 
          className={`bl-tab-btn ${activeTab === 'methods' ? 'active' : ''}`}
          onClick={() => { setActiveTab('methods'); setCurrentPage(1); }}
        >
          <Settings size={18} />
          Methods
        </button>
      </div>

      {/* Content */}
      <div className="bl-card">
        {/* Search & Filters */}
        {activeTab === 'wallets' && (
          <div className="bl-toolbar">
            <div className="bl-search-box">
              <Search size={18} />
              <input
                type="text"
                placeholder="Search by email or name..."
                value={searchQuery}
                onChange={(e) => { setSearchQuery(e.target.value); setCurrentPage(1); }}
              />
            </div>
            <Dropdown
              value={statusFilter}
              onChange={(value) => { setStatusFilter(value); setCurrentPage(1); }}
              options={statusOptions}
              placeholder="All Status"
            />
          </div>
        )}

        {/* Wallets Table */}
        {activeTab === 'wallets' && (
          <table className="bl-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? renderTableSkeleton(5) : wallets.length === 0 ? (
                <tr>
                  <td colSpan={5} className="bl-empty-state">
                    <Wallet size={40} />
                    <p>No wallets found</p>
                  </td>
                </tr>
              ) : (
                wallets.map((wallet) => (
                  <tr key={wallet.id}>
                    <td>
                      <div className="bl-user-cell">
                        <div className="bl-avatar">
                          <User size={18} />
                        </div>
                        <div className="bl-user-info">
                          <span className="bl-user-name">{wallet.display_name || 'Unknown'}</span>
                          <span className="bl-user-email">{wallet.user_email}</span>
                        </div>
                      </div>
                    </td>
                    <td>
                      <strong>{formatCurrency(wallet.balance)}</strong>
                    </td>
                    <td>{getStatusBadge(wallet.status)}</td>
                    <td>{formatDate(wallet.created_at)}</td>
                    <td>
                      <div className="bl-table-actions">
                        <button
                          className="bl-btn-icon bl-btn-success"
                          title="Add Funds"
                          onClick={() => {
                            setSelectedWallet(wallet);
                            setShowAddFundsModal(true);
                          }}
                        >
                          <Plus size={16} />
                        </button>
                        <button
                          className="bl-btn-icon bl-btn-danger"
                          title="Deduct Funds"
                          onClick={() => {
                            setSelectedWallet(wallet);
                            setShowDeductFundsModal(true);
                          }}
                        >
                          <Minus size={16} />
                        </button>
                        {wallet.status === 'active' ? (
                          <button
                            className="bl-btn-icon bl-btn-warning"
                            title="Suspend Wallet"
                            onClick={() => updateWalletStatus(wallet.user_id, 'suspended')}
                          >
                            <Ban size={16} />
                          </button>
                        ) : (
                          <button
                            className="bl-btn-icon bl-btn-success"
                            title="Activate Wallet"
                            onClick={() => updateWalletStatus(wallet.user_id, 'active')}
                          >
                            <CheckCircle size={16} />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}

        {/* Transactions Table */}
        {activeTab === 'transactions' && (
          <table className="bl-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Description</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              {loading ? renderTableSkeleton(6) : transactions.length === 0 ? (
                <tr>
                  <td colSpan={6} className="bl-empty-state">
                    <RefreshCw size={40} />
                    <p>No transactions found</p>
                  </td>
                </tr>
              ) : (
                transactions.map((tx) => (
                  <tr key={tx.id}>
                    <td>
                      <div className="bl-user-cell">
                        <div className="bl-avatar">
                          <User size={18} />
                        </div>
                        <div className="bl-user-info">
                          <span className="bl-user-name">{tx.display_name || 'Unknown'}</span>
                          <span className="bl-user-email">{tx.user_email}</span>
                        </div>
                      </div>
                    </td>
                    <td>{getTransactionTypeBadge(tx.type, tx.amount)}</td>
                    <td>
                      <span className={parseFloat(tx.amount) > 0 ? 'bl-text-success' : 'bl-text-danger'}>
                        {parseFloat(tx.amount) > 0 ? '+' : ''}{formatCurrency(tx.amount)}
                      </span>
                    </td>
                    <td>{formatCurrency(tx.balance_after)}</td>
                    <td className="bl-text-truncate">{tx.description}</td>
                    <td>{formatDate(tx.created_at)}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        )}

        {/* Withdrawal Requests Table */}
        {activeTab === 'withdrawals' && (
          <>
            <div className="bl-toolbar">
              <Dropdown
                value={withdrawalStatusFilter}
                onChange={(value) => { setWithdrawalStatusFilter(value); setCurrentPage(1); }}
                options={withdrawalStatusOptions}
                placeholder="All Status"
              />
            </div>
            <table className="bl-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? renderTableSkeleton(6) : withdrawalRequests.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="bl-empty-state">
                      <ArrowDownCircle size={40} />
                      <p>No withdrawal requests found</p>
                    </td>
                  </tr>
                ) : (
                  withdrawalRequests.map((req) => (
                    <React.Fragment key={req.id}>
                      <tr className={expandedRequest === req.id ? 'bl-row-expanded' : ''}>
                        <td>
                          <div className="bl-user-cell">
                            <div className="bl-avatar">
                              <User size={18} />
                            </div>
                            <div className="bl-user-info">
                              <span className="bl-user-name">{req.display_name || 'Unknown'}</span>
                              <span className="bl-user-email">{req.user_email}</span>
                            </div>
                          </div>
                        </td>
                        <td>
                          <strong className="bl-text-danger">-{formatCurrency(req.amount)}</strong>
                        </td>
                        <td>{req.method_name}</td>
                        <td>{getWithdrawalStatusBadge(req.status)}</td>
                        <td>{formatDate(req.created_at)}</td>
                        <td>
                          <div className="bl-table-actions">
                            <button
                              className="bl-btn-icon bl-btn-secondary"
                              title="View Details"
                              onClick={() => setExpandedRequest(expandedRequest === req.id ? null : req.id)}
                            >
                              {expandedRequest === req.id ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                            </button>
                            {req.status === 'pending' && (
                              <>
                                <button
                                  className="bl-btn-icon bl-btn-success"
                                  title="Mark Complete"
                                  onClick={() => { setActionModal({ type: 'complete', request: req }); setActionNote(''); }}
                                >
                                  <CheckCircle size={16} />
                                </button>
                                <button
                                  className="bl-btn-icon bl-btn-danger"
                                  title="Cancel & Refund"
                                  onClick={() => { setActionModal({ type: 'cancel', request: req }); setActionNote(''); }}
                                >
                                  <XCircle size={16} />
                                </button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                      {expandedRequest === req.id && (
                        <tr className="bl-expanded-row">
                          <td colSpan={6}>
                            <div className="bl-withdrawal-details">
                              <div className="bl-detail-grid">
                                <div className="bl-detail-section">
                                  <h4><FileText size={16} /> Payment Details</h4>
                                  {Object.entries(req.method_details).length > 0 ? (
                                    <dl className="bl-detail-list">
                                      {Object.entries(req.method_details).map(([key, value]) => (
                                        <div key={key} className="bl-detail-item">
                                          <dt>{key.replace(/_/g, ' ')}</dt>
                                          <dd>{value}</dd>
                                        </div>
                                      ))}
                                    </dl>
                                  ) : (
                                    <p className="bl-text-muted">No details provided</p>
                                  )}
                                </div>
                                {req.admin_note && (
                                  <div className="bl-detail-section">
                                    <h4><FileText size={16} /> Admin Note</h4>
                                    <p>{req.admin_note}</p>
                                  </div>
                                )}
                                {req.processed_at && (
                                  <div className="bl-detail-section">
                                    <h4><Clock size={16} /> Processed</h4>
                                    <p>{formatDate(req.processed_at)}</p>
                                  </div>
                                )}
                              </div>
                            </div>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  ))
                )}
              </tbody>
            </table>
          </>
        )}

        {/* Withdrawal Methods Builder */}
        {activeTab === 'methods' && (
          <div className="bl-methods-builder">
            <div className="bl-methods-header">
              <div>
                <h3>Withdrawal Methods</h3>
                <p className="bl-text-muted">Configure the payment methods users can choose when withdrawing funds.</p>
              </div>
              <div className="bl-methods-actions">
                <button className="bl-btn-secondary" onClick={addMethod}>
                  <PlusCircle size={18} />
                  Add Method
                </button>
                <button 
                  className="bl-btn-primary" 
                  onClick={saveWithdrawalMethods}
                  disabled={methodsSaving}
                >
                  <Save size={18} />
                  {methodsSaving ? 'Saving...' : 'Save Methods'}
                </button>
              </div>
            </div>

            {methodsLoading ? (
              <div style={{ padding: '2rem' }}>
                {[...Array(2)].map((_, i) => (
                  <div key={i} style={{ marginBottom: '1rem' }}>
                    <SkeletonLoader height="120px" width="100%" borderRadius="8px" />
                  </div>
                ))}
              </div>
            ) : withdrawalMethods.length === 0 ? (
              <div className="bl-empty-state" style={{ padding: '3rem' }}>
                <Settings size={40} />
                <p>No withdrawal methods configured</p>
                <button className="bl-btn-primary" onClick={addMethod} style={{ marginTop: '1rem' }}>
                  <PlusCircle size={18} />
                  Add Your First Method
                </button>
              </div>
            ) : (
              <div className="bl-methods-list">
                {withdrawalMethods.map((method, mIndex) => (
                  <div key={method.id || mIndex} className={`bl-method-card ${!method.enabled ? 'disabled' : ''}`}>
                    <div className="bl-method-card-header">
                      <div className="bl-method-card-title">
                        <button 
                          className="bl-toggle-btn"
                          onClick={() => updateMethod(mIndex, { enabled: !method.enabled })}
                          title={method.enabled ? 'Disable' : 'Enable'}
                        >
                          {method.enabled ? <ToggleRight size={24} className="bl-text-success" /> : <ToggleLeft size={24} className="bl-text-muted" />}
                        </button>
                        <input
                          type="text"
                          className="bl-method-name-input"
                          value={method.name}
                          onChange={(e) => updateMethod(mIndex, { 
                            name: e.target.value,
                            id: method.id.startsWith('method_') ? e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') : method.id,
                          })}
                          placeholder="Method Name (e.g. bKash, PayPal)"
                        />
                      </div>
                      <button 
                        className="bl-btn-icon bl-btn-danger"
                        onClick={() => removeMethod(mIndex)}
                        title="Remove Method"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>

                    <div className="bl-method-card-body">
                      <div className="bl-input-group">
                        <label>Instructions (shown to users)</label>
                        <textarea
                          value={method.instructions}
                          onChange={(e) => updateMethod(mIndex, { instructions: e.target.value })}
                          placeholder="e.g. We will send money within 24 hours."
                          rows={2}
                        />
                      </div>

                      <div className="bl-method-fields">
                        <div className="bl-fields-header">
                          <label>Fields (information to collect from users)</label>
                          <button className="bl-btn-sm bl-btn-secondary" onClick={() => addField(mIndex)}>
                            <Plus size={14} />
                            Add Field
                          </button>
                        </div>

                        {method.fields.length === 0 ? (
                          <p className="bl-text-muted bl-text-sm">No fields added yet. Click "Add Field" to collect user information.</p>
                        ) : (
                          method.fields.map((field, fIndex) => (
                            <div key={fIndex} className="bl-field-row">
                              <input
                                type="text"
                                value={field.label}
                                onChange={(e) => {
                                  const label = e.target.value;
                                  const key = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                                  updateField(mIndex, fIndex, { label, key });
                                }}
                                placeholder="Field Label"
                                className="bl-field-input"
                              />
                              <select
                                value={field.type}
                                onChange={(e) => updateField(mIndex, fIndex, { type: e.target.value })}
                                className="bl-field-select"
                              >
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="email">Email</option>
                                <option value="tel">Phone</option>
                                <option value="textarea">Textarea</option>
                              </select>
                              <input
                                type="text"
                                value={field.placeholder}
                                onChange={(e) => updateField(mIndex, fIndex, { placeholder: e.target.value })}
                                placeholder="Placeholder"
                                className="bl-field-input"
                              />
                              <label className="bl-field-required">
                                <input
                                  type="checkbox"
                                  checked={field.required}
                                  onChange={(e) => updateField(mIndex, fIndex, { required: e.target.checked })}
                                />
                                Required
                              </label>
                              <button 
                                className="bl-btn-icon bl-btn-danger bl-btn-sm"
                                onClick={() => removeField(mIndex, fIndex)}
                                title="Remove Field"
                              >
                                <Trash2 size={14} />
                              </button>
                            </div>
                          ))
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="bl-pagination">
            <button
              className="bl-btn-secondary"
              disabled={currentPage === 1}
              onClick={() => setCurrentPage(currentPage - 1)}
            >
              <ChevronLeft size={18} />
              Previous
            </button>
            <span className="bl-pagination-text">
              Page {currentPage} of {totalPages}
            </span>
            <button
              className="bl-btn-secondary"
              disabled={currentPage === totalPages}
              onClick={() => setCurrentPage(currentPage + 1)}
            >
              Next
              <ChevronRight size={18} />
            </button>
          </div>
        )}
      </div>

      {/* Add Funds Modal */}
      {showAddFundsModal && selectedWallet && (
        <div className="bl-modal-overlay" onClick={closeModal}>
          <div className="bl-modal" onClick={(e) => e.stopPropagation()}>
            <div className="bl-modal-header">
              <h3><Plus size={20} /> Add Funds</h3>
              <button className="bl-modal-close" onClick={closeModal}>
                <X size={20} />
              </button>
            </div>
            <div className="bl-modal-body">
              <div className="bl-selected-user">
                <div className="bl-avatar lg">
                  <User size={24} />
                </div>
                <div className="bl-user-details">
                  <span className="bl-user-name">{selectedWallet.display_name}</span>
                  <span className="bl-user-email">{selectedWallet.user_email}</span>
                  {selectedWallet.phone && <span className="bl-user-phone">{selectedWallet.phone}</span>}
                  <span className="bl-current-balance">
                    Current Balance: <strong>{formatCurrency(selectedWallet.balance)}</strong>
                  </span>
                </div>
              </div>
              
              <div className="bl-input-group">
                <label>Amount ({stats?.currency || 'USD'})</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="Enter amount"
                />
              </div>
              
              <div className="bl-input-group">
                <label>Description (optional)</label>
                <input
                  type="text"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Reason for adding funds"
                />
              </div>
            </div>
            <div className="bl-modal-footer">
              <button className="bl-btn-secondary" onClick={closeModal}>
                Cancel
              </button>
              <button 
                className="bl-btn-primary"
                onClick={handleCredit}
                disabled={!amount || processing}
              >
                {processing ? 'Processing...' : `Add ${amount ? formatCurrency(parseFloat(amount)) : 'Funds'}`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Deduct Funds Modal */}
      {showDeductFundsModal && selectedWallet && (
        <div className="bl-modal-overlay" onClick={closeModal}>
          <div className="bl-modal" onClick={(e) => e.stopPropagation()}>
            <div className="bl-modal-header">
              <h3><Minus size={20} /> Deduct Funds</h3>
              <button className="bl-modal-close" onClick={closeModal}>
                <X size={20} />
              </button>
            </div>
            <div className="bl-modal-body">
              <div className="bl-alert bl-alert-warning">
                <AlertCircle size={18} />
                <span>This will deduct funds from the user's wallet.</span>
              </div>
              
              <div className="bl-selected-user">
                <div className="bl-avatar lg">
                  <User size={24} />
                </div>
                <div className="bl-user-details">
                  <span className="bl-user-name">{selectedWallet.display_name}</span>
                  <span className="bl-user-email">{selectedWallet.user_email}</span>
                  {selectedWallet.phone && <span className="bl-user-phone">{selectedWallet.phone}</span>}
                  <span className="bl-current-balance">
                    Current Balance: <strong>{formatCurrency(selectedWallet.balance)}</strong>
                  </span>
                </div>
              </div>
              
              <div className="bl-input-group">
                <label>Amount ({stats?.currency || 'USD'})</label>
                <input
                  type="number"
                  step="0.01"
                  min="0.01"
                  max={parseFloat(selectedWallet.balance)}
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  placeholder="Enter amount"
                />
              </div>
              
              <div className="bl-input-group">
                <label>Description (optional)</label>
                <input
                  type="text"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  placeholder="Reason for deducting funds"
                />
              </div>
            </div>
            <div className="bl-modal-footer">
              <button className="bl-btn-secondary" onClick={closeModal}>
                Cancel
              </button>
              <button 
                className="bl-btn-danger"
                onClick={handleDebit}
                disabled={!amount || processing}
              >
                {processing ? 'Processing...' : `Deduct ${amount ? formatCurrency(parseFloat(amount)) : 'Funds'}`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* User Search Modal */}
      {showUserSearch && (
        <div className="bl-modal-overlay" onClick={closeModal}>
          <div className="bl-modal bl-modal-search" onClick={(e) => e.stopPropagation()}>
            <div className="bl-modal-header">
              <h3><Search size={20} /> Search User</h3>
              <button className="bl-modal-close" onClick={closeModal}>
                <X size={20} />
              </button>
            </div>
            <div className="bl-modal-body">
              <div className="bl-search-box full-width">
                <Search size={18} />
                <input
                  type="text"
                  placeholder="Search by email, name, or username..."
                  value={userSearchQuery}
                  onChange={(e) => setUserSearchQuery(e.target.value)}
                  autoFocus
                />
              </div>
              
              <div className="bl-search-results">
                {searchingUsers ? (
                  <div className="bl-search-loading">
                    {[...Array(3)].map((_, i) => (
                      <div key={i} className="bl-search-skeleton">
                        <SkeletonLoader width="40px" height="40px" borderRadius="50%" />
                        <div className="bl-skeleton-text">
                          <SkeletonLoader width="150px" height="16px" />
                          <SkeletonLoader width="200px" height="12px" />
                        </div>
                      </div>
                    ))}
                  </div>
                ) : userSearchResults.length === 0 && userSearchQuery.length >= 2 ? (
                  <div className="bl-search-empty">
                    <User size={32} />
                    <p>No users found</p>
                  </div>
                ) : (
                  userSearchResults.map((user) => (
                    <div 
                      key={user.ID} 
                      className="bl-search-result-item"
                      onClick={() => handleQuickAddFunds(user)}
                    >
                      <div className="bl-avatar">
                        <User size={18} />
                      </div>
                      <div className="bl-user-info">
                        <span className="bl-user-name">{user.display_name}</span>
                        <span className="bl-user-email">{user.user_email}</span>
                      </div>
                      <div className="bl-user-wallet">
                        <span className="bl-wallet-balance">{formatCurrency(user.wallet_balance)}</span>
                        {getStatusBadge(user.wallet_status || 'active')}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Withdrawal Action Modal (Complete/Cancel) */}
      {actionModal && (
        <div className="bl-modal-overlay" onClick={() => { setActionModal(null); setActionNote(''); }}>
          <div className="bl-modal" onClick={(e) => e.stopPropagation()}>
            <div className="bl-modal-header">
              <h3>
                {actionModal.type === 'complete' ? (
                  <><CheckCircle size={20} /> Complete Withdrawal</>
                ) : (
                  <><XCircle size={20} /> Cancel Withdrawal</>
                )}
              </h3>
              <button className="bl-modal-close" onClick={() => { setActionModal(null); setActionNote(''); }}>
                <X size={20} />
              </button>
            </div>
            <div className="bl-modal-body">
              {actionModal.type === 'cancel' && (
                <div className="bl-alert bl-alert-warning">
                  <AlertCircle size={18} />
                  <span>This will refund {formatCurrency(actionModal.request.amount)} back to the user's wallet.</span>
                </div>
              )}
              
              {actionModal.type === 'complete' && (
                <div className="bl-alert bl-alert-success">
                  <CheckCircle size={18} />
                  <span>Confirm that you have sent {formatCurrency(actionModal.request.amount)} to the user via {actionModal.request.method_name}.</span>
                </div>
              )}
              
              <div className="bl-selected-user">
                <div className="bl-avatar lg">
                  <User size={24} />
                </div>
                <div className="bl-user-details">
                  <span className="bl-user-name">{actionModal.request.display_name}</span>
                  <span className="bl-user-email">{actionModal.request.user_email}</span>
                  {actionModal.request.phone && <span className="bl-user-phone">{actionModal.request.phone}</span>}
                  <span className="bl-current-balance">
                    Amount: <strong>{formatCurrency(actionModal.request.amount)}</strong> via {actionModal.request.method_name}
                  </span>
                </div>
              </div>

              {Object.entries(actionModal.request.method_details).length > 0 && (
                <div className="bl-withdrawal-details" style={{ marginBottom: '1rem' }}>
                  <h4 style={{ fontSize: '0.875rem', marginBottom: '0.5rem', color: 'var(--bl-text-200)' }}>Payment Details</h4>
                  <dl className="bl-detail-list">
                    {Object.entries(actionModal.request.method_details).map(([key, value]) => (
                      <div key={key} className="bl-detail-item">
                        <dt>{key.replace(/_/g, ' ')}</dt>
                        <dd>{value}</dd>
                      </div>
                    ))}
                  </dl>
                </div>
              )}
              
              <div className="bl-input-group">
                <label>Admin Note (optional)</label>
                <textarea
                  value={actionNote}
                  onChange={(e) => setActionNote(e.target.value)}
                  placeholder={actionModal.type === 'complete' 
                    ? 'e.g. Sent via bKash, transaction ref: ABC123' 
                    : 'e.g. Reason for cancellation'}
                  rows={2}
                />
              </div>
            </div>
            <div className="bl-modal-footer">
              <button className="bl-btn-secondary" onClick={() => { setActionModal(null); setActionNote(''); }}>
                Cancel
              </button>
              <button 
                className={actionModal.type === 'complete' ? 'bl-btn-primary' : 'bl-btn-danger'}
                onClick={actionModal.type === 'complete' ? handleCompleteWithdrawal : handleCancelWithdrawal}
                disabled={actionProcessing}
              >
                {actionProcessing ? 'Processing...' : (
                  actionModal.type === 'complete' ? 'Confirm Complete' : 'Cancel & Refund'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Wallets;
