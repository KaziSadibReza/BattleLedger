/**
 * My Profile Page — Frontend Dashboard
 *
 * Editable fields: display name, first/last name, phone, bio, avatar.
 * Email change requires two-step OTP (current → new).
 * Username is read-only.
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import apiFetch from '@wordpress/api-fetch';
import {
  Camera, Trash2, Save, Mail, Phone, User, FileText,
  Shield, Loader2, CheckCircle, Send, ArrowRight, X,
} from 'lucide-react';
import Skeleton from '../components/Skeleton';
import { showNotification } from '../components/Notifications';

/* ── Types ──────────────────────────────────────────────────── */

interface ProfileData {
  id: number;
  username: string;
  displayName: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  avatar: string;
  bio: string;
  dateRegistered: string;
}

interface ProfileProps {
  currentUser: { id: number; email: string; displayName: string; avatar: string };
}

/* ── Email change wizard steps ──────────────────────────────── */
type EmailStep = 'idle' | 'send-old' | 'verify-old' | 'enter-new' | 'verify-new' | 'done';

/* ── Component ──────────────────────────────────────────────── */

const Profile: React.FC<ProfileProps> = () => {
  const [profile, setProfile] = useState<ProfileData | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const mountedRef = useRef(true);

  // Editable fields
  const [displayName, setDisplayName] = useState('');
  const [phone, setPhone] = useState('');
  const [bio, setBio] = useState('');

  // Avatar
  const [avatarPreview, setAvatarPreview] = useState('');
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [deletingAvatar, setDeletingAvatar] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  // Email change
  const [emailStep, setEmailStep] = useState<EmailStep>('idle');
  const [newEmail, setNewEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [emailBusy, setEmailBusy] = useState(false);

  useEffect(() => { return () => { mountedRef.current = false; }; }, []);

  /* ── Fetch profile ─────────────────────────────────────── */

  const fetchProfile = useCallback(async () => {
    try {
      setLoading(true);
      const data = await apiFetch<ProfileData>({ path: '/battle-ledger/v1/user/profile' });
      if (!mountedRef.current) return;
      setProfile(data);
      setDisplayName(data.displayName);
      setPhone(data.phone);
      setBio(data.bio);
      setAvatarPreview(data.avatar);
    } catch {
      showNotification('Failed to load profile.', 'error');
    } finally {
      if (mountedRef.current) setLoading(false);
    }
  }, []);

  useEffect(() => { fetchProfile(); }, [fetchProfile]);

  /* ── Save profile ──────────────────────────────────────── */

  const handleSave = async () => {
    setSaving(true);
    try {
      const data = await apiFetch<ProfileData>({
        path: '/battle-ledger/v1/user/profile',
        method: 'POST',
        data: { displayName, phone, bio },
      });
      if (!mountedRef.current) return;
      setProfile(data);
      showNotification('Profile updated!', 'success');
    } catch {
      showNotification('Failed to save profile.', 'error');
    } finally {
      if (mountedRef.current) setSaving(false);
    }
  };

  /* ── Avatar upload ─────────────────────────────────────── */

  const handleAvatarChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Client-side validation
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
      showNotification('Only JPEG, PNG, WebP, and GIF files allowed.', 'error');
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      showNotification('Image must be under 2 MB.', 'error');
      return;
    }

    setUploadingAvatar(true);
    const formData = new FormData();
    formData.append('avatar', file);

    try {
      const data = await apiFetch<ProfileData>({
        path: '/battle-ledger/v1/user/avatar',
        method: 'POST',
        body: formData,
      });
      if (!mountedRef.current) return;
      setProfile(data);
      setAvatarPreview(data.avatar);
      showNotification('Photo updated!', 'success');
    } catch {
      showNotification('Failed to upload photo.', 'error');
    } finally {
      if (mountedRef.current) setUploadingAvatar(false);
      // Reset file input
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const handleDeleteAvatar = async () => {
    setDeletingAvatar(true);
    try {
      const data = await apiFetch<ProfileData>({
        path: '/battle-ledger/v1/user/avatar',
        method: 'DELETE',
      });
      if (!mountedRef.current) return;
      setProfile(data);
      setAvatarPreview(data.avatar);
      showNotification('Photo removed.', 'success');
    } catch {
      showNotification('Failed to remove photo.', 'error');
    } finally {
      if (mountedRef.current) setDeletingAvatar(false);
    }
  };

  /* ── Email change flow ─────────────────────────────────── */

  const sendOldOtp = async () => {
    setEmailBusy(true);
    try {
      const res = await apiFetch<{ success: boolean; error?: string }>({
        path: '/battle-ledger/v1/user/email/send-old-otp',
        method: 'POST',
      });
      if (!mountedRef.current) return;
      if (res.success) {
        setEmailStep('verify-old');
        showNotification('Verification code sent to your current email.', 'info');
      } else {
        showNotification(res.error || 'Failed to send code.', 'error');
      }
    } catch {
      showNotification('Failed to send verification code.', 'error');
    } finally {
      if (mountedRef.current) setEmailBusy(false);
    }
  };

  const verifyOldOtp = async () => {
    if (!otp.trim()) return;
    setEmailBusy(true);
    try {
      const res = await apiFetch<{ success: boolean; error?: string }>({
        path: '/battle-ledger/v1/user/email/verify-old-otp',
        method: 'POST',
        data: { otp: otp.trim() },
      });
      if (!mountedRef.current) return;
      if (res.success) {
        setOtp('');
        setEmailStep('enter-new');
        showNotification('Current email verified!', 'success');
      } else {
        showNotification(res.error || 'Invalid code.', 'error');
      }
    } catch {
      showNotification('Verification failed.', 'error');
    } finally {
      if (mountedRef.current) setEmailBusy(false);
    }
  };

  const sendNewOtp = async () => {
    if (!newEmail.trim()) return;
    setEmailBusy(true);
    try {
      const res = await apiFetch<{ success: boolean; error?: string }>({
        path: '/battle-ledger/v1/user/email/send-new-otp',
        method: 'POST',
        data: { newEmail: newEmail.trim() },
      });
      if (!mountedRef.current) return;
      if (res.success) {
        setEmailStep('verify-new');
        showNotification('Verification code sent to your new email.', 'info');
      } else {
        showNotification(res.error || 'Failed to send code.', 'error');
      }
    } catch (err: unknown) {
      const msg = (err as { message?: string })?.message || 'Failed to send verification code.';
      showNotification(msg, 'error');
    } finally {
      if (mountedRef.current) setEmailBusy(false);
    }
  };

  const confirmNewEmail = async () => {
    if (!otp.trim()) return;
    setEmailBusy(true);
    try {
      const res = await apiFetch<{ success: boolean; error?: string }>({
        path: '/battle-ledger/v1/user/email/confirm',
        method: 'POST',
        data: { newEmail: newEmail.trim(), otp: otp.trim() },
      });
      if (!mountedRef.current) return;
      if (res.success) {
        setEmailStep('done');
        setOtp('');
        setNewEmail('');
        showNotification('Email address updated!', 'success');
        fetchProfile(); // refresh
      } else {
        showNotification(res.error || 'Invalid code.', 'error');
      }
    } catch {
      showNotification('Failed to change email.', 'error');
    } finally {
      if (mountedRef.current) setEmailBusy(false);
    }
  };

  const cancelEmailChange = () => {
    setEmailStep('idle');
    setOtp('');
    setNewEmail('');
  };

  /* ── Loading skeleton ──────────────────────────────────── */

  if (loading) {
    return (
      <div className="bl-profile">
        <div className="bl-card bl-profile-card">
          <div className="bl-profile-avatar-section">
            <Skeleton variant="circular" width={100} height={100} />
          </div>
          <div className="bl-profile-fields">
            {[1, 2, 3, 4, 5].map((n) => (
              <div className="bl-profile-field" key={n}>
                <Skeleton width="30%" height={14} />
                <Skeleton width="100%" height={40} />
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!profile) return null;

  const dirty =
    displayName !== profile.displayName ||
    phone !== profile.phone ||
    bio !== profile.bio;

  /* ── Render ────────────────────────────────────────────── */

  return (
    <div className="bl-profile">
      {/* ── Avatar card ────────────────────────────────── */}
      <div className="bl-card bl-profile-card">
        <div className="bl-profile-avatar-section">
          <div className="bl-profile-avatar-wrapper">
            <img
              src={avatarPreview}
              alt={profile.displayName}
              className="bl-profile-avatar-img"
            />
            <button
              className="bl-profile-avatar-overlay"
              onClick={() => fileRef.current?.click()}
              disabled={uploadingAvatar}
              title="Change photo"
            >
              {uploadingAvatar ? <Loader2 size={20} className="bl-spin" /> : <Camera size={20} />}
            </button>
            <input
              ref={fileRef}
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              className="bl-profile-avatar-input"
              onChange={handleAvatarChange}
            />
          </div>
          <div className="bl-profile-avatar-actions">
            <button
              className="bl-btn-secondary bl-btn-sm"
              onClick={() => fileRef.current?.click()}
              disabled={uploadingAvatar}
            >
              <Camera size={14} />
              <span>{uploadingAvatar ? 'Uploading…' : 'Change Photo'}</span>
            </button>
            <button
              className="bl-btn-danger bl-btn-sm"
              onClick={handleDeleteAvatar}
              disabled={deletingAvatar}
            >
              <Trash2 size={14} />
              <span>{deletingAvatar ? 'Removing…' : 'Remove'}</span>
            </button>
          </div>
          <p className="bl-profile-avatar-hint">JPEG, PNG, WebP or GIF · Max 2 MB</p>
        </div>

        {/* ── Profile fields ────────────────────────────── */}
        <div className="bl-profile-fields">
          {/* Username (read-only) */}
          <div className="bl-profile-field">
            <label><Shield size={14} /> Username</label>
            <input type="text" value={profile.username} disabled className="bl-input" />
            <span className="bl-profile-hint">Username cannot be changed</span>
          </div>

          {/* Display Name */}
          <div className="bl-profile-field">
            <label><User size={14} /> Display Name</label>
            <input
              type="text"
              value={displayName}
              onChange={(e) => setDisplayName(e.target.value)}
              className="bl-input"
              placeholder="Your public name"
            />
          </div>

          {/* Phone */}
          <div className="bl-profile-field">
            <label><Phone size={14} /> Phone Number</label>
            <input
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="bl-input"
              placeholder="+1 (555) 123-4567"
            />
          </div>

          {/* Bio */}
          <div className="bl-profile-field">
            <label><FileText size={14} /> Bio</label>
            <textarea
              value={bio}
              onChange={(e) => setBio(e.target.value)}
              className="bl-input bl-textarea"
              rows={3}
              placeholder="A short bio about you…"
            />
          </div>

          {/* Save button */}
          <div className="bl-profile-actions">
            <button
              className="bl-btn-primary"
              onClick={handleSave}
              disabled={saving || !dirty}
            >
              {saving ? <Loader2 size={16} className="bl-spin" /> : <Save size={16} />}
              <span>{saving ? 'Saving…' : 'Save Changes'}</span>
            </button>
          </div>
        </div>
      </div>

      {/* ── Email change card ──────────────────────────── */}
      <div className="bl-card bl-profile-email-card">
        <h3><Mail size={18} /> Email Address</h3>
        <p className="bl-profile-email-current">
          Current: <strong>{profile.email}</strong>
        </p>

        {emailStep === 'idle' && (
          <button className="bl-btn-secondary" onClick={() => setEmailStep('send-old')}>
            Change Email
          </button>
        )}

        {emailStep === 'send-old' && (
          <div className="bl-profile-email-step">
            <p>We'll send a verification code to your current email to confirm your identity.</p>
            <div className="bl-profile-email-btns">
              <button className="bl-btn-primary" onClick={sendOldOtp} disabled={emailBusy}>
                {emailBusy ? <Loader2 size={14} className="bl-spin" /> : <Send size={14} />}
                <span>Send Code</span>
              </button>
              <button className="bl-btn-secondary" onClick={cancelEmailChange}>Cancel</button>
            </div>
          </div>
        )}

        {emailStep === 'verify-old' && (
          <div className="bl-profile-email-step">
            <p>Enter the code sent to <strong>{profile.email}</strong></p>
            <div className="bl-profile-otp-row">
              <input
                type="text"
                value={otp}
                onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                className="bl-input bl-otp-input"
                placeholder="000000"
                maxLength={6}
                autoFocus
              />
              <button className="bl-btn-primary" onClick={verifyOldOtp} disabled={emailBusy || otp.length < 6}>
                {emailBusy ? <Loader2 size={14} className="bl-spin" /> : <CheckCircle size={14} />}
                <span>Verify</span>
              </button>
            </div>
            <button className="bl-profile-cancel-link" onClick={cancelEmailChange}>Cancel</button>
          </div>
        )}

        {emailStep === 'enter-new' && (
          <div className="bl-profile-email-step">
            <p className="bl-profile-step-success">
              <CheckCircle size={14} /> Current email verified
            </p>
            <label>Enter your new email address:</label>
            <div className="bl-profile-otp-row">
              <input
                type="email"
                value={newEmail}
                onChange={(e) => setNewEmail(e.target.value)}
                className="bl-input"
                placeholder="new@example.com"
                autoFocus
              />
              <button className="bl-btn-primary" onClick={sendNewOtp} disabled={emailBusy || !newEmail.trim()}>
                {emailBusy ? <Loader2 size={14} className="bl-spin" /> : <ArrowRight size={14} />}
                <span>Send Code</span>
              </button>
            </div>
            <button className="bl-profile-cancel-link" onClick={cancelEmailChange}>Cancel</button>
          </div>
        )}

        {emailStep === 'verify-new' && (
          <div className="bl-profile-email-step">
            <p className="bl-profile-step-success">
              <CheckCircle size={14} /> Code sent to <strong>{newEmail}</strong>
            </p>
            <div className="bl-profile-otp-row">
              <input
                type="text"
                value={otp}
                onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                className="bl-input bl-otp-input"
                placeholder="000000"
                maxLength={6}
                autoFocus
              />
              <button className="bl-btn-primary" onClick={confirmNewEmail} disabled={emailBusy || otp.length < 6}>
                {emailBusy ? <Loader2 size={14} className="bl-spin" /> : <CheckCircle size={14} />}
                <span>Confirm</span>
              </button>
            </div>
            <button className="bl-profile-cancel-link" onClick={cancelEmailChange}>Cancel</button>
          </div>
        )}

        {emailStep === 'done' && (
          <div className="bl-profile-email-step">
            <p className="bl-profile-step-success">
              <CheckCircle size={14} /> Email has been changed successfully!
            </p>
            <button className="bl-btn-secondary" onClick={() => setEmailStep('idle')}>
              <X size={14} /> Close
            </button>
          </div>
        )}
      </div>

      {/* ── Account info card ──────────────────────────── */}
      <div className="bl-card bl-profile-info-card">
        <h3>Account Info</h3>
        <div className="bl-profile-info-grid">
          <div className="bl-profile-info-item">
            <span className="bl-profile-info-label">Member since</span>
            <span className="bl-profile-info-value">
              {new Date(profile.dateRegistered).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric',
              })}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Profile;
