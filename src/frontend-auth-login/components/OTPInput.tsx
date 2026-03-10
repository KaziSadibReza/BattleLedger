import React, { forwardRef } from 'react';
import { useOTP } from '../hooks/useOTP';

interface OTPInputProps {
  length?: number;
  value?: string;
  onChange?: (value: string) => void;
  onComplete?: (value: string) => void;
  disabled?: boolean;
  error?: boolean;
  autoFocus?: boolean;
}

export const OTPInput = forwardRef<HTMLDivElement, OTPInputProps>(({
  length = 6,
  value,
  onChange,
  onComplete,
  disabled = false,
  error = false,
  autoFocus = true,
}, ref) => {
  const {
    otp,
    handleChange,
    handleKeyDown,
    handlePaste,
    getOtpValue,
    isComplete,
    inputRefs,
  } = useOTP(length);

  // Sync external value
  React.useEffect(() => {
    if (value !== undefined && value !== getOtpValue()) {
      const digits = value.split('').slice(0, length);
      const newOtp = Array(length).fill('');
      digits.forEach((d, i) => { newOtp[i] = d; });
      // Note: We can't call setOtp here directly, so we handle controlled mode differently
    }
  }, [value]);

  // Notify parent of changes
  React.useEffect(() => {
    const currentValue = getOtpValue();
    onChange?.(currentValue);
    
    if (isComplete) {
      onComplete?.(currentValue);
    }
  }, [otp]);

  // Auto focus first input
  React.useEffect(() => {
    if (autoFocus && !disabled) {
      inputRefs.current[0]?.focus();
    }
  }, [autoFocus, disabled]);

  return (
    <div 
      ref={ref}
      className={`ak-otp-container ${error ? 'error' : ''} ${disabled ? 'disabled' : ''}`}
      onPaste={handlePaste}
    >
      {otp.map((digit, index) => (
        <input
          key={index}
          ref={el => { inputRefs.current[index] = el; }}
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          maxLength={1}
          value={digit}
          onChange={(e) => handleChange(index, e.target.value)}
          onKeyDown={(e) => handleKeyDown(index, e)}
          disabled={disabled}
          className="ak-otp-input"
          autoComplete="one-time-code"
          aria-label={`Digit ${index + 1} of ${length}`}
        />
      ))}
    </div>
  );
});

OTPInput.displayName = 'OTPInput';

export default OTPInput;
