import { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';

interface EmailTemplate {
  id: string;
  name: string;
  subject: string;
  description: string;
  enabled: boolean;
  design: EmailDesign;
}

interface EmailDesign {
  // Header
  headerBgColor: string;
  headerTextColor: string;
  logoUrl: string;
  logoWidth: string;
  showHeader: boolean;
  
  // Body
  bodyBgColor: string;
  contentBgColor: string;
  contentTextColor: string;
  contentPadding: string;
  
  // Button
  buttonBgColor: string;
  buttonTextColor: string;
  buttonRadius: string;
  buttonText: string;
  
  // Footer
  footerBgColor: string;
  footerTextColor: string;
  footerText: string;
  showFooter: boolean;
  showSocialLinks: boolean;
  
  // Typography
  fontFamily: string;
  headingSize: string;
  bodySize: string;
  
  // Content
  heading: string;
  preheader: string;
  bodyContent: string;
}

const defaultDesign: EmailDesign = {
  headerBgColor: '#6366f1',
  headerTextColor: '#ffffff',
  logoUrl: '',
  logoWidth: '150px',
  showHeader: true,
  bodyBgColor: '#f3f4f6',
  contentBgColor: '#ffffff',
  contentTextColor: '#374151',
  contentPadding: '40px',
  buttonBgColor: '#6366f1',
  buttonTextColor: '#ffffff',
  buttonRadius: '8px',
  buttonText: 'Click Here',
  footerBgColor: '#f9fafb',
  footerTextColor: '#9ca3af',
  footerText: '© 2026 BattleLedger. All rights reserved.',
  showFooter: true,
  showSocialLinks: true,
  fontFamily: 'Arial, sans-serif',
  headingSize: '28px',
  bodySize: '16px',
  heading: 'Email Heading',
  preheader: '',
  bodyContent: 'Your email content goes here. You can use {{variables}} for dynamic content.',
};

const emailTemplates: { id: string; name: string; description: string }[] = [
  { id: 'welcome', name: 'Welcome Email', description: 'Sent when a new user registers' },
  { id: 'otp_login', name: 'Login OTP', description: 'One-time password for login' },
  { id: 'otp_verify', name: 'Verification OTP', description: 'Email verification code' },
  { id: 'password_reset', name: 'Password Reset', description: 'Password reset request' },
  { id: 'password_changed', name: 'Password Changed', description: 'Confirmation of password change' },
  { id: 'tournament_invite', name: 'Tournament Invitation', description: 'Invite to participate in tournament' },
  { id: 'match_reminder', name: 'Match Reminder', description: 'Upcoming match notification' },
  { id: 'match_result', name: 'Match Result', description: 'Match result notification' },
];

const templateVariables: Record<string, string[]> = {
  welcome: ['{{user_name}}', '{{user_email}}', '{{site_name}}', '{{login_url}}'],
  otp_login: ['{{user_name}}', '{{otp_code}}', '{{expires_in}}'],
  otp_verify: ['{{user_name}}', '{{otp_code}}', '{{expires_in}}'],
  password_reset: ['{{user_name}}', '{{reset_link}}', '{{expires_in}}'],
  password_changed: ['{{user_name}}', '{{date_time}}'],
  tournament_invite: ['{{user_name}}', '{{tournament_name}}', '{{start_date}}', '{{join_link}}'],
  match_reminder: ['{{user_name}}', '{{match_name}}', '{{opponent}}', '{{match_time}}', '{{match_link}}'],
  match_result: ['{{user_name}}', '{{match_name}}', '{{result}}', '{{score}}'],
};

export default function EmailTemplates() {
  const [templates, setTemplates] = useState<Record<string, EmailTemplate>>({});
  const [selectedTemplate, setSelectedTemplate] = useState<string>('welcome');
  const [design, setDesign] = useState<EmailDesign>(defaultDesign);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [activeSection, setActiveSection] = useState<'header' | 'body' | 'button' | 'footer'>('header');

  useEffect(() => {
    loadTemplates();
  }, []);

  useEffect(() => {
    if (templates[selectedTemplate]) {
      setDesign(templates[selectedTemplate].design);
    } else {
      setDesign({
        ...defaultDesign,
        heading: getDefaultHeading(selectedTemplate),
        bodyContent: getDefaultBody(selectedTemplate),
        buttonText: getDefaultButton(selectedTemplate),
      });
    }
  }, [selectedTemplate, templates]);

  const loadTemplates = async () => {
    try {
      const response = await apiFetch<Record<string, any>>({
        path: '/battle-ledger/v1/settings/email-templates',
      });
      if (response) {
        // Convert API response to our template format
        const loadedTemplates: Record<string, EmailTemplate> = {};
        for (const [id, template] of Object.entries(response)) {
          loadedTemplates[id] = {
            id,
            name: emailTemplates.find(t => t.id === id)?.name || id,
            subject: template.header?.subject || '',
            description: emailTemplates.find(t => t.id === id)?.description || '',
            enabled: template.enabled ?? true,
            design: {
              headerBgColor: template.header?.backgroundColor || defaultDesign.headerBgColor,
              headerTextColor: template.header?.textColor || defaultDesign.headerTextColor,
              logoUrl: '',
              logoWidth: '150px',
              showHeader: true,
              bodyBgColor: '#f3f4f6',
              contentBgColor: template.body?.backgroundColor || defaultDesign.contentBgColor,
              contentTextColor: template.body?.textColor || defaultDesign.contentTextColor,
              contentPadding: '40px',
              buttonBgColor: template.button?.backgroundColor || defaultDesign.buttonBgColor,
              buttonTextColor: template.button?.textColor || defaultDesign.buttonTextColor,
              buttonRadius: template.button?.borderRadius || defaultDesign.buttonRadius,
              buttonText: template.button?.text || defaultDesign.buttonText,
              footerBgColor: template.footer?.backgroundColor || defaultDesign.footerBgColor,
              footerTextColor: template.footer?.textColor || defaultDesign.footerTextColor,
              footerText: template.footer?.text || defaultDesign.footerText,
              showFooter: true,
              showSocialLinks: template.footer?.showSocialLinks ?? true,
              fontFamily: 'Arial, sans-serif',
              headingSize: template.header?.titleSize || defaultDesign.headingSize,
              bodySize: template.body?.fontSize || defaultDesign.bodySize,
              heading: template.header?.title || '',
              preheader: '',
              bodyContent: template.body?.content || '',
            },
          };
        }
        setTemplates(loadedTemplates);
      }
    } catch (error) {
      console.error('Failed to load email templates:', error);
    }
  };

  const saveTemplate = async () => {
    setSaving(true);
    try {
      // Convert to API format
      const templateData = {
        enabled: true,
        header: {
          subject: design.heading,
          title: design.heading,
          backgroundColor: design.headerBgColor,
          textColor: design.headerTextColor,
          titleSize: design.headingSize,
        },
        body: {
          content: design.bodyContent,
          backgroundColor: design.contentBgColor,
          textColor: design.contentTextColor,
          fontSize: design.bodySize,
        },
        button: {
          showButton: design.buttonText ? true : false,
          text: design.buttonText,
          url: '',
          backgroundColor: design.buttonBgColor,
          textColor: design.buttonTextColor,
          borderRadius: design.buttonRadius,
        },
        footer: {
          text: design.footerText,
          backgroundColor: design.footerBgColor,
          textColor: design.footerTextColor,
          showSocialLinks: design.showSocialLinks,
        },
      };

      await apiFetch({
        path: '/battle-ledger/v1/settings/email-templates',
        method: 'POST',
        data: {
          template_id: selectedTemplate,
          template_data: templateData,
        },
      });

      const templateInfo = emailTemplates.find(t => t.id === selectedTemplate);
      setTemplates(prev => ({
        ...prev,
        [selectedTemplate]: {
          id: selectedTemplate,
          name: templateInfo?.name || selectedTemplate,
          subject: design.heading,
          description: templateInfo?.description || '',
          enabled: true,
          design,
        },
      }));
      
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (error) {
      console.error('Failed to save template:', error);
    } finally {
      setSaving(false);
    }
  };

  const sendTestEmail = async () => {
    const email = prompt('Enter email address for test:');
    if (!email) return;
    
    try {
      const response = await apiFetch<{ success: boolean; message: string }>({
        path: '/battle-ledger/v1/email/test',
        method: 'POST',
        data: { template_id: selectedTemplate, email },
      });
      alert(response.message || 'Test email sent!');
    } catch (error: any) {
      alert(error.message || 'Failed to send test email');
    }
  };

  const getDefaultHeading = (templateId: string): string => {
    const headings: Record<string, string> = {
      welcome: 'Welcome to BattleLedger!',
      otp_login: 'Your Login Code',
      otp_verify: 'Verify Your Email',
      password_reset: 'Reset Your Password',
      password_changed: 'Password Changed Successfully',
      tournament_invite: "You're Invited!",
      match_reminder: 'Match Starting Soon',
      match_result: 'Match Results',
    };
    return headings[templateId] || 'Email Heading';
  };

  const getDefaultBody = (templateId: string): string => {
    const bodies: Record<string, string> = {
      welcome: 'Hi {{user_name}},\n\nWelcome to BattleLedger! Your account has been created successfully.\n\nStart by exploring tournaments and connecting with other players.',
      otp_login: 'Hi {{user_name}},\n\nYour login code is:\n\n<strong style="font-size: 32px; letter-spacing: 8px;">{{otp_code}}</strong>\n\nThis code expires in {{expires_in}}.',
      otp_verify: 'Hi {{user_name}},\n\nPlease verify your email address using the code below:\n\n<strong style="font-size: 32px; letter-spacing: 8px;">{{otp_code}}</strong>\n\nThis code expires in {{expires_in}}.',
      password_reset: 'Hi {{user_name}},\n\nWe received a request to reset your password. Click the button below to create a new password.\n\nThis link expires in {{expires_in}}.',
      password_changed: 'Hi {{user_name}},\n\nYour password was successfully changed on {{date_time}}.\n\nIf you did not make this change, please contact support immediately.',
      tournament_invite: 'Hi {{user_name}},\n\nYou have been invited to join {{tournament_name}}!\n\nThe tournament starts on {{start_date}}.',
      match_reminder: 'Hi {{user_name}},\n\nYour match against {{opponent}} is starting at {{match_time}}.\n\nDon\'t forget to prepare!',
      match_result: 'Hi {{user_name}},\n\nThe results for {{match_name}} are in!\n\nResult: {{result}}\nScore: {{score}}',
    };
    return bodies[templateId] || 'Your email content goes here.';
  };

  const getDefaultButton = (templateId: string): string => {
    const buttons: Record<string, string> = {
      welcome: 'Get Started',
      otp_login: '',
      otp_verify: '',
      password_reset: 'Reset Password',
      password_changed: 'View Account',
      tournament_invite: 'Join Tournament',
      match_reminder: 'View Match',
      match_result: 'View Details',
    };
    return buttons[templateId] || 'Click Here';
  };

  const insertVariable = (variable: string) => {
    setDesign(prev => ({
      ...prev,
      bodyContent: prev.bodyContent + ' ' + variable,
    }));
  };

  const renderColorInput = (label: string, key: keyof EmailDesign) => (
    <div className="bl-email-field">
      <label>{label}</label>
      <div className="bl-color-input">
        <input
          type="color"
          value={design[key] as string}
          onChange={(e) => setDesign(prev => ({ ...prev, [key]: e.target.value }))}
        />
        <input
          type="text"
          value={design[key] as string}
          onChange={(e) => setDesign(prev => ({ ...prev, [key]: e.target.value }))}
        />
      </div>
    </div>
  );

  const renderTextInput = (label: string, key: keyof EmailDesign, placeholder?: string) => (
    <div className="bl-email-field">
      <label>{label}</label>
      <input
        type="text"
        value={design[key] as string}
        onChange={(e) => setDesign(prev => ({ ...prev, [key]: e.target.value }))}
        placeholder={placeholder}
      />
    </div>
  );

  const renderToggle = (label: string, key: keyof EmailDesign) => (
    <div className="bl-email-field bl-toggle-row">
      <label>{label}</label>
      <button
        className={`bl-toggle ${design[key] ? 'active' : ''}`}
        onClick={() => setDesign(prev => ({ ...prev, [key]: !prev[key] }))}
      >
        <span className="bl-toggle-slider" />
      </button>
    </div>
  );

  return (
    <div className="bl-email-templates">
      <div className="bl-page-header">
        <div>
          <h1>Email Templates</h1>
          <p>Design beautiful email templates with our visual editor</p>
        </div>
        <div className="bl-header-actions">
          <button className="bl-btn bl-btn-outline" onClick={sendTestEmail}>
            <span className="dashicons dashicons-email-alt" />
            Send Test
          </button>
          <button
            className={`bl-btn bl-btn-primary ${saving ? 'loading' : ''} ${saved ? 'saved' : ''}`}
            onClick={saveTemplate}
            disabled={saving}
          >
            {saving ? (
              <>
                <span className="bl-spinner" />
                Saving...
              </>
            ) : saved ? (
              <>
                <span className="dashicons dashicons-yes-alt" />
                Saved!
              </>
            ) : (
              <>
                <span className="dashicons dashicons-cloud-saved" />
                Save Template
              </>
            )}
          </button>
        </div>
      </div>

      <div className="bl-email-layout">
        {/* Template Selector */}
        <div className="bl-template-list">
          <h4>Templates</h4>
          {emailTemplates.map((template) => (
            <button
              key={template.id}
              className={`bl-template-item ${selectedTemplate === template.id ? 'active' : ''}`}
              onClick={() => setSelectedTemplate(template.id)}
            >
              <span className="bl-template-icon">
                {template.id.includes('otp') ? '🔑' : 
                 template.id.includes('password') ? '🔒' : 
                 template.id.includes('welcome') ? '👋' : 
                 template.id.includes('tournament') ? '🏆' : 
                 template.id.includes('match') ? '⚔️' : '📧'}
              </span>
              <div className="bl-template-info">
                <span className="bl-template-name">{template.name}</span>
                <span className="bl-template-desc">{template.description}</span>
              </div>
              {templates[template.id]?.enabled && (
                <span className="bl-template-status active" title="Customized">✓</span>
              )}
            </button>
          ))}
        </div>

        {/* Design Panel */}
        <div className="bl-design-panel">
          <div className="bl-design-tabs">
            <button
              className={activeSection === 'header' ? 'active' : ''}
              onClick={() => setActiveSection('header')}
            >
              <span className="dashicons dashicons-heading" />
              Header
            </button>
            <button
              className={activeSection === 'body' ? 'active' : ''}
              onClick={() => setActiveSection('body')}
            >
              <span className="dashicons dashicons-text" />
              Content
            </button>
            <button
              className={activeSection === 'button' ? 'active' : ''}
              onClick={() => setActiveSection('button')}
            >
              <span className="dashicons dashicons-button" />
              Button
            </button>
            <button
              className={activeSection === 'footer' ? 'active' : ''}
              onClick={() => setActiveSection('footer')}
            >
              <span className="dashicons dashicons-editor-insertmore" />
              Footer
            </button>
          </div>

          <div className="bl-design-content">
            {activeSection === 'header' && (
              <div className="bl-design-section">
                {renderToggle('Show Header', 'showHeader')}
                {design.showHeader && (
                  <>
                    {renderColorInput('Background', 'headerBgColor')}
                    {renderColorInput('Text Color', 'headerTextColor')}
                    {renderTextInput('Logo URL', 'logoUrl', 'https://example.com/logo.png')}
                    {renderTextInput('Logo Width', 'logoWidth', '150px')}
                  </>
                )}
              </div>
            )}

            {activeSection === 'body' && (
              <div className="bl-design-section">
                {renderColorInput('Background', 'bodyBgColor')}
                {renderColorInput('Content Background', 'contentBgColor')}
                {renderColorInput('Text Color', 'contentTextColor')}
                
                <div className="bl-email-field">
                  <label>Font Family</label>
                  <select
                    value={design.fontFamily}
                    onChange={(e) => setDesign(prev => ({ ...prev, fontFamily: e.target.value }))}
                  >
                    <option value="Arial, sans-serif">Arial</option>
                    <option value="'Helvetica Neue', Helvetica, sans-serif">Helvetica</option>
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Times New Roman', serif">Times New Roman</option>
                    <option value="Verdana, sans-serif">Verdana</option>
                  </select>
                </div>
                
                {renderTextInput('Heading', 'heading')}
                
                <div className="bl-email-field">
                  <label>Body Content</label>
                  <div className="bl-variables-bar">
                    <span>Insert:</span>
                    {templateVariables[selectedTemplate]?.map((v) => (
                      <button key={v} onClick={() => insertVariable(v)} title={v}>
                        {v.replace(/{{|}}/g, '')}
                      </button>
                    ))}
                  </div>
                  <textarea
                    value={design.bodyContent}
                    onChange={(e) => setDesign(prev => ({ ...prev, bodyContent: e.target.value }))}
                    rows={8}
                    placeholder="Enter your email content..."
                  />
                </div>
              </div>
            )}

            {activeSection === 'button' && (
              <div className="bl-design-section">
                {renderTextInput('Button Text', 'buttonText', 'Click Here')}
                {design.buttonText && (
                  <>
                    {renderColorInput('Background', 'buttonBgColor')}
                    {renderColorInput('Text Color', 'buttonTextColor')}
                    {renderTextInput('Border Radius', 'buttonRadius', '8px')}
                  </>
                )}
                <p className="bl-field-hint">Leave button text empty to hide the button</p>
              </div>
            )}

            {activeSection === 'footer' && (
              <div className="bl-design-section">
                {renderToggle('Show Footer', 'showFooter')}
                {design.showFooter && (
                  <>
                    {renderColorInput('Background', 'footerBgColor')}
                    {renderColorInput('Text Color', 'footerTextColor')}
                    <div className="bl-email-field">
                      <label>Footer Text</label>
                      <textarea
                        value={design.footerText}
                        onChange={(e) => setDesign(prev => ({ ...prev, footerText: e.target.value }))}
                        rows={3}
                      />
                    </div>
                    {renderToggle('Show Social Links', 'showSocialLinks')}
                  </>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Live Preview */}
        <div className="bl-email-preview">
          <div className="bl-preview-toolbar">
            <span>Preview</span>
            <div className="bl-device-toggle">
              <button className="active" title="Desktop">
                <span className="dashicons dashicons-desktop" />
              </button>
              <button title="Mobile">
                <span className="dashicons dashicons-smartphone" />
              </button>
            </div>
          </div>
          
          <div className="bl-email-preview-container" style={{ background: design.bodyBgColor }}>
            <div 
              className="bl-email-template"
              style={{ 
                fontFamily: design.fontFamily,
                maxWidth: '600px',
                margin: '0 auto',
              }}
            >
              {/* Header */}
              {design.showHeader && (
                <div 
                  className="bl-et-header"
                  style={{ 
                    background: design.headerBgColor, 
                    color: design.headerTextColor,
                    padding: '30px 40px',
                    textAlign: 'center',
                  }}
                >
                  {design.logoUrl ? (
                    <img 
                      src={design.logoUrl} 
                      alt="Logo" 
                      style={{ width: design.logoWidth, height: 'auto' }}
                    />
                  ) : (
                    <h1 style={{ margin: 0, fontSize: '24px', fontWeight: 'bold' }}>
                      BattleLedger
                    </h1>
                  )}
                </div>
              )}

              {/* Body */}
              <div 
                className="bl-et-body"
                style={{ 
                  background: design.contentBgColor, 
                  color: design.contentTextColor,
                  padding: design.contentPadding,
                }}
              >
                <h2 style={{ 
                  fontSize: design.headingSize, 
                  marginTop: 0, 
                  marginBottom: '20px',
                  color: design.contentTextColor,
                }}>
                  {design.heading}
                </h2>
                
                <div 
                  style={{ fontSize: design.bodySize, lineHeight: 1.6 }}
                  dangerouslySetInnerHTML={{ 
                    __html: design.bodyContent
                      .replace(/\n/g, '<br>')
                      .replace(/{{(\w+)}}/g, '<span style="color: ' + design.buttonBgColor + '">[$1]</span>')
                  }}
                />

                {design.buttonText && (
                  <div style={{ textAlign: 'center', marginTop: '30px' }}>
                    <a
                      href="#"
                      style={{
                        display: 'inline-block',
                        background: design.buttonBgColor,
                        color: design.buttonTextColor,
                        padding: '14px 32px',
                        borderRadius: design.buttonRadius,
                        textDecoration: 'none',
                        fontWeight: 600,
                        fontSize: '16px',
                      }}
                    >
                      {design.buttonText}
                    </a>
                  </div>
                )}
              </div>

              {/* Footer */}
              {design.showFooter && (
                <div 
                  className="bl-et-footer"
                  style={{ 
                    background: design.footerBgColor, 
                    color: design.footerTextColor,
                    padding: '30px 40px',
                    textAlign: 'center',
                    fontSize: '13px',
                  }}
                >
                  {design.showSocialLinks && (
                    <div style={{ marginBottom: '20px' }}>
                      <a href="#" style={{ display: 'inline-block', margin: '0 8px', opacity: 0.7 }}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill={design.footerTextColor}>
                          <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                        </svg>
                      </a>
                      <a href="#" style={{ display: 'inline-block', margin: '0 8px', opacity: 0.7 }}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill={design.footerTextColor}>
                          <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073z"/>
                        </svg>
                      </a>
                      <a href="#" style={{ display: 'inline-block', margin: '0 8px', opacity: 0.7 }}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill={design.footerTextColor}>
                          <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.495v-9.294h-3.128v-3.622h3.128v-2.671c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12v9.293h6.116c.73 0 1.323-.593 1.323-1.325v-21.35c0-.732-.593-1.325-1.325-1.325z"/>
                        </svg>
                      </a>
                    </div>
                  )}
                  <p style={{ margin: 0 }}>{design.footerText}</p>
                  <p style={{ margin: '10px 0 0', fontSize: '12px', opacity: 0.7 }}>
                    <a href="#" style={{ color: design.footerTextColor }}>Unsubscribe</a> | 
                    <a href="#" style={{ color: design.footerTextColor }}> Privacy Policy</a>
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
