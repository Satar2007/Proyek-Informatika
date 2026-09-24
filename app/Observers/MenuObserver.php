<?php

namespace App\Observers;

use App\Models\Menu;
use App\Models\PriceHistory;

class MenuObserver
{
    /**
     * Handle the Menu "updated" event.
     *
     * Setiap kali kolom 'harga' berubah (mis. lewat MenuController::update),
     * otomatis dicatat ke tabel price_histories. Tidak perlu kolom "diubah oleh"
     * karena hanya role admin yang punya akses ke fitur ubah harga menu —
     * informasi itu sudah tersirat dari sistem permission, tidak perlu
     * diduplikasi per baris history.
     */
    public function updated(Menu $menu): void
    {
        if (! $menu->wasChanged('harga')) {
            return;
        }

        PriceHistory::create([
            'menu_id'            => $menu->id,
            'harga_lama'         => $menu->getOriginal('harga'),
            'harga_baru'         => $menu->harga,
            'tanggal_perubahan'  => now(),
        ]);
    }
}
