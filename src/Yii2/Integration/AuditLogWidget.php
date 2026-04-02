<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Integration;

use FaustVik\AuditLog\Core\Contracts\AuditStorageInterface;
use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\DisplayMode;
use FaustVik\AuditLog\Core\Query\AuditLogQuery;
use Yii;
use yii\base\Widget;
use yii\db\ActiveRecord;

/**
 * Base widget for displaying model change history
 *
 * For filter version, use AuditLogFilterWidget
 *
 * @example
 * ```php
 * <?= \FaustVik\AuditLog\Yii2\Integration\AuditLogWidget::widget([
 *     'model' => $user,
 *     'limit' => 50,
 *     'title' => 'Change History',
 *     'displayMode' => DisplayMode::TEXT,
 * ]) ?>
 * ```
 */
class AuditLogWidget extends Widget
{
    /**
     * @var ActiveRecord Model to display change history for
     */
    public ActiveRecord $model;

    /**
     * @var int<0, max> Number of records to display (0 = all)
     */
    public int $limit = 0;

    /**
     * @var string Widget title
     */
    public string $title = 'Change History';

    /**
     * @var DisplayMode Display mode: text, accordion, modal
     */
    public DisplayMode $displayMode = DisplayMode::Text;

    /**
     * @var int JSON flags for formatting (e.g. JSON_PRETTY_PRINT)
     */
    public int $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

    /**
     * @var array<string, string> CSS classes for widget elements
     */
    public array $cssClasses = [
        'container' => 'box box-primary',
        'header' => 'box-header with-border',
        'headerTitle' => 'box-title',
        'body' => 'box-body',
        'footer' => 'box-footer',
        'table' => 'table table-bordered table-striped table-hover',
        'tableResponsive' => 'table-responsive',
        'alert' => 'alert alert-info',
        'alertIcon' => 'fa fa-info-circle',
        'labelSuccess' => 'label label-success',
        'labelWarning' => 'label label-warning',
        'labelDanger' => 'label label-danger',
        'labelDefault' => 'label label-default',
        'labelInfo' => 'label label-info',
        'btnInfo' => 'btn btn-xs btn-info',
        'btnDefault' => 'btn btn-xs btn-default',
        'btnIcon' => 'fa fa-eye',
        'modalLg' => 'modal-lg',
    ];

    /**
     * @var AuditStorageInterface|null Custom storage (for tests)
     */
    public ?AuditStorageInterface $storage = null;

    public function run(): string
    {
        $logs = $this->fetchLogs();

        return $this->renderFile(__DIR__ . '/views/audit-log-widget.php', [
            'logs' => $logs,
            'title' => $this->title,
            'cssClasses' => $this->cssClasses,
            'displayMode' => $this->displayMode,
            'jsonFlags' => $this->jsonFlags,
        ]);
    }

    /**
     * Get log records for model
     *
     * @return array<int, LogEntry>
     */
    protected function fetchLogs(): array
    {
        $storage = $this->getStorage();
        $entityId = $this->model->getPrimaryKey();

        if ($entityId === null) {
            return [];
        }

        if (!is_int($entityId) && !is_string($entityId)) {
            return [];
        }

        $query = (new AuditLogQuery($storage))
            ->forEntity($this->model::class, $entityId)
            ->limit($this->limit)
            ->orderBy('created_at DESC');

        return $query->all();
    }

    protected function getStorage(): AuditStorageInterface
    {
        if ($this->storage !== null) {
            return $this->storage; // For tests
        }

        return Yii::createObject(AuditStorageInterface::class);
    }
}
