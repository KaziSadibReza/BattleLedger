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
     * Get a saved email template merged with defaults.
     */
    public static function get_saved_email_template(string $template_id): ?array {
        $template_id = sanitize_key($template_id);
        $defaults = self::get_default_email_templates();

        if (!isset($defaults[$template_id])) {
            return null;
        }

        $templates = get_option('battle_ledger_email_templates', []);
        if (!isset($templates[$template_id]) || !is_array($templates[$template_id])) {
            return null;
        }

        return wp_parse_args($templates[$template_id], $defaults[$template_id]);
    }

    /**
     * Get email template resolved against defaults.
     * Returns default template when no custom override exists.
     */
    public static function get_email_template(string $template_id): ?array {
        $template_id = sanitize_key($template_id);
        $defaults = self::get_default_email_templates();

        if (!isset($defaults[$template_id])) {
            return null;
        }

        $templates = get_option('battle_ledger_email_templates', []);
        if (isset($templates[$template_id]) && is_array($templates[$template_id])) {
            return wp_parse_args($templates[$template_id], $defaults[$template_id]);
        }

        return $defaults[$template_id];
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
            '{{user_id}}' => '123',
            '{{user_email}}' => 'test@example.com',
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
            '{{deposit_amount}}' => '$250.00',
            '{{order_id}}' => '50',
            '{{wallet_url}}' => home_url('/dashboard/?bl_tab=wallet'),
            '{{request_id}}' => '4',
            '{{withdrawal_amount}}' => '$900.00',
            '{{withdrawal_method}}' => 'bKash',
            '{{admin_note}}' => 'Please update your payout number and try again.',
            '{{admin_panel_url}}' => admin_url('admin.php?page=battleledger#/wallets'),
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
    public static function build_email_html(array $template, array $replacements): string {
        $header = $template['header'] ?? [];
        $body_content = $template['body'] ?? [];
        $button = $template['button'] ?? [];
        $footer = $template['footer'] ?? [];
        
        // Replace variables
        $replace = static function($text) use ($replacements): string {
            $text = is_scalar($text) ? (string) $text : '';
            return str_replace(array_keys($replacements), array_values($replacements), $text);
        };

        // Light markdown support to make template text cleaner in final emails.
        $format_body_content = static function (string $text): string {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
            $text = (string) preg_replace(
                '/`([^`]+)`/',
                '<code style="font-family: Consolas, Monaco, monospace; background: #f3f4f6; color: #1f2937; border-radius: 6px; padding: 2px 6px;">$1</code>',
                $text
            );
            return wp_kses_post(nl2br($text));
        };

        $show_header = !isset($header['showHeader']) || !empty($header['showHeader']);
        $logo_url = esc_url($header['logoUrl'] ?? '');
        $logo_width = esc_attr($header['logoWidth'] ?? '150px');
        $show_footer = !isset($footer['showFooter']) || !empty($footer['showFooter']);

        $subject = $replace($header['subject'] ?? '');
        $title = trim($replace($header['title'] ?? ''));
        if ($title === '') {
            $title = get_bloginfo('name');
        }

        $configured_brand_label = array_key_exists('brandLabel', $header)
            ? trim((string) ($header['brandLabel'] ?? ''))
            : trim((string) get_bloginfo('name'));
        $brand_label = trim($replace($configured_brand_label));
        $logo_alt = $brand_label !== '' ? $brand_label : get_bloginfo('name');
        $notification_label = $brand_label !== '' ? trim($brand_label . ' Notification') : '';

        $body_text_color = esc_attr($body_content['textColor'] ?? '#374151');
        $body_font_size = esc_attr($body_content['fontSize'] ?? '16px');
        $body_background = esc_attr($body_content['backgroundColor'] ?? '#ffffff');
        $body_outer_background = esc_attr($body_content['outerBackgroundColor'] ?? '#eef2f7');
        $body_padding = esc_attr($body_content['contentPadding'] ?? '34px 28px');
        $header_background = esc_attr($header['backgroundColor'] ?? '#6366f1');
        $header_text_color = esc_attr($header['textColor'] ?? '#ffffff');
        $footer_background = esc_attr($footer['backgroundColor'] ?? '#f8fafc');
        $footer_text_color = esc_attr($footer['textColor'] ?? '#64748b');

        $raw_body_content = is_scalar($body_content['content'] ?? '') ? (string) $body_content['content'] : '';

        $content_html = $format_body_content($replace($raw_body_content));

        $preheader_raw = wp_strip_all_tags($replace($raw_body_content));
        $preheader_raw = trim((string) preg_replace('/\s+/', ' ', $preheader_raw));
        if (function_exists('mb_substr')) {
            $preheader = mb_substr($preheader_raw, 0, 130);
        } else {
            $preheader = substr($preheader_raw, 0, 130);
        }

        // Render OTP templates with a dedicated verification-focused layout.
        $otp_code = trim((string) ($replacements['{{otp_code}}'] ?? ''));
        $is_otp_template = $otp_code !== '' && strpos($raw_body_content, '{{otp_code}}') !== false;

        if ($is_otp_template) {
            $otp_characters = preg_split('//u', $otp_code, -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($otp_characters) || empty($otp_characters)) {
                $otp_characters = str_split($otp_code);
            }

            $otp_cells = '';
            foreach ($otp_characters as $character) {
                $otp_cells .= '
                                        <td style="padding: 0 4px;">
                                            <div style="min-width: 44px; height: 56px; line-height: 56px; background: #f4f5f7; border-radius: 10px; text-align: center; font-size: 36px; font-weight: 700; color: ' . $header_background . '; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">' . esc_html((string) $character) . '</div>
                                        </td>';
            }

            $otp_intro_source = $replace($raw_body_content);
            $otp_intro_source = str_replace($otp_code, '', $otp_intro_source);
            $otp_intro_source = str_replace(['{{otp_code}}', '**'], '', $otp_intro_source);
            $otp_intro_source = (string) preg_replace('/This code\s+will\s+expire[^\n\r\.]*[\.\n\r]?/i', '', $otp_intro_source);
            $otp_intro_source = (string) preg_replace('/If you didn.?t request[^\n\r\.]*[\.\n\r]?/i', '', $otp_intro_source);
            $otp_intro_source = trim((string) preg_replace('/\s+/', ' ', wp_strip_all_tags($otp_intro_source)));

            if ($otp_intro_source === '') {
                $otp_intro_source = 'Use the code below to complete verification:';
            }

            $expires_in_text = trim((string) ($replacements['{{expires_in}}'] ?? '10 minutes'));
            if ($expires_in_text === '') {
                $expires_in_text = '10 minutes';
            }

            $otp_brand_line = '';
            if ($brand_label !== '') {
                $otp_brand_line = '<p style="margin: 8px 0 0; font-size: 26px; line-height: 1.3; color: #64748b;">' . esc_html($brand_label) . '</p>';
            }

            $logo_block = '';
            if (!empty($logo_url)) {
                $logo_block = '
                            <div style="margin: 0 0 12px;">
                                <img src="' . $logo_url . '" alt="' . esc_attr($logo_alt) . '" style="display: inline-block; max-width: 100%; width: ' . $logo_width . '; height: auto;" />
                            </div>';
            }

            $footer_text = trim($replace($footer['text'] ?? ''));
            if ($footer_text === '') {
                $footer_text = $brand_label !== ''
                    ? '© ' . gmdate('Y') . ' ' . $brand_label . '. All rights reserved.'
                    : '© ' . gmdate('Y') . '. All rights reserved.';
            }

            return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: ' . $body_outer_background . '; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all; color: transparent;">' . esc_html($preheader) . '</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 36px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 520px; width: 100%; border-collapse: separate; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 28px -24px rgba(0, 0, 0, 0.32);">
                    <tr>
                        <td style="padding: 28px 28px 20px; text-align: center; border-bottom: 1px solid #e5e7eb; background-color: ' . $body_background . ';">' . $logo_block . '
                            <h1 style="margin: 0; font-size: 42px; line-height: 1.1; color: ' . $header_background . '; font-weight: 700; letter-spacing: -0.02em;">' . esc_html($title) . '</h1>
                            ' . $otp_brand_line . '
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px; text-align: center; background-color: ' . $body_background . '; color: ' . $body_text_color . ';">
                            <p style="margin: 0 0 22px; font-size: ' . $body_font_size . '; line-height: 1.6; color: ' . $body_text_color . ';">' . esc_html($otp_intro_source) . '</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto 22px; border-collapse: collapse;">
                                <tr>' . $otp_cells . '
                                </tr>
                            </table>
                            <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #64748b;">This code will expire in <strong>' . esc_html($expires_in_text) . '</strong>.<br>If you didn\'t request this code, please ignore this email.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 28px; text-align: center; background-color: ' . $footer_background . '; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; color: ' . $footer_text_color . '; font-size: 13px; line-height: 1.65;">' . esc_html($footer_text) . '</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        }

        $button_background = esc_attr($button['backgroundColor'] ?? '#6366f1');
        $button_text_color = esc_attr($button['textColor'] ?? '#ffffff');
        $button_radius = esc_attr($button['borderRadius'] ?? '10px');

        $hex_to_rgba = static function (string $hex, float $alpha): string {
            $hex = trim($hex);
            if (strpos($hex, '#') === 0) {
                $hex = substr($hex, 1);
            }

            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }

            if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
                return 'rgba(99, 102, 241, ' . $alpha . ')';
            }

            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            return 'rgba(' . $r . ', ' . $g . ', ' . $b . ', ' . $alpha . ')';
        };

        $inner_card_border = esc_attr($hex_to_rgba($button_background, 0.22));
        $inner_card_background = esc_attr($hex_to_rgba($button_background, 0.05));

        $header_section = '';
        if ($show_header) {
            if (!empty($logo_url)) {
                $header_content = '
                        <img src="' . $logo_url . '" alt="' . esc_attr($logo_alt) . '" style="display: inline-block; max-width: 100%; width: ' . $logo_width . '; height: auto;" />';
            } else {
                $header_subtitle = '';
                if ($notification_label !== '') {
                    $header_subtitle = '<p style="margin: 8px 0 0; color: ' . $header_text_color . '; opacity: 0.82; font-size: 13px;">' . esc_html($notification_label) . '</p>';
                }

                $header_content = '
                        <h1 style="margin: 0; font-size: 30px; line-height: 1.15; color: ' . $header_text_color . '; font-weight: 700; letter-spacing: -0.01em;">' . esc_html($title) . '</h1>
                    ' . $header_subtitle;
            }

            $header_section = '
                <tr>
                    <td style="background-color: ' . $header_background . '; color: ' . $header_text_color . '; padding: 30px 40px; text-align: center;">
                        ' . $header_content . '
                    </td>
                </tr>';
        }

        $button_section = '';
        if (!empty($button['showButton']) && !empty($button['text'])) {
            $button_section = '
                        <div style="text-align: center; margin-top: 30px;">
                            <a href="' . esc_url($replace($button['url'] ?? '#')) . '" style="display: inline-block; background: ' . $button_background . '; color: ' . $button_text_color . '; padding: 14px 32px; border-radius: ' . $button_radius . '; text-decoration: none; font-weight: 600; font-size: 16px;">
                                ' . esc_html($replace($button['text'])) . '
                            </a>
                        </div>';
        }

        $social_icons = '';
        if (!empty($footer['showSocialLinks'])) {
            $social_links = [];

            if (!empty($footer['twitterUrl'])) {
                $social_links[] = '<a href="' . esc_url($footer['twitterUrl']) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin: 0 8px; opacity: 0.7; text-decoration: none; color: ' . $footer_text_color . ';">Twitter</a>';
            }

            if (!empty($footer['instagramUrl'])) {
                $social_links[] = '<a href="' . esc_url($footer['instagramUrl']) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin: 0 8px; opacity: 0.7; text-decoration: none; color: ' . $footer_text_color . ';">Instagram</a>';
            }

            if (!empty($footer['facebookUrl'])) {
                $social_links[] = '<a href="' . esc_url($footer['facebookUrl']) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin: 0 8px; opacity: 0.7; text-decoration: none; color: ' . $footer_text_color . ';">Facebook</a>';
            }

            if (!empty($social_links)) {
                $social_icons = '
                    <div style="margin-bottom: 20px;">' . implode('', $social_links) . '</div>';
            }
        }

        $footer_section = '';
        if ($show_footer) {
            $footer_text = trim($replace($footer['text'] ?? ''));
            if ($footer_text === '') {
                $footer_text = $brand_label !== ''
                    ? '© ' . gmdate('Y') . ' ' . $brand_label . '. All rights reserved.'
                    : '© ' . gmdate('Y') . '. All rights reserved.';
            }

            $footer_section = '
                <tr>
                    <td style="background: ' . $footer_background . '; color: ' . $footer_text_color . '; padding: 30px 40px; text-align: center; font-size: 13px; border-top: 1px solid #e5e7eb;">
                        ' . $social_icons . '
                        <p style="margin: 0;">' . esc_html($footer_text) . '</p>
                    </td>
                </tr>';
        }

        $notification_label_block = '';
        if ($notification_label !== '') {
            $notification_label_block = '<p style="margin: 0 0 8px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: ' . $footer_text_color . '; font-weight: 600;">' . esc_html($notification_label) . '</p>';
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html($subject) . '</title>
</head>
<body style="margin: 0; padding: 0; background-color: ' . $body_outer_background . '; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; mso-hide: all; color: transparent;">' . esc_html($preheader) . '</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 24px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%; border-collapse: separate; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 20px 30px -24px rgba(15, 23, 42, 0.35);">
                    ' . $header_section . '
                    <tr>
                        <td style="background: ' . $body_background . '; color: ' . $body_text_color . '; padding: ' . $body_padding . ';">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: separate; border: 1px solid ' . $inner_card_border . '; border-radius: 14px; background: ' . $inner_card_background . ';">
                                <tr>
                                    <td style="padding: 22px 20px;">
                                        ' . $notification_label_block . '
                                        <h2 style="margin: 0 0 16px; font-size: ' . esc_attr($header['titleSize'] ?? '28px') . '; line-height: 1.25; color: ' . $body_text_color . '; font-weight: 700;">' . esc_html($title) . '</h2>
                                        <div style="font-size: ' . $body_font_size . '; line-height: 1.7; color: ' . $body_text_color . ';">' . $content_html . '</div>
                                    </td>
                                </tr>
                            </table>
                            ' . $button_section . '
                        </td>
                    </tr>
                    ' . $footer_section . '
                </table>
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
                    'brandLabel' => $site_name,
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
            'wallet_deposit_confirmed' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Wallet Deposit Confirmed - {{deposit_amount}}',
                    'title' => 'Deposit Confirmed',
                    'backgroundColor' => '#10b981',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                    'brandLabel' => $site_name,
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nGreat news - your wallet deposit has been successfully completed.\n\n**Amount:** {{deposit_amount}}\n**Order ID:** #{{order_id}}\n\nYour wallet balance has been updated and the funds are now ready to use.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Open Wallet',
                    'url' => '{{wallet_url}}',
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
            'withdrawal_request_admin' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'BattleLedger: New Withdrawal Request #{{request_id}}',
                    'title' => 'New Withdrawal Request',
                    'backgroundColor' => '#f59e0b',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                    'brandLabel' => $site_name,
                ],
                'body' => [
                    'content' => "Hello Admin,\n\nA new withdrawal request has been submitted and requires processing.\n\n**Request ID:** #{{request_id}}\n**User:** {{user_name}} (ID: {{user_id}})\n**Email:** {{user_email}}\n**Amount:** {{withdrawal_amount}}\n**Method:** {{withdrawal_method}}\n\nPlease review this request from the wallet management panel.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Review Request',
                    'url' => '{{admin_panel_url}}',
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
            'withdrawal_approved_user' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Withdrawal Approved - {{withdrawal_amount}}',
                    'title' => 'Withdrawal Approved',
                    'backgroundColor' => '#10b981',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                    'brandLabel' => $site_name,
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nYour withdrawal request has been approved.\n\n**Request ID:** #{{request_id}}\n**Amount:** {{withdrawal_amount}}\n**Method:** {{withdrawal_method}}\n\nOur team is now processing your payout.",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Open Wallet',
                    'url' => '{{wallet_url}}',
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
            'withdrawal_cancelled_user' => [
                'enabled' => true,
                'header' => [
                    'subject' => 'Withdrawal Cancelled - {{withdrawal_amount}}',
                    'title' => 'Withdrawal Cancelled',
                    'backgroundColor' => '#ef4444',
                    'textColor' => '#ffffff',
                    'titleSize' => '28px',
                    'brandLabel' => $site_name,
                ],
                'body' => [
                    'content' => "Hi {{user_name}},\n\nYour withdrawal request was cancelled.\n\n**Request ID:** #{{request_id}}\n**Amount:** {{withdrawal_amount}}\n**Method:** {{withdrawal_method}}\n\nThe amount remains available in your wallet for future use.\n\n**Admin Note:** {{admin_note}}",
                    'backgroundColor' => '#ffffff',
                    'textColor' => '#374151',
                    'fontSize' => '16px',
                ],
                'button' => [
                    'showButton' => true,
                    'text' => 'Open Wallet',
                    'url' => '{{wallet_url}}',
                    'backgroundColor' => '#ef4444',
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
                'showHeader' => (bool) ($template['header']['showHeader'] ?? true),
                'logoUrl' => esc_url_raw($template['header']['logoUrl'] ?? ''),
                'logoWidth' => sanitize_text_field($template['header']['logoWidth'] ?? '150px'),
                'brandLabel' => sanitize_text_field($template['header']['brandLabel'] ?? ''),
            ];
        }
        
        // Body
        if (isset($template['body']) && is_array($template['body'])) {
            $sanitized['body'] = [
                'content' => wp_kses_post($template['body']['content'] ?? ''),
                'backgroundColor' => sanitize_hex_color($template['body']['backgroundColor'] ?? '') ?: '#ffffff',
                'outerBackgroundColor' => sanitize_hex_color($template['body']['outerBackgroundColor'] ?? '') ?: '#eef2f7',
                'textColor' => sanitize_hex_color($template['body']['textColor'] ?? '') ?: '#374151',
                'fontSize' => sanitize_text_field($template['body']['fontSize'] ?? '16px'),
                'contentPadding' => sanitize_text_field($template['body']['contentPadding'] ?? '34px 28px'),
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
                'showFooter' => (bool) ($template['footer']['showFooter'] ?? true),
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
