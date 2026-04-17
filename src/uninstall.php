<?php

global $db;

// Remove our MIXMON_POST line from globals_custom.conf
$globalsFile = '/etc/asterisk/globals_custom.conf';
$marker = '; transcription-module';

if (file_exists($globalsFile)) {
    $existing = file_get_contents($globalsFile);
    $lines = array_filter(
        explode("\n", $existing),
        fn($l) => !str_contains($l, $marker)
    );
    $content = implode("\n", array_filter($lines, fn($l) => $l !== '')) . "\n";
    file_put_contents($globalsFile, $content);
}

// Drop the log table
$db->query("DROP TABLE IF EXISTS transcription_log");

// Remove installed scripts
$scripts = [
    '/var/lib/asterisk/agi-bin/transcription-upload.sh',
    '/var/lib/asterisk/agi-bin/transcription-log.php',
];
foreach ($scripts as $script) {
    if (file_exists($script)) {
        unlink($script);
    }
}

// Remove generated config file
$confFile = '/etc/asterisk/transcription.conf';
if (file_exists($confFile)) {
    unlink($confFile);
}
