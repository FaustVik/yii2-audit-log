<?php

declare(strict_types=1);

/**
 * @var array{
 *     currentPage: int,
 *     pageSize: int,
 *     totalCount: int,
 *     pageCount: int,
 *     pageName: string,
 * } $pagination
 * @var array<string, string> $cssClasses
 */

if ($pagination['pageCount'] <= 1) {
    return;
}

$currentPage = $pagination['currentPage'];
$pageCount = $pagination['pageCount'];
$pageName = $pagination['pageName'];

$buildUrl = static function (int $page) use ($pageName): string {
    $params = \Yii::$app->request->queryParams;
    unset($params['route']);
    $params[$pageName] = $page;
    $qs = http_build_query($params);
    return \Yii::$app->request->baseUrl . '/' . \Yii::$app->request->pathInfo . ($qs !== '' ? '?' . $qs : '');
};

// Show at most 7 page links around current page
$window = 3;
$start = max(1, $currentPage - $window);
$end = min($pageCount, $currentPage + $window);

?>
<nav aria-label="Audit log pagination" style="margin-top: 10px;">
    <ul class="pagination" style="margin: 0;">

        <!-- Previous -->
        <?php if ($currentPage > 1): ?>
            <li>
                <a href="<?= htmlspecialchars($buildUrl($currentPage - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                   aria-label="Previous">&laquo;</a>
            </li>
        <?php else: ?>
            <li class="disabled"><span>&laquo;</span></li>
        <?php endif; ?>

        <!-- First page + ellipsis -->
        <?php if ($start > 1): ?>
            <li><a href="<?= htmlspecialchars($buildUrl(1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">1</a></li>
            <?php if ($start > 2): ?>
                <li class="disabled"><span>…</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page window -->
        <?php for ($p = $start; $p <= $end; $p++): ?>
            <?php if ($p === $currentPage): ?>
                <li class="active"><span><?= $p ?></span></li>
            <?php else: ?>
                <li><a href="<?= htmlspecialchars($buildUrl($p), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $p ?></a></li>
            <?php endif; ?>
        <?php endfor; ?>

        <!-- Ellipsis + last page -->
        <?php if ($end < $pageCount): ?>
            <?php if ($end < $pageCount - 1): ?>
                <li class="disabled"><span>…</span></li>
            <?php endif; ?>
            <li><a href="<?= htmlspecialchars($buildUrl($pageCount), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $pageCount ?></a></li>
        <?php endif; ?>

        <!-- Next -->
        <?php if ($currentPage < $pageCount): ?>
            <li>
                <a href="<?= htmlspecialchars($buildUrl($currentPage + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                   aria-label="Next">&raquo;</a>
            </li>
        <?php else: ?>
            <li class="disabled"><span>&raquo;</span></li>
        <?php endif; ?>

    </ul>
</nav>
