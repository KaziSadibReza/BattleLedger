/**
 * Deposit Modal with WooCommerce Payment Integration
 * Processes payment directly — no WooCommerce checkout page redirect.
 * Supports all WC gateways: direct (COD, BACS, Stripe) and redirect (PayPal, SSLCommerz, bKash).
 */

import React, { useState, useEffect, useRef, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Modal from './Modal';
import Loader from './Loader';
import { showNotification } from './Notifications';
import {
  DollarSign,
  CreditCard,
  Building2,
  Wallet,
  Shield,
  CheckCircle,
  ExternalLink,
  Clock,
  Loader2,
} from 'lucide-react';

interface DepositModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  currentBalance: number;
  currency: string;
}

interface PaymentGateway {
  id: string;
  title: string;
  description: string;
  enabled: boolean;
  icon?: string;
  supports: string[];
}

type DepositStep = 'form' | 'processing' | 'success' | 'pending' | 'awaiting-external';

const DepositModal: React.FC<DepositModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  currentBalance,
  currency,
}) => {
  const [amount, setAmount] = useState('');
  const [paymentGateways, setPaymentGateways] = useState<PaymentGateway[]>([]);
  const [selectedGateway, setSelectedGateway] = useState<string>('');
  const [loading, setLoading] = useState(false);
  const [gatewaysLoading, setGatewaysLoading] = useState(false);
  const [step, setStep] = useState<DepositStep>('form');
  const [pendingOrderId, setPendingOrderId] = useState<number | null>(null);
  const [resultMessage, setResultMessage] = useState('');
  const [newBalance, setNewBalance] = useState<number | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // Fetch available payment gateways
  useEffect(() => {
    if (isOpen) {
      fetchPaymentGateways();
    }
    return () => stopPolling();
  }, [isOpen]);

  // Visibility change: check order status when user returns to tab
  useEffect(() => {
    const handleVisibility = () => {
      if (document.visibilityState === 'visible' && pendingOrderId && step === 'awaiting-external') {
        checkOrderStatus(pendingOrderId);
      }
    };
    document.addEventListener('visibilitychange', handleVisibility);
    return () => document.removeEventListener('visibilitychange', handleVisibility);
  }, [pendingOrderId, step]);

  const stopPolling = () => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  };

  const startPolling = (orderId: number) => {
    stopPolling();
    pollRef.current = setInterval(() => {
      checkOrderStatus(orderId);
    }, 5000); // Poll every 5 seconds
  };

  const checkOrderStatus = useCallback(async (orderId: number) => {
    try {
      const res = await apiFetch<{
        success: boolean;
        status: string;
        is_paid: boolean;
        new_balance: number;
      }>({
        path: `/battle-ledger/v1/wallet/order-status/${orderId}`,
      });

      if (res.is_paid) {
        stopPolling();
        setNewBalance(res.new_balance);
        setResultMessage('Payment confirmed! Funds added to your wallet.');
        setStep('success');
      }
    } catch (err) {
      // Continue polling silently
    }
  }, []);

  const fetchPaymentGateways = async () => {
    try {
      setGatewaysLoading(true);
      const response = await apiFetch<{ gateways: PaymentGateway[] }>({
        path: '/battle-ledger/v1/wallet/payment-gateways',
      });
      const enabled = response.gateways.filter(g => g.enabled);
      setPaymentGateways(enabled);
      if (enabled.length > 0) {
        setSelectedGateway(enabled[0].id);
      }
    } catch (error) {
      console.error('Error fetching payment gateways:', error);
      showNotification('Failed to load payment methods', 'error');
    } finally {
      setGatewaysLoading(false);
    }
  };

  const handleAmountChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    if (value === '' || /^\d*\.?\d{0,2}$/.test(value)) {
      setAmount(value);
    }
  };

  const quickAmounts = [100, 250, 500, 1000, 2500, 5000];

  const handleDeposit = async () => {
    if (!amount || parseFloat(amount) <= 0) {
      showNotification('Please enter a valid amount', 'warning');
      return;
    }
    if (!selectedGateway) {
      showNotification('Please select a payment method', 'warning');
      return;
    }

    try {
      setLoading(true);
      setStep('processing');

      const response = await apiFetch<{
        success: boolean;
        order_id?: number;
        requires_redirect?: boolean;
        redirect_url?: string;
        gateway_title?: string;
        message?: string;
        new_balance?: number;
        order_status?: string;
        wallet_credited?: boolean;
      }>({
        path: '/battle-ledger/v1/wallet/deposit',
        method: 'POST',
        data: {
          amount: parseFloat(amount),
          payment_method: selectedGateway,
        },
      });

      if (response.success) {
        if (response.requires_redirect && response.redirect_url) {
          // External gateway (PayPal, SSLCommerz, bKash, etc.)
          setPendingOrderId(response.order_id || null);
          setStep('awaiting-external');
          startPolling(response.order_id!);
          window.open(response.redirect_url, '_blank');
        } else if (response.wallet_credited) {
          // Payment confirmed & wallet credited
          setNewBalance(response.new_balance ?? null);
          setResultMessage(response.message || 'Deposit successful! Funds added to your wallet.');
          setStep('success');
          onSuccess(); // Refresh wallet data
        } else {
          // Payment pending (on-hold, awaiting confirmation)
          setResultMessage(response.message || 'Deposit order created. Awaiting payment confirmation.');
          setNewBalance(response.new_balance ?? null);
          setStep('pending');
        }
      } else {
        showNotification(response.message || 'Failed to process deposit', 'error');
        setStep('form');
      }
    } catch (error: any) {
      console.error('Deposit error:', error);
      showNotification(error.message || 'Failed to process deposit', 'error');
      setStep('form');
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    stopPolling();
    setAmount('');
    setSelectedGateway(paymentGateways.length > 0 ? paymentGateways[0].id : '');
    setPendingOrderId(null);
    setResultMessage('');
    setNewBalance(null);
    setStep('form');
    setLoading(false);
    onClose();
  };

  const handleSuccessClose = () => {
    onSuccess();
    handleClose();
  };

  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(value);
  };

  const getPaymentIcon = (gatewayId: string) => {
    switch (gatewayId) {
      case 'bacs':
      case 'cheque':
        return <Building2 size={20} />;
      case 'stripe':
      case 'stripe_cc':
        return <CreditCard size={20} />;
      case 'cod':
        return <Wallet size={20} />;
      default:
        return <CreditCard size={20} />;
    }
  };

  const selectedGatewayTitle = paymentGateways.find(g => g.id === selectedGateway)?.title || '';

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Deposit Funds"
      size="medium"
    >
      <div className="bl-deposit-modal">
        {/* Step 1: Form */}
        {step === 'form' && (
          <>
            {/* Amount Input */}
            <div className="bl-form-group">
              <label>Amount</label>
              <div className="bl-amount-input">
                <span className="bl-currency-symbol">{currency}</span>
                <input
                  type="text"
                  value={amount}
                  onChange={handleAmountChange}
                  placeholder="0.00"
                  autoFocus
                />
              </div>
            </div>

            {/* Quick Amount Buttons */}
            <div className="bl-quick-amounts">
              {quickAmounts.map((value) => (
                <button
                  key={value}
                  className={`bl-quick-amount-btn ${amount === value.toString() ? 'active' : ''}`}
                  onClick={() => setAmount(value.toString())}
                >
                  {formatCurrency(value)}
                </button>
              ))}
            </div>

            {/* Payment Gateway Selection */}
            <div className="bl-form-group">
              <label>Payment Method</label>
              {gatewaysLoading ? (
                <Loader size="small" />
              ) : paymentGateways.length === 0 ? (
                <div className="bl-empty-state-small">
                  <p>No payment methods available</p>
                  <span>Please contact support</span>
                </div>
              ) : (
                <div className="bl-payment-gateways">
                  {paymentGateways.map((gateway) => (
                    <div
                      key={gateway.id}
                      className={`bl-payment-gateway ${selectedGateway === gateway.id ? 'active' : ''}`}
                      onClick={() => setSelectedGateway(gateway.id)}
                    >
                      <div className="bl-gateway-icon">
                        {getPaymentIcon(gateway.id)}
                      </div>
                      <div className="bl-gateway-info">
                        <h4>{gateway.title}</h4>
                        {gateway.description && <p>{gateway.description}</p>}
                      </div>
                      <div className="bl-gateway-radio">
                        <input
                          type="radio"
                          name="payment_gateway"
                          checked={selectedGateway === gateway.id}
                          onChange={() => setSelectedGateway(gateway.id)}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* Summary Preview */}
            {amount && parseFloat(amount) > 0 && (
              <div className="bl-deposit-summary-preview">
                <div className="bl-summary-row">
                  <span>Current Balance</span>
                  <span>{formatCurrency(currentBalance)}</span>
                </div>
                <div className="bl-summary-row">
                  <span>Deposit</span>
                  <span className="bl-text-success">+{formatCurrency(parseFloat(amount))}</span>
                </div>
                <div className="bl-summary-row bl-summary-total">
                  <span>New Balance</span>
                  <strong>{formatCurrency(currentBalance + parseFloat(amount))}</strong>
                </div>
              </div>
            )}

            {/* Security Note */}
            <div className="bl-security-note">
              <Shield size={14} />
              <span>Payments are processed securely via {selectedGatewayTitle || 'your selected gateway'}</span>
            </div>
          </>
        )}

        {/* Step 2: Processing */}
        {step === 'processing' && (
          <div className="bl-deposit-processing-state">
            <div className="bl-processing-spinner">
              <Loader2 size={40} className="spinning" />
            </div>
            <h3>Processing Payment</h3>
            <p>Please wait while we process your {formatCurrency(parseFloat(amount))} deposit via {selectedGatewayTitle}...</p>
          </div>
        )}

        {/* Step 3: Success (wallet credited) */}
        {step === 'success' && (
          <div className="bl-deposit-success-state">
            <div className="bl-success-icon">
              <CheckCircle size={48} />
            </div>
            <h3>Deposit Successful!</h3>
            <p>{resultMessage}</p>
            {newBalance !== null && (
              <div className="bl-new-balance-display">
                <span>Your New Balance</span>
                <strong>{formatCurrency(newBalance)}</strong>
              </div>
            )}
          </div>
        )}

        {/* Step 3b: Pending (order created, awaiting payment confirmation) */}
        {step === 'pending' && (
          <div className="bl-deposit-pending-state">
            <div className="bl-pending-icon">
              <Clock size={48} />
            </div>
            <h3>Payment Pending</h3>
            <p>{resultMessage}</p>
            <div className="bl-pending-note">
              Your wallet will be updated automatically once the payment is confirmed.
            </div>
          </div>
        )}

        {/* Step 4: Awaiting External Payment */}
        {step === 'awaiting-external' && (
          <div className="bl-deposit-awaiting-state">
            <div className="bl-awaiting-icon">
              <ExternalLink size={40} />
            </div>
            <h3>Complete Your Payment</h3>
            <p>
              A new tab has been opened for you to complete payment via <strong>{selectedGatewayTitle}</strong>.
            </p>
            <div className="bl-awaiting-status">
              <Clock size={16} className="spinning" />
              <span>Waiting for payment confirmation...</span>
            </div>
            <p className="bl-awaiting-hint">
              Once you complete payment in the other tab, this will update automatically.
            </p>
          </div>
        )}
      </div>

      {/* Modal Footer */}
      <div className="bl-modal-footer">
        {step === 'form' && (
          <>
            <button className="bl-btn-secondary" onClick={handleClose}>
              Cancel
            </button>
            <button
              className="bl-btn-primary"
              onClick={handleDeposit}
              disabled={!amount || parseFloat(amount) <= 0 || !selectedGateway || loading}
            >
              <DollarSign size={16} />
              Deposit {amount && parseFloat(amount) > 0 ? formatCurrency(parseFloat(amount)) : ''}
            </button>
          </>
        )}
        {step === 'success' && (
          <button className="bl-btn-primary" onClick={handleSuccessClose} style={{ width: '100%' }}>
            Done
          </button>
        )}
        {step === 'pending' && (
          <button className="bl-btn-secondary" onClick={handleClose} style={{ width: '100%' }}>
            OK, Got It
          </button>
        )}
        {step === 'awaiting-external' && (
          <>
            <button className="bl-btn-secondary" onClick={() => {
              if (pendingOrderId) checkOrderStatus(pendingOrderId);
            }}>
              Check Status
            </button>
            <button className="bl-btn-secondary" onClick={handleClose}>
              Close
            </button>
          </>
        )}
      </div>
    </Modal>
  );
};

export default DepositModal;
