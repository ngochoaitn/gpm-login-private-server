<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PclZip;
use Illuminate\Support\Facades\DB;

class UpdateService
{
    public function updateFromRemoteZip(string $zipUrl = 'https://github.com/ngochoaitn/gpm-login-private-server/releases/download/latest/latest-update.zip')
    {
        $zipFileName = 'update.zip';
        $zipFilePath = storage_path('app/' . $zipFileName);

        try {
            if (!$this->downloadFileFromUrl($zipUrl, $zipFilePath)) {
                return ['success' => false, 'message' => 'Cannot download ZIP file'];
            }

            $archive = new PclZip($zipFilePath);
            $destination = base_path();

            if ($archive->extract(PCLZIP_OPT_PATH, $destination) == 0) {
                return ['success' => false, 'message' => 'Failed to extract the ZIP file'];
            }

            Storage::delete($zipFileName);

            try {
                $this->migrationDatabase();
            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()];
            }

            return [
                'success' => true,
                'message' => 'ok'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }



    private function downloadFileFromUrl(string $url, string $fileName)
    {
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n" .
                    "Accept: */*\r\n" .
                    "Accept-Encoding: gzip, deflate, br\r\n"
            ],
            "https" => [
                "method" => "GET",
                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) coc_coc_browser/87.0.152 Chrome/81.0.4044.152 Safari/537.36\r\n" .
                    "Accept: */*\r\n" .
                    "Accept: */*\r\n" .
                    "Accept-Encoding: gzip, deflate, br\r\n"
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $context = stream_context_create($opts);

        $content = @file_get_contents($url, false, $context);
        if ($content != false) {
            file_put_contents($fileName, $content);
            return true;
        } else {
            return false;
        }
    }

    public function migrationDatabase()
    {
        try {
            $this->createMigrationsTable();

            $migrationsPath = database_path('migrations');
            $migrationFiles = glob($migrationsPath . '/*.php');
            sort($migrationFiles);

            $executedMigrations = [];
            $errors = [];

            foreach ($migrationFiles as $migrationFile) {
                $migrationName = basename($migrationFile, '.php');

                if ($this->migrationAlreadyRun($migrationName)) {
                    continue;
                }

                try {
                    $sqlStatements = $this->convertMigrationToSQL($migrationFile);

                    foreach ($sqlStatements as $sql) {
                        if (!empty(trim($sql))) {
                            DB::statement($sql);
                        }
                    }

                    $this->recordMigration($migrationName);
                    $executedMigrations[] = $migrationName;

                } catch (\Exception $e) {
                    $errors[] = "Migration {$migrationName} failed: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                throw new \Exception('Some migrations failed: ' . implode('; ', $errors));
            }

            return [
                'success' => true,
                'message' => 'ok',
                'executed' => $executedMigrations
            ];

        } catch (\Exception $e) {
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }


    private function convertMigrationToSQL(string $migrationFile): array
    {
        $migrationName = basename($migrationFile, '.php');
        $sqlStatements = [];

        return $sqlStatements;
    }


    private function createMigrationsTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL
        )";

        DB::statement($sql);
    }

    private function migrationAlreadyRun(string $migrationName): bool
    {
        return DB::table('migrations')
            ->where('migration', $migrationName)
            ->exists();
    }

    private function recordMigration(string $migrationName)
    {
        // Get the next batch number
        $batch = DB::table('migrations')->max('batch') ?? 0;
        $batch++;

        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $batch
        ]);
    }
}
