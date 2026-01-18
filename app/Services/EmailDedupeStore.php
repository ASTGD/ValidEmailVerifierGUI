<?php

namespace App\Services;

use PDO;

class EmailDedupeStore
{
    private array $memory = [];
    private int $memoryLimit;
    private ?PDO $sqlite = null;
    private ?string $sqlitePath = null;

    public function __construct(int $memoryLimit)
    {
        $this->memoryLimit = max(0, $memoryLimit);
    }

    public function isNew(string $email): bool
    {
        if ($this->sqlite) {
            return $this->insertSqlite($email);
        }

        if (isset($this->memory[$email])) {
            return false;
        }

        $this->memory[$email] = true;

        if ($this->memoryLimit > 0 && count($this->memory) > $this->memoryLimit) {
            $this->migrateToSqlite();
        }

        return true;
    }

    public function cleanup(): void
    {
        $this->sqlite = null;

        if ($this->sqlitePath && file_exists($this->sqlitePath)) {
            @unlink($this->sqlitePath);
        }

        $this->sqlitePath = null;
    }

    private function migrateToSqlite(): void
    {
        $this->sqlitePath = tempnam(sys_get_temp_dir(), 'verifier-dedupe-');
        $this->sqlite = new PDO('sqlite:'.$this->sqlitePath);
        $this->sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqlite->exec('CREATE TABLE IF NOT EXISTS emails (email TEXT PRIMARY KEY)');

        $statement = $this->sqlite->prepare('INSERT OR IGNORE INTO emails (email) VALUES (:email)');

        foreach (array_keys($this->memory) as $email) {
            $statement->execute(['email' => $email]);
        }

        $this->memory = [];
    }

    private function insertSqlite(string $email): bool
    {
        $statement = $this->sqlite->prepare('INSERT OR IGNORE INTO emails (email) VALUES (:email)');
        $statement->execute(['email' => $email]);

        return $statement->rowCount() > 0;
    }
}
