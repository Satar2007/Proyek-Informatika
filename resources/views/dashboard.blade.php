<x-app-layout>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="page-lead flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-[#D9B08C] bg-white px-3 py-1 text-xs font-black uppercase tracking-wider text-[#7B4B2A] shadow-sm">
                    Dashboard
                </div>

                <h1 class="mt-4 text-3xl font-black tracking-tight text-[#4B2E1F]">
                    JIMNY COFFEE
                </h1>

                <p class="mt-2 max-w-2xl text-sm font-semibold text-[#7B4B2A]/80">
                    Selamat datang di sistem manajemen coffee shop.
                </p>
            </div>
        </div>

        {{-- Welcome Card --}}
        <div class="overflow-hidden rounded-[2rem] border border-[#D9B08C]/60 bg-white shadow-xl shadow-[#4B2E1F]/5">
            <div class="border-b border-[#E8D8C7] bg-gradient-to-br from-white to-[#F8F5F0] px-6 py-5">
                <h2 class="text-xl font-black text-[#4B2E1F]">
                    Login Berhasil
                </h2>

                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/80">
                    Akun kamu berhasil masuk ke sistem.
                </p>
            </div>

            <div class="p-6">
                <div class="rounded-3xl border border-[#D9B08C]/50 bg-[#FFFDF9] p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#7B4B2A] text-2xl text-white shadow-lg shadow-[#7B4B2A]/20">
                                ☕
                            </div>

                            <div>
                                <p class="text-lg font-black text-[#4B2E1F]">
                                    Sistem Siap Digunakan
                                </p>

                                <p class="mt-1 text-sm font-semibold text-[#7B4B2A]/80">
                                    Gunakan menu navigasi untuk mengelola transaksi, menu, stok, laporan, dan operasional lainnya.
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('kasir.index') }}"
                            class="inline-flex items-center justify-center rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/20 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                            Buka Kasir
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>