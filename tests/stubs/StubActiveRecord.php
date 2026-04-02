<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Stubs;

use yii\db\ActiveRecord;

/**
 * Stub ActiveRecord for testing AuditLogBehavior
 */
class StubActiveRecord extends ActiveRecord
{
    public int|string|array|null $primaryKeyValue = 1;

    public array $attributes = [];

    public array $oldAttributes = [];

    public function __construct(array $config = [])
    {
        // Do not call parent::__construct() to avoid initialization
    }

    public static function tableName(): string
    {
        return 'stub_table';
    }

    /**
     * @inheritDoc
     */
    public function getPrimaryKey($asArray = false)
    {
        return $this->primaryKeyValue;
    }

    /**
     * @inheritDoc
     */
    public function getAttributes($names = null, $except = []): array
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function getOldAttributes(): array
    {
        return $this->oldAttributes;
    }

    /**
     * @inheritDoc
     */
    public function setOldAttributes($values): void
    {
        $this->oldAttributes = $values;
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        $this->attributes = $values;
    }

    public function setPrimaryKey(int|string|array|null $value): void
    {
        $this->primaryKeyValue = $value;
    }
}
