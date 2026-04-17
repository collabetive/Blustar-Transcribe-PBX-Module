<?php

$settings = $module->getAllSettings();
$enabled = $settings['enabled'] === 'true';

?>

<form method="post" id="transcription-settings-form">
    <input type="hidden" name="action" value="save_settings">

    <div class="section" data-id="general">
        <div class="section-title">
            <h3>General</h3>
        </div>
        <div class="section-body">
            <div class="element-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-3">
                                    <label class="control-label" for="enabled">Enabled</label>
                                    <i class="fa fa-question-circle fpbx-help-icon" data-for="enabled"></i>
                                </div>
                                <div class="col-md-9">
                                    <span class="radioset">
                                        <input type="radio" name="enabled" id="enabled_yes" value="true" <?= $enabled ? 'checked' : '' ?>>
                                        <label for="enabled_yes">Yes</label>
                                        <input type="radio" name="enabled" id="enabled_no" value="false" <?= !$enabled ? 'checked' : '' ?>>
                                        <label for="enabled_no">No</label>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="row hidden" data-id="enabled" data-help>
                            <div class="col-md-12">
                                <span class="help-block fpbx-help-block">Enable or disable automatic transcription of call recordings.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section" data-id="server">
        <div class="section-title">
            <h3>Transcription Server</h3>
        </div>
        <div class="section-body">
            <div class="element-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-3">
                                    <label class="control-label" for="server_host">Server Host <span class="text-danger">*</span></label>
                                    <i class="fa fa-question-circle fpbx-help-icon" data-for="server_host"></i>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="server_host" name="server_host"
                                           value="<?= htmlspecialchars($settings['server_host']) ?>"
                                           placeholder="192.168.1.100 or transcribe.example.com" required>
                                </div>
                            </div>
                        </div>
                        <div class="row hidden" data-id="server_host" data-help>
                            <div class="col-md-12">
                                <span class="help-block fpbx-help-block">IP address or hostname of the transcription server.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="element-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-3">
                                    <label class="control-label" for="server_port">Server Port</label>
                                    <i class="fa fa-question-circle fpbx-help-icon" data-for="server_port"></i>
                                </div>
                                <div class="col-md-9">
                                    <input type="number" class="form-control" id="server_port" name="server_port"
                                           value="<?= htmlspecialchars($settings['server_port']) ?>"
                                           min="1" max="65535" placeholder="8600">
                                </div>
                            </div>
                        </div>
                        <div class="row hidden" data-id="server_port" data-help>
                            <div class="col-md-12">
                                <span class="help-block fpbx-help-block">Port number the transcription API listens on (default: 8600).</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="element-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-3">
                                    <label class="control-label" for="api_key">API Key <span class="text-danger">*</span></label>
                                    <i class="fa fa-question-circle fpbx-help-icon" data-for="api_key"></i>
                                </div>
                                <div class="col-md-9">
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="api_key" name="api_key"
                                               value="<?= htmlspecialchars($settings['api_key']) ?>" required>
                                        <span class="input-group-btn">
                                            <button class="btn btn-default" type="button" id="toggle-api-key">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row hidden" data-id="api_key" data-help>
                            <div class="col-md-12">
                                <span class="help-block fpbx-help-block">API key used to authenticate with the transcription server webhook (X-API-KEY header).</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section" data-id="maintenance">
        <div class="section-title">
            <h3>Maintenance</h3>
        </div>
        <div class="section-body">
            <div class="element-container">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="form-group">
                                <div class="col-md-3">
                                    <label class="control-label" for="log_retention_days">Log Retention (days)</label>
                                    <i class="fa fa-question-circle fpbx-help-icon" data-for="log_retention_days"></i>
                                </div>
                                <div class="col-md-9">
                                    <input type="number" class="form-control" id="log_retention_days" name="log_retention_days"
                                           value="<?= htmlspecialchars($settings['log_retention_days']) ?>"
                                           min="1" max="365" placeholder="30">
                                </div>
                            </div>
                        </div>
                        <div class="row hidden" data-id="log_retention_days" data-help>
                            <div class="col-md-12">
                                <span class="help-block fpbx-help-block">Number of days to keep transfer log entries before automatic cleanup.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="section" data-id="actions">
        <div class="section-body">
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary" id="btn-save">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-default" id="btn-test-api">
                        <i class="fa fa-plug"></i> Test Connection
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<div id="test-results" class="hidden" style="margin-top: 15px;">
    <div class="alert" id="test-result-alert"></div>
</div>
