<?php

namespace FreePBX\modules;

class Blustartranscription extends \FreePBX_Helpers implements \BMO
{
    private const CONF_FILE = '/etc/asterisk/transcription.conf';

    private const DEFAULTS = [
        'server_host'        => '',
        'server_port'        => '8600',
        'api_key'            => '',
        'enabled'            => 'true',
        'log_retention_days' => '30',
    ];

    public function install()
    {
    }

    public function uninstall()
    {
    }

    public function backup()
    {
    }

    public function restore($backup)
    {
    }

    /** Error message from the last failed save in this request, shown by the view. */
    private ?string $saveError = null;

    public function doConfigPageInit($page)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
            if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $this->saveError = 'Security token missing or expired. Please reload the page and try again.';
                return;
            }
            try {
                $this->saveSettings($_POST);
            } catch (\InvalidArgumentException $e) {
                $this->saveError = $e->getMessage();
            }
        }
    }

    public function getSaveError(): ?string
    {
        return $this->saveError;
    }

    // --- CSRF protection ---

    private const CSRF_SESSION_KEY = 'blustartranscription_csrf';

    public function getCsrfToken(): string
    {
        if (empty($_SESSION[self::CSRF_SESSION_KEY])) {
            $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    public function verifyCsrfToken(string $token): bool
    {
        $expected = $_SESSION[self::CSRF_SESSION_KEY] ?? '';
        return $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    // --- AJAX (FreePBX BMO pattern) ---

    public function ajaxRequest($req, &$setting)
    {
        switch ($req) {
            case 'test_api':
            case 'purge_logs':
                return true;
        }
        return false;
    }

    public function ajaxHandler()
    {
        $command = $_REQUEST['command'] ?? '';

        // State-changing / outbound-request commands must be POSTed with a valid token
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Method not allowed'];
        }
        if (!$this->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'message' => 'Security token missing or expired. Please reload the page and try again.'];
        }

        switch ($command) {
            case 'test_api':
                return $this->testApiConnection();

            case 'purge_logs':
                $deleted = $this->purgeLogs();
                return ['success' => true, 'message' => "Purged {$deleted} old log entries"];

            default:
                return ['success' => false, 'message' => 'Unknown command'];
        }
    }

    // --- Configuration ---

    public function getSettingValue(string $key): string
    {
        $val = $this->getConfig($key);
        if ($val === false || $val === null) {
            return self::DEFAULTS[$key] ?? '';
        }
        return (string) $val;
    }

    public function getAllSettings(): array
    {
        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = $this->getSettingValue($key);
        }
        return $settings;
    }

    /**
     * Validate and persist settings.
     *
     * @throws \InvalidArgumentException on invalid input (nothing is written)
     */
    public function saveSettings(array $post): bool
    {
        $clean = [];
        foreach (self::DEFAULTS as $key => $default) {
            if (isset($post[$key]) && is_string($post[$key])) {
                $clean[$key] = $this->validateSetting($key, trim($post[$key]));
            }
        }

        foreach ($clean as $key => $value) {
            $this->setConfig($key, $value);
        }

        // Radio buttons always send a value, but normalize to 'true'/'false'
        $enabledVal = ($post['enabled'] ?? 'false') === 'true' ? 'true' : 'false';
        $this->setConfig('enabled', $enabledVal);

        $this->writeConfFile();
        needreload();
        return true;
    }

    /**
     * Validate a single setting value. Values end up in a shell-sourced config
     * file and in URLs, so they are restricted to safe character sets.
     */
    private function validateSetting(string $key, string $value): string
    {
        switch ($key) {
            case 'server_host':
                if ($value === '') {
                    throw new \InvalidArgumentException('Server host is required');
                }
                $isIp = filter_var($value, FILTER_VALIDATE_IP) !== false;
                $isHost = strlen($value) <= 253
                    && preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$/', $value) === 1;
                if (!$isIp && !$isHost) {
                    throw new \InvalidArgumentException('Server host must be a valid hostname or IP address');
                }
                return $value;

            case 'server_port':
                if (preg_match('/^\d{1,5}$/', $value) !== 1 || (int) $value < 1 || (int) $value > 65535) {
                    throw new \InvalidArgumentException('Server port must be a number between 1 and 65535');
                }
                return (string) (int) $value;

            case 'log_retention_days':
                if (preg_match('/^\d{1,3}$/', $value) !== 1 || (int) $value < 1 || (int) $value > 365) {
                    throw new \InvalidArgumentException('Log retention must be between 1 and 365 days');
                }
                return (string) (int) $value;

            case 'api_key':
                // Sent as an HTTP header; reject control characters (header injection) and absurd lengths
                if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || strlen($value) > 512) {
                    throw new \InvalidArgumentException('API key contains invalid characters');
                }
                return $value;

            case 'enabled':
                return $value === 'true' ? 'true' : 'false';

            default:
                return $value;
        }
    }

    public function writeConfFile(): void
    {
        $settings = $this->getAllSettings();

        $lines = [
            '# Auto-generated by FreePBX Transcription module',
            '# Do not edit manually — changes will be overwritten',
            '',
        ];

        foreach ($settings as $key => $value) {
            // Shell-safe quoting
            $escaped = str_replace("'", "'\\''", $value);
            $lines[] = strtoupper($key) . "='" . $escaped . "'";
        }

        $content = implode("\n", $lines) . "\n";
        file_put_contents(self::CONF_FILE, $content);
        chmod(self::CONF_FILE, 0640);
        chown(self::CONF_FILE, 'asterisk');
        chgrp(self::CONF_FILE, 'asterisk');
    }

    // --- Logging ---

    public function addLog(string $filename, string $status, string $errorMsg = ''): void
    {
        $db = \FreePBX::Database();
        $stmt = $db->prepare(
            "INSERT INTO transcription_log (filename, status, error_msg) VALUES (?, ?, ?)"
        );
        $stmt->execute([$filename, $status, $errorMsg]);
    }

    public function getLogs(int $page = 1, int $perPage = 50, string $statusFilter = ''): array
    {
        $db = \FreePBX::Database();
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($statusFilter !== '') {
            $where = 'WHERE status = ?';
            $params[] = $statusFilter;
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM transcription_log $where";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // Fetch page — LIMIT/OFFSET must be bound as integers
        $sql = "SELECT * FROM transcription_log $where ORDER BY timestamp DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    public function purgeLogs(): int
    {
        $days = (int) $this->getSettingValue('log_retention_days');
        if ($days <= 0) {
            $days = 30;
        }

        $db = \FreePBX::Database();
        $stmt = $db->prepare(
            "DELETE FROM transcription_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    // --- Connection Testing ---

    public function testApiConnection(): array
    {
        $host = $this->getSettingValue('server_host');
        $port = $this->getSettingValue('server_port');
        $apiKey = $this->getSettingValue('api_key');

        if (empty($host)) {
            return ['success' => false, 'message' => 'Server host is required'];
        }

        $results = [];

        // Test 1: Health endpoint (no auth required)
        $healthUrl = "http://{$host}:{$port}/health";
        $ch = curl_init($healthUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $connectTime = round(curl_getinfo($ch, CURLINFO_CONNECT_TIME) * 1000);
        $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($error) {
            $detail = match ($errno) {
                7       => "Connection refused — is the transcription service running on {$host}:{$port}?",
                6       => "Could not resolve host '{$host}' — check the hostname/IP",
                28      => "Connection timed out after 5s — check firewall rules and that {$host}:{$port} is reachable",
                default => $error,
            };
            return [
                'success' => false,
                'message' => "Cannot reach {$healthUrl} — {$detail}",
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => "Health check returned HTTP {$httpCode} (expected 200). Response: " . substr($response, 0, 200),
            ];
        }

        $results[] = "Health OK ({$totalTime}ms)";

        // Test 2: Webhook auth (send empty body to verify API key works)
        if (!empty($apiKey)) {
            $webhookUrl = "http://{$host}:{$port}/webhook";
            $ch = curl_init($webhookUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '{}',
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-API-KEY: ' . $apiKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 401) {
                $results[] = "API key REJECTED — check the key matches the server's API_KEY";
                return [
                    'success' => false,
                    'message' => implode('. ', $results),
                ];
            } elseif ($httpCode === 400) {
                // 400 = Bad Request means auth passed but payload was invalid (expected)
                $results[] = "API key accepted";
            } else {
                $results[] = "Webhook returned HTTP {$httpCode}: " . substr($response, 0, 200);
            }
        } else {
            $results[] = "API key not configured — skipped auth test";
        }

        return [
            'success' => true,
            'message' => implode('. ', $results),
        ];
    }
}
