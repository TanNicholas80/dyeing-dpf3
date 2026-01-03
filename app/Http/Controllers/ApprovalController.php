<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Proses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Daftar approval untuk FM (filter type = FM).
     * Biasanya hanya menampilkan data dengan status pending,
     * tetapi bisa disesuaikan jika ingin menampilkan semua.
     */
    public function approval_fm()
    {
        $approvals = Approval::with(['proses', 'requester', 'approver'])
            ->where('type', 'FM')
            ->orderByRaw("FIELD(status, 'pending','approved','rejected')")
            ->orderByDesc('created_at')
            ->get();

        return view('approval.approval_fm', compact('approvals'));
    }

    /**
     * Daftar approval untuk VP (filter type = VP).
     */
    public function approval_vp()
    {
        $approvals = Approval::with(['proses', 'requester', 'approver'])
            ->where('type', 'VP')
            ->orderByRaw("FIELD(status, 'pending','approved','rejected')")
            ->orderByDesc('created_at')
            ->get();

        return view('approval.approval_vp', compact('approvals'));
    }

    /**
     * Update status approval (pending -> approved / rejected).
     *
     * Request contoh:
     *  - approval_id : ID approval
     *  - status      : approved / rejected
     *  - note        : (opsional, alasan/komentar)
     */
    public function approval_status(Request $request)
    {
        $validated = $request->validate([
            'approval_id' => 'required|exists:approvals,id',
            'status'      => 'required|in:approved,rejected',
            'note'        => 'nullable|string|max:500',
        ]);

        $approval = Approval::with('proses')->findOrFail($validated['approval_id']);

        // Hanya boleh ubah dari pending
        if ($approval->status !== 'pending') {
            return back()->with('error', 'Status approval sudah diproses dan tidak dapat diubah lagi.');
        }

        // Mulai transaksi database
        DB::beginTransaction();
        
        try {
            $approval->status = $validated['status'];
            $approval->note   = $validated['note'] ?? $approval->note;

            // Simpan siapa yang meng-approve / reject
            if (Auth::check()) {
                $approval->approved_by = Auth::id();
            }

            $approval->save();

            // Jika status approved, eksekusi action sesuai jenis action
            if ($validated['status'] === 'approved') {
                $this->executeApprovedAction($approval);
            } elseif ($validated['status'] === 'rejected') {
                $this->executeRejectedAction($approval);
            }

            // Commit transaksi
            DB::commit();

            $message = $validated['status'] === 'approved' 
                ? 'Approval berhasil disetujui dan perubahan telah diterapkan.' 
                : 'Approval telah ditolak.';

            // Jika butuh response JSON (mis. dari AJAX)
            if ($request->wantsJson()) {
                // Reload approval dengan relasi, tapi jika proses sudah dihapus, skip proses
                $approval->load(['requester', 'approver']);
                if ($approval->proses_id) {
                    try {
                        $approval->load('proses');
                    } catch (\Exception $e) {
                        // Proses sudah dihapus, tidak perlu load
                    }
                }
                
                return response()->json([
                    'status'  => 'success',
                    'message' => $message,
                    'data'    => $approval,
                ]);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            // Rollback jika ada error
            DB::rollBack();
            
            $errorMessage = 'Gagal memproses approval: ' . $e->getMessage();
            
            if ($request->wantsJson()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $errorMessage,
                ], 500);
            }

            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Eksekusi action ketika approval di-approve
     */
    private function executeApprovedAction(Approval $approval)
    {
        $proses = $approval->proses;
        
        // Jika proses tidak ditemukan (sudah dihapus), throw exception
        if (!$proses) {
            throw new \Exception("Proses tidak ditemukan. Mungkin sudah dihapus sebelumnya.");
        }

        $history = $approval->history_data ?? [];

        switch ($approval->action) {
            case 'edit_cycle_time':
                // Update cycle_time di Proses
                if (isset($history['new_cycle_time'])) {
                    $proses->cycle_time = $history['new_cycle_time'];
                    $proses->save();
                } else {
                    throw new \Exception("Data 'new_cycle_time' tidak ditemukan dalam history_data.");
                }
                break;

            case 'delete_proses':
                // Soft delete proses (menggunakan soft deletes, data tidak benar-benar dihapus)
                $proses->delete();
                break;

            case 'move_machine':
                // Update mesin_id di Proses
                if (isset($history['new_mesin_id'])) {
                    $proses->mesin_id = $history['new_mesin_id'];
                    $proses->save();
                } else {
                    throw new \Exception("Data 'new_mesin_id' tidak ditemukan dalam history_data.");
                }
                break;

            case 'create_reprocess':
                // Untuk create_reprocess, proses sudah dibuat sebelumnya
                // Tidak perlu action tambahan, hanya approval saja
                // Proses akan otomatis muncul di dashboard setelah di-approve
                break;

            default:
                // Action tidak dikenali, throw exception
                throw new \Exception("Action '{$approval->action}' tidak dikenali.");
        }
    }

    /**
     * Eksekusi action ketika approval di-reject
     */
    private function executeRejectedAction(Approval $approval)
    {
        switch ($approval->action) {
            case 'create_reprocess':
                // Jika create_reprocess di-reject, hapus proses yang baru dibuat
                $proses = $approval->proses;
                if ($proses) {
                    $proses->delete();
                }
                break;

            case 'edit_cycle_time':
            case 'delete_proses':
            case 'move_machine':
                // Untuk action lain, tidak perlu action khusus saat reject
                // Data tetap seperti semula (tidak ada perubahan)
                break;

            default:
                // Action tidak dikenali, tidak perlu action khusus
                break;
        }
    }
}
