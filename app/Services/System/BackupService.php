<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Portable database backup via PDO (works on shared hosting without the mysqldump binary).
 * Writes storage/app/backups/auxtech-YYYYmmdd-His.sql.gz and keeps 30 days.
 */
class BackupService
{
    public function run(int $keepDays = 30): string
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $sql = '-- Auxtech Ops backup '.now()->toDateTimeString()." ({$driver})\nSET FOREIGN_KEY_CHECKS=0;\n";
        $tables = $driver === 'sqlite'
            ? collect($conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name')
            : collect($conn->select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]);
        $pdo = $conn->getPdo();
        foreach ($tables as $t) {
            if ($driver !== 'sqlite') {
                $create = (array) $conn->selectOne("SHOW CREATE TABLE `{$t}`");
                $sql .= "\nDROP TABLE IF EXISTS `{$t}`;\n".array_values($create)[1].";\n";
            }
            foreach ($conn->table($t)->cursor() as $row) {
                $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), (array) $row);
                $sql .= "INSERT INTO `{$t}` (`".implode('`,`', array_keys((array) $row)).'`) VALUES ('.implode(',', $vals).");\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $path = 'backups/auxtech-'.now()->format('Ymd-His').'.sql.gz';
        Storage::disk('local')->put($path, gzencode($sql, 6));

        foreach (Storage::disk('local')->files('backups') as $f) {
            if (Storage::disk('local')->lastModified($f) < now()->subDays($keepDays)->getTimestamp()) {
                Storage::disk('local')->delete($f);
            }
        }

        return $path;
    }
}
