<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SmartAssistantController extends Controller
{
    public function insights(Request $request): JsonResponse
    {
        $role = $request->user()->role;
        $scope = $this->transactionScope($request);

        $today = (clone $scope)->whereDate('created_at', today());
        $todayRevenue = (clone $today)->sum('grand_total');
        $todayTransactions = (clone $today)->count();

        $lowStock = Menu::query()
            ->where('is_active', true)
            ->whereColumn('stok', '<=', 'minimum_stok')
            ->orderBy('stok')
            ->take(5)
            ->get(['id', 'nama_menu', 'stok', 'minimum_stok']);

        $topMenus = $this->topMenus($request, 7, 3);
        $peakHour = $this->peakHour($request);

        $messages = [];
        if ($todayTransactions === 0) {
            $messages[] = 'Belum ada transaksi sukses hari ini. Data akan diperbarui otomatis setelah transaksi berhasil.';
        } else {
            $messages[] = 'Hari ini tercatat ' . $todayTransactions . ' transaksi sukses dengan omzet Rp ' . number_format($todayRevenue, 0, ',', '.') . '.';
        }

        if ($lowStock->isNotEmpty()) {
            $names = $lowStock->take(3)->pluck('nama_menu')->implode(', ');
            $messages[] = 'Perhatian stok: ' . $names . ' sudah berada di bawah atau sama dengan batas minimum.';
        }

        if ($topMenus->isNotEmpty()) {
            $messages[] = 'Menu terlaris 7 hari terakhir: ' . $topMenus->pluck('nama_menu')->implode(', ') . '.';
        }

        if ($peakHour) {
            $messages[] = 'Jam ramai berdasarkan transaksi sukses: sekitar pukul ' . sprintf('%02d.00', $peakHour) . ' WIB.';
        }

        if ($role === 'kasir') {
            $messages[] = 'Mode Kasir aktif: saya memprioritaskan bantuan transaksi, stok, dan rekomendasi menu.';
        } elseif ($role === 'admin') {
            $messages[] = 'Mode Admin aktif: saya memprioritaskan stok, operasional, dan tren menu.';
        } else {
            $messages[] = 'Mode Owner aktif: saya memprioritaskan omzet, tren penjualan, prediksi, dan keputusan stok.';
        }

        return response()->json([
            'role' => $role,
            'messages' => $messages,
            'low_stock' => $lowStock,
            'top_menus' => $topMenus,
            'peak_hour' => $peakHour,
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate(['message' => ['required', 'string', 'max:500']]);

        $role = $request->user()->role;
        $message = mb_strtolower(trim($request->string('message')->toString()));

        if ($this->contains($message, ['stok', 'habis', 'restock', 'persediaan'])) {
            return response()->json($this->stockAnswer($role));
        }

        if ($this->contains($message, ['laku', 'terlaris', 'menu populer', 'menu favorit'])) {
            return response()->json($this->topMenuAnswer($request));
        }

        if ($this->contains($message, ['prediksi', 'forecast', 'besok', 'minggu depan'])) {
            return response()->json($this->forecastAnswer($request, $role));
        }

        if ($this->contains($message, ['omzet', 'pendapatan', 'penjualan hari ini', 'penjualan'])) {
            return response()->json($this->salesAnswer($request, $role));
        }

        if ($this->contains($message, ['ramai', 'jam ramai', 'jam sibuk', 'peak hour'])) {
            $hour = $this->peakHour($request);
            return response()->json([
                'answer' => $hour === null
                    ? 'Belum cukup data transaksi sukses untuk menentukan jam ramai.'
                    : 'Berdasarkan transaksi sukses yang tersedia, jam ramai terdeteksi sekitar pukul ' . sprintf('%02d.00', $hour) . ' WIB.',
                'type' => 'insight',
            ]);
        }

        if ($this->contains($message, ['rekomendasi', 'rekomendasi menu', 'cocok', 'tambahan', 'pairing'])) {
            return response()->json($this->recommendationAnswer($request, $message));
        }

        if ($this->contains($message, ['bantuan', 'apa yang bisa', 'kamu bisa', 'fitur'])) {
            return response()->json([
                'answer' => $this->helpText($role),
                'type' => 'help',
            ]);
        }

        return response()->json([
            'answer' => 'Saya bisa membantu berdasarkan data POS JIMNY COFFEE. Coba tanyakan: “stok apa yang perlu direstock?”, “menu paling laku?”, “omzet hari ini?”, “jam ramai?”, atau “prediksi penjualan minggu depan?”.',
            'type' => 'help',
        ]);
    }

    private function transactionScope(Request $request)
    {
        $query = Transaction::query()->where('status', 'success');
        if ($request->user()->role === 'kasir') {
            $query->where('user_id', $request->user()->id);
        }
        return $query;
    }

    private function topMenus(Request $request, int $days = 7, int $limit = 5)
    {
        $query = TransactionDetail::query()
            ->select('menus.id', 'menus.nama_menu', DB::raw('SUM(transaction_details.qty) AS total_terjual'))
            ->join('transactions', 'transaction_details.transaction_id', '=', 'transactions.id')
            ->join('menus', 'transaction_details.menu_id', '=', 'menus.id')
            ->where('transactions.status', 'success')
            ->where('transactions.created_at', '>=', now()->subDays($days));

        if ($request->user()->role === 'kasir') {
            $query->where('transactions.user_id', $request->user()->id);
        }

        return $query->groupBy('menus.id', 'menus.nama_menu')
            ->orderByDesc('total_terjual')
            ->take($limit)
            ->get();
    }

    private function stockAnswer(string $role): array
    {
        $items = Menu::query()
            ->where('is_active', true)
            ->whereColumn('stok', '<=', 'minimum_stok')
            ->orderBy('stok')
            ->take(8)
            ->get(['nama_menu', 'stok', 'minimum_stok']);

        if ($items->isEmpty()) {
            return ['answer' => 'Tidak ada menu yang saat ini berada di bawah atau sama dengan batas minimum stok.', 'type' => 'stock'];
        }

        $lines = $items->map(function ($item) {
            $need = max(0, $item->minimum_stok - $item->stok);
            return $item->nama_menu . ': stok ' . $item->stok . ', minimum ' . $item->minimum_stok . ($need > 0 ? ', tambah minimal ' . $need : ', pantau stok');
        })->implode('; ');

        return [
            'answer' => 'Ada ' . $items->count() . ' menu yang perlu diperhatikan. ' . $lines . '.',
            'type' => 'stock',
            'items' => $items,
        ];
    }

    private function topMenuAnswer(Request $request): array
    {
        $menus = $this->topMenus($request, 7, 5);
        if ($menus->isEmpty()) {
            return ['answer' => 'Belum ada transaksi sukses yang cukup untuk membaca menu terlaris.', 'type' => 'sales'];
        }

        $ranking = $menus->values()->map(fn ($menu, $i) => ($i + 1) . '. ' . $menu->nama_menu . ' (' . $menu->total_terjual . ' terjual)')->implode('; ');
        return ['answer' => 'Dalam 7 hari terakhir: ' . $ranking . '.', 'type' => 'sales', 'items' => $menus];
    }

    private function salesAnswer(Request $request, string $role): array
    {
        $today = $this->transactionScope($request)->whereDate('created_at', today());
        $yesterday = $this->transactionScope($request)->whereDate('created_at', today()->subDay());
        $todayRevenue = (clone $today)->sum('grand_total');
        $yesterdayRevenue = (clone $yesterday)->sum('grand_total');
        $difference = $todayRevenue - $yesterdayRevenue;
        $trend = $difference > 0 ? 'naik' : ($difference < 0 ? 'turun' : 'sama');

        $answer = 'Omzet hari ini Rp ' . number_format($todayRevenue, 0, ',', '.') . '. Dibanding kemarin Rp ' . number_format($yesterdayRevenue, 0, ',', '.') . ', nilainya ' . $trend . ' Rp ' . number_format(abs($difference), 0, ',', '.') . '.';
        if ($role === 'kasir') {
            $answer .= ' Angka ini hanya menghitung transaksi sukses milik akun kasir yang sedang login.';
        }
        return ['answer' => $answer, 'type' => 'sales'];
    }

    private function forecastAnswer(Request $request, string $role): array
    {
        $scope = $this->transactionScope($request);
        $daily = $scope->where('created_at', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(created_at) AS tanggal'), DB::raw('SUM(grand_total) AS omzet'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('tanggal')
            ->pluck('omzet');

        if ($daily->isEmpty()) {
            return ['answer' => 'Belum ada data transaksi sukses 7 hari terakhir untuk membuat prediksi.', 'type' => 'forecast'];
        }

        // Weighted moving average: hari terbaru diberi bobot lebih besar.
        $weights = [1, 2, 3, 4, 5, 6, 7];
        $values = $daily->values()->all();
        $values = array_slice($values, -count($weights));
        $weights = array_slice($weights, -count($values));
        $weighted = 0; $weightTotal = 0;
        foreach ($values as $i => $value) {
            $weight = $weights[$i];
            $weighted += ((float) $value) * $weight;
            $weightTotal += $weight;
        }
        $forecast = $weightTotal > 0 ? $weighted / $weightTotal : 0;

        $answer = 'Prediksi omzet harian berikutnya sekitar Rp ' . number_format($forecast, 0, ',', '.') . ' menggunakan Weighted Moving Average dari data transaksi sukses 7 hari terakhir.';
        if ($role === 'owner') {
            $answer .= ' Gunakan angka ini sebagai estimasi pendukung, bukan jaminan hasil penjualan.';
        }
        return ['answer' => $answer, 'type' => 'forecast', 'forecast' => round($forecast)];
    }

    private function recommendationAnswer(Request $request, string $message): array
    {
        $base = Menu::query()->where('is_active', true)->get(['id', 'nama_menu']);
        if ($base->isEmpty()) {
            return ['answer' => 'Belum ada menu aktif untuk direkomendasikan.', 'type' => 'recommendation'];
        }

        $query = TransactionDetail::query()
            ->select('a.menu_id AS menu_a', 'b.menu_id AS menu_b', DB::raw('COUNT(DISTINCT a.transaction_id) AS frekuensi'))
            ->from('transaction_details AS a')
            ->join('transaction_details AS b', function ($join) {
                $join->on('a.transaction_id', '=', 'b.transaction_id')->whereColumn('a.menu_id', '<', 'b.menu_id');
            })
            ->join('transactions', 'a.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'success')
            ->where('transactions.created_at', '>=', now()->subDays(60));

        if ($request->user()->role === 'kasir') {
            $query->where('transactions.user_id', $request->user()->id);
        }

        $pairs = $query->groupBy('a.menu_id', 'b.menu_id')->orderByDesc('frekuensi')->take(5)->get();
        if ($pairs->isEmpty()) {
            $top = $this->topMenus($request, 30, 3);
            $fallback = $top->pluck('nama_menu')->implode(', ');
            return ['answer' => $fallback ? 'Belum ada pola pasangan menu yang kuat. Sebagai alternatif, menu yang paling sering terjual 30 hari terakhir adalah ' . $fallback . '.' : 'Belum cukup data transaksi untuk membuat rekomendasi.', 'type' => 'recommendation'];
        }

        $menuNames = Menu::whereIn('id', $pairs->pluck('menu_a')->merge($pairs->pluck('menu_b'))->unique())->pluck('nama_menu', 'id');
        $text = $pairs->map(fn ($p) => $menuNames[$p->menu_a] . ' + ' . $menuNames[$p->menu_b] . ' (' . $p->frekuensi . ' transaksi bersama)')->implode('; ');
        return ['answer' => 'Pola pasangan menu yang paling sering muncul: ' . $text . '.', 'type' => 'recommendation', 'pairs' => $pairs];
    }

    private function peakHour(Request $request): ?int
    {
        $query = $this->transactionScope($request)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('HOUR(created_at) AS jam'), DB::raw('COUNT(*) AS jumlah'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderByDesc('jumlah');
        return optional($query->first())->jam !== null ? (int) $query->first()->jam : null;
    }

    private function contains(string $text, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($text, $word)) return true;
        }
        return false;
    }

    private function helpText(string $role): string
    {
        return match ($role) {
            'kasir' => 'Sebagai Kasir, saya membantu cek stok, menu terlaris, rekomendasi menu tambahan, dan ringkasan transaksi akunmu.',
            'admin' => 'Sebagai Admin, saya membantu cek stok, menu terlaris, tren penjualan, jam ramai, dan kebutuhan restock.',
            default => 'Sebagai Owner, saya membantu membaca omzet, tren menu, jam ramai, kebutuhan stok, rekomendasi, dan prediksi penjualan.',
        };
    }
}
