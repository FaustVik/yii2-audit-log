<?php

declare(strict_types=1);

/**
 * @var array<int, \FaustVik\AuditLog\Core\DTO\LogEntry> $logs
 * @var string $title
 * @var array<string, string> $cssClasses
 * @var \FaustVik\AuditLog\Core\Enums\DisplayMode $displayMode
 * @var int $jsonFlags
 * @var array{currentPage: int, pageSize: int, totalCount: int, pageCount: int, pageName: string}|null $pagination
 * @var \yii\base\View $this
 */
$totalCount = $pagination !== null ? $pagination['totalCount'] : count($logs);

if ($pagination !== null) {
    $from = ($pagination['currentPage'] - 1) * $pagination['pageSize'] + 1;
    $to = min($pagination['currentPage'] * $pagination['pageSize'], $totalCount);
    $countLabel = "Showing {$from}–{$to} of {$totalCount}";
} else {
    $countLabel = "Total records: {$totalCount}";
}
?>

<div class="<?= $cssClasses['container'] ?>">
    <div class="<?= $cssClasses['header'] ?>">
        <h3 class="<?= $cssClasses['headerTitle'] ?>"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
    </div>
    <div class="<?= $cssClasses['body'] ?>">
        <?= $this->render('_table-render', [
            'logs' => $logs,
            'cssClasses' => $cssClasses,
            'displayMode' => $displayMode,
            'jsonFlags' => $jsonFlags,
        ]) ?>

        <div class="<?= $cssClasses['footer'] ?>">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <p class="text-muted" style="margin: 0;">
                    <i class="fa fa-info-circle"></i>
                    <?= htmlspecialchars($countLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </p>
                <?php if ($pagination !== null): ?>
                    <?= $this->render('_pagination', [
                        'pagination' => $pagination,
                        'cssClasses' => $cssClasses,
                    ]) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
