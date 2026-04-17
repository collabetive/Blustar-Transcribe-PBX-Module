#!/usr/bin/php
<?php
// CLI helper for logging transcription transfer results to the database.
// Usage: transcription-log.php <filename> <status> [error_message]
// Called by transcription-upload.sh to avoid shell injection via inline PHP.

if ($argc < 3) {
    fwrite(STDERR, "Usage: transcription-log.php <filename> <status> [error_message]\n");
    exit(1);
}

$filename = $argv[1];
$status   = $argv[2];
$errorMsg = $argv[3] ?? '';

require '/etc/freepbx.conf';
\FreePBX::Transcription()->addLog($filename, $status, $errorMsg);
