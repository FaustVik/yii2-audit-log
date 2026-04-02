<?php

declare(strict_types=1);

namespace App\Models\Stubs;

use yii\db\ActiveRecord;

/**
 * Mock ActiveRecord with {{%tableName}} prefix format
 * Used to test getLogTableName() bug #4
 */
class MockUserWithPrefix extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user}}';
    }
}
