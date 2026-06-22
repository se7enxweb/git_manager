<?php
/**
 * AGPLDumpSanitizer
 *
 * Sanitizes SQL database dumps to make them safe for AGPL-compatible public sharing.
 * Removes or replaces all private, personally identifiable, and security-sensitive data
 * so that the dump can be shared with other developers per GPL/AGPL obligations without
 * exposing private user data, credentials, API keys, or server infrastructure details.
 *
 * Replacement values use long, uppercase, underscore-separated strings so recipients
 * immediately know what must be replaced before the dump can be used.
 *
 * @package GitManager
 * @author  SE7ENX
 * @date    2026-06-21
 */
class AGPLDumpSanitizer
{
    /** @var array Log of sanitization actions taken */
    private $log = [];

    /**
     * Tables whose INSERT rows are fully removed (structure preserved).
     * Session and audit data has no value for code sharing.
     */
    private $excludedTables = [
        'ezsession',
        'ezaudit',
        'ezpending_actions',
        'eznotificationcollection',
        'eznotificationcollection_item',
    ];

    /**
     * Sanitize a SQL dump file and write the result to a new AGPL-compatible file.
     *
     * @param string $inputFile  Path to the raw SQL dump file
     * @param string $outputFile Path to write sanitized output (null = auto)
     * @return array Result with success, file path, size, and log entries
     */
    public function sanitize( $inputFile, $outputFile = null )
    {
        if( !file_exists( $inputFile ) ) {
            return [ 'success' => false, 'message' => "File not found: {$inputFile}" ];
        }

        if( $outputFile === null ) {
            $outputFile = preg_replace( '/\.sql$/', '_agpl_compatible.sql', $inputFile );
        }

        $content = file_get_contents( $inputFile );
        if( $content === false ) {
            return [ 'success' => false, 'message' => 'Cannot read input file' ];
        }

        // Run all sanitization passes in order (most specific first)
        $content = $this->removeExcludedTableData( $content );
        $content = $this->sanitizeUserPasswords( $content );
        $content = $this->sanitizeEmailAddresses( $content );
        $content = $this->sanitizeGoogleAnalyticsIds( $content );
        $content = $this->sanitizeStripeKeys( $content );
        $content = $this->sanitizeRecaptchaKeys( $content );
        $content = $this->sanitizeGenericApiKeys( $content );
        $content = $this->sanitizeSettingValues( $content );
        $content = $this->sanitizeDiskPaths( $content );
        $content = $this->sanitizeDatabaseCredentials( $content );
        $content = $this->sanitizeIpAddresses( $content );
        $content = $this->sanitizeSmtpCredentials( $content );

        $result = file_put_contents( $outputFile, $this->buildHeader() . $content );
        if( $result === false ) {
            return [ 'success' => false, 'message' => 'Cannot write output file' ];
        }

        return [
            'success' => true,
            'file'    => $outputFile,
            'size'    => filesize( $outputFile ),
            'log'     => $this->log,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sanitization Passes
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Remove all INSERT rows for tables that should never be shared.
     */
    private function removeExcludedTableData( $content )
    {
        foreach( $this->excludedTables as $table ) {
            $before = strlen( $content );
            $content = preg_replace(
                "/^INSERT INTO `?{$table}`?\s[^\n]+\n/im",
                "-- [{$table}: all rows removed for AGPL compatibility]\n",
                $content
            );
            if( strlen( $content ) !== $before ) {
                $this->log[] = "Removed INSERT data from table: {$table}";
            }
        }
        return $content;
    }

    /**
     * Anonymize password hashes in ezuser INSERT statements.
     * Covers bcrypt ($2y$...), MD5 (32 hex chars), and SHA1/SHA256 lengths.
     */
    private function sanitizeUserPasswords( $content )
    {
        $patterns = [
            // bcrypt
            "/'\\\$2[ayb]\\\$[0-9]{2}\\\$[A-Za-z0-9\\.\/]{53}'/",
            // MD5 / SHA1 / SHA256 hex strings in quoted context (32–64 chars)
            "/'([0-9a-f]{32,64})'/i",
        ];

        $count = 0;
        foreach( $patterns as $p ) {
            $content = preg_replace(
                $p,
                "'REPLACE_WITH_YOUR_HASHED_PASSWORD_VALUE_DO_NOT_DISTRIBUTE_OR_USE_AS_IS'",
                $content, -1, $c
            );
            $count += (int)$c;
        }

        if( $count > 0 ) $this->log[] = "Replaced {$count} password hash(es)";
        return $content;
    }

    /**
     * Replace email addresses with a clearly-marked placeholder.
     */
    private function sanitizeEmailAddresses( $content )
    {
        $content = preg_replace(
            "/'[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}'/",
            "'REPLACE_WITH_YOUR_VALID_EMAIL_ADDRESS_AT_YOUR_OWN_DOMAIN_DOT_TLD'",
            $content, -1, $count
        );
        if( (int)$count > 0 ) $this->log[] = "Replaced {$count} email address(es)";
        return $content;
    }

    /**
     * Replace all Google Analytics / Tag Manager tracking IDs.
     * Scans both UA- (Universal), G- (GA4), and GTM- formats.
     */
    private function sanitizeGoogleAnalyticsIds( $content )
    {
        $replacements = [
            '/\bUA-[0-9]{5,12}-[0-9]{1,4}\b/i'    => 'UA-YOUR_GOOGLE_ANALYTICS_UNIVERSAL_TRACKING_ID_REPLACE_BEFORE_USE',
            '/\bG-[A-Z0-9]{6,12}\b/'               => 'G-YOUR_GA4_MEASUREMENT_ID_REPLACE_BEFORE_USE',
            '/\bGTM-[A-Z0-9]{4,8}\b/'              => 'GTM-YOUR_GOOGLE_TAG_MANAGER_CONTAINER_ID_REPLACE_BEFORE_USE',
            '/\bAW-[0-9]{8,12}\b/'                 => 'AW-YOUR_GOOGLE_ADS_CONVERSION_ID_REPLACE_BEFORE_USE',
        ];

        $total = 0;
        foreach( $replacements as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content, -1, $c );
            $total += (int)$c;
        }
        if( $total > 0 ) $this->log[] = "Replaced {$total} Google tracking ID(s)";
        return $content;
    }

    /**
     * Replace Stripe live and test API keys.
     */
    private function sanitizeStripeKeys( $content )
    {
        $replacements = [
            '/\bsk_live_[a-zA-Z0-9]{20,60}\b/' => 'sk_live_REPLACE_WITH_YOUR_STRIPE_SECRET_KEY_LIVE_NOT_FOR_DISTRIBUTION',
            '/\bpk_live_[a-zA-Z0-9]{20,60}\b/' => 'pk_live_REPLACE_WITH_YOUR_STRIPE_PUBLISHABLE_KEY_LIVE_NOT_FOR_DISTRIBUTION',
            '/\bsk_test_[a-zA-Z0-9]{20,60}\b/' => 'sk_test_REPLACE_WITH_YOUR_STRIPE_SECRET_KEY_TEST_NOT_FOR_DISTRIBUTION',
            '/\bpk_test_[a-zA-Z0-9]{20,60}\b/' => 'pk_test_REPLACE_WITH_YOUR_STRIPE_PUBLISHABLE_KEY_TEST_NOT_FOR_DISTRIBUTION',
            '/\brk_live_[a-zA-Z0-9]{20,60}\b/' => 'rk_live_REPLACE_WITH_YOUR_STRIPE_RESTRICTED_KEY_NOT_FOR_DISTRIBUTION',
        ];

        $total = 0;
        foreach( $replacements as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content, -1, $c );
            $total += (int)$c;
        }
        if( $total > 0 ) $this->log[] = "Replaced {$total} Stripe API key(s)";
        return $content;
    }

