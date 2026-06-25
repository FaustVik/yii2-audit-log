<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Yii2\Adapter\Helpers;

use Yii;

/**
 * Helper for resolving yii\db\Expression objects
 *
 * If resolution fails, returns null — the caller should handle
 * missing values as needed (e.g. log, skip, or use defaults).
 */
final class ExpressionResolver
{
    /**
     * Resolve Expression to value
     *
     * Returns null if resolution fails (no logging is performed).
     *
     * @param mixed $value Value to resolve
     * @return mixed Resolved value or null if not resolvable
     */
    public function resolve(mixed $value): mixed
    {
        if (!$value instanceof \yii\db\Expression) {
            return $value;
        }

        try {
            $sql = 'SELECT ' . $value->expression;

            if (Yii::$app === null || !Yii::$app->has('db')) {
                return null;
            }

            return Yii::$app->db->createCommand($sql, $value->params)->queryScalar();
        } catch (\Throwable) {
            return null;
        }
    }
}
