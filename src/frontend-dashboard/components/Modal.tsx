/**
 * Reusable Modal Component
 */

import React, { useEffect } from 'react';
import { X } from 'lucide-react';

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  size?: 'small' | 'medium' | 'large';
  footer?: React.ReactNode;
}

const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  title,
  children,
  size = 'medium',
  footer,
}) => {
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
    return () => {
      document.body.style.overflow = 'unset';
    };
  }, [isOpen]);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div className="bl-modal-overlay" onClick={onClose}>
      <div
        className={`bl-modal bl-modal--${size}`}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="bl-modal-header">
          <h3>{title}</h3>
          <button className="bl-modal-close" onClick={onClose}>
            <X size={20} />
          </button>
        </div>
        
        <div className="bl-modal-content">{children}</div>
        
        {footer && <div className="bl-modal-footer">{footer}</div>}
      </div>
    </div>
  );
};

export default Modal;
