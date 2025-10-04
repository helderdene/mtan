<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateCentralDatabase extends Command
{
    protected $signature = 'db:create-central';
    protected $description = 'Create the central database for multi-tenancy';

    public function handle(): int
    {
        $database = config('database.connections.central.database');
        $host = config('database.connections.central.host');
        $username = config('database.connections.central.username');
        $password = config('database.connections.central.password');
        $charset = config('database.connections.central.charset');
        $collation = config('database.connections.central.collation');

        try {
            // Connect without specifying database
            $pdo = new \PDO(
                "mysql:host={$host}",
                $username,
                $password
            );

            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}`
                       CHARACTER SET {$charset}
                       COLLATE {$collation}");

            $this->info("✓ Database '{$database}' created successfully");

            return 0;
        } catch (\PDOException $e) {
            $this->error("Failed to create database: " . $e->getMessage());
            return 1;
        }
    }
}
