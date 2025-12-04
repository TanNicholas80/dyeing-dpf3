<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MesinController extends Controller
{
    public function index()
    {
        $mesins = Mesin::all();
        return view('mesin.index', compact('mesins'));
    }

    public function create()
    {
        return view('mesin.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis_mesin' => 'required|unique:mesins,jenis_mesin',
            'status' => 'required|boolean',
        ]);

        Mesin::create($request->only('jenis_mesin', 'status'));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil ditambahkan.');
    }

    public function edit(Mesin $mesin)
    {
        return view('mesin.edit', compact('mesin'));
    }

    public function update(Request $request, Mesin $mesin)
    {
        $request->validate([
            'jenis_mesin' => 'required|unique:mesins,jenis_mesin,' . $mesin->id,
            'status' => 'required|boolean',
        ]);

        $mesin->update($request->only('jenis_mesin', 'status'));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil diperbarui.');
    }

    public function destroy(Mesin $mesin)
    {
        $mesin->delete();

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil dihapus.');
    }

    public function statuses()
    {
        try {
            $mesins = Mesin::all(['id', 'status']);
            $result = [];
            foreach ($mesins as $mesin) {
                $result[$mesin->id] = [
                    'status' => (bool) $mesin->status,
                    'label' => $mesin->status ? 'Hidup' : 'Mati',
                ];
            }
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
