<?php

namespace App\Http\Controllers;

use App\Models\Auxl;
use App\Models\AuxlDetail;
use App\Models\BarcodeAux;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuxlController extends Controller
{
    public function index()
    {
        $auxls = Auxl::with('details')->orderByDesc('created_at')->get();

        // Tandai auxl yang sudah dipakai proses (ter-scan sebagai Barcode AUX aktif).
        $usageCountsByBarcode = collect();
        if ($auxls->isNotEmpty()) {
            $barcodes = $auxls->pluck('barcode')->filter()->unique()->values();
            if ($barcodes->isNotEmpty()) {
                $usageCountsByBarcode = BarcodeAux::whereIn('barcode', $barcodes)
                    ->where('cancel', false)
                    ->selectRaw('barcode, COUNT(*) as total')
                    ->groupBy('barcode')
                    ->pluck('total', 'barcode');
            }
        }

        // Tandai auxl yang masih menunggu approval (FM atau VP) untuk jenis reproses
        if ($auxls->isNotEmpty()) {
            $pendingApprovals = Approval::whereIn('auxl_id', $auxls->pluck('id'))
                ->where('action', 'create_aux_reprocess')
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('auxl_id');

            $auxls->transform(function ($auxl) use ($pendingApprovals) {
                $auxlCollection = $pendingApprovals->get($auxl->id);
                $auxl->pendingApproval = $auxlCollection ? $auxlCollection->first() : null;
                return $auxl;
            });
        }

        $auxls->transform(function ($auxl) use ($usageCountsByBarcode) {
            $usedCount = (int) ($usageCountsByBarcode[$auxl->barcode] ?? 0);
            $auxl->isUsedByProses = $usedCount > 0;
            $auxl->usedCount = $usedCount;
            return $auxl;
        });

        return view('auxl.index', compact('auxls'));
    }

    public function create()
    {
        return view('auxl.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jenis' => 'required',
            'code' => 'required',
            'konstruksi' => 'nullable',
            'customer' => 'nullable',
            'marketing' => 'nullable',
            'date' => 'nullable|date',
            'color' => 'nullable',
            'details' => 'required|array',
            'details.*.auxiliary' => 'required',
            'details.*.konsentrasi' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($data) {
            // Generate barcode unik: AUX-[running number 10 digit]
            $last = Auxl::orderByDesc('id')->first();
            $nextNumber = $last ? ($last->id + 1) : 1;
            $barcode = 'AUX-' . str_pad($nextNumber, 10, '0', STR_PAD_LEFT);
            $data['barcode'] = $barcode;

            $auxl = Auxl::create($data);
            foreach ($data['details'] as $detail) {
                $auxl->details()->create($detail);
            }

            // Jika jenis reproses, trigger approval 2 step (FM lalu VP)
            if ($auxl->jenis === 'reproses') {
                $this->createReprocessApproval($auxl);

                return redirect()
                    ->route('aux.index')
                    ->with('success', 'Data Auxl disimpan dengan status menunggu approval FM & VP. Barcode: ' . $barcode);
            }

            return redirect()
                ->route('aux.index')
                ->with('success', 'Data Auxl berhasil disimpan. Barcode: ' . $barcode);
        });
    }

    public function show($id)
    {
        $auxl = Auxl::with('details')->findOrFail($id);
        return view('auxl.show', compact('auxl'));
    }

    public function edit($id)
    {
        $auxl = Auxl::with('details')->findOrFail($id);
        return view('auxl.edit', compact('auxl'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'jenis' => 'required',
            'code' => 'required',
            'konstruksi' => 'nullable',
            'customer' => 'nullable',
            'marketing' => 'nullable',
            'date' => 'nullable|date',
            'color' => 'nullable',
            'barcode' => 'nullable',
            'details' => 'required|array',
            'details.*.auxiliary' => 'required',
            'details.*.konsentrasi' => 'required|numeric',
        ]);
        $auxl = Auxl::findOrFail($id);
        $auxl->update($data);
        // Hapus detail lama, simpan ulang
        $auxl->details()->delete();
        foreach ($data['details'] as $detail) {
            $auxl->details()->create($detail);
        }
        return redirect()->route('aux.index')->with('success', 'Data Auxl berhasil diupdate.');
    }

    public function destroy($id)
    {
        $auxl = Auxl::findOrFail($id);
        $auxl->delete();
        return redirect()->route('aux.index')->with('success', 'Data Auxl berhasil dihapus.');
    }

    /**
     * Membuat approval awal untuk alur reproses:
     * 1) Pending FM
     * 2) Setelah FM approve, otomatis dibuat approval VP (di ApprovalController)
     */
    private function createReprocessApproval(Auxl $auxl): void
    {
        // Cegah duplikasi approval FM yang masih pending
        $existingPending = Approval::where('auxl_id', $auxl->id)
            ->where('action', 'create_aux_reprocess')
            ->where('type', 'FM')
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            return;
        }

        Approval::create([
            'auxl_id'     => $auxl->id,
            'status'      => 'pending',
            'type'        => 'FM',
            'action'      => 'create_aux_reprocess',
            'history_data'=> [
                'auxl_snapshot' => $auxl->toArray(),
                'details'       => $auxl->details()->get()->toArray(),
            ],
            'requested_by'=> Auth::id(),
        ]);
    }

    /**
     * Proxy untuk Select2 auxiliary dari API eksternal.
     * Route: POST /api/proxy-auxiliary
     */
    public function proxyAuxiliary(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'http://18.139.142.16:8020/sap/bc/zdyes/zterima_zchm?sap-client=100', [
                'headers' => [
                    'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                    'Content-Type' => 'text/plain',
                    'Accept' => 'application/json',
                ],
                'body' => '"' . $q . '"',
                'timeout' => 10,
            ]);
            $data = json_decode($response->getBody(), true);
            $results = collect($data)
                ->filter(function($item) use ($q) {
                    return isset($item['matnr']);
                })
                ->map(function($item) {
                    return [
                        'id' => $item['matnr'],
                        'text' => $item['matnr'],
                    ];
                })
                ->values()
                ->all();
            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    /**
     * Proxy untuk Select2 customer dari API SAP.
     * Route: POST /api/proxy-customer
     */
    public function proxyCustomerSearch(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'http://18.139.142.16:8020/sap/bc/zdyes/zterima_cstmr?sap-client=100', [
                'headers' => [
                    'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                    'Content-Type' => 'text/plain',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($q),
                'timeout' => 10,
            ]);
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return response()->json(['results' => []]);
            }
            $results = collect($data)
                ->filter(fn ($item) => isset($item['customer']))
                ->map(function ($item) {
                    $customer = $item['customer'];
                    return [
                        'id' => $customer,
                        'text' => $customer,
                    ];
                })
                ->unique('id')
                ->values()
                ->all();
            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    public function proxyMarketingSearch(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', 'http://18.139.142.16:8020/sap/bc/zdyes/zterima_mkt?sap-client=100', [
                'headers' => [
                    'Authorization' => 'Basic RFRfV01TOldtczAxMTEyMDI1QA==',
                    'Content-Type' => 'text/plain',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($q),
                'timeout' => 10,
            ]);
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                return response()->json(['results' => []]);
            }
            $results = collect($data)
                ->filter(fn ($item) => isset($item['marketing']))
                ->map(function ($item) {
                    $marketing = $item['marketing'];
                    return [
                        'id' => $marketing,
                        'text' => $marketing,
                    ];
                })
                ->unique('id')
                ->values()
                ->all();
            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }
}
