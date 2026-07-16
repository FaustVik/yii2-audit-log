<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Integration;

use FaustVik\AuditLog\Core\Enums\FilterParam;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Core\Query\AuditLogQuery;

/**
 * Widget for displaying model change history with filters
 *
 * @example
 * ```php
 * <?= \FaustVik\AuditLog\Yii2\Integration\AuditLogFilterWidget::widget([
 *     'model' => $user,
 *     'limit' => 50,
 *     'title' => 'Change History',
 *     'displayMode' => DisplayMode::Modal,
 * ]) ?>
 * ```
 */
class AuditLogFilterWidget extends AuditLogWidget
{
    /**
     * @var array<string, mixed> Log filters (default parameters)
     *
     * @phpstan-var array{
     *     operation?: Operation|null,
     *     dateFrom?: string|null,
     *     dateTo?: string|null,
     *     userId?: int|string|null,
     *     userType?: string|null,
     * }
     */
    public array $filters = [];

    /**
     * @var bool Show the filter form
     */
    public bool $showFilters = true;

    /**
     * @var bool Preserve current URL params when submitting the filter form
     * If true, all current query parameters (except filter params) will be
     * added as hidden fields in the form
     */
    public bool $preserveQueryParams = true;

    /**
     * @var array<int, FilterParam> List of filter parameters to exclude from preservation
     * These parameters will be handled by the form and won't be added as hidden fields
     */
    public array $filterParams = [
        FilterParam::Operation,
        FilterParam::DateFrom,
        FilterParam::DateTo,
        FilterParam::UserId,
        FilterParam::UserType,
    ];

    public function run(): string
    {
        $pagination = null;

        if ($this->pageSize > 0) {
            $currentPage = max(1, (int) \Yii::$app->request->get($this->pageName, 1));
            $totalCount = $this->fetchTotalCount();
            $pageCount = $totalCount > 0 ? (int) ceil($totalCount / $this->pageSize) : 1;
            $currentPage = min($currentPage, $pageCount);
            $offset = max(0, ($currentPage - 1) * $this->pageSize);

            $logs = $this->fetchLogs($this->pageSize, $offset);

            $pagination = [
                'currentPage' => $currentPage,
                'pageSize' => $this->pageSize,
                'totalCount' => $totalCount,
                'pageCount' => $pageCount,
                'pageName' => $this->pageName,
            ];
        } else {
            $logs = $this->fetchLogs($this->limit, 0);
        }

        return $this->renderFile($this->getWidgetViewPath(), [
            'logs' => $logs,
            'title' => $this->title,
            'cssClasses' => $this->cssClasses,
            'displayMode' => $this->displayMode,
            'jsonFlags' => $this->jsonFlags,
            'filters' => $this->filters,
            'resetUrl' => $this->buildResetUrl(),
            'preserveQueryParams' => $this->preserveQueryParams,
            'filterParams' => $this->filterParams,
            'pageName' => $this->pageName,
            'pagination' => $pagination,
        ]);
    }

    protected function getWidgetViewPath(): string
    {
        return __DIR__ . '/views/audit-log-filter-widget.php';
    }

    /**
     * @param int<0, max> $limit
     * @param int<0, max> $offset
     * @return array<int, \FaustVik\AuditLog\Core\DTO\LogEntry>
     */
    protected function fetchLogs(int $limit = 0, int $offset = 0): array
    {
        $query = $this->buildQuery();

        if ($query === null) {
            return [];
        }

        return $query->limit($limit)->offset($offset)->all();
    }

    protected function fetchTotalCount(): int
    {
        $query = $this->buildQuery();

        if ($query === null) {
            return 0;
        }

        return $query->count();
    }

    /**
     * Build a query with all active filters applied but without limit/offset.
     */
    private function buildQuery(): ?AuditLogQuery
    {
        $entityId = $this->model->getPrimaryKey();

        if ($entityId === null || (!is_int($entityId) && !is_string($entityId))) {
            return null;
        }

        $filters = $this->filters;
        $request = \Yii::$app->request;

        $operation = $request->get(FilterParam::Operation->value);
        if ($operation !== null && $operation !== '') {
            $filters[FilterParam::Operation->value] = Operation::tryFrom($operation);
        }

        $dateFrom = $request->get(FilterParam::DateFrom->value);
        if ($dateFrom !== null && $dateFrom !== '') {
            $filters[FilterParam::DateFrom->value] = $dateFrom;
        }

        $dateTo = $request->get(FilterParam::DateTo->value);
        if ($dateTo !== null && $dateTo !== '') {
            $filters[FilterParam::DateTo->value] = $dateTo;
        }

        $userId = $request->get(FilterParam::UserId->value);
        if ($userId !== null && $userId !== '') {
            $filters[FilterParam::UserId->value] = $userId;
        }

        $userType = $request->get(FilterParam::UserType->value);
        if ($userType !== null && $userType !== '') {
            $filters[FilterParam::UserType->value] = $userType;
        }

        $query = (new AuditLogQuery($this->getStorage()))
            ->forEntity($this->model::class, $entityId)
            ->orderBy('created_at DESC');

        $op = $filters[FilterParam::Operation->value] ?? null;
        if ($op instanceof Operation) {
            $query->operation($op);
        }

        $dateFrom = is_string($filters[FilterParam::DateFrom->value] ?? null) ? $filters[FilterParam::DateFrom->value] : null;
        $dateTo = is_string($filters[FilterParam::DateTo->value] ?? null) ? $filters[FilterParam::DateTo->value] : null;

        if ($dateFrom !== null || $dateTo !== null) {
            $query->dateRange($dateFrom, $dateTo);
        }

        if (isset($filters[FilterParam::UserId->value])) {
            $query->userId($filters[FilterParam::UserId->value]);
        }

        if (isset($filters[FilterParam::UserType->value])) {
            $query->userType($filters[FilterParam::UserType->value]);
        }

        return $query;
    }

    /**
     * Build URL for resetting filters
     * Preserves all current URL parameters except filter parameters
     */
    private function buildResetUrl(): string
    {
        $params = \Yii::$app->request->queryParams;
        unset($params['route']);

        foreach ($this->filterParams as $filterParam) {
            unset($params[$filterParam->value]);
        }

        $queryString = http_build_query($params);

        return \Yii::$app->request->baseUrl . \Yii::$app->request->pathInfo . ($queryString !== '' ? '?' . $queryString : '');
    }
}
