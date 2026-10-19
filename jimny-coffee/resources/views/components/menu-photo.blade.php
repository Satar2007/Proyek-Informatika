@props(['menu'])
@php
    $photo = config('menu_photos.'.($menu->slug ?: Illuminate\Support\Str::slug($menu->nama_menu)));
    $cellHeight = $photo['cell_height'] ?? 3;
@endphp
@if($menu->gambar)
    <img src="{{ asset('storage/'.$menu->gambar) }}" alt="{{ $menu->nama_menu }}" loading="lazy" decoding="async" class="menu-photo-image">
@elseif($photo)
    <svg class="menu-photo-image" viewBox="0 0 4 {{ $cellHeight }}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="{{ $menu->nama_menu }} — ilustrasi">
        <svg x="0" y="0" width="4" height="{{ $cellHeight }}" viewBox="{{ $photo['column'] * 4 }} {{ $photo['row'] * $cellHeight }} 4 {{ $cellHeight }}" preserveAspectRatio="none" overflow="hidden">
            <image href="{{ asset('images/menu-defaults/'.$photo['sheet']) }}" width="12" height="{{ $cellHeight * 2 }}" preserveAspectRatio="none" />
        </svg>
    </svg>
@else
    <span class="menu-photo-placeholder" role="img" aria-label="Belum ada foto"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:inline-block;vertical-align:middle;flex-shrink:0"><path d="M4 8h12v7a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5ZM16 9h2a3 3 0 0 1 0 6h-2M7 3v2M12 3v2"/></svg></span>
@endif
