<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use Illuminate\Http\Request;

class MesinController extends Controller
{
    public function index()
    {
        $mesins = Mesin::all();
        return view('mesin.index', compact('mesins'));
    }

    public function toggleStatus(Request $request, Mesin $mesin)
    {
        $mesin->status = !$mesin->status;
        $mesin->save();

        return response()->json([
            'success' => true,
            'status' => $mesin->status,
            'label' => $mesin->status ? 'Hidup' : 'Mati',
        ]);
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

    public function status(Mesin $mesin)
    {
        return response()->json([
            'status' => $mesin->status,
            'label' => $mesin->status ? 'Hidup' : 'Mati',
        ]);
    }
}