    /**
     * Replace reCAPTCHA v2/v3 and hCaptcha site/secret keys.
     */
    private function sanitizeRecaptchaKeys( $content )
    {
        // reCAPTCHA keys are 40-char alphanumeric strings near known INI key names
        $keywordPatterns = [
            'SiteKey', 'SecretKey', 'site_key', 'secret_key',
            'PublicKey', 'PrivateKey', 'public_key', 'private_key',
        ];

        $count = 0;
        foreach( $keywordPatterns as $kw ) {
            $content = preg_replace(
                "/('{$kw}'[^']*|{$kw}\s*=\s*)'([0-9A-Za-z_\-]{30,60})'/",
                "$1'REPLACE_WITH_YOUR_" . strtoupper( preg_replace('/[^A-Z0-9]/i','_',$kw) ) . "_VALUE_NOT_FOR_DISTRIBUTION'",
                $content, -1, $c
            );
            $count += (int)$c;
        }
        if( $count > 0 ) $this->log[] = "Replaced {$count} reCAPTCHA/hCaptcha key(s)";
        return $content;
    }

    /**
     * Replace generic long API key patterns (SendGrid SG.*, AWS, etc.)
     */
    private function sanitizeGenericApiKeys( $content )
    {
        $replacements = [
            '/\bSG\.[a-zA-Z0-9_\-]{20,80}\b/'        => 'SG.REPLACE_WITH_YOUR_SENDGRID_API_KEY_NOT_FOR_DISTRIBUTION',
            '/\bAKIA[A-Z0-9]{16}\b/'                  => 'AKIA_REPLACE_WITH_YOUR_AWS_ACCESS_KEY_ID_NOT_FOR_DISTRIBUTION',
            '/(?<=\b)[a-zA-Z0-9]{20}\/[a-zA-Z0-9+\/]{40}(?=\b)/' => 'REPLACE_WITH_YOUR_AWS_SECRET_ACCESS_KEY_NOT_FOR_DISTRIBUTION',
            // PayPal client IDs (long alphanumeric near PayPal context)
            '/\bEJ[a-zA-Z0-9_\-]{60,80}\b/'           => 'REPLACE_WITH_YOUR_PAYPAL_CLIENT_ID_NOT_FOR_DISTRIBUTION',
        ];

        $total = 0;
        foreach( $replacements as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content, -1, $c );
            $total += (int)$c;
        }
        if( $total > 0 ) $this->log[] = "Replaced {$total} generic API key(s)";
        return $content;
    }

    /**
     * Sanitize settings values stored in DB (ezsite_data, ezini, override tables).
     * Targets named setting keys associated with credentials or private config.
     */
    private function sanitizeSettingValues( $content )
    {
        $sensitiveKeys = [
            'Password', 'password', 'SMTPPassword', 'MailPassword', 'DatabasePassword',
            'SecretKey', 'secret_key', 'ApiKey', 'api_key', 'APIKey',
            'WebhookSecret', 'webhook_secret', 'AuthToken', 'auth_token',
            'AccessToken', 'access_token', 'ClientSecret', 'client_secret',
            'PrivateKey', 'private_key', 'SigningKey', 'signing_key',
        ];

        $count = 0;
        foreach( $sensitiveKeys as $key ) {
            $safe = strtoupper( preg_replace( '/[^A-Z0-9]/i', '_', $key ) );
            // Matches: 'KeyName','value' or KeyName='value' or "KeyName","value"
            $content = preg_replace(
                "/(['\"]?{$key}['\"]?\s*[=,]\s*)['\"]([^'\"]{4,})['\"](?=[,)])/",
                "$1'REPLACE_WITH_YOUR_{$safe}_VALUE_NOT_FOR_DISTRIBUTION'",
                $content, -1, $c
            );
            $count += (int)$c;
        }

        if( $count > 0 ) $this->log[] = "Replaced {$count} sensitive settings value(s)";
        return $content;
    }

    /**
     * Replace absolute server disk paths that reveal infrastructure.
     */
    private function sanitizeDiskPaths( $content )
    {
        $prefixes = [ '/var/www', '/home/', '/srv/', '/opt/', '/data/', '/web/', '/vhosts/' ];
        $count = 0;
        foreach( $prefixes as $prefix ) {
            $escaped = preg_quote( $prefix, '/' );
            $content = preg_replace(
                "/{$escaped}[a-zA-Z0-9._\-\/]*/",
                '/REPLACE_WITH_YOUR_SERVER_ABSOLUTE_INSTALLATION_PATH',
                $content, -1, $c
            );
            $count += (int)$c;
        }
        if( $count > 0 ) $this->log[] = "Replaced {$count} absolute disk path(s)";
        return $content;
    }

    /**
     * Remove database credentials if accidentally embedded in dump comments or SET statements.
     */
    private function sanitizeDatabaseCredentials( $content )
    {
        $patterns = [
            "/(password|passwd|pwd)\s*=\s*'[^']+'/i"  => "$1='REPLACE_WITH_YOUR_DATABASE_PASSWORD_VALUE_HERE_NOT_FOR_DISTRIBUTION'",
            '/(password|passwd|pwd)\s*=\s*"[^"]+"/i'  => '$1="REPLACE_WITH_YOUR_DATABASE_PASSWORD_VALUE_HERE_NOT_FOR_DISTRIBUTION"',
        ];

        $count = 0;
        foreach( $patterns as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content, -1, $c );
            $count += (int)$c;
        }
        if( $count > 0 ) $this->log[] = "Replaced {$count} database credential(s)";
        return $content;
    }

    /**
     * Redact IP addresses stored in user/session data.
     */
    private function sanitizeIpAddresses( $content )
    {
        // IPv4 inside SQL quoted strings
        $content = preg_replace(
            "/'(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'/",
            "'0_0_0_0_IP_ADDRESS_REDACTED_FOR_USER_PRIVACY'",
            $content, -1, $count
        );
        if( (int)$count > 0 ) $this->log[] = "Replaced {$count} IP address(es)";
        return $content;
    }

    /**
     * Sanitize SMTP credentials stored in settings tables.
     */
    private function sanitizeSmtpCredentials( $content )
    {
        $patterns = [
            "/(SMTPUser|smtp_user|MailUser|mail_user)['\",=\s]+['\"]([^'\"]{2,})['\"]/"
                => "$1='REPLACE_WITH_YOUR_SMTP_USERNAME_OR_EMAIL_NOT_FOR_DISTRIBUTION'",
            "/(SMTPPassword|smtp_password|MailPassword|mail_password)['\",=\s]+['\"]([^'\"]{2,})['\"]/"
                => "$1='REPLACE_WITH_YOUR_SMTP_PASSWORD_VALUE_NOT_FOR_DISTRIBUTION'",
            "/(SMTPHost|smtp_host|MailHost|mail_host)['\",=\s]+['\"]([^'\"]{4,})['\"]/"
                => "$1='REPLACE_WITH_YOUR_SMTP_HOST_SERVER_ADDRESS_NOT_FOR_DISTRIBUTION'",
        ];

        $count = 0;
        foreach( $patterns as $pattern => $replacement ) {
            $content = preg_replace( $pattern, $replacement, $content, -1, $c );
            $count += (int)$c;
        }
        if( $count > 0 ) $this->log[] = "Replaced {$count} SMTP credential(s)";
        return $content;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildHeader()
    {
        $date = date( 'Y-m-d H:i:s' );
        return
            "-- ================================================================\n" .
            "-- AGPL COMPATIBLE DUMP — SANITIZED FOR PUBLIC SHARING\n" .
            "-- Generated : {$date}\n" .
            "-- Generator : AGPLDumpSanitizer / Git Manager (SE7ENX / 7x)\n" .
            "-- ----------------------------------------------------------------\n" .
            "-- This dump has been automatically sanitized. The following data\n" .
            "-- categories have been removed or replaced with placeholder values:\n" .
            "--\n" .
            "--   ✗ User password hashes (ezuser table)\n" .
            "--   ✗ User email addresses\n" .
            "--   ✗ Server IP addresses (session/user data)\n" .
            "--   ✗ Absolute server disk paths\n" .
            "--   ✗ Database credentials\n" .
            "--   ✗ SMTP credentials (host, user, password)\n" .
            "--   ✗ Google Analytics / GA4 / GTM / Google Ads tracking IDs\n" .
            "--   ✗ Stripe API keys (live and test)\n" .
            "--   ✗ SendGrid / AWS / PayPal API keys\n" .
            "--   ✗ reCAPTCHA / hCaptcha site and secret keys\n" .
            "--   ✗ Named settings: Password, SecretKey, ApiKey, AuthToken, etc.\n" .
            "--   ✗ Session table data (ezsession, ezaudit, etc.)\n" .
            "--\n" .
            "-- All replaced values use the format:\n" .
            "--   REPLACE_WITH_YOUR_DESCRIPTIVE_NAME_NOT_FOR_DISTRIBUTION\n" .
            "--\n" .
            "-- Search for 'REPLACE_WITH_YOUR' to find all values needing update.\n" .
            "-- DO NOT use this dump on a live server without replacing all values.\n" .
            "-- ================================================================\n\n";
    }

    /**
     * Scan INI override files for Google Analytics keys (for pre-flight warning).
     *
     * @param string $basePath eZ Publish root directory
     * @return array Found tracking IDs
     */
    public static function scanForAnalyticsKeys( $basePath )
    {
        $found = [];
        $searchPaths = array_merge(
            glob( $basePath . '/settings/override/bcwebsitestatistics.ini*' ) ?: [],
            glob( $basePath . '/extension/*/settings/override/bcwebsitestatistics.ini*' ) ?: [],
            glob( $basePath . '/var/*/override/bcwebsitestatistics.ini*' ) ?: []
        );

        foreach( $searchPaths as $file ) {
            if( !is_readable( $file ) ) continue;
            $text = file_get_contents( $file );
            if( preg_match_all( '/\b(?:UA-[0-9]+-[0-9]+|G-[A-Z0-9]+|GTM-[A-Z0-9]+|AW-[0-9]+)\b/i', $text, $m ) ) {
                $found = array_merge( $found, $m[0] );
            }
        }

        return array_unique( $found );
    }

    public function getLog()
    {
        return $this->log;
    }
}

?>