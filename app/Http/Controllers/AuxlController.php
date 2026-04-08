<?php

namespace App\Http\Controllers;

use App\Models\Auxl;
use App\Models\AuxlDetail;
use App\Models\BarcodeAux;
use App\Models\Approval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AuxlController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $auxls = Auxl::query();

            return \Yajra\DataTables\Facades\DataTables::of($auxls)
                ->addColumn('checkbox', function ($auxl) {
                    return '<input type="checkbox" class="barcode-checkbox" value="' . $auxl->barcode . '" data-code="' . $auxl->code . '" data-customer="' . $auxl->customer . '" data-marketing="' . $auxl->marketing . '">';
                })
                ->editColumn('jenis', function ($auxl) {
                    $options = \App\Models\Auxl::getJenisOptions();
                    return $options[$auxl->jenis] ?? ucfirst($auxl->jenis ?? '-');
                })
                ->addColumn('dipakai', function ($auxl) {
                    $usedCount = \App\Models\BarcodeAux::where('barcode', $auxl->barcode)->where('cancel', false)->count();
                    if ($usedCount > 0) {
                        return '<span class="badge badge-success">Sudah dipakai (' . $usedCount . ')</span>';
                    } else {
                        return '<span class="badge badge-secondary">Belum dipakai</span>';
                    }
                })
                ->addColumn('action', function ($auxl) {
                    $pendingApproval = \App\Models\Approval::where('auxl_id', $auxl->id)
                        ->where('action', 'create_aux_reprocess')
                        ->where('status', 'pending')
                        ->orderByDesc('created_at')
                        ->first();
                        
                    if ($pendingApproval) {
                        $waitingLabel = strtoupper($pendingApproval->type);
                        return '<span class="badge badge-warning text-dark">Menunggu approval ' . $waitingLabel . '</span>';
                    }
                    
                    $userRole = strtolower(Auth::user()->role ?? '');
                    $canManageAuxl = $userRole !== 'owner';
                    $isSuperAdmin = $userRole === 'super_admin';
                    
                    $showBtn = '<a href="' . route('aux.show', $auxl->id) . '" class="btn btn-info btn-sm mr-1"><i class="fas fa-eye"></i> Detail</a>';
                    $editBtn = $canManageAuxl ? '<a href="' . route('aux.edit', $auxl->id) . '" class="btn btn-warning btn-sm mr-1"><i class="fas fa-pen"></i> Edit</a>' : '';
                    $deleteBtn = $isSuperAdmin ? '<button type="button" class="btn btn-danger btn-sm" onclick="showDeleteModal(\'' . route('aux.destroy', $auxl->id) . '\', \'' . $auxl->barcode . '\')"><i class="fas fa-trash-alt"></i> Hapus</button>' : '';

                    return $showBtn . $editBtn . $deleteBtn;
                })
                ->setRowClass(function ($auxl) {
                    $hasPending = \App\Models\Approval::where('auxl_id', $auxl->id)
                        ->where('action', 'create_aux_reprocess')
                        ->where('status', 'pending')
                        ->exists();
                    return $hasPending ? 'table-warning' : '';
                })
                ->rawColumns(['checkbox', 'dipakai', 'action'])
                ->make(true);
        }

        return view('auxl.index');
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
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:auxiliary:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
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
                ->filter(fn ($item) => isset($item['matnr']))
                ->map(fn ($item) => [
                    'id' => $item['matnr'],
                    'text' => $item['matnr'],
                ])
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
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
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:customer:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
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
                ->map(fn ($item) => [
                    'id' => $item['customer'],
                    'text' => $item['customer'],
                ])
                ->unique('id')
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }

    public function proxyMarketingSearch(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 3) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'proxy_sap:marketing:' . md5($q);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
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
                ->map(fn ($item) => [
                    'id' => $item['marketing'],
                    'text' => $item['marketing'],
                ])
                ->unique('id')
                ->values()
                ->all();
            $payload = ['results' => $results];
            Cache::put($cacheKey, $payload, now()->addMinutes(10));
            return response()->json($payload);
        } catch (\Exception $e) {
            return response()->json(['results' => []]);
        }
    }
}
