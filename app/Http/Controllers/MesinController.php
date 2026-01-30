<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use App\Events\MesinCreated;
use App\Events\MesinUpdated;
use App\Events\MesinDeleted;
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

        $mesin = Mesin::create($request->only('jenis_mesin', 'status'));

        // Broadcast event untuk update real-time di dashboard
        event(new MesinCreated([
            'id' => $mesin->id,
            'jenis_mesin' => $mesin->jenis_mesin,
            'status' => (bool) $mesin->status,
        ]));

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
        
        // Refresh untuk memastikan data terbaru
        $mesin->refresh();

        // Broadcast event untuk update real-time di dashboard
        event(new MesinUpdated([
            'id' => $mesin->id,
            'jenis_mesin' => $mesin->jenis_mesin,
            'status' => (bool) $mesin->status,
        ]));

        return redirect()->route('mesin.index')->with('success', 'Mesin berhasil diperbarui.');
    }

    public function destroy(Mesin $mesin)
    {
        $mesinId = $mesin->id;
        $mesin->delete();

        // Broadcast event untuk update real-time di dashboard
        event(new MesinDeleted($mesinId));

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
