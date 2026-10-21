<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    /**
     * Pastikan pengguna adalah Super Admin Platform Pusat
     */
    private function authorizeSuperAdmin(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && $user->expedition_id === null && in_array($user->role, ['ADMIN', 'SUPER_ADMIN', 'OWNER']),
            403,
            'Akses ditolak. Fitur cadangan dan pemulihan hanya dapat diakses oleh Super Admin / Pemilik Platform.'
        );
    }

    /**
     * Dapatkan daftar berkas cadangan terkini (JSON)
     */
    public function index(BackupService $backupService): JsonResponse
    {
        $this->authorizeSuperAdmin();

        return response()->json([
            'status' => 'success',
            'backups' => $backupService->getBackupsList(),
        ]);
    }

    /**
     * Buat berkas cadangan baru sekarang
     */
    public function create(BackupService $backupService): JsonResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $result = $backupService->createBackup();
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'backups' => $backupService->getBackupsList(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal membuat cadangan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unggah berkas cadangan dari komputer lokal
     */
    public function upload(Request $request, BackupService $backupService): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'backup_file' => 'required|file|max:204800', // Maksimal 200MB
        ], [
            'backup_file.required' => 'Pilih berkas cadangan yang ingin diunggah.',
            'backup_file.max' => 'Ukuran berkas melebihi batas maksimal 200MB.',
        ]);

        try {
            $result = $backupService->uploadBackup($request->file('backup_file'));
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'backups' => $backupService->getBackupsList(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengunggah cadangan: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unduh berkas cadangan ke komputer lokal
     */
    public function download(string $name, BackupService $backupService): BinaryFileResponse
    {
        $this->authorizeSuperAdmin();

        $filePath = $backupService->getBackupFilePath($name);
        if (!$filePath) {
            abort(404, 'Berkas cadangan tidak ditemukan.');
        }

        return response()->download($filePath, basename($filePath));
    }

    /**
     * Pulihkan sistem dari berkas cadangan terpilih
     */
    public function restore(Request $request, BackupService $backupService): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'name' => 'required|string',
        ]);

        try {
            $result = $backupService->restoreBackup($request->input('name'));
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'backups' => $backupService->getBackupsList(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memulihkan cadangan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus berkas cadangan dari server
     */
    public function destroy(string $name, BackupService $backupService): JsonResponse
    {
        $this->authorizeSuperAdmin();

        $deleted = $backupService->deleteBackup($name);
        if (!$deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Berkas cadangan tidak ditemukan atau gagal dihapus.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Berkas cadangan '{$name}' berhasil dihapus.",
            'backups' => $backupService->getBackupsList(),
        ]);
    }
}
