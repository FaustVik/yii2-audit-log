<?php

declare(strict_types=1);

/**
 * @var array<int, \FaustVik\AuditLog\Core\DTO\LogEntry> $logs
 * @var string $title
 * @var array<string, string> $cssClasses
 * @var \FaustVik\AuditLog\Core\Enums\DisplayMode $displayMode
 * @var int $jsonFlags
 * @var \yii\base\View $this
 */
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
            <p class="text-muted">
                <i class="fa fa-info-circle"></i>
                Total records: <strong><?= count($logs) ?></strong>
            </p>
        </div>
    </div>
</div>
