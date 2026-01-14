<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Auxl;
use App\Models\Proses;
use App\Models\DetailProses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Normalisasi ulang kolom order untuk semua proses pending
     * pada satu mesin (mulai = null, selesai = null).
     */
    private function reorderPendingProcessesForMachine($mesinId): void
    {
        if (!$mesinId) {
            return;
        }

        $pending = Proses::where('mesin_id', $mesinId)
            ->whereNull('mulai')
            ->whereNull('selesai')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $i = 1;
        foreach ($pending as $proses) {
            $proses->order = $i++;
            $proses->save();
        }
    }

    /**
     * Daftar approval untuk FM (filter type = FM).
     * Biasanya hanya menampilkan data dengan status pending,
     * tetapi bisa disesuaikan jika ingin menampilkan semua.
     */
    public function approval_fm()
    {
        $approvals = Approval::with(['proses.details', 'auxl', 'requester', 'approver'])
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
        $approvals = Approval::with(['proses.details', 'auxl', 'requester', 'approver'])
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
                if ($approval->auxl_id) {
                    $approval->load('auxl');
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
        if ($approval->action === 'create_aux_reprocess') {
            $auxl = Auxl::find($approval->auxl_id);

            if (! $auxl) {
                throw new \Exception("Data Auxl tidak ditemukan untuk approval ini.");
            }

            // Setelah FM approve, otomatis buat approval VP jika belum ada
            if ($approval->type === 'FM') {
                $existingVpApproval = Approval::where('auxl_id', $auxl->id)
                    ->where('type', 'VP')
                    ->where('action', 'create_aux_reprocess')
                    ->first();

                if (! $existingVpApproval) {
                    // Ambil details dari auxl yang masih ada
                    $auxlDetails = $auxl->details ? $auxl->details->toArray() : [];
                    
                    // Jika tidak ada details di auxl, coba ambil dari approval FM sebelumnya
                    if (empty($auxlDetails) && $approval->history_data && isset($approval->history_data['details'])) {
                        $auxlDetails = $approval->history_data['details'];
                    }
                    
                    Approval::create([
                        'auxl_id'     => $auxl->id,
                        'status'      => 'pending',
                        'type'        => 'VP',
                        'action'      => 'create_aux_reprocess',
                        'history_data'=> [
                            'auxl_snapshot' => $auxl->toArray(),
                            'details'       => $auxlDetails,
                            'fm_approval_id'=> $approval->id,
                        ],
                        'note'        => null,
                        'requested_by'=> $approval->requested_by,
                        'approved_by' => null,
                    ]);
                }
            }

            // Tidak ada eksekusi lanjutan untuk VP selain mencatat approval
            return;
        }

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
                // Update mesin_id di Proses dan normalisasi ulang urutan (order)
                if (!isset($history['new_mesin_id']) || !isset($history['old_mesin_id'])) {
                    throw new \Exception("Data 'old_mesin_id' atau 'new_mesin_id' tidak ditemukan dalam history_data.");
                }

                $oldMesinId = (int) $history['old_mesin_id'];
                $newMesinId = (int) $history['new_mesin_id'];

                DB::transaction(function () use ($proses, $oldMesinId, $newMesinId) {
                    // Pindahkan proses ke mesin baru
                    $proses->mesin_id = $newMesinId;
                    $proses->save();

                    // Normalisasi ulang order di mesin asal dan mesin tujuan
                    $this->reorderPendingProcessesForMachine($oldMesinId);
                    $this->reorderPendingProcessesForMachine($newMesinId);
                });
                break;

            case 'create_reprocess':
                // Untuk create_reprocess dengan type FM: setelah FM approve, buat approval VP
                if ($approval->type === 'FM') {
                    // Cek apakah sudah ada approval VP untuk proses ini
                    $existingVpApproval = Approval::where('proses_id', $proses->id)
                        ->where('type', 'VP')
                        ->where('action', 'create_reprocess')
                        ->first();
                    
                    // Jika belum ada approval VP, buat approval VP baru
                    if (!$existingVpApproval) {
                        // Ambil snapshot DetailProses dari history_data FM approval atau dari database
                        $detailProsesSnapshots = [];
                        if (isset($history['detail_proses_snapshot']) && is_array($history['detail_proses_snapshot'])) {
                            $detailProsesSnapshots = $history['detail_proses_snapshot'];
                        } else {
                            // Fallback: ambil dari database jika tidak ada di history_data
                            $detailProsesList = DetailProses::where('proses_id', $proses->id)->get();
                            $detailProsesSnapshots = $detailProsesList->map(function ($detail) {
                                return $detail->toArray();
                            })->toArray();
                        }
                        
                        Approval::create([
                            'proses_id'    => $proses->id,
                            'status'       => 'pending',
                            'type'         => 'VP',
                            'action'       => 'create_reprocess',
                            'history_data' => [
                                'proses_snapshot' => $proses->toArray(),
                                'detail_proses_snapshot' => $detailProsesSnapshots, // Snapshot DetailProses untuk history lengkap
                                'fm_approval_id'  => $approval->id, // Track approval FM yang sudah approve
                            ],
                            'note'         => null,
                            'requested_by' => $approval->requested_by, // Tetap dari user yang request awal
                            'approved_by'  => null, // Akan diisi saat VP approve/reject
                        ]);
                    }
                } 
                break;

            case 'swap_position':
                /**
                 * Modifikasi: selain support tukar posisi 2 proses,
                 * juga support "reorder" (pindahkan proses ke posisi proses lain,
                 * dan proses di antaranya ikut bergeser).
                 *
                 * Cara kerja:
                 *  - proses1_id  : proses yang dipindahkan
                 *  - proses2_id  : proses tujuan (proses1 akan pindah ke posisi proses2)
                 *
                 * Contoh:
                 *   Urutan awal: P1(1), P2(2), P3(3)
                 *   User pilih: proses1 = P3, proses2 = P1
                 *   Hasil akhir: P3(1), P1(2), P2(3)
                 */
                if (isset($history['proses1_id']) && isset($history['proses2_id'])) {
                    $proses1Id = (int) $history['proses1_id'];
                    $proses2Id = (int) $history['proses2_id'];

                    // Ambil kedua proses (pakai fresh data saat approval)
                    $proses1 = Proses::find($proses1Id);
                    $proses2 = Proses::find($proses2Id);

                    if (!$proses1 || !$proses2) {
                        throw new \Exception("Salah satu atau kedua proses tidak ditemukan untuk swap/reorder position.");
                    }

                    // Validasi: kedua proses harus di mesin yang sama
                    if ($proses1->mesin_id !== $proses2->mesin_id) {
                        throw new \Exception("Kedua proses harus berada di mesin yang sama untuk swap/reorder position.");
                    }

                    $mesinId  = $proses1->mesin_id;
                    $oldOrder = (int) ($proses1->order ?? 0);
                    $newOrder = (int) ($proses2->order ?? 0);

                    // Jika belum ada order yang jelas, normalisasi dulu berdasarkan ID
                    if ($oldOrder === 0 || $newOrder === 0) {
                        $allProses = Proses::where('mesin_id', $mesinId)
                            ->orderBy('order')
                            ->orderBy('id')
                            ->get();

                        $idx = 1;
                        foreach ($allProses as $p) {
                            $p->order = $idx++;
                            $p->save();
                        }

                        // Refresh nilai order setelah normalisasi
                        $proses1->refresh();
                        $proses2->refresh();
                        $oldOrder = (int) $proses1->order;
                        $newOrder = (int) $proses2->order;
                    }

                    if ($oldOrder === $newOrder) {
                        // Tidak ada perubahan posisi
                        break;
                    }

                    DB::transaction(function () use ($mesinId, $proses1, $oldOrder, $newOrder) {
                        // Pindahkan proses1 ke posisi newOrder
                        if ($newOrder < $oldOrder) {
                            // Contoh: 3 -> 1  => geser 1..2 jadi 2..3
                            Proses::where('mesin_id', $mesinId)
                                ->where('id', '!=', $proses1->id)
                                ->whereBetween('order', [$newOrder, $oldOrder - 1])
                                ->increment('order');
                        } else {
                            // Contoh: 1 -> 3  => geser 2..3 jadi 1..2
                            Proses::where('mesin_id', $mesinId)
                                ->where('id', '!=', $proses1->id)
                                ->whereBetween('order', [$oldOrder + 1, $newOrder])
                                ->decrement('order');
                        }

                        $proses1->order = $newOrder;
                        $proses1->save();
                    });
                } else {
                    throw new \Exception("Data 'proses1_id' atau 'proses2_id' tidak ditemukan dalam history_data untuk swap_position.");
                }
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
            case 'create_aux_reprocess':
                // Jika approval Auxl ditolak, hapus Auxl dan approval VP yang masih pending
                $auxl = Auxl::find($approval->auxl_id);
                if ($auxl) {
                    $auxl->delete();
                }

                Approval::where('auxl_id', $approval->auxl_id)
                    ->where('action', 'create_aux_reprocess')
                    ->where('type', 'VP')
                    ->where('status', 'pending')
                    ->delete();
                break;

            case 'create_reprocess':
                // Jika create_reprocess di-reject oleh FM, hapus proses yang baru dibuat
                // Jika di-reject oleh VP, juga hapus proses
                $proses = $approval->proses;
                if ($proses) {
                    $proses->delete();
                }
                break;

            case 'edit_cycle_time':
            case 'delete_proses':
            case 'move_machine':
            case 'swap_position':
                // Untuk action lain, tidak perlu action khusus saat reject
                // Data tetap seperti semula (tidak ada perubahan)
                break;

            default:
                // Action tidak dikenali, tidak perlu action khusus
                break;
        }
    }
}