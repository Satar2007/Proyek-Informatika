<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AkunController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', ['kasir', 'admin', 'owner'])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('admin.akun.index', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role'     => 'required|in:kasir,admin,owner',
        ]);

        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);

        return redirect()->back()->with('success', 'Akun berhasil ditambahkan!');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6',
            'role'     => 'required|in:kasir,admin,owner',
        ]);

        $data = [
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $request->role,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->back()->with('success', 'Akun berhasil diupdate!');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'Tidak bisa hapus akun sendiri!');
        }

        $user->delete();

        return redirect()->back()->with('success', 'Akun berhasil dihapus!');
    }
}