<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class UpdateService
{
    private string $tempZipPath;
    private string $extractPath;

    public function __construct()
    {
        $this->tempZipPath = storage_path('app/temp_update.zip');
        $this->extractPath = storage_path('app/temp_update_extracted');
    }

    /**
     * Dapatkan token GitHub jika ada di .env atau Setting
     */
    private function getGitHubToken(): ?string
    {
        return config('services.github.token') 
            ?: env('GITHUB_TOKEN') 
            ?: Setting::withoutGlobalScope('expedition')->where('key', 'github_token')->value('value');
    }

    /**
     * Header HTTP untuk request ke GitHub
     */
    private function getHeaders(): array
    {
        $headers = [
            'User-Agent' => 'JeparaCargo-Update-System',
            'Cache-Control' => 'no-cache',
        ];

        $token = $this->getGitHubToken();
        if (!empty($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Cek versi terbaru dari GitHub API
     */
    public function checkLatestUpdate(string $repo, string $branch = 'main'): array
    {
        $repo = trim($repo);
        $branch = trim($branch) ?: 'main';
        $timestamp = now()->timestamp;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders($this->getHeaders())
                ->timeout(15)
                ->get("https://api.github.com/repos/{$repo}/commits/{$branch}?t={$timestamp}");

            if ($response->successful()) {
                $data = $response->json();
                $latestSha = $data['sha'] ?? null;

                // Ambil SHA yang terpasang di database / git lokal
                $localSha = Setting::withoutGlobalScope('expedition')->where('key', 'system_current_sha')->value('value');
                if (!$localSha && file_exists(base_path('.git'))) {
                    @exec('git rev-parse HEAD', $gitOutput);
                    $localSha = $gitOutput[0] ?? null;
                }

                $isUpToDate = !empty($localSha) && !empty($latestSha) && (substr($localSha, 0, 7) === substr($latestSha, 0, 7));

                return [
                    'status' => 'success',
                    'commit_sha' => $latestSha,
                    'short_sha' => substr($latestSha, 0, 7),
                    'local_sha' => $localSha,
                    'short_local_sha' => $localSha ? substr($localSha, 0, 7) : 'Belum Ada',
                    'is_up_to_date' => $isUpToDate,
                    'commit_message' => $data['commit']['message'] ?? '',
                    'author' => $data['commit']['author']['name'] ?? 'GitHub User',
                    'date' => $data['commit']['author']['date'] ?? '',
                    'url' => $data['html_url'] ?? '',
                ];
            }

            if ($response->status() === 404) {
                return [
                    'status' => 'error',
                    'message' => "Repository '{$repo}' atau branch '{$branch}' tidak ditemukan di GitHub. Pastikan format penulisan benar (contoh: username/jepara-cargo-app).",
                ];
            }

            if ($response->status() === 403) {
                return [
                    'status' => 'error',
                    'message' => 'Akses GitHub dibatasi (API Rate Limit). Masukkan Personal Access Token jika repository bersifat privat.',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Gagal terhubung ke GitHub API (HTTP ' . $response->status() . ').',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Koneksi ke GitHub gagal: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Tahap 1: Buat Backup Database Otomatis sebelum menimpa file
     */
    public function createBackup(): array
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $backupFileName = 'backup_cargo_' . now()->format('Y-m-d_H-i-s') . '.zip';
        $backupFilePath = $backupDir . '/' . $backupFileName;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($backupFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Simpan semua database sqlite
                $dbFiles = File::files(database_path());
                foreach ($dbFiles as $file) {
                    if ($file->getExtension() === 'sqlite') {
                        $zip->addFile($file->getPathname(), 'database/' . $file->getFilename());
                    }
                }
                // Simpan .env saat ini
                if (File::exists(base_path('.env'))) {
                    $zip->addFile(base_path('.env'), '.env.backup');
                }
                $zip->close();

                return [
                    'status' => 'success',
                    'backup_file' => $backupFileName,
                    'message' => 'Cadangan database berhasil dibuat.',
                ];
            }
        }

        // Fallback jika ZipArchive tidak tersedia
        $defaultSqlite = database_path('database.sqlite');
        if (File::exists($defaultSqlite)) {
            $copyName = 'database.sqlite.bak_' . now()->format('Ymd_His');
            File::copy($defaultSqlite, $backupDir . '/' . $copyName);
            return [
                'status' => 'success',
                'backup_file' => $copyName,
                'message' => 'File database SQLite berhasil diamankan.',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Backup dilewati (database remote/MySQL atau file sqlite tidak ada).',
        ];
    }

    /**
     * Tahap 2: Unduh file ZIP paket kode dari GitHub
     */
    public function downloadUpdate(string $repo, string $branch = 'main'): bool
    {
        $repo = preg_replace('#^https?://github\.com/#i', '', trim($repo));
        $repo = preg_replace('#\.git$#i', '', $repo);
        $repo = trim($repo, '/');
        $branch = trim($branch) ?: 'main';

        if (File::exists($this->tempZipPath)) {
            File::delete($this->tempZipPath);
        }

        // Urutan endpoint unduhan yang tahan banting:
        // 1. Codeload langsung tanpa token (solusi terandal untuk repo publik, bebas blokir 404 dari token usang)
        // 2. GitHub REST API zipball (resmi untuk repo privat dengan Personal Access Token)
        // 3. Web archive standar fallback
        $endpoints = [
            [
                'url' => "https://codeload.github.com/{$repo}/zip/refs/heads/{$branch}",
                'headers' => ['User-Agent' => 'JeparaCargo-Update-System', 'Cache-Control' => 'no-cache'],
            ],
            [
                'url' => "https://api.github.com/repos/{$repo}/zipball/{$branch}",
                'headers' => array_merge($this->getHeaders(), ['Accept' => 'application/vnd.github+json']),
            ],
            [
                'url' => "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip",
                'headers' => ['User-Agent' => 'JeparaCargo-Update-System'],
            ],
        ];

        $lastError = null;
        $success = false;

        foreach ($endpoints as $ep) {
            try {
                if (File::exists($this->tempZipPath)) {
                    File::delete($this->tempZipPath);
                }

                $response = Http::withoutVerifying()
                    ->withHeaders($ep['headers'])
                    ->sink($this->tempZipPath)
                    ->timeout(300)
                    ->get($ep['url']);

                if ($response->successful() && File::exists($this->tempZipPath) && File::size($this->tempZipPath) > 1000) {
                    $success = true;
                    break;
                } else {
                    $lastError = 'HTTP ' . $response->status();
                }
            } catch (Exception $e) {
                $lastError = $e->getMessage();
            }
        }

        if (!$success) {
            if (File::exists($this->tempZipPath)) {
                File::delete($this->tempZipPath);
            }
            throw new Exception("Gagal mengunduh file update dari GitHub ({$lastError}). Pastikan repository publik atau token GitHub valid.");
        }

        return true;
    }

    /**
     * Tahap 3: Ekstrak dan timpa file kode secara aman
     */
    public function extractAndApplyUpdate(): bool
    {
        if (!File::exists($this->tempZipPath)) {
            throw new Exception("File ZIP update tidak ditemukan di server.");
        }

        $zip = new ZipArchive();
        if ($zip->open($this->tempZipPath) !== true) {
            throw new Exception("Gagal membuka file ZIP pembaruan.");
        }

        if (File::exists($this->extractPath)) {
            File::deleteDirectory($this->extractPath);
        }

        $zip->extractTo($this->extractPath);
        $zip->close();

        $extractedFolders = File::directories($this->extractPath);
        if (count($extractedFolders) === 0) {
            throw new Exception("Struktur file ZIP hasil ekstrak tidak valid.");
        }

        $sourcePath = $extractedFolders[0];
        $this->copyDirectoryAndReplace($sourcePath, base_path());

        // Bersihkan direktori temporary
        File::deleteDirectory($this->extractPath);
        if (File::exists($this->tempZipPath)) {
            File::delete($this->tempZipPath);
        }

        return true;
    }

    /**
     * Tahap 4: Migrasi database, bersihkan cache, dan simpan SHA commit aktif
     */
    public function finalizeUpdate(?string $commitSha = null): bool
    {
        // 1. Bersihkan semua cache framework
        Artisan::call('optimize:clear');

        // 2. Jalankan migrasi database otomatis (aman untuk production)
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Exception $e) {
            // Log migrasi jika ada error minor
        }

        // 3. Simpan SHA commit terpasang ke tabel Settings
        if ($commitSha) {
            Setting::withoutGlobalScope('expedition')->updateOrCreate(
                ['key' => 'system_current_sha'],
                ['value' => $commitSha]
            );
        }

        return true;
    }

    /**
     * Copy dan replace direktori secara rekursif dengan proteksi file kritis
     */
    private function copyDirectoryAndReplace(string $source, string $destination): void
    {
        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $items = new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $basename = $item->getBasename();
            $target = $destination . '/' . $basename;

            // PROTEKSI KRITIS 1: JANGAN TIMPA ATAU SENTUH DIREKTORI STORAGE SERVER
            if ($basename === 'storage' && $destination === base_path()) {
                continue;
            }

            // PROTEKSI KRITIS 2: JANGAN TIMPA FILE KONFIGURASI ENV LOKAL/HOSTING
            if (in_array($basename, ['.env', '.env.example', '.env.production', '.env.backup'])) {
                continue;
            }

            // PROTEKSI KRITIS 3: JANGAN TIMPA FILE DATABASE SQLITE OPERASIONAL
            if (str_ends_with(strtolower($basename), '.sqlite') || $basename === 'database.sqlite') {
                continue;
            }

            // Khusus untuk direktori 'public' di root aplikasi, arahkan ke public_path() jika berbeda
            if ($basename === 'public' && $destination === base_path()) {
                $target = public_path();
            }

            if ($item->isDir()) {
                $this->copyDirectoryAndReplace($item->getPathname(), $target);
            } else {
                File::copy($item->getPathname(), $target);
            }
        }
    }
}
