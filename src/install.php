<?php

global $db;

// Create transcription_log table for tracking transfer history
$sql = "CREATE TABLE IF NOT EXISTS transcription_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    filename VARCHAR(512) NOT NULL,
    status ENUM('success', 'error', 'scp_failed', 'webhook_failed') NOT NULL,
    error_msg TEXT NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$db->query($sql);

$agiBin = '/var/lib/asterisk/agi-bin';

// Copy upload script to Asterisk agi-bin directory
$src = __DIR__ . '/agi-bin/transcription-upload.sh';
$dst = $agiBin . '/transcription-upload.sh';

if (file_exists($src)) {
    copy($src, $dst);
    chmod($dst, 0755);
    chown($dst, 'asterisk');
    chgrp($dst, 'asterisk');
}

// Copy log helper script
$logSrc = __DIR__ . '/agi-bin/transcription-log.php';
$logDst = $agiBin . '/transcription-log.php';

if (file_exists($logSrc)) {
    copy($logSrc, $logDst);
    chmod($logDst, 0755);
    chown($logDst, 'asterisk');
    chgrp($logDst, 'asterisk');
}

// Hook into FreePBX's recording system via MIXMON_POST global variable.
// When set, FreePBX's sub-record-check passes this as the post-recording
// command to MixMonitor, which runs it after each recording completes.
// Custom globals go in globals_custom.conf (auto-included by Asterisk).
$globalsFile = '/etc/asterisk/globals_custom.conf';
$marker = '; transcription-module';
// ^{MIXMONITOR_FILENAME} is MixMonitor's deferred variable syntax —
// it expands to the full recording path when the post-recording command runs.
$line = "MIXMON_POST = $dst ^{MIXMONITOR_FILENAME} $marker";

// Read existing globals, preserve other content
$existing = file_exists($globalsFile) ? file_get_contents($globalsFile) : '';

// Remove any previous transcription-module line
$lines = array_filter(
    explode("\n", $existing),
    fn($l) => !str_contains($l, $marker)
);

// Append our MIXMON_POST line
$lines[] = $line;
$content = implode("\n", array_filter($lines, fn($l) => $l !== '')) . "\n";
file_put_contents($globalsFile, $content);
chown($globalsFile, 'asterisk');
chgrp($globalsFile, 'asterisk');
