<?php

namespace Tests\Feature;

use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sql_backup_explicitly_requires_a_mysql_connection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Backup SQL saat ini hanya mendukung koneksi MySQL.');

        app(DatabaseBackupService::class)->writeTo(fopen('php://temp', 'w+b'));
    }
}
