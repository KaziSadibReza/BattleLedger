<?php
/**
 * OTP Manager for Email Verification
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class OTPManager {
    
    /**
     * Email template types
     */
    const EMAIL_VERIFICATION = 'verification';
    const EMAIL_LOGIN = 'login';
    const EMAIL_PASSWORD_RESET = 'password_reset';
    
    /**
     * Send OTP to email
     */
    public static function send_otp(string $email, string $type = self::EMAIL_VERIFICATION): array {
        $email = Security::sanitize_email($email);
        
        if (!Security::validate_email($email)) {
            return [
                'success' => false,
                'error' => 'Invalid email address.',
            ];
        }
        
        // Check rate limit
        $rate_check = Security::check_rate_limit($email, 'otp_send');
        if (!$rate_check['allowed']) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Too many OTP requests. Please wait %d minutes and try again.',
                    ceil($rate_check['reset_time'] / 60)
                ),
            ];
        }
        
        // Check if OTP already exists and is still valid
        $existing = Security::get_otp_data($email);
        if ($existing) {
            $resend_cooldown = 60; // 60 seconds between resends
            $elapsed = time() - $existing['created'];
            
            if ($elapsed < $resend_cooldown) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Please wait %d seconds before requesting a new code.',
                        $resend_cooldown - $elapsed
                    ),
                ];
            }
        }
        
        // Generate OTP
        $otp = Security::generate_otp();
        
        // Store OTP
        if (!Security::store_otp($email, $otp)) {
            return [
                'success' => false,
                'error' => 'Failed to generate verification code. Please try again.',
            ];
        }
        
        // Send email
        $email_sent = self::send_otp_email($email, $otp, $type);
        
        if (!$email_sent) {
            Security::delete_otp($email);
            return [
                'success' => false,
                'error' => 'Failed to send verification email. Please try again.',
            ];
        }
        
        // Record rate limit
        Security::record_rate_limit($email, 'otp_send');
        
        Security::log_auth_event('otp_sent', 0, [
            'email' => $email,
            'type' => $type,
        ]);
        
        $expiry = AuthSettings::get('otp_expiry_minutes', 10);
        
        return [
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in' => $expiry * 60,
        ];
    }
    
    /**
     * Verify OTP
     * 
     * @param string $email User email
     * @param string $otp OTP code
     * @param bool $consume If true, delete OTP after validation. If false, mark as verified.
     */
    public static function verify_otp(string $email, string $otp, bool $consume = false): array {
        $email = Security::sanitize_email($email);
        $otp = sanitize_text_field(trim($otp));
        
        if (!Security::validate_email($email)) {
            return [
                'success' => false,
                'error' => 'Invalid email address.',
            ];
        }
        
        if (empty($otp)) {
            return [
                'success' => false,
                'error' => 'Please enter the verification code.',
            ];
        }
        
        // Check rate limit for verification attempts
        $rate_check = Security::check_rate_limit($email, 'otp_verify');
        if (!$rate_check['allowed']) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Too many verification attempts. Please wait %d minutes.',
                    ceil($rate_check['reset_time'] / 60)
                ),
            ];
        }
        
        // Validate OTP (pass consume flag to Security::validate_otp)
        $result = Security::validate_otp($email, $otp, $consume);
        
        if (!$result['valid']) {
            Security::record_rate_limit($email, 'otp_verify');
            
            Security::log_auth_event('otp_verify_failed', 0, [
                'email' => $email,
            ]);
            
            return [
                'success' => false,
                'error' => $result['error'],
            ];
        }
        
        // Clear rate limits on success
        Security::clear_rate_limit($email, 'otp_verify');
        Security::clear_rate_limit($email, 'otp_send');
        
        Security::log_auth_event('otp_verified', 0, [
            'email' => $email,
        ]);
        
        return [
            'success' => true,
            'message' => 'Email verified successfully.',
        ];
    }
    
    /**
     * OTP Login - Verify and authenticate user
     */
    public static function otp_login(string $email, string $otp): array {
        // Consume OTP on login (one-time use)
        $verify_result = self::verify_otp($email, $otp, true);
        
        if (!$verify_result['success']) {
            return $verify_result;
        }
        
        $email = Security::sanitize_email($email);
        $user = get_user_by('email', $email);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'No account found with this email address.',
            ];
        }
        
        Security::log_auth_event('otp_login', $user->ID, [
            'email' => $email,
        ]);
        
        return [
            'success' => true,
            'user_id' => $user->ID,
        ];
    }
    
    /**
     * Send OTP email
     */
    private static function send_otp_email(string $email, string $otp, string $type): bool {
        $site_name = get_bloginfo('name');
        $expiry = AuthSettings::get('otp_expiry_minutes', 10);
        
        switch ($type) {
            case self::EMAIL_LOGIN:
                $subject = sprintf('[%s] Your Login Code', $site_name);
                $heading = 'Login Verification';
                $message = 'Use the code below to log in to your account:';
                break;
            
            case self::EMAIL_PASSWORD_RESET:
                $subject = sprintf('[%s] Password Reset Code', $site_name);
                $heading = 'Password Reset';
                $message = 'Use the code below to reset your password:';
                break;
            
            case self::EMAIL_VERIFICATION:
            default:
                $subject = sprintf('[%s] Verify Your Email', $site_name);
                $heading = 'Email Verification';
                $message = 'Use the code below to verify your email address:';
                break;
        }
        
        $html_content = self::get_email_template($otp, $heading, $message, $expiry);
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
        ];
        
        // Allow filtering email parameters
        $email_params = apply_filters('battleledger_otp_email', [
            'to' => $email,
            'subject' => $subject,
            'message' => $html_content,
            'headers' => $headers,
        ], $type, $otp);
        
        return wp_mail(
            $email_params['to'],
            $email_params['subject'],
            $email_params['message'],
            $email_params['headers']
        );
    }
    
    /**
     * Get email template
     */
    private static function get_email_template(string $otp, string $heading, string $message, int $expiry): string {
        $site_name = get_bloginfo('name');
        $primary_color = '#d6336c';
        
        // Split OTP into individual digits for styling
        $otp_digits = str_split($otp);
        $otp_html = '';
        foreach ($otp_digits as $digit) {
            $otp_html .= sprintf(
                '<span style="display: inline-block; width: 45px; height: 55px; line-height: 55px; text-align: center; font-size: 28px; font-weight: bold; color: %s; background: #f8f9fa; border-radius: 8px; margin: 0 4px; font-family: monospace;">%s</span>',
                $primary_color,
                esc_html($digit)
            );
        }
        
        return sprintf(
            '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen, Ubuntu, sans-serif; background-color: #f4f4f5;">
    <table role="presentation" style="width: 100%%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="width: 100%%; max-width: 480px; border-collapse: collapse; background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 32px 32px 24px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                            <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: %1$s;">%2$s</h1>
                            <p style="margin: 8px 0 0; font-size: 14px; color: #71717a;">%3$s</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 24px; font-size: 15px; line-height: 1.6; color: #3f3f46; text-align: center;">
                                %4$s
                            </p>
                            
                            <!-- OTP Code -->
                            <div style="text-align: center; margin: 24px 0;">
                                %5$s
                            </div>
                            
                            <p style="margin: 24px 0 0; font-size: 13px; line-height: 1.5; color: #71717a; text-align: center;">
                                This code will expire in <strong>%6$d minutes</strong>.<br>
                                If you didn\'t request this code, please ignore this email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 32px; text-align: center; background: #fafafa; border-radius: 0 0 16px 16px;">
                            <p style="margin: 0; font-size: 12px; color: #a1a1aa;">
                                © %7$d %8$s. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>',
            esc_attr($primary_color),
            esc_html($heading),
            esc_html($site_name),
            esc_html($message),
            $otp_html,
            $expiry,
            gmdate('Y'),
            esc_html($site_name)
        );
    }
}
