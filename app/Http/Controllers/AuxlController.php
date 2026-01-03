<?php

namespace App\Http\Controllers;

use App\Models\Auxl;
use App\Models\AuxlDetail;
use Illuminate\Http\Request;

class AuxlController extends Controller
{
    public function index()
    {
        $auxls = Auxl::with('details')->orderByDesc('created_at')->get();
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
        // Generate barcode unik: AUX-[running number 10 digit]
        $last = Auxl::orderByDesc('id')->first();
        $nextNumber = $last ? ($last->id + 1) : 1;
        $barcode = 'AUX-' . str_pad($nextNumber, 10, '0', STR_PAD_LEFT);
        $data['barcode'] = $barcode;
        $auxl = Auxl::create($data);
        foreach ($data['details'] as $detail) {
            $auxl->details()->create($detail);
        }
        return redirect()->route('aux.index')->with('success', 'Data Auxl berhasil disimpan. Barcode: ' . $barcode);
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
}
