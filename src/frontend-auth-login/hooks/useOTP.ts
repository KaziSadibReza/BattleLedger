import React, { useState, useCallback } from 'react';

interface UseOTPReturn {
  otp: string[];
  setOtp: React.Dispatch<React.SetStateAction<string[]>>;
  handleChange: (index: number, value: string) => void;
  handleKeyDown: (index: number, e: React.KeyboardEvent<HTMLInputElement>) => void;
  handlePaste: (e: React.ClipboardEvent) => void;
  getOtpValue: () => string;
  clearOtp: () => void;
  isComplete: boolean;
  inputRefs: React.MutableRefObject<(HTMLInputElement | null)[]>;
}

export const useOTP = (length: number = 6): UseOTPReturn => {
  const [otp, setOtp] = useState<string[]>(Array(length).fill(''));
  const inputRefs = React.useRef<(HTMLInputElement | null)[]>([]);

  const handleChange = useCallback((index: number, value: string) => {
    // Only allow digits
    const digit = value.replace(/\D/g, '').slice(-1);
    
    setOtp(prev => {
      const newOtp = [...prev];
      newOtp[index] = digit;
      return newOtp;
    });

    // Auto-focus next input
    if (digit && index < length - 1) {
      inputRefs.current[index + 1]?.focus();
    }
  }, [length]);

  const handleKeyDown = useCallback((index: number, e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace') {
      if (!otp[index] && index > 0) {
        // Move to previous input if current is empty
        inputRefs.current[index - 1]?.focus();
        setOtp(prev => {
          const newOtp = [...prev];
          newOtp[index - 1] = '';
          return newOtp;
        });
      } else {
        // Clear current input
        setOtp(prev => {
          const newOtp = [...prev];
          newOtp[index] = '';
          return newOtp;
        });
      }
      e.preventDefault();
    } else if (e.key === 'ArrowLeft' && index > 0) {
      inputRefs.current[index - 1]?.focus();
    } else if (e.key === 'ArrowRight' && index < length - 1) {
      inputRefs.current[index + 1]?.focus();
    }
  }, [otp, length]);

  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, length);
    
    if (pastedData) {
      const newOtp = Array(length).fill('');
      pastedData.split('').forEach((digit, i) => {
        newOtp[i] = digit;
      });
      setOtp(newOtp);
      
      // Focus the next empty input or the last one
      const nextEmptyIndex = newOtp.findIndex(d => !d);
      if (nextEmptyIndex !== -1) {
        inputRefs.current[nextEmptyIndex]?.focus();
      } else {
        inputRefs.current[length - 1]?.focus();
      }
    }
  }, [length]);

  const getOtpValue = useCallback(() => otp.join(''), [otp]);

  const clearOtp = useCallback(() => {
    setOtp(Array(length).fill(''));
    inputRefs.current[0]?.focus();
  }, [length]);

  const isComplete = otp.every(digit => digit !== '');

  return {
    otp,
    setOtp,
    handleChange,
    handleKeyDown,
    handlePaste,
    getOtpValue,
    clearOtp,
    isComplete,
    inputRefs,
  };
};

export default useOTP;
