<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MesinCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search_user')) {
            $search = $request->input('search_user');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'LIKE', "%$search%")
                    ->orWhere('username', 'LIKE', "%$search%")
                    ->orWhere('role', 'LIKE', "%$search%");
            });
        }

        $user = $query->get();

        return view('user.index', compact('user', 'request'));
    }

    /**
     * Form tambah user.
     */
    public function create(MesinCacheService $mesinCache)
    {
        $mesins = $mesinCache->getSelectionList();
        return view('user.create', compact('mesins'));
    }

    /**
     * Simpan user baru.
     */
    public function store(Request $request)
    {
        $rules = [
            'nama' => 'required|string|max:255',
            'role' => 'required|string',
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'mesin' => 'nullable|string|max:255',
        ];

        // Jika role = 'mesin', mesin wajib diisi
        if ($request->role === 'mesin') {
            $rules['mesin'] = 'required|string|max:255';
        }

        $request->validate($rules);

        User::create([
            'nama' => $request->nama,
            'role' => $request->role,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'mesin' => $request->mesin,
        ]);

        return redirect()->route('user.index')->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Form edit user.
     */
    public function edit($id, MesinCacheService $mesinCache)
    {
        $user = User::findOrFail($id);
        $mesins = $mesinCache->getSelectionList();
        return view('user.edit', compact('user', 'mesins'));
    }

    /**
     * Update user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'nama' => 'required|string|max:255',
            'role' => 'required|string',
            'username' => 'required|string|unique:users,username,' . $user->id,
            'password' => 'nullable|string|min:6',
            'mesin' => 'nullable|string|max:255',
        ];

        // Jika role = 'mesin', mesin wajib diisi
        if ($request->role === 'mesin') {
            $rules['mesin'] = 'required|string|max:255';
        }

        $request->validate($rules);

        $data = [
            'nama' => $request->nama,
            'role' => $request->role,
            'username' => $request->username,
            'mesin' => $request->mesin,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('user.index')->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Hapus user.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('user.index')->with('success', 'User berhasil dihapus.');
    }

    public function editProfile()
    {
        $user = Auth::user();
        return view('user.profile', compact('user'));
    }


    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'current_password'  => 'required|string',
            'password'          => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->route('user.profile')->withErrors($validator)->withInput();
        }

        // Cek apakah password lama cocok
        if (!Hash::check($request->current_password, $user->password)) {
            return redirect()->route('user.profile')
                ->withErrors(['current_password' => 'Password lama tidak sesuai.'])
                ->withInput();
        }

        // Password lama cocok → update password baru
        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        return redirect()->route('user.profile')->with('success', 'Password berhasil diperbarui.');
    }
}
