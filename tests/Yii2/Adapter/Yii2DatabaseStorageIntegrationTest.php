<?php

declare(strict_types=1);

namespace FaustVik\AuditLog\Tests\Yii2\Adapter;

use FaustVik\AuditLog\Core\DTO\LogEntry;
use FaustVik\AuditLog\Core\Enums\Operation;
use FaustVik\AuditLog\Yii2\Adapter\Yii2DatabaseStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use yii\db\Connection;

#[CoversClass(Yii2DatabaseStorage::class)]
final class Yii2DatabaseStorageIntegrationTest extends TestCase
{
    private ?Connection $db = null;
    private ?Yii2DatabaseStorage $storage = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create SQLite in-memory DB
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->db = new Connection([
            'pdo' => $pdo,
            'enableSchemaCache' => false,
        ]);

        // Initialize Yii::$app with correct stub
        $app = new class ($this->db) {
            public Connection $db;
            public function __construct(Connection $db)
            {
                $this->db = $db;
            }
            public function getDb(): Connection
            {
                return $this->db;
            }
            public function has(string $id): bool
            {
                return false;
            }
        };
        \Yii::$app = $app;

        // Create table directly via PDO
        $pdo->exec(<<<'SQL'
            CREATE TABLE user_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_id INTEGER NOT NULL,
                operation VARCHAR(10) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                user_id INTEGER,
                user_type VARCHAR(20) NOT NULL DEFAULT 'system',
                route VARCHAR(255),
                module VARCHAR(100),
                ip_address VARCHAR(45),
                user_agent TEXT,
                changed_attributes JSON,
                custom_data JSON
            )
        SQL);

        // Insert test data
        $this->seedTestData();

        $this->storage = new Yii2DatabaseStorage('_log');
    }

    protected function tearDown(): void
    {
        $this->db?->close();
        $this->db = null;
        $this->storage = null;
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $records = [
            // userId=1, admin, INSERT, 2024-01-15
            ['entity_id' => 1, 'operation' => 'INSERT', 'user_id' => 1, 'user_type' => 'admin', 'route' => 'user/create', 'module' => 'user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0', 'created_at' => '2024-01-15 10:00:00', 'changed_attributes' => json_encode(['name' => ['old' => null, 'new' => 'John']]), 'custom_data' => json_encode(['source' => 'admin_panel'])],
            // userId=1, admin, UPDATE, 2024-03-20
            ['entity_id' => 1, 'operation' => 'UPDATE', 'user_id' => 1, 'user_type' => 'admin', 'route' => 'user/update', 'module' => 'user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0', 'created_at' => '2024-03-20 14:00:00', 'changed_attributes' => json_encode(['name' => ['old' => 'John', 'new' => 'Jane']]), 'custom_data' => json_encode([])],
            // userId=2, user, UPDATE, 2024-06-01
            ['entity_id' => 2, 'operation' => 'UPDATE', 'user_id' => 2, 'user_type' => 'user', 'route' => 'profile/edit', 'module' => 'site', 'ip_address' => '192.168.1.1', 'user_agent' => 'Chrome/120', 'created_at' => '2024-06-01 09:00:00', 'changed_attributes' => json_encode(['email' => ['old' => 'a@test.com', 'new' => 'b@test.com']]), 'custom_data' => json_encode(['ip' => '192.168.1.1'])],
            // userId=2, user, DELETE, 2024-06-15
            ['entity_id' => 2, 'operation' => 'DELETE', 'user_id' => 2, 'user_type' => 'user', 'route' => 'profile/delete', 'module' => 'site', 'ip_address' => '192.168.1.1', 'user_agent' => 'Chrome/120', 'created_at' => '2024-06-15 16:00:00', 'changed_attributes' => json_encode([]), 'custom_data' => json_encode(['reason' => 'user request'])],
            // userId=3, api, INSERT, 2024-07-01
            ['entity_id' => 3, 'operation' => 'INSERT', 'user_id' => 3, 'user_type' => 'api', 'route' => 'api/v1/users', 'module' => 'api', 'ip_address' => '10.0.0.1', 'user_agent' => 'API-Client/2.0', 'created_at' => '2024-07-01 08:00:00', 'changed_attributes' => json_encode(['name' => ['old' => null, 'new' => 'API User']]), 'custom_data' => json_encode(['api_key' => 'xxx'])],
            // userId=3, api, UPDATE, 2024-08-10
            ['entity_id' => 3, 'operation' => 'UPDATE', 'user_id' => 3, 'user_type' => 'api', 'route' => 'api/v1/users/3', 'module' => 'api', 'ip_address' => '10.0.0.1', 'user_agent' => 'API-Client/2.0', 'created_at' => '2024-08-10 12:00:00', 'changed_attributes' => json_encode(['status' => ['old' => 'active', 'new' => 'inactive']]), 'custom_data' => json_encode([])],
            // userId=null, system, INSERT, 2024-02-01
            ['entity_id' => 4, 'operation' => 'INSERT', 'user_id' => null, 'user_type' => 'system', 'route' => null, 'module' => null, 'ip_address' => null, 'user_agent' => null, 'created_at' => '2024-02-01 00:00:00', 'changed_attributes' => json_encode([]), 'custom_data' => json_encode(['cron' => true])],
            // userId=5, admin, DELETE, 2024-09-01
            ['entity_id' => 5, 'operation' => 'DELETE', 'user_id' => 5, 'user_type' => 'admin', 'route' => 'admin/users/5', 'module' => 'admin', 'ip_address' => '172.16.0.1', 'user_agent' => 'Firefox/119', 'created_at' => '2024-09-01 11:00:00', 'changed_attributes' => json_encode([]), 'custom_data' => json_encode(['admin_note' => 'banned'])],
            // userId=5, admin, UPDATE, 2024-10-15
            ['entity_id' => 1, 'operation' => 'UPDATE', 'user_id' => 5, 'user_type' => 'admin', 'route' => 'admin/users/1', 'module' => 'admin', 'ip_address' => '172.16.0.1', 'user_agent' => 'Firefox/119', 'created_at' => '2024-10-15 15:00:00', 'changed_attributes' => json_encode(['role' => ['old' => 'user', 'new' => 'admin']]), 'custom_data' => json_encode([])],
            // userId=1, admin, INSERT, 2024-11-01
            ['entity_id' => 6, 'operation' => 'INSERT', 'user_id' => 1, 'user_type' => 'admin', 'route' => 'user/create', 'module' => 'user', 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0', 'created_at' => '2024-11-01 10:00:00', 'changed_attributes' => json_encode(['name' => ['old' => null, 'new' => 'NewUser']]), 'custom_data' => json_encode([])],
        ];

        foreach ($records as $record) {
            $this->db->createCommand()->insert('user_log', $record)->execute();
        }
    }

    // ============================================================
    // Group A: Basic
    // ============================================================

    #[Test]
    public function shouldReturnAllRecordsWhenNoFilters(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User');

        $this->assertCount(10, $results);
    }

    #[Test]
    public function shouldReturnEmptyWhenNoMatches(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', entityId: 9999);

        $this->assertEmpty($results);
    }

    #[Test]
    public function shouldReturnLogEntryObjects(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', limit: 1);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(LogEntry::class, $results[0]);
    }

    // ============================================================
    // Group B: Filters
    // ============================================================

    #[Test]
    public function shouldFilterByEntityId(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', entityId: 1);

        $this->assertCount(3, $results);
        foreach ($results as $entry) {
            $this->assertSame(1, $entry->entityId);
        }
    }

    #[Test]
    public function shouldFilterByOperation(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', operation: Operation::Delete);

        $this->assertCount(2, $results);
        foreach ($results as $entry) {
            $this->assertSame(Operation::Delete, $entry->operation);
        }
    }

    #[Test]
    public function shouldFilterByUserId(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', userId: 1);

        $this->assertCount(3, $results);
        foreach ($results as $entry) {
            $this->assertSame(1, $entry->userId);
        }
    }

    #[Test]
    public function shouldFilterByUserType(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', userType: 'api');

        $this->assertCount(2, $results);
        foreach ($results as $entry) {
            $this->assertSame('api', $entry->userType);
        }
    }

    #[Test]
    public function shouldFilterByDateFrom(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', dateFrom: '2024-07-01');

        // Records >= 2024-07-01: #5(07-01), #6(08-10), #8(09-01), #9(10-15), #10(11-01) = 5
        $this->assertCount(5, $results);
        foreach ($results as $entry) {
            $this->assertGreaterThanOrEqual('2024-07-01', substr($entry->createdAt, 0, 10));
        }
    }

    #[Test]
    public function shouldFilterByDateTo(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', dateTo: '2024-02-01');

        $this->assertCount(2, $results);
        foreach ($results as $entry) {
            $this->assertLessThanOrEqual('2024-02-01 23:59:59', $entry->createdAt);
        }
    }

    #[Test]
    public function shouldFilterByDateRange(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', dateFrom: '2024-06-01', dateTo: '2024-08-31');

        // Records 2024-06-01..2024-08-31: #3(06-01), #4(06-15), #5(07-01), #6(08-10) = 4
        $this->assertCount(4, $results);
        foreach ($results as $entry) {
            $date = substr($entry->createdAt, 0, 10);
            $this->assertGreaterThanOrEqual('2024-06-01', $date);
            $this->assertLessThanOrEqual('2024-08-31', $date);
        }
    }

    // ============================================================
    // Group C: Pagination + sorting
    // ============================================================

    #[Test]
    public function shouldApplyLimit(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', limit: 3);

        $this->assertCount(3, $results);
    }

    #[Test]
    public function shouldApplyOffset(): void
    {
        $allResults = $this->storage->getWithFilters('App\Models\User');
        $offsetResults = $this->storage->getWithFilters('App\Models\User', offset: 5, limit: 5);

        $this->assertCount(5, $offsetResults);
        // Records should match
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($allResults[5 + $i]->entityId, $offsetResults[$i]->entityId);
        }
    }

    #[Test]
    public function shouldApplyOrderBy(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', orderBy: 'user_id ASC');

        $prevUserId = null;
        foreach ($results as $entry) {
            if ($prevUserId !== null) {
                $this->assertGreaterThanOrEqual($prevUserId, $entry->userId ?? 0);
            }
            $prevUserId = $entry->userId;
        }
    }

    #[Test]
    public function shouldCombineLimitAndOffset(): void
    {
        $allResults = $this->storage->getWithFilters('App\Models\User', orderBy: 'created_at DESC');
        $limitedResults = $this->storage->getWithFilters('App\Models\User', offset: 2, limit: 3, orderBy: 'created_at DESC');

        $this->assertCount(3, $limitedResults);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame($allResults[2 + $i]->entityId, $limitedResults[$i]->entityId);
            $this->assertSame($allResults[2 + $i]->createdAt, $limitedResults[$i]->createdAt);
        }
    }

    // ============================================================
    // Group D: Count
    // ============================================================

    #[Test]
    public function countShouldReturnTotalWhenNoFilters(): void
    {
        $count = $this->storage->countWithFilters('App\Models\User');

        $this->assertSame(10, $count);
    }

    #[Test]
    public function countShouldReturnZeroWhenNoMatches(): void
    {
        $count = $this->storage->countWithFilters('App\Models\User', entityId: 9999);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function countShouldApplyFilters(): void
    {
        $count = $this->storage->countWithFilters('App\Models\User', operation: Operation::Update);

        $this->assertSame(4, $count);

        // Verify getWithFilters returns same count
        $results = $this->storage->getWithFilters('App\Models\User', operation: Operation::Update);
        $this->assertCount($count, $results);
    }

    // ============================================================
    // Group E: JSON encoding/decoding
    // ============================================================

    #[Test]
    public function shouldEncodeAndDecodeJsonFields(): void
    {
        $results = $this->storage->getWithFilters('App\Models\User', entityId: 1, orderBy: 'created_at ASC');

        $this->assertNotEmpty($results);
        $firstEntry = $results[0];

        // changed_attributes should be decoded
        $this->assertIsArray($firstEntry->changedAttributes);
        $this->assertArrayHasKey('name', $firstEntry->changedAttributes);
        $this->assertSame(['old' => null, 'new' => 'John'], $firstEntry->changedAttributes['name']);

        // custom_data should be decoded
        $this->assertIsArray($firstEntry->customData);
        $this->assertSame(['source' => 'admin_panel'], $firstEntry->customData);
    }
}
