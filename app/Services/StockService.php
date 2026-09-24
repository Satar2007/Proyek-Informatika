<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;
use Exception;

class StockService
{
    public function kurangiStok(Menu $menu, int $qty, int $createdBy, string $catatan = ''): bool
    {
        return DB::transaction(function () use ($menu, $qty, $createdBy, $catatan) {
            $menu = Menu::lockForUpdate()->findOrFail($menu->id);

            if ($qty <= 0) {
                throw new Exception('Jumlah stok yang dikurangi harus lebih dari 0.');
            }

            if ($menu->stok < $qty) {
                throw new Exception("Stok tidak mencukupi untuk {$menu->nama_menu}.");
            }

            $qtyBefore = $menu->stok;

            $menu->stok -= $qty;
            $menu->save();

            StockLog::create([
                'menu_id'    => $menu->id,
                'tipe'       => 'out',
                'qty_before' => $qtyBefore,
                'qty_change' => $qty,
                'qty_after'  => $menu->stok,
                'catatan'    => $catatan,
                'created_by' => $createdBy,
            ]);

            return true;
        });
    }

    public function tambahStok(Menu $menu, int $qty, int $createdBy, string $catatan = ''): bool
    {
        return DB::transaction(function () use ($menu, $qty, $createdBy, $catatan) {
            $menu = Menu::lockForUpdate()->findOrFail($menu->id);

            if ($qty <= 0) {
                throw new Exception('Jumlah stok yang ditambahkan harus lebih dari 0.');
            }

            $qtyBefore = $menu->stok;

            $menu->stok += $qty;
            $menu->save();

            StockLog::create([
                'menu_id'    => $menu->id,
                'tipe'       => 'in',
                'qty_before' => $qtyBefore,
                'qty_change' => $qty,
                'qty_after'  => $menu->stok,
                'catatan'    => $catatan,
                'created_by' => $createdBy,
            ]);

            return true;
        });
    }
}