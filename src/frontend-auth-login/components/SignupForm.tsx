import React, { useState } from 'react';
import { Mail, Lock, Eye, EyeOff, User, ArrowRight, Check, X } from 'lucide-react';
import OTPInput from './OTPInput';

interface FormSettings {
  colors?: Record<string, string>;
  typography?: Record<string, string>;
  labels?: {
    signUpTitle?: string;
    signUpSubtitle?: string;
    emailLabel?: string;
    passwordLabel?: string;
    signUpButtonText?: string;
  };
  features?: {
    showLogo?: boolean;
    logoUrl?: string;
    showSocialLogin?: boolean;
  };
}

interface SignupFormProps {
  apiUrl: string;
  nonce: string;
  googleEnabled: boolean;
  onSuccess: (user: any) => void;
  onError: (message: string) => void;
  onSwitchToLogin?: () => void;
  onGoogleLogin?: () => void;
  formSettings?: FormSettings;
}

type SignupStep = 'email' | 'verify' | 'details';

interface PasswordValidation {
  minLength: boolean;
  hasUppercase: boolean;
  hasLowercase: boolean;
  hasNumber: boolean;
}

export const SignupForm: React.FC<SignupFormProps> = ({
  apiUrl,
  nonce,
  googleEnabled,
  onSuccess,
  onError,
  onSwitchToLogin,
  onGoogleLogin,
  formSettings,
}) => {
  // Get settings with defaults
  const labels = formSettings?.labels || {};
  const features = formSettings?.features || {};
  
  const signUpTitle = labels.signUpTitle || 'Create an account';
  const signUpSubtitle = labels.signUpSubtitle || 'Get started with BattleLedger';
  const emailLabel = labels.emailLabel || 'Email';
  const signUpButtonText = labels.signUpButtonText || 'Create Account';
  
  const showSocialLogin = features.showSocialLogin !== false && googleEnabled;
  const showLogo = features.showLogo && features.logoUrl;

  const [step, setStep] = useState<SignupStep>('email');
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [acceptTerms, setAcceptTerms] = useState(false);

  const passwordValidation: PasswordValidation = {
    minLength: password.length >= 8,
    hasUppercase: /[A-Z]/.test(password),
    hasLowercase: /[a-z]/.test(password),
    hasNumber: /[0-9]/.test(password),
  };

  const isPasswordValid = Object.values(passwordValidation).every(Boolean);
  const passwordsMatch = password === confirmPassword && confirmPassword.length > 0;

  const handleSendOTP = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!email) {
      onError('Please enter your email');
      return;
    }

    setLoading(true);
    try {
      // Check if email exists first
      const checkResponse = await fetch(`${apiUrl}/check-email`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        credentials: 'include',
        body: JSON.stringify({ email }),
      });

      const checkData = await checkResponse.json();

      if (checkData.exists) {
        onError('An account with this email already exists. Please sign in instead.');
        setLoading(false);
        return;
      }

      // Send OTP
      const response = await fetch(`${apiUrl}/otp/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        credentials: 'include',
        body: JSON.stringify({ email, type: 'verification' }),
      });

      const data = await response.json();

      if (data.success) {
        setStep('verify');
      } else {
        onError(data.message || 'Failed to send verification code');
      }
    } catch (error) {
      onError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOTP = async (otpValue: string) => {
    if (otpValue.length < 6) return;
    setOtp(otpValue);

    setLoading(true);
    try {
      const response = await fetch(`${apiUrl}/otp/verify`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        credentials: 'include',
        body: JSON.stringify({ email, otp: otpValue }),
      });

      const data = await response.json();

      if (data.success) {
        setStep('details');
      } else {
        onError(data.message || 'Invalid verification code');
      }
    } catch (error) {
      onError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!isPasswordValid) {
      onError('Please meet all password requirements');
      return;
    }

    if (!passwordsMatch) {
      onError('Passwords do not match');
      return;
    }

    if (!acceptTerms) {
      onError('Please accept the terms and conditions');
      return;
    }

    setLoading(true);
    try {
      // Need to send OTP again for registration verification
      const otpResponse = await fetch(`${apiUrl}/otp/send`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        credentials: 'include',
        body: JSON.stringify({ email, type: 'verification' }),
      });

      const otpData = await otpResponse.json();
      if (!otpData.success) {
        // Use stored OTP for registration
      }

      const response = await fetch(`${apiUrl}/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        credentials: 'include',
        body: JSON.stringify({
          email,
          otp,
          password,
          display_name: displayName,
        }),
      });

      const data = await response.json();

      if (data.success) {
        onSuccess(data.user);
      } else {
        onError(data.message || 'Registration failed');
      }
    } catch (error) {
      onError('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleLogin = async () => {
    if (!googleEnabled) return;

    try {
      const response = await fetch(`${apiUrl}/google/url`, {
        credentials: 'include',
      });
      const data = await response.json();

      if (data.success && data.url) {
        window.location.href = data.url;
      } else {
        onError('Google signup is not available');
      }
    } catch (error) {
      onError('Failed to initiate Google signup');
    }
  };

  // Step 2: Verify OTP
  if (step === 'verify') {
    return (
      <div className="ak-auth-form">
        <div className="ak-form-header">
          <h2>Verify your email</h2>
          <p>Enter the 6-digit code sent to <strong>{email}</strong></p>
        </div>

        <OTPInput
          length={6}
          onComplete={handleVerifyOTP}
          disabled={loading}
          autoFocus
        />
        
        {loading && (
          <div className="ak-submit-btn" style={{ pointerEvents: 'none' }}>
            <span className="ak-spinner" />
            <span>Verifying...</span>
          </div>
        )}

        <div className="ak-resend-otp">
          <span>Didn't receive the code?</span>
          <button
            type="button"
            onClick={handleSendOTP}
            disabled={loading}
          >
            Resend
          </button>
        </div>

        <div className="ak-form-footer">
          <p>
            <button type="button" onClick={() => setStep('email')}>
              Use a different email
            </button>
          </p>
        </div>
      </div>
    );
  }

  // Step 3: Account details
  if (step === 'details') {
    return (
      <div className="ak-auth-form">
        <div className="ak-form-header">
          <h2>Create your account</h2>
          <p>Set up your password for <strong>{email}</strong></p>
        </div>

        <form onSubmit={handleRegister}>
          <div className="ak-form-group">
            <label className="ak-form-label" htmlFor="signup-name">Display Name (optional)</label>
            <div className="ak-input-wrapper">
              <span className="ak-input-icon"><User size={18} /></span>
              <input
                type="text"
                id="signup-name"
                className="ak-form-input"
                value={displayName}
                onChange={(e) => setDisplayName(e.target.value)}
                placeholder="John Doe"
                autoComplete="name"
              />
            </div>
          </div>

          <div className="ak-form-group">
            <label className="ak-form-label" htmlFor="signup-password">Password</label>
            <div className="ak-input-wrapper ak-password-wrapper">
              <span className="ak-input-icon"><Lock size={18} /></span>
              <input
                type={showPassword ? 'text' : 'password'}
                id="signup-password"
                className="ak-form-input"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                autoComplete="new-password"
                required
              />
              <button
                type="button"
                className="ak-password-toggle"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            
            {/* Password requirements */}
            <div className="ak-password-requirements">
              <div className={`requirement ${passwordValidation.minLength ? 'valid' : ''}`}>
                {passwordValidation.minLength ? <Check size={14} /> : <X size={14} />}
                At least 8 characters
              </div>
              <div className={`requirement ${passwordValidation.hasUppercase ? 'valid' : ''}`}>
                {passwordValidation.hasUppercase ? <Check size={14} /> : <X size={14} />}
                One uppercase letter
              </div>
              <div className={`requirement ${passwordValidation.hasLowercase ? 'valid' : ''}`}>
                {passwordValidation.hasLowercase ? <Check size={14} /> : <X size={14} />}
                One lowercase letter
              </div>
              <div className={`requirement ${passwordValidation.hasNumber ? 'valid' : ''}`}>
                {passwordValidation.hasNumber ? <Check size={14} /> : <X size={14} />}
                One number
              </div>
            </div>
          </div>

          <div className="ak-form-group">
            <label className="ak-form-label" htmlFor="signup-confirm-password">Confirm Password</label>
            <div className="ak-input-wrapper ak-password-wrapper">
              <span className="ak-input-icon"><Lock size={18} /></span>
              <input
                type={showConfirmPassword ? 'text' : 'password'}
                id="signup-confirm-password"
                className="ak-form-input"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder="••••••••"
                autoComplete="new-password"
                required
              />
              <button
                type="button"
                className="ak-password-toggle"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              >
                {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            {confirmPassword && !passwordsMatch && (
              <span className="ak-form-error">Passwords do not match</span>
            )}
          </div>

          <div className="ak-terms-check">
            <input
              type="checkbox"
              id="accept-terms"
              checked={acceptTerms}
              onChange={(e) => setAcceptTerms(e.target.checked)}
              required
            />
            <label htmlFor="accept-terms">
              I agree to the <a href="/terms" target="_blank">Terms of Service</a> and{' '}
              <a href="/privacy" target="_blank">Privacy Policy</a>
            </label>
          </div>

          <button
            type="submit"
            className="ak-submit-btn"
            disabled={loading || !isPasswordValid || !passwordsMatch || !acceptTerms}
          >
            {loading ? (
              <span className="ak-spinner" />
            ) : (
              <>
                {signUpButtonText}
                <ArrowRight size={18} />
              </>
            )}
          </button>
        </form>
      </div>
    );
  }

  // Step 1: Email entry
  return (
    <div className="ak-auth-form">
      {/* Logo */}
      {showLogo && (
        <div className="ak-form-logo">
          <img src={features.logoUrl} alt="Logo" />
        </div>
      )}
      
      <div className="ak-form-header">
        <h2>{signUpTitle}</h2>
        <p>{signUpSubtitle}</p>
      </div>

      {/* Google Signup Button */}
      {showSocialLogin && (
        <>
          <button
            type="button"
            className="ak-social-btn google"
            onClick={onGoogleLogin || handleGoogleLogin}
          >
            <svg viewBox="0 0 24 24" width="20" height="20">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
          </button>

          <div className="ak-divider">
            <span>or</span>
          </div>
        </>
      )}

      <form onSubmit={handleSendOTP}>
        <div className="ak-form-group">
          <label className="ak-form-label" htmlFor="signup-email">{emailLabel}</label>
          <div className="ak-input-wrapper">
            <span className="ak-input-icon"><Mail size={18} /></span>
            <input
              type="email"
              id="signup-email"
              className="ak-form-input"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              autoComplete="email"
              required
            />
          </div>
        </div>

        <button
          type="submit"
          className="ak-submit-btn"
          disabled={loading || !email}
        >
          {loading ? (
            <span className="ak-spinner" />
          ) : (
            <>
              Continue
              <ArrowRight size={18} />
            </>
          )}
        </button>
      </form>

      {onSwitchToLogin && (
        <div className="ak-form-footer">
          <p>
            Already have an account?{' '}
            <button type="button" onClick={onSwitchToLogin}>
              Sign In
            </button>
          </p>
        </div>
      )}
    </div>
  );
};

export default SignupForm;
