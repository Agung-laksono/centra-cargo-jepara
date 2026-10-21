<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BackupService
{
    /**
     * Dapatkan path direktori folder cadangan
     */
    public function getBackupDirectory(): string
    {
        $dir = storage_path('app/backups');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Aktifkan Maintenance Mode untuk mengunci aplikasi dari input user luar
     */
    public function enableMaintenanceMode(): void
    {
        try {
            if (!app()->isDownForMaintenance()) {
                Artisan::call('down', [
                    '--render' => 'errors::503',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengaktifkan maintenance mode: ' . $e->getMessage());
        }
    }

    /**
     * Nonaktifkan Maintenance Mode untuk membuka kembali aplikasi secara normal
     */
    public function disableMaintenanceMode(): void
    {
        try {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal menonaktifkan maintenance mode: ' . $e->getMessage());
        }
    }

    /**
     * Dapatkan daftar seluruh file backup yang tersedia
     */
    public function getBackupsList(): array
    {
        $backupDir = $this->getBackupDirectory();
        $files = File::files($backupDir);

        $backups = [];
        foreach ($files as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['zip', 'sqlite'])) {
                continue;
            }

            $sizeBytes = $file->getSize();
            $sizeFormatted = $sizeBytes >= 1048576
                ? round($sizeBytes / 1048576, 2) . ' MB'
                : round($sizeBytes / 1024, 2) . ' KB';

            $timestamp = $file->getMTime();
            $filename = $file->getFilename();

            // Tentukan tipe cadangan untuk tampilan UI
            $type = 'Manual';
            if (str_starts_with($filename, 'backup_safety_pre_restore_')) {
                $type = 'Safety Snapshot';
            } elseif (str_starts_with($filename, 'backup_cargo_')) {
                $type = 'Sistem / Otomatis';
            } elseif (str_contains($filename, '_uploaded_')) {
                $type = 'Unggahan';
            }

            $backups[] = [
                'name' => $filename,
                'path' => $file->getPathname(),
                'size' => $sizeFormatted,
                'size_bytes' => $sizeBytes,
                'date' => Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s'),
                'human_date' => Carbon::createFromTimestamp($timestamp)->diffForHumans(),
                'timestamp' => $timestamp,
                'type' => $type,
                'is_zip' => $ext === 'zip',
            ];
        }

        // Urutkan dari yang paling baru
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Buat berkas cadangan kompresi ZIP baru (Database SQLite, Media Public, & .env)
     */
    public function createBackup(string $prefix = 'backup_cargo_'): array
    {
        $backupDir = $this->getBackupDirectory();
        $timestampStr = now()->format('Y-m-d_H-i-s');
        $fileName = $prefix . $timestampStr . '.zip';
        $filePath = $backupDir . '/' . $fileName;

        if (!class_exists('ZipArchive')) {
            // Fallback jika ekstensi ZipArchive PHP belum aktif
            $sqlitePath = database_path('database.sqlite');
            if (File::exists($sqlitePath)) {
                $sqliteBackupName = $prefix . $timestampStr . '.sqlite';
                File::copy($sqlitePath, $backupDir . '/' . $sqliteBackupName);
                return [
                    'status' => 'success',
                    'name' => $sqliteBackupName,
                    'message' => 'Cadangan database SQLite berhasil dibuat (tanpa media karena ZipArchive tidak aktif).',
                ];
            }
            throw new Exception('Ekstensi ZipArchive PHP tidak aktif dan berkas SQLite tidak ditemukan.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Gagal membuat berkas arsip ZIP cadangan.');
        }

        // 1. Tambahkan semua database SQLite di database/
        $dbFiles = File::files(database_path());
        $dbCount = 0;
        foreach ($dbFiles as $f) {
            if (strtolower($f->getExtension()) === 'sqlite') {
                $zip->addFile($f->getPathname(), 'database/' . $f->getFilename());
                $dbCount++;
            }
        }

        // 2. Tambahkan berkas media upload di storage/app/public/
        $publicStoragePath = storage_path('app/public');
        $mediaCount = 0;
        if (File::isDirectory($publicStoragePath)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($publicStoragePath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $item) {
                if (!$item->isDir()) {
                    $realPath = $item->getRealPath();
                    $relPath = 'public/' . substr($realPath, strlen($publicStoragePath) + 1);
                    $zip->addFile($realPath, str_replace('\\', '/', $relPath));
                    $mediaCount++;
                }
            }
        }

        // 3. Tambahkan konfigurasi .env sebagai .env.backup
        if (File::exists(base_path('.env'))) {
            $zip->addFile(base_path('.env'), '.env.backup');
        }

        $zip->close();

        return [
            'status' => 'success',
            'name' => $fileName,
            'size' => round(filesize($filePath) / 1048576, 2) . ' MB',
            'db_count' => $dbCount,
            'media_count' => $mediaCount,
            'message' => "Cadangan berhasil dibuat ({$dbCount} file database, {$mediaCount} berkas media).",
        ];
    }

    /**
     * Unggah berkas cadangan dari komputer lokal
     */
    public function uploadBackup(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['zip', 'sqlite'])) {
            throw new Exception('Format berkas tidak didukung. Hanya berkas .zip atau .sqlite yang diperbolehkan.');
        }

        // Validasi integritas zip jika berkas zip
        if ($ext === 'zip' && class_exists('ZipArchive')) {
            $testZip = new ZipArchive();
            $res = $testZip->open($file->getRealPath());
            if ($res !== true) {
                throw new Exception('Berkas ZIP yang diunggah rusak atau tidak terbaca.');
            }
            $testZip->close();
        }

        $backupDir = $this->getBackupDirectory();
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());

        $targetPath = $backupDir . '/' . $safeName;
        if (File::exists($targetPath)) {
            $baseNameOnly = pathinfo($safeName, PATHINFO_FILENAME);
            $safeName = $baseNameOnly . '_uploaded_' . now()->format('Ymd_His') . '.' . $ext;
            $targetPath = $backupDir . '/' . $safeName;
        }

        $file->move($backupDir, $safeName);

        return [
            'status' => 'success',
            'name' => $safeName,
            'message' => "Berkas cadangan '{$safeName}' berhasil diunggah.",
        ];
    }

    /**
     * Pulihkan sistem dari berkas cadangan (Database SQLite dan Media Public)
     */
    public function restoreBackup(string $fileName): array
    {
        $safeName = basename($fileName);
        $backupDir = $this->getBackupDirectory();
        $backupPath = $backupDir . '/' . $safeName;

        if (!File::exists($backupPath)) {
            throw new Exception("Berkas cadangan '{$safeName}' tidak ditemukan.");
        }

        // Aktifkan Maintenance Mode selama pemulihan agar tidak ada input lain
        $this->enableMaintenanceMode();

        try {
            // 1. Putus koneksi aktif SQLite untuk melepas lock file
            DB::disconnect();

            // 2. Buat safety snapshot otomatis sebelum menimpa data aktif
            if (!str_starts_with($safeName, 'backup_safety_pre_restore_')) {
                $this->createBackup('backup_safety_pre_restore_');
            }

            // 3. Ekstrak / Timpa data sesuai jenis ekstensi
            if (str_ends_with($safeName, '.sqlite')) {
                File::copy($backupPath, database_path('database.sqlite'));
            } else {
                if (!class_exists('ZipArchive')) {
                    throw new Exception('Ekstensi ZipArchive PHP tidak aktif, tidak dapat mengekstrak arsip cadangan.');
                }

                $zip = new ZipArchive();
                if ($zip->open($backupPath) !== true) {
                    throw new Exception('Gagal membuka berkas arsip ZIP cadangan.');
                }

                // Validasi: pastikan arsip mengandung minimal 1 database .sqlite
                $hasDatabase = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_ends_with($entryName, '.sqlite')) {
                        $hasDatabase = true;
                        break;
                    }
                }

                if (!$hasDatabase) {
                    $zip->close();
                    throw new Exception('Berkas arsip tidak valid: tidak ditemukan database SQLite di dalamnya.');
                }

                // Ekstrak file-file database SQLite langsung ke database_path()
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_ends_with($entryName, '.sqlite')) {
                        $stream = $zip->getStream($entryName);
                        if ($stream) {
                            $targetDb = database_path(basename($entryName));
                            File::put($targetDb, stream_get_contents($stream));
                            fclose($stream);
                        }
                    }
                }

                // Ekstrak berkas media ke storage/app/public/
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entryName = $zip->getNameIndex($i);
                    if (str_starts_with($entryName, 'public/')) {
                        $stream = $zip->getStream($entryName);
                        if ($stream) {
                            $targetFile = storage_path('app/' . $entryName);
                            File::ensureDirectoryExists(dirname($targetFile));
                            File::put($targetFile, stream_get_contents($stream));
                            fclose($stream);
                        }
                    }
                }

                $zip->close();
            }

            // 4. Bersihkan cache optimasi framework
            Artisan::call('optimize:clear');

            return [
                'status' => 'success',
                'message' => "Sistem berhasil dipulihkan dari cadangan '{$safeName}'.",
            ];
        } finally {
            // Selalu nonaktifkan maintenance mode di akhir
            $this->disableMaintenanceMode();
        }
    }

    /**
     * Hapus berkas cadangan dari penyimpanan server
     */
    public function deleteBackup(string $fileName): bool
    {
        $safeName = basename($fileName);
        $backupPath = $this->getBackupDirectory() . '/' . $safeName;

        if (File::exists($backupPath)) {
            return File::delete($backupPath);
        }

        return false;
    }

    /**
     * Dapatkan path absolut berkas cadangan untuk unduhan yang aman
     */
    public function getBackupFilePath(string $fileName): ?string
    {
        $safeName = basename($fileName);
        $backupPath = $this->getBackupDirectory() . '/' . $safeName;

        if (File::exists($backupPath)) {
            return $backupPath;
        }

        return null;
    }
}
