<?php

declare(strict_types=1);

namespace App\Models\Stubs;

use yii\db\ActiveRecord;

/**
 * Mock ActiveRecord without table prefix
 */
class MockUserWithoutPrefix extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'user';
    }
}
