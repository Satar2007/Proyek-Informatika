<div id="jimny-owner-v20" data-endpoint="{{ route('smart-assistant.owner-v20') }}">
    <header class="j20-heading"><div><span class="j20-kicker">JIMNY · PUSAT KEPUTUSAN</span><h2>Kondisi bisnis, lebih jelas.</h2><p>Penjualan, rencana permintaan, dan persiapan menu dari satu sumber POS.</p></div>
    <div class="j20-filters"><label>Periode laporan<select id="j20-days"><option value="7">7 hari selesai</option><option value="30" selected>30 hari selesai</option><option value="90">90 hari selesai</option></select></label><button type="button" id="j20-refresh">Perbarui</button></div></header>
    <nav class="j20-tabs" aria-label="Bagian JSA"><button type="button" data-j20-tab="overview" aria-pressed="true">Ringkasan</button><button type="button" data-j20-tab="forecast" aria-pressed="false">Prediksi</button><button type="button" data-j20-tab="stock" aria-pressed="false">Persiapan stok</button></nav>
    <p id="j20-status" role="status">Buka JSA untuk membaca data POS.</p>
    <div id="j20-content" hidden>
      <section data-j20-panel="overview"><div id="j20-kpis" class="j20-kpis"></div>
        <div class="j20-section-heading"><h3>Perlu perhatian</h3><span>Prioritas berdasarkan aturan terukur</span></div><div id="j20-actions" class="j20-actions"></div>
        <article class="j20-card"><h3>Tren penjualan sebelum pajak</h3><div id="j20-trend"></div><details><summary>Lihat angka harian</summary><div id="j20-trend-table" class="j20-table-wrap"></div></details></article>
        <article class="j20-card"><h3>Performa menu</h3><p>Urut berdasarkan porsi. Nilai item sebelum diskon struk dan pajak.</p><div id="j20-menus" class="j20-table-wrap"></div></article>
        <p class="j20-note">Laba belum dihitung: harga modal/HPP dan biaya operasional belum tersedia.</p>
      </section>
      <section data-j20-panel="forecast" hidden><article class="j20-card"><div class="j20-section-heading"><div><span class="j20-kicker">SATU PROYEKSI UTAMA · DATA POS</span><h3>Rencana permintaan</h3></div><label>Jangka prediksi<select id="j20-horizon"><option value="1">Besok</option><option value="7" selected>7 hari</option><option value="30">30 hari</option><option value="90">90 hari</option><option value="365">365 hari</option></select></label></div><div id="j20-forecast"></div></article>
      <article class="j20-card"><h3>Prediksi dibanding realisasi</h3><div id="j20-evaluation"></div></article>
      <p class="j20-note">Prediksi membantu perencanaan. Pola tahunan belum dimodelkan secara khusus; proyeksi panjang adalah skenario sederhana. Data sintetis tidak membuktikan akurasi usaha nyata.</p></section>
      <section data-j20-panel="stock" hidden><article class="j20-card"><h3>Persiapan porsi untuk 7 hari</h3><p>Target = perkiraan kebutuhan 7 hari + batas minimum. Stok yang ditampilkan adalah stok tersedia di POS; reservasi tidak dikurangi dua kali.</p><div id="j20-stock" class="j20-table-wrap"></div></article><p class="j20-note">Satuan porsi/menu, bukan bahan baku. Periksa stok fisik, daya simpan, dan kapasitas sebelum menyiapkan tambahan. Tidak ada pembelian atau perubahan stok otomatis.</p></section>
      <details class="j20-definitions"><summary>Sumber data dan cara membaca angka</summary><div id="j20-definitions"></div></details>
    </div>
</div>
