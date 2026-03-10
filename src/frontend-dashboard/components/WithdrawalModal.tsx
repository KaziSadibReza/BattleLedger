/**
 * Withdrawal Modal Component
 * Dynamically loads withdrawal methods configured by the admin.
 */

import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import Modal from './Modal';
import Dropdown from './Dropdown';
import { showNotification } from './Notifications';
import { AlertCircle, ArrowRight, Loader } from 'lucide-react';

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

interface WithdrawalModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  currentBalance: number;
  currency: string;
}

const WithdrawalModal: React.FC<WithdrawalModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  currentBalance,
  currency,
}) => {
  const [amount, setAmount] = useState('');
  const [selectedMethodId, setSelectedMethodId] = useState('');
  const [details, setDetails] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  
  // Methods from API
  const [methods, setMethods] = useState<WithdrawalMethod[]>([]);
  const [methodsLoading, setMethodsLoading] = useState(false);

  // Load methods when modal opens
  useEffect(() => {
    if (isOpen) {
      fetchMethods();
    }
  }, [isOpen]);

  const fetchMethods = async () => {
    setMethodsLoading(true);
    try {
      const response = await apiFetch<{ methods: WithdrawalMethod[] }>({
        path: '/battle-ledger/v1/wallet/withdrawal-methods',
      });
      setMethods(response.methods);
      // Auto-select first method
      if (response.methods.length > 0 && !selectedMethodId) {
        setSelectedMethodId(response.methods[0].id);
      }
    } catch (error) {
      console.error('Error loading withdrawal methods:', error);
      showNotification('Failed to load withdrawal methods', 'error');
    } finally {
      setMethodsLoading(false);
    }
  };

  const selectedMethod = methods.find(m => m.id === selectedMethodId);

  const handleAmountChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    if (value === '' || /^\d*\.?\d{0,2}$/.test(value)) {
      setAmount(value);
    }
  };

  const handleDetailChange = (key: string, value: string) => {
    setDetails(prev => ({ ...prev, [key]: value }));
  };

  const handleMethodChange = (methodId: string) => {
    setSelectedMethodId(methodId);
    setDetails({}); // Reset details when method changes
  };

  const handleWithdraw = async () => {
    const withdrawAmount = parseFloat(amount);

    if (!amount || withdrawAmount <= 0) {
      showNotification('Please enter a valid amount', 'warning');
      return;
    }

    if (withdrawAmount > currentBalance) {
      showNotification('Insufficient balance', 'error');
      return;
    }

    if (!selectedMethod) {
      showNotification('Please select a withdrawal method', 'warning');
      return;
    }

    // Validate required fields
    for (const field of selectedMethod.fields) {
      if (field.required && !details[field.key]?.trim()) {
        showNotification(`Please fill in "${field.label}"`, 'warning');
        return;
      }
    }

    try {
      setLoading(true);
      
      const response = await apiFetch<{
        success: boolean;
        message?: string;
      }>({
        path: '/battle-ledger/v1/wallet/withdraw',
        method: 'POST',
        data: {
          amount: withdrawAmount,
          method: selectedMethodId,
          details: details,
        },
      });

      if (response.success) {
        showNotification(
          response.message || 'Withdrawal request submitted successfully!',
          'success'
        );
        onSuccess();
        handleClose();
      } else {
        showNotification(response.message || 'Failed to process withdrawal', 'error');
      }
    } catch (error: any) {
      console.error('Withdrawal error:', error);
      showNotification(
        error.message || 'Failed to process withdrawal',
        'error'
      );
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setAmount('');
    setDetails({});
    setSelectedMethodId(methods.length > 0 ? methods[0].id : '');
    onClose();
  };

  const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
    }).format(value);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
      title="Withdraw Funds"
      size="medium"
    >
      <div className="bl-withdrawal-modal">
        {/* Current Balance */}
        <div className="bl-balance-info">
          <span className="bl-balance-label">Available Balance</span>
          <span className="bl-balance-amount">{formatCurrency(currentBalance)}</span>
        </div>

        {methodsLoading ? (
          <div className="bl-methods-loading">
            <Loader size={24} className="bl-spinner" />
            <span>Loading withdrawal methods...</span>
          </div>
        ) : methods.length === 0 ? (
          <div className="bl-warning-box">
            <AlertCircle size={20} />
            <div>
              <strong>No Methods Available</strong>
              <p>No withdrawal methods are currently configured. Please contact the administrator.</p>
            </div>
          </div>
        ) : (
          <>
            {/* Amount Input */}
            <div className="bl-form-group">
              <label>Withdrawal Amount</label>
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
              {amount && parseFloat(amount) > currentBalance && (
                <span className="bl-input-error">
                  Amount exceeds available balance
                </span>
              )}
            </div>

            {/* Withdrawal Method */}
            <div className="bl-form-group">
              <label>Withdrawal Method</label>
              <Dropdown
                value={selectedMethodId}
                onChange={handleMethodChange}
                options={methods.map(m => ({ value: m.id, label: m.name }))}
                placeholder="Select method"
              />
            </div>

            {/* Method Instructions */}
            {selectedMethod?.instructions && (
              <div className="bl-method-instructions">
                <AlertCircle size={16} />
                <span>{selectedMethod.instructions}</span>
              </div>
            )}

            {/* Dynamic Fields */}
            {selectedMethod && selectedMethod.fields.length > 0 && (
              <div className="bl-bank-details">
                <h4>{selectedMethod.name} Details</h4>
                {selectedMethod.fields.map((field) => (
                  <div key={field.key} className="bl-form-group">
                    <label>
                      {field.label}
                      {field.required && <span className="bl-required">*</span>}
                    </label>
                    {field.type === 'textarea' ? (
                      <textarea
                        className="bl-input"
                        value={details[field.key] || ''}
                        onChange={(e) => handleDetailChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                        rows={3}
                      />
                    ) : field.type === 'tel' ? (
                      <div className="bl-phone-input-group">
                        <input
                          type="text"
                          className="bl-input bl-country-code"
                          value={details[`${field.key}_code`] || '+880'}
                          onChange={(e) => handleDetailChange(`${field.key}_code`, e.target.value)}
                          placeholder="+880"
                        />
                        <input
                          type="tel"
                          className="bl-input bl-phone-number"
                          value={details[field.key] || ''}
                          onChange={(e) => handleDetailChange(field.key, e.target.value)}
                          placeholder={field.placeholder}
                        />
                      </div>
                    ) : (
                      <input
                        type={field.type || 'text'}
                        className="bl-input"
                        value={details[field.key] || ''}
                        onChange={(e) => handleDetailChange(field.key, e.target.value)}
                        placeholder={field.placeholder}
                      />
                    )}
                  </div>
                ))}
              </div>
            )}

            {/* Warning */}
            <div className="bl-warning-box">
              <AlertCircle size={20} />
              <div>
                <strong>Important</strong>
                <p>The withdrawal amount will be deducted from your wallet immediately. You will be notified once processed.</p>
              </div>
            </div>
          </>
        )}
      </div>

      {/* Modal Footer */}
      <div className="bl-modal-footer">
        <button className="bl-btn-secondary" onClick={handleClose}>
          Cancel
        </button>
        <button
          className="bl-btn-primary"
          onClick={handleWithdraw}
          disabled={
            loading ||
            methodsLoading ||
            methods.length === 0 ||
            !amount ||
            parseFloat(amount) <= 0 ||
            parseFloat(amount) > currentBalance
          }
        >
          {loading ? 'Processing...' : (
            <>
              Request Withdrawal <ArrowRight size={16} />
            </>
          )}
        </button>
      </div>
    </Modal>
  );
};

export default WithdrawalModal;
