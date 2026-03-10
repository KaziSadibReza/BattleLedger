<?php
namespace BattleLedger\Api;

/**
 * Settings API controller for Form Customization and Email Templates
 */
class SettingsController {
    
    /**
     * Register routes
     */
    public static function register_routes() {
        // Form Customization Settings
        register_rest_route('battle-ledger/v1', '/settings/form', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_form_settings'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'update_form_settings'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);
        
        // Email Template Settings
        register_rest_route('battle-ledger/v1', '/settings/email-templates', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_email_templates'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'update_email_templates'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);
        
        // Send Test Email
        register_rest_route('battle-ledger/v1', '/email/test', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'send_test_email'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
        
        // Frontend Appearance Settings (Dashboard + Live Tournaments colors)
        register_rest_route('battle-ledger/v1', '/settings/frontend-appearance', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_frontend_appearance'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'update_frontend_appearance'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);
    }
    
    /**
     * Get form customization settings
     */
    public static function get_form_settings($request) {
        $defaults = self::get_default_form_settings();
        $settings = get_option('battle_ledger_form_settings', []);
        
        return rest_ensure_response(wp_parse_args($settings, $defaults));
    }
    
    /**
     * Update form customization settings
     */
    public static function update_form_settings($request) {
        $params = $request->get_json_params();
        
        // Sanitize settings
        $sanitized = self::sanitize_form_settings($params);
        
        // Save to options
        update_option('battle_ledger_form_settings', $sanitized);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Form settings saved successfully', 'battle-ledger'),
            'settings' => $sanitized,
        ]);
    }
    
    /**
     * Get email templates
     */
    public static function get_email_templates($request) {
        $defaults = self::get_default_email_templates();
        $templates = get_option('battle_ledger_email_templates', []);
        
        // Merge with defaults
        $result = [];
        foreach ($defaults as $key => $default) {
            $result[$key] = isset($templates[$key]) 
                ? wp_parse_args($templates[$key], $default) 
                : $default;
        }
        
        return rest_ensure_response($result);
    }
    
    /**
     * Update email templates
     */
    public static function update_email_templates($request) {
        $params = $request->get_json_params();
        
        // Get current templates
        $current = get_option('battle_ledger_email_templates', []);
        
        // Sanitize and merge
        if (isset($params['template_id']) && isset($params['template_data'])) {
            // Single template update
            $template_id = sanitize_key($params['template_id']);
            $current[$template_id] = self::sanitize_email_template($params['template_data']);
        } else {
            // Full update
            foreach ($params as $key => $template) {
                $current[sanitize_key($key)] = self::sanitize_email_template($template);
            }
        }
        
        update_option('battle_ledger_email_templates', $current);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Email templates saved successfully', 'battle-ledger'),
        ]);
    }
    
    /**
     * Send test email
     */
    public static function send_test_email($request) {
        $params = $request->get_json_params();
        
        $template_id = sanitize_key($params['template_id'] ?? '');
        $email = sanitize_email($params['email'] ?? '');
        
        if (empty($email)) {
            return new \WP_Error('invalid_email', __('Invalid email address', 'battle-ledger'), ['status' => 400]);
        }
        
        // Get the template
        $templates = get_option('battle_ledger_email_templates', []);
        $defaults = self::get_default_email_templates();
        
        $template = isset($templates[$template_id]) 
            ? wp_parse_args($templates[$template_id], $defaults[$template_id] ?? [])
            : ($defaults[$template_id] ?? null);
        
        if (!$template) {
            return new \WP_Error('template_not_found', __('Template not found', 'battle-ledger'), ['status' => 404]);
        }
        
        // Replace variables with test data
        $test_data = [
            '{{user_name}}' => 'Test User',
            '{{otp_code}}' => '123456',
            '{{reset_link}}' => home_url('/reset-password/?token=test123'),
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{current_year}}' => date('Y'),
            '{{tournament_name}}' => 'Test Tournament',
            '{{tournament_date}}' => date('F j, Y', strtotime('+7 days')),
            '{{tournament_link}}' => home_url('/tournaments/test/'),
            '{{payment_link}}' => home_url('/payment/?order=test123'),
            '{{payment_amount}}' => '$50.00',
        ];
        
