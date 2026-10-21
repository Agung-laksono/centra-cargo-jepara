<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\UpdateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemUpdateController extends Controller
{
    /**
     * Pastikan pengguna adalah Administrator (Super Admin atau Admin Ekspedisi)
     */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->expedition_id === null || in_array($user->role, ['ADMIN', 'SUPER_ADMIN', 'OWNER'])), 403, 'Akses ditolak. Fitur pembaruan sistem hanya dapat diakses oleh Administrator.');
    }

    /**
     * Cek status pembaruan ke GitHub
     */
    public function check(Request $request, UpdateService $updateService): JsonResponse
    {
        $this->authorizeAdmin();

        $repo = $request->input('repo') 
            ?: Setting::withoutGlobalScope('expedition')->where('key', 'github_repo')->value('value') 
            ?: env('GITHUB_REPO', '');

        $branch = $request->input('branch') 
            ?: Setting::withoutGlobalScope('expedition')->where('key', 'github_branch')->value('value') 
            ?: env('GITHUB_BRANCH', 'main');

        if (empty($repo)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nama Repository GitHub belum diatur. Masukkan format Username/NamaRepo.',
            ], 422);
        }

        $result = $updateService->checkLatestUpdate($repo, $branch);
        return response()->json($result);
    }

    /**
     * Simpan Konfigurasi Repository GitHub ke Database
     */
    public function saveConfig(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'repo' => 'required|string|max:255',
            'branch' => 'required|string|max:100',
            'token' => 'nullable|string|max:255',
        ]);

        Setting::withoutGlobalScope('expedition')->updateOrCreate(
            ['key' => 'github_repo'],
            ['value' => trim($validated['repo'])]
        );

        Setting::withoutGlobalScope('expedition')->updateOrCreate(
            ['key' => 'github_branch'],
            ['value' => trim($validated['branch'])]
        );

        if (!empty($validated['token'])) {
            Setting::withoutGlobalScope('expedition')->updateOrCreate(
                ['key' => 'github_token'],
                ['value' => trim($validated['token'])]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan GitHub Repository berhasil disimpan.',
        ]);
    }

    /**
     * Jalankan Pembaruan Sistem secara Bertahap (Step 1-4)
     */
    public function executeStep(Request $request, UpdateService $updateService): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'step' => 'required|integer|in:1,2,3,4',
            'repo' => 'nullable|string',
            'branch' => 'nullable|string',
            'commit_sha' => 'nullable|string',
        ]);

        $step = (int) $request->input('step');
        $repo = $request->input('repo') 
            ?: Setting::withoutGlobalScope('expedition')->where('key', 'github_repo')->value('value') 
            ?: env('GITHUB_REPO', '');

        $branch = $request->input('branch') 
            ?: Setting::withoutGlobalScope('expedition')->where('key', 'github_branch')->value('value') 
            ?: env('GITHUB_BRANCH', 'main');

        try {
            switch ($step) {
                case 1:
                    // Fase 1: Backup Database
                    $backupResult = $updateService->createBackup();
                    return response()->json([
                        'status' => 'success',
                        'step' => 1,
                        'message' => 'Fase 1/4 Selesai: Database berhasil diamankan sebelum pembaruan.',
                        'data' => $backupResult,
                    ]);

                case 2:
                    // Fase 2: Unduh File ZIP dari GitHub
                    if (empty($repo)) {
                        throw new Exception('Repository GitHub belum diatur.');
                    }
                    $updateService->downloadUpdate($repo, $branch);
                    return response()->json([
                        'status' => 'success',
                        'step' => 2,
                        'message' => "Fase 2/4 Selesai: Paket kode terbaru dari branch '{$branch}' berhasil diunduh.",
                    ]);

                case 3:
                    // Fase 3: Ekstrak dan Timpa File Aplikasi
                    $updateService->extractAndApplyUpdate();
                    return response()->json([
                        'status' => 'success',
                        'step' => 3,
                        'message' => 'Fase 3/4 Selesai: Berkas sistem berhasil diperbarui dan ditimpa secara aman.',
                    ]);

                case 4:
                    // Fase 4: Migrasi & Reset Cache
                    $commitSha = $request->input('commit_sha');
                    $updateService->finalizeUpdate($commitSha);
                    return response()->json([
                        'status' => 'success',
                        'step' => 4,
                        'message' => 'Pembaruan Selesai 100%! Sistem berhasil diperbarui ke versi terbaru.',
                    ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'step' => $step,
                'message' => 'Gagal pada Fase ' . $step . ': ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['status' => 'error', 'message' => 'Tahap tidak valid.'], 400);
    }
}
