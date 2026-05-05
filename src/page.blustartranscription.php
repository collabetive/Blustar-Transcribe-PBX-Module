<?php

if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

$module = \FreePBX::Blustartranscription();

// Determine which tab to show (whitelist valid values)
$tab = in_array($_GET['tab'] ?? '', ['settings', 'logs'], true) ? $_GET['tab'] : 'settings';

?>

<div id="toolbar-all">
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="<?= $tab === 'settings' ? 'active' : '' ?>">
            <a href="?display=blustartranscription&tab=settings">Settings</a>
        </li>
        <li role="presentation" class="<?= $tab === 'logs' ? 'active' : '' ?>">
            <a href="?display=blustartranscription&tab=logs">Transfer Logs</a>
        </li>
    </ul>
</div>

<div class="tab-content display">
    <?php
    switch ($tab) {
        case 'logs':
            include __DIR__ . '/views/logs.php';
            break;
        default:
            include __DIR__ . '/views/settings.php';
            break;
    }
    ?>
</div>

<script src="modules/blustartranscription/assets/js/transcription.js"></script>