        $subject = str_replace(array_keys($test_data), array_values($test_data), $template['header']['subject'] ?? 'Test Email');
        $body = self::build_email_html($template, $test_data);
        
        // Send email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($email, $subject, $body, $headers);
        
        if ($sent) {
            return rest_ensure_response([
                'success' => true,
                'message' => sprintf(__('Test email sent to %s', 'battle-ledger'), $email),
            ]);
        } else {
            return new \WP_Error('email_failed', __('Failed to send email', 'battle-ledger'), ['status' => 500]);
        }
    }
    
    /**
     * Build email HTML from template
     */
    private static function build_email_html($template, $replacements) {
        $header = $template['header'] ?? [];
        $body_content = $template['body'] ?? [];
        $button = $template['button'] ?? [];
        $footer = $template['footer'] ?? [];
        
        // Replace variables
        $replace = function($text) use ($replacements) {
            return str_replace(array_keys($replacements), array_values($replacements), $text);
        };
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($replace($header['subject'] ?? '')) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <!-- Header -->
        <tr>
            <td style="background-color: ' . esc_attr($header['backgroundColor'] ?? '#6366f1') . '; padding: 40px 30px; text-align: center;">
                <h1 style="margin: 0; font-size: ' . esc_attr($header['titleSize'] ?? '28px') . '; color: ' . esc_attr($header['textColor'] ?? '#ffffff') . '; font-weight: 700;">
                    ' . esc_html($replace($header['title'] ?? '')) . '
                </h1>
            </td>
        </tr>
        
        <!-- Body -->
        <tr>
            <td style="background-color: ' . esc_attr($body_content['backgroundColor'] ?? '#ffffff') . '; padding: 40px 30px;">
                <div style="color: ' . esc_attr($body_content['textColor'] ?? '#374151') . '; font-size: ' . esc_attr($body_content['fontSize'] ?? '16px') . '; line-height: 1.6;">
                    ' . wp_kses_post(nl2br($replace($body_content['content'] ?? ''))) . '
                </div>';
        
        // Button
        if (!empty($button['showButton']) && !empty($button['text'])) {
            $html .= '
                <div style="text-align: center; margin-top: 30px;">
                    <a href="' . esc_url($replace($button['url'] ?? '#')) . '" style="display: inline-block; background-color: ' . esc_attr($button['backgroundColor'] ?? '#6366f1') . '; color: ' . esc_attr($button['textColor'] ?? '#ffffff') . '; padding: 14px 32px; text-decoration: none; border-radius: ' . esc_attr($button['borderRadius'] ?? '8px') . '; font-weight: 600; font-size: 16px;">
                        ' . esc_html($replace($button['text'])) . '
                    </a>
                </div>';
        }
        
        $html .= '
            </td>
        </tr>
        
        <!-- Footer -->
        <tr>
            <td style="background-color: ' . esc_attr($footer['backgroundColor'] ?? '#f8fafc') . '; padding: 30px; text-align: center;">
                <p style="margin: 0 0 10px; color: ' . esc_attr($footer['textColor'] ?? '#64748b') . '; font-size: 14px;">
                    ' . esc_html($replace($footer['text'] ?? '')) . '
                </p>';
        
        // Social links
        if (!empty($footer['showSocialLinks'])) {
            $html .= '
                <div style="margin-top: 20px;">';
            if (!empty($footer['facebookUrl'])) {
                $html .= '<a href="' . esc_url($footer['facebookUrl']) . '" style="display: inline-block; margin: 0 8px; color: ' . esc_attr($footer['textColor'] ?? '#64748b') . ';">Facebook</a>';
            }
            if (!empty($footer['twitterUrl'])) {
                $html .= '<a href="' . esc_url($footer['twitterUrl']) . '" style="display: inline-block; margin: 0 8px; color: ' . esc_attr($footer['textColor'] ?? '#64748b') . ';">Twitter</a>';
            }
            if (!empty($footer['instagramUrl'])) {
                $html .= '<a href="' . esc_url($footer['instagramUrl']) . '" style="display: inline-block; margin: 0 8px; color: ' . esc_attr($footer['textColor'] ?? '#64748b') . ';">Instagram</a>';
            }
            $html .= '
                </div>';
        }
        
        $html .= '
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get default form settings
     */
    private static function get_default_form_settings() {
        return [
            'colors' => [
                'primaryColor' => '#6366f1',
                'accentColor' => '#818cf8',
                'backgroundColor' => '#f8fafc',
                'surfaceColor' => '#ffffff',
                'textColor' => '#1e293b',
                'mutedColor' => '#64748b',
                'borderColor' => '#e2e8f0',
                'errorColor' => '#ef4444',
                'successColor' => '#10b981',
            ],
            'typography' => [
                'fontFamily' => 'Inter',
                'headingSize' => '28',
                'labelSize' => '13',
                'inputSize' => '15',
                'buttonSize' => '15',
                'borderRadius' => '12',
            ],
            'labels' => [
                'signInTitle' => 'Welcome back',
                'signInSubtitle' => 'Sign in to continue to your account',
                'signUpTitle' => 'Create Account',
                'signUpSubtitle' => 'Join the competition today',
                'emailLabel' => 'Email address',
                'passwordLabel' => 'Password',
                'nameLabel' => 'Full name',
                'submitSignIn' => 'Sign In',
                'submitSignUp' => 'Create Account',
                'toggleToSignUp' => "Don't have an account?",
                'toggleToSignIn' => 'Already have an account?',
            ],
            'features' => [
                'showLogo' => true,
                'logoUrl' => '',
                'showSocialLogin' => true,
                'enableOtp' => true,
                'enablePassword' => true,
                'rememberMe' => true,
                'showTerms' => false,
                'termsUrl' => '',
            ],
        ];
    }
    
    /**
     * Get default email templates
     */
    private static function get_default_email_templates() {
        $site_name = get_bloginfo('name');
        
        return [
            'welcome' => [
                'enabled' => true,
                'header' => [
                    'subject' => "Welcome to {$site_name}, {{user_name}}!",
                    'title' => 'Welcome!',
                    'backgroundColor' => '#6366f1',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nWelcome to {$site_name}! We're excited to have you join our community.\n\nYou can now participate in tournaments, track your progress, and compete with other players.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Get Started',
                    'url' => '{{site_url}}/dashboard/',
                    'backgroundColor' => '#6366f1',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'otp_login' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Your Login Code - {{otp_code}}',
                    'title' => 'Your Login Code',
                    'backgroundColor' => '#10b981',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nYour one-time login code is:\n\n**{{otp_code}}**\n\nThis code will expire in 10 minutes. If you didn't request this, please ignore this email.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => false,
                    'text' => '',
                    'url' => '',
                    'backgroundColor' => '#10b981',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'otp_verify' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Verify Your Email - {{otp_code}}',
                    'title' => 'Verify Your Email',
                    'backgroundColor' => '#8b5cf6',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nPlease verify your email address by entering this code:\n\n**{{otp_code}}**\n\nThis code expires in 15 minutes.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => false,
                    'text' => '',
                    'url' => '',
                    'backgroundColor' => '#8b5cf6',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'password_reset' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Reset Your Password',
                    'title' => 'Password Reset',
                    'backgroundColor' => '#f59e0b',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nWe received a request to reset your password. Click the button below to set a new password.\n\nIf you didn't request this, you can safely ignore this email.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Reset Password',
                    'url' => '{{reset_link}}',
                    'backgroundColor' => '#f59e0b',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'tournament_registration' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Registration Confirmed: {{tournament_name}}',
                    'title' => 'You\'re Registered!',
                    'backgroundColor' => '#06b6d4',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nYou've successfully registered for **{{tournament_name}}**!\n\n📅 Date: {{tournament_date}}\n\nGet ready to compete! We'll send you more details as the tournament approaches.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'View Tournament',
                    'url' => '{{tournament_link}}',
                    'backgroundColor' => '#06b6d4',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'tournament_reminder' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Reminder: {{tournament_name}} starts soon!',
                    'title' => 'Tournament Reminder',
                    'backgroundColor' => '#ec4899',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nJust a friendly reminder that **{{tournament_name}}** is coming up!\n\n📅 Date: {{tournament_date}}\n\nMake sure you're ready to compete!",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'View Details',
                    'url' => '{{tournament_link}}',
                    'backgroundColor' => '#ec4899',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'payment_confirmation' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Payment Received - {{payment_amount}}',
                    'title' => 'Payment Confirmed',
                    'backgroundColor' => '#22c55e',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nWe've received your payment of **{{payment_amount}}** for **{{tournament_name}}**.\n\nYour registration is now complete! Good luck in the tournament!",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'View Receipt',
                    'url' => '{{payment_link}}',
                    'backgroundColor' => '#22c55e',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
            'account_notification' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Account Update',
                    'title' => 'Account Notification',
                    'backgroundColor' => '#64748b',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nThis is a notification about your account. Please review the details and take any necessary action.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'View Account',
                    'url' => '{{site_url}}/account/',
                    'backgroundColor' => '#64748b',
                    'textColor' => '#ffffff',
                    'borderRadius' => '8px',
                ],
                'footer' => [
                    'text' => "© {{current_year}} {$site_name}. All rights reserved.",
                    'backgroundColor' => '#f8fafc',
                    'textColor' => '#64748b',
                    'showSocialLinks' => false,
                ],
            ],
        ];
    }
    
    /**
     * Sanitize form settings
     */
    private static function sanitize_form_settings($settings) {
        $sanitized = [];
        
        // Colors - preserve camelCase keys
        if (isset($settings['colors']) && is_array($settings['colors'])) {
            $sanitized['colors'] = [];
            $allowed_color_keys = [
                'primaryColor', 'primaryDarkColor', 'accentColor', 'backgroundColor',
                'surfaceColor', 'borderColor', 'textPrimaryColor', 'textSecondaryColor',
                'textMutedColor', 'successColor', 'errorColor'
            ];
            foreach ($settings['colors'] as $key => $value) {
                if (in_array($key, $allowed_color_keys)) {
                    $sanitized['colors'][$key] = sanitize_hex_color($value) ?: $value;
                }
            }
        }
        
        // Typography - preserve camelCase keys
        if (isset($settings['typography']) && is_array($settings['typography'])) {
            $sanitized['typography'] = [];
            $allowed_typography_keys = ['fontFamily', 'headingFontSize', 'bodyFontSize', 'borderRadius'];
            foreach ($settings['typography'] as $key => $value) {
                if (in_array($key, $allowed_typography_keys)) {
                    $sanitized['typography'][$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Labels - preserve camelCase keys
        if (isset($settings['labels']) && is_array($settings['labels'])) {
            $sanitized['labels'] = [];
            $allowed_label_keys = [
                'signInTitle', 'signInSubtitle', 'signUpTitle', 'signUpSubtitle',
                'emailLabel', 'passwordLabel', 'signInButtonText', 'signUpButtonText'
            ];
            foreach ($settings['labels'] as $key => $value) {
                if (in_array($key, $allowed_label_keys)) {
                    $sanitized['labels'][$key] = sanitize_text_field($value);
                }
            }
        }
        
        // Features - preserve camelCase keys
        if (isset($settings['features']) && is_array($settings['features'])) {
            $sanitized['features'] = [];
            $allowed_feature_keys = [
                'showLogo', 'logoUrl', 'showSocialLogin', 'showOtpLogin',
                'showPasswordLogin', 'defaultLoginMethod', 'loginRedirect',
                'logoutRedirect', 'registrationRedirect'
            ];
            foreach ($settings['features'] as $key => $value) {
                if (in_array($key, $allowed_feature_keys)) {
                    if (is_bool($value)) {
                        $sanitized['features'][$key] = $value;
                    } else {
                        $sanitized['features'][$key] = sanitize_text_field($value);
                    }
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize email template
     */
    private static function sanitize_email_template($template) {
        $sanitized = [];
        
        if (isset($template['enabled'])) {
            $sanitized['enabled'] = (bool) $template['enabled'];
        }
        
        // Header
        if (isset($template['header']) && is_array($template['header'])) {
            $sanitized['header'] = [
                'subject' => sanitize_text_field($template['header']['subject'] ?? ''),
                'title' => sanitize_text_field($template['header']['title'] ?? ''),
                'backgroundColor' => sanitize_hex_color($template['header']['backgroundColor'] ?? '') ?: '#6366f1',
                'textColor' => sanitize_hex_color($template['header']['textColor'] ?? '') ?: '#ffffff',
                'titleSize' => sanitize_text_field($template['header']['titleSize'] ?? '28px'),
            ];
        }
        
        // Body
        if (isset($template['body']) && is_array($template['body'])) {
            $sanitized['body'] = [
                'content' => wp_kses_post($template['body']['content'] ?? ''),
                'backgroundColor' => sanitize_hex_color($template['body']['backgroundColor'] ?? '') ?: '#ffffff',
                'textColor' => sanitize_hex_color($template['body']['textColor'] ?? '') ?: '#374151',
                'fontSize' => sanitize_text_field($template['body']['fontSize'] ?? '16px'),
            ];
        }
        
        // Button
        if (isset($template['button']) && is_array($template['button'])) {
            $sanitized['button'] = [
                'showButton' => (bool) ($template['button']['showButton'] ?? false),
                'text' => sanitize_text_field($template['button']['text'] ?? ''),
                'url' => esc_url_raw($template['button']['url'] ?? ''),
                'backgroundColor' => sanitize_hex_color($template['button']['backgroundColor'] ?? '') ?: '#6366f1',
                'textColor' => sanitize_hex_color($template['button']['textColor'] ?? '') ?: '#ffffff',
                'borderRadius' => sanitize_text_field($template['button']['borderRadius'] ?? '8px'),
            ];
        }
        
        // Footer
        if (isset($template['footer']) && is_array($template['footer'])) {
            $sanitized['footer'] = [
                'text' => sanitize_text_field($template['footer']['text'] ?? ''),
                'backgroundColor' => sanitize_hex_color($template['footer']['backgroundColor'] ?? '') ?: '#f8fafc',
                'textColor' => sanitize_hex_color($template['footer']['textColor'] ?? '') ?: '#64748b',
                'showSocialLinks' => (bool) ($template['footer']['showSocialLinks'] ?? false),
                'facebookUrl' => esc_url_raw($template['footer']['facebookUrl'] ?? ''),
                'twitterUrl' => esc_url_raw($template['footer']['twitterUrl'] ?? ''),
                'instagramUrl' => esc_url_raw($template['footer']['instagramUrl'] ?? ''),
            ];
        }
        
        return $sanitized;
    }
    
    /* ================================================================
     * Frontend Appearance (Dashboard + Live Tournaments colors)
     * ================================================================ */
    
    /**
     * Default frontend appearance colors
     */
    private static function get_default_frontend_appearance(): array {
        return [
            'primaryColor'       => '#6366f1',
            'primaryHoverColor'  => '#4f46e5',
            'successColor'       => '#10b981',
            'warningColor'       => '#f59e0b',
            'dangerColor'        => '#ef4444',
            'backgroundColor'    => '#f8fafc',
            'surfaceColor'       => '#ffffff',
            'borderColor'        => '#e2e8f0',
            'textColor'          => '#0f172a',
            'textSecondaryColor' => '#475569',
            'textMutedColor'     => '#94a3b8',
        ];
    }
    
    /**
     * GET frontend appearance settings
     */
    public static function get_frontend_appearance($request) {
        $defaults = self::get_default_frontend_appearance();
        $saved = get_option('battle_ledger_frontend_appearance', []);
        return rest_ensure_response(wp_parse_args($saved, $defaults));
    }
    
    /**
     * POST (save) frontend appearance settings
     */
    public static function update_frontend_appearance($request) {
        $params = $request->get_json_params();
        $defaults = self::get_default_frontend_appearance();
        $sanitized = [];
        
        foreach ($defaults as $key => $default_val) {
            if (isset($params[$key])) {
                $sanitized[$key] = sanitize_hex_color($params[$key]) ?: $default_val;
            } else {
                $sanitized[$key] = $default_val;
            }
        }
        
        update_option('battle_ledger_frontend_appearance', $sanitized);
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Frontend appearance saved successfully', 'battle-ledger'),
            'settings' => $sanitized,
        ]);
    }
}
