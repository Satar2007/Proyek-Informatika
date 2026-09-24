<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\StockLog;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class MenuController extends Controller
{
    public function __construct(private StockService $stockService)
    {
    }

    public function index()
    {
        $menus = Menu::with('category')
            ->latest()
            ->paginate(10);

        $totalMenu = Menu::count();

        $stokRendah = Menu::where('stok', '>', 0)
            ->whereColumn('stok', '<=', 'minimum_stok')
            ->count();

        $stokHabis = Menu::where('stok', '<=', 0)->count();

        return view('admin.menu.index', compact(
            'menus',
            'totalMenu',
            'stokRendah',
            'stokHabis'
        ));
    }

    public function create()
    {
        $categories = Category::orderBy('nama_kategori')->get();

        return view('admin.menu.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_menu'    => 'required|string|max:255',
            'category_id'  => 'required|exists:categories,id',
            'harga'        => 'required|integer|min:0',
            'stok'         => 'required|integer|min:0',
            'minimum_stok' => 'nullable|integer|min:0',
            'deskripsi'    => 'nullable|string',
            'gambar'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $gambar = null;

        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar')->store('menus', 'public');
        }

        $menu = Menu::create([
            'category_id'  => $request->category_id,
            'nama_menu'    => $request->nama_menu,
            'slug'         => Str::slug($request->nama_menu),
            'deskripsi'    => $request->deskripsi,
            'harga'        => $request->harga,
            'stok'         => $request->stok,
            'minimum_stok' => $request->minimum_stok ?? 5,
            'gambar'       => $gambar,
            'is_active'    => $request->has('is_active'),
        ]);

        // Stok awal tetap dicatat ke Log Stok supaya riwayat lengkap sejak menu dibuat.
        if ($menu->stok > 0) {
            StockLog::create([
                'menu_id'    => $menu->id,
                'tipe'       => 'in',
                'qty_before' => 0,
                'qty_change' => $menu->stok,
                'qty_after'  => $menu->stok,
                'catatan'    => 'Stok awal saat menu dibuat',
                'created_by' => $request->user()->id,
            ]);
        }

        return redirect()
            ->route('admin.menu.index')
            ->with('success', 'Menu berhasil ditambahkan!');
    }

    public function edit(Menu $menu)
    {
        $categories = Category::orderBy('nama_kategori')->get();

        return view('admin.menu.edit', compact('menu', 'categories'));
    }

    public function update(Request $request, Menu $menu)
    {
        $request->validate([
            'nama_menu'    => 'required|string|max:255',
            'category_id'  => 'required|exists:categories,id',
            'harga'        => 'required|integer|min:0',
            'minimum_stok' => 'nullable|integer|min:0',
            'deskripsi'    => 'nullable|string',
            'gambar'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $gambar = $menu->gambar;

        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar')->store('menus', 'public');
        }

        // Catatan: 'stok' sengaja tidak diubah dari sini. Semua perubahan stok
        // wajib lewat tombol Tambah/Kurangi Stok (menuju MenuController::tambahStok /
        // kurangiStok) supaya selalu tercatat di Log Stok.
        $menu->update([
            'category_id'  => $request->category_id,
            'nama_menu'    => $request->nama_menu,
            'slug'         => Str::slug($request->nama_menu),
            'deskripsi'    => $request->deskripsi,
            'harga'        => $request->harga,
            'minimum_stok' => $request->minimum_stok ?? 5,
            'gambar'       => $gambar,
            'is_active'    => $request->has('is_active'),
        ]);

        return redirect()
            ->route('admin.menu.index')
            ->with('success', 'Menu berhasil diupdate!');
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return redirect()
            ->route('admin.menu.index')
            ->with('success', 'Menu berhasil dihapus!');
    }

    public function priceHistory()
    {
        $histories = PriceHistory::with('menu')
            ->latest('tanggal_perubahan')
            ->paginate(20);

        $totalNaik = PriceHistory::whereColumn('harga_baru', '>', 'harga_lama')->count();
        $totalTurun = PriceHistory::whereColumn('harga_baru', '<', 'harga_lama')->count();

        return view('admin.menu.price-history', compact('histories', 'totalNaik', 'totalTurun'));
    }

    public function tambahStok(Request $request, Menu $menu)
    {
        $request->validate([
            'qty'     => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:255',
        ]);

        try {
            $this->stockService->tambahStok(
                $menu,
                (int) $request->qty,
                $request->user()->id,
                $request->catatan ?? 'Tambah stok manual'
            );
        } catch (Exception $e) {
            return redirect()
                ->route('admin.menu.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.menu.index')
            ->with('success', "Stok {$menu->nama_menu} berhasil ditambahkan.");
    }

    public function kurangiStok(Request $request, Menu $menu)
    {
        $request->validate([
            'qty'     => 'required|integer|min:1',
            'catatan' => 'nullable|string|max:255',
        ]);

        try {
            $this->stockService->kurangiStok(
                $menu,
                (int) $request->qty,
                $request->user()->id,
                $request->catatan ?? 'Kurangi stok manual (koreksi/rusak/kadaluarsa)'
            );
        } catch (Exception $e) {
            return redirect()
                ->route('admin.menu.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.menu.index')
            ->with('success', "Stok {$menu->nama_menu} berhasil dikurangi.");
    }
}