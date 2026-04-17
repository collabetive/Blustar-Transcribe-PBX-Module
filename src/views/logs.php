<?php

$page = max(1, (int) ($_GET['p'] ?? 1));
$statusFilter = $_GET['status'] ?? '';
$logs = $module->getLogs($page, 50, $statusFilter);

?>

<div class="section" data-id="log-filters">
    <div class="section-body">
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-md-6">
                <form method="get" class="form-inline">
                    <input type="hidden" name="display" value="transcription">
                    <input type="hidden" name="tab" value="logs">
                    <div class="form-group">
                        <label for="status-filter">Filter by status:</label>
                        <select name="status" id="status-filter" class="form-control" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="success" <?= $statusFilter === 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="error" <?= $statusFilter === 'error' ? 'selected' : '' ?>>Error</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="col-md-6 text-right">
                <span class="text-muted"><?= $logs['total'] ?> entries</span>
                <button type="button" class="btn btn-sm btn-warning" id="btn-purge-logs" style="margin-left: 10px;">
                    <i class="fa fa-trash"></i> Purge Old Logs
                </button>
            </div>
        </div>
    </div>
</div>

<div class="section" data-id="log-table">
    <div class="section-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th style="width: 170px;">Timestamp</th>
                    <th>Filename</th>
                    <th style="width: 130px;">Status</th>
                    <th>Error</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs['rows'])): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No log entries found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs['rows'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['timestamp']) ?></td>
                            <td><code><?= htmlspecialchars($row['filename']) ?></code></td>
                            <td>
                                <?php
                                $badgeClass = match ($row['status']) {
                                    'success' => 'label-success',
                                    'error'   => 'label-danger',
                                    default   => 'label-warning',
                                };
                                ?>
                                <span class="label <?= $badgeClass ?>"><?= htmlspecialchars($row['status']) ?></span>
                            </td>
                            <td class="text-danger"><?= htmlspecialchars($row['error_msg'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($logs['pages'] > 1): ?>
            <nav class="text-center">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $logs['pages']; $i++): ?>
                        <li class="<?= $i === $logs['page'] ? 'active' : '' ?>">
                            <a href="?display=transcription&tab=logs&p=<?= $i ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
