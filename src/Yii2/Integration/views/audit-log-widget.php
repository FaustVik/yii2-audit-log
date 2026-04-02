<?php

declare(strict_types=1);

/**
 * @var array<int, \FaustVik\AuditLog\Core\DTO\LogEntry> $logs
 * @var string $title
 * @var array<string, string> $cssClasses
 * @var \FaustVik\AuditLog\Core\Enums\DisplayMode $displayMode Режим отображения
 * @var int $jsonFlags Флаги JSON
 */

use FaustVik\AuditLog\Core\Enums\DisplayMode;
use FaustVik\AuditLog\Core\Enums\Operation;

/**
 * Convert datetime to safe HTML ID (no spaces or special chars)
 */
$toSafeId = static function (?string $value): string {
    return preg_replace('/[^a-zA-Z0-9_-]/', '-', $value ?? 'unknown') ?: 'unknown';
};

?>

<div class="<?= $cssClasses['container'] ?>">
    <div class="<?= $cssClasses['header'] ?>">
        <h3 class="<?= $cssClasses['headerTitle'] ?>"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
    </div>
    <div class="<?= $cssClasses['body'] ?>">

        <?php if (empty($logs)): ?>
            <div class="<?= $cssClasses['alert'] ?>">
                <i class="<?= $cssClasses['alertIcon'] ?>"></i> No change history found for this record.
            </div>
        <?php else: ?>
            <div class="<?= $cssClasses['tableResponsive'] ?>">
                <table class="<?= $cssClasses['table'] ?>">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 100px;">Operation</th>
                            <th style="width: 150px;">Date/Time</th>
                            <th style="width: 80px;">User ID</th>
                            <th style="width: 100px;">User Type</th>
                            <th style="width: 120px;">IP Address</th>
                            <th style="width: 150px;">Route</th>
                            <th style="width: 100px;">Module</th>
                            <th>Changed Attributes</th>
                            <th>Custom Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php /** @var \FaustVik\AuditLog\Core\DTO\LogEntry $log */ ?>
                            <tr>
                                <td><?= $log->entityId ?></td>
                                <td>
                                    <?php
                                    $badgeClass = match ($log->operation) {
                                        Operation::Insert => $cssClasses['labelSuccess'],
                                        Operation::Update => $cssClasses['labelWarning'],
                                        Operation::Delete => $cssClasses['labelDanger'],
                                    };
                            ?>
                                    <span class="<?= $badgeClass ?>">
                                        <?= $log->operation->value ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= $log->createdAt ?? 'N/A' ?></small>
                                </td>
                                <td><?= $log->userId ?? 'N/A' ?></td>
                                <td>
                                    <span class="<?= $cssClasses['labelInfo'] ?>">
                                        <?= $log->userType ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= $log->ipAddress ?? 'N/A' ?></small>
                                </td>
                                <td>
                                    <small><?= $log->route ?? 'N/A' ?></small>
                                </td>
                                <td>
                                    <small><?= $log->module ?? 'N/A' ?></small>
                                </td>

                                <?php if ($displayMode === DisplayMode::Accordion): ?>
                                    <!-- Accordion режим -->
                                    <td>
                                        <button type="button" class="<?= $cssClasses['btnInfo'] ?>"
                                                onclick="var d=document.getElementById('log-changes-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>'); d.style.display=d.style.display==='none'||d.style.display===''?'table-row':'none'">
                                            <i class="<?= $cssClasses['btnIcon'] ?>"></i> View
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" class="<?= $cssClasses['btnDefault'] ?>"
                                                onclick="var d=document.getElementById('log-custom-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>'); d.style.display=d.style.display==='none'||d.style.display===''?'table-row':'none'">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                    </td>

                                <?php elseif ($displayMode === DisplayMode::Modal): ?>
                                    <!-- Modal режим -->
                                    <td>
                                        <button type="button" class="<?= $cssClasses['btnInfo'] ?>"
                                                onclick="openModal('modal-changes-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>')">
                                            <i class="<?= $cssClasses['btnIcon'] ?>"></i> View
                                        </button>
                                    </td>
                                    <td>
                                        <button type="button" class="<?= $cssClasses['btnDefault'] ?>"
                                                onclick="openModal('modal-custom-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>')">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                    </td>

                                <?php else: ?>
                                    <!-- Текстовый режим (по умолчанию) -->
                                    <td>
                                        <?php if (!empty($log->changedAttributes)): ?>
                                            <pre style="font-size: 11px; margin: 0; max-height: 300px; overflow: auto; background: #f9f9f9; padding: 8px; border-radius: 3px;"><?= htmlspecialchars(json_encode($log->changedAttributes, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($log->customData)): ?>
                                            <pre style="font-size: 11px; margin: 0; max-height: 300px; overflow: auto; background: #f9f9f9; padding: 8px; border-radius: 3px;"><?= htmlspecialchars(json_encode($log->customData, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($displayMode === DisplayMode::Accordion): ?>
                                <!-- Скрытые строки для accordion режима -->
                                <tr id="log-changes-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>" style="display: none;">
                                    <td colspan="8" style="background: #f9f9f9; padding: 10px;">
                                        <strong>Changed Attributes:</strong>
                                        <pre style="margin: 10px 0 0 0; background: #fff; padding: 10px; border-radius: 3px;"><?= htmlspecialchars(json_encode($log->changedAttributes, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                    </td>
                                </tr>
                                <tr id="log-custom-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>" style="display: none;">
                                    <td colspan="8" style="background: #f9f9f9; padding: 10px;">
                                        <strong>Custom Data:</strong>
                                        <pre style="margin: 10px 0 0 0; background: #fff; padding: 10px; border-radius: 3px;"><?= htmlspecialchars(json_encode($log->customData, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                                    </td>
                                </tr>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($displayMode === DisplayMode::Modal): ?>
                <!-- Модальные окна (выносятся за пределы таблицы) -->
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; z-index: 9999;" id="modal-overlay" onclick="closeModal()"></div>
                
                <?php foreach ($logs as $log): ?>
                    <!-- Modal для changed attributes -->
                    <div id="modal-changes-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>" class="audit-log-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; max-width: 80%; max-height: 80%; overflow: auto; z-index: 10000; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">Changed Attributes</h4>
                            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                        </div>
                        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow: auto;"><?= htmlspecialchars(json_encode($log->changedAttributes, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                    </div>
                    
                    <!-- Modal для custom data -->
                    <div id="modal-custom-<?= $log->entityId ?>-<?= $toSafeId($log->createdAt) ?>" class="audit-log-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; max-width: 80%; max-height: 80%; overflow: auto; z-index: 10000; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0;">Custom Data</h4>
                            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                        </div>
                        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow: auto;"><?= htmlspecialchars(json_encode($log->customData, $jsonFlags) ?: '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                    </div>
                <?php endforeach; ?>
                
                <script>
                function openModal(modalId) {
                    document.getElementById('modal-overlay').style.display = 'block';
                    document.getElementById(modalId).style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
                function closeModal() {
                    document.getElementById('modal-overlay').style.display = 'none';
                    document.querySelectorAll('.audit-log-modal').forEach(function(m) {
                        m.style.display = 'none';
                    });
                    document.body.style.overflow = '';
                }
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') closeModal();
                });
                </script>
            <?php endif; ?>

            <div class="<?= $cssClasses['footer'] ?>">
                <p class="text-muted">
                    <i class="fa fa-info-circle"></i>
                    Total records: <strong><?= count($logs) ?></strong>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
