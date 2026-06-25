<?php

declare(strict_types=1);

/**
 * @var array<int, \FaustVik\AuditLog\Core\DTO\LogEntry> $logs
 * @var string $title
 * @var array<string, string> $cssClasses
 * @var \FaustVik\AuditLog\Core\Enums\DisplayMode $displayMode
 * @var int $jsonFlags
 * @var array<string, mixed> $filters
 * @var string $resetUrl
 * @var bool $preserveQueryParams
 * @var array<int, \FaustVik\AuditLog\Core\Enums\FilterParam> $filterParams
 * @var \yii\base\View $this
 */

use FaustVik\AuditLog\Core\Enums\FilterParam;
use FaustVik\AuditLog\Core\Enums\Operation;
use yii\helpers\Html;

$filterParamValues = array_map(static fn (FilterParam $p): string => $p->value, $filterParams);

$dateFrom = is_string($filters[FilterParam::DateFrom->value] ?? null) ? $filters[FilterParam::DateFrom->value] : '';
$dateTo = is_string($filters[FilterParam::DateTo->value] ?? null) ? $filters[FilterParam::DateTo->value] : '';

?>

<div class="<?= $cssClasses['container'] ?>">
    <div class="<?= $cssClasses['header'] ?>">
        <h3 class="<?= $cssClasses['headerTitle'] ?>"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
    </div>
    <div class="<?= $cssClasses['body'] ?>">

        <!-- Filter form -->
        <form method="get" class="<?= $cssClasses['filterContainer'] ?? 'audit-log-filters' ?>" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
            <?php if ($preserveQueryParams): ?>
                <!-- Preserve current URL parameters (except filter params) -->
                <?php foreach (Yii::$app->request->queryParams as $name => $value): ?>
                    <?php if (!in_array($name, $filterParamValues, true)): ?>
                        <?= Html::hiddenInput($name, $value) ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-sm-3">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Operation</label>
                    <select name="<?= FilterParam::Operation->value ?>" class="form-control" style="width: 100%;">
                        <option value="">All Operations</option>
                        <option value="INSERT" <?= (isset($filters[FilterParam::Operation->value]) && $filters[FilterParam::Operation->value] === Operation::Insert) ? 'selected' : '' ?>>Insert</option>
                        <option value="UPDATE" <?= (isset($filters[FilterParam::Operation->value]) && $filters[FilterParam::Operation->value] === Operation::Update) ? 'selected' : '' ?>>Update</option>
                        <option value="DELETE" <?= (isset($filters[FilterParam::Operation->value]) && $filters[FilterParam::Operation->value] === Operation::Delete) ? 'selected' : '' ?>>Delete</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Date From</label>
                    <input type="date" name="<?= FilterParam::DateFrom->value ?>" class="form-control" style="width: 100%;"
                           value="<?= htmlspecialchars($dateFrom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                </div>
                <div class="col-sm-3">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Date To</label>
                    <input type="date" name="<?= FilterParam::DateTo->value ?>" class="form-control" style="width: 100%;"
                           value="<?= htmlspecialchars($dateTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                </div>
                <div class="col-sm-3">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary" style="margin-right: 5px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn btn-default">
                            <i class="fa fa-refresh"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </form>

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
