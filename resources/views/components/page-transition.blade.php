{{-- Layar transisi halaman: ilustrasi kopi penuh + uap, daun, dan biji yang bergerak. Dipakai di layout utama & guest. --}}
<div class="page-transition" id="page-transition" aria-hidden="true">
    <div class="pt-stage">
        <img class="pt-art" src="{{ asset('images/coffee-illustration.jpg') }}" alt="" draggable="false">

        <svg class="pt-fx" viewBox="0 0 736 414" preserveAspectRatio="none" aria-hidden="true">
            {{-- Uap dari cangkir --}}
            <g class="pt-steam" fill="none" stroke="#6a3b22" stroke-width="1.5" stroke-linecap="round">
                <path pathLength="100" d="M646 194 C637 178 657 166 647 150 S641 118 651 98"/>
                <path pathLength="100" d="M656 194 C667 176 647 164 659 146 S669 116 657 94"/>
                <path pathLength="100" d="M637 195 C629 181 645 171 637 157 S633 134 640 118"/>
            </g>

            {{-- Daun melayang --}}
            <g class="pt-leaves">
                <g class="pt-item" style="--d:0s;--t:7s;--x:12px;--y:-14px;--r:16deg"><path d="M118 250c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#4a2a1c"/><path d="M118 250h17" stroke="#d9a877" stroke-width=".7"/></g>
                <g class="pt-item" style="--d:.8s;--t:8s;--x:-10px;--y:-12px;--r:-20deg"><path d="M232 302c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#5a3322"/><path d="M232 302h17" stroke="#d9a877" stroke-width=".7"/></g>
                <g class="pt-item" style="--d:1.6s;--t:6.5s;--x:14px;--y:-16px;--r:22deg"><path d="M350 240c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#4a2a1c"/><path d="M350 240h17" stroke="#d9a877" stroke-width=".7"/></g>
                <g class="pt-item" style="--d:.4s;--t:9s;--x:-12px;--y:-10px;--r:-14deg"><path d="M448 262c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#7a4a2c"/><path d="M448 262h17" stroke="#e8c49a" stroke-width=".7"/></g>
                <g class="pt-item" style="--d:2.2s;--t:7.5s;--x:10px;--y:-15px;--r:18deg"><path d="M60 338c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#4a2a1c"/><path d="M60 338h17" stroke="#d9a877" stroke-width=".7"/></g>
                <g class="pt-item" style="--d:1.2s;--t:8.5s;--x:-14px;--y:-12px;--r:-18deg"><path d="M566 300c4.5-6 12-6 17 0-5 6-12.5 6-17 0Z" fill="#5a3322"/><path d="M566 300h17" stroke="#d9a877" stroke-width=".7"/></g>
            </g>

            {{-- Biji kopi melayang --}}
            <g class="pt-beans">
                <g class="pt-item" style="--d:.2s;--t:6s;--x:8px;--y:-18px;--r:30deg"><ellipse cx="190" cy="130" rx="7" ry="4.6" fill="#6b3d22"/><path d="M184 131c4-3 8-3 12-2" stroke="#e8c49a" stroke-width=".9" fill="none"/></g>
                <g class="pt-item" style="--d:1s;--t:7s;--x:-8px;--y:-16px;--r:-26deg"><ellipse cx="300" cy="70" rx="6" ry="4" fill="#7a4a2c"/><path d="M295 71c3-2 7-2 10-1" stroke="#e8c49a" stroke-width=".9" fill="none"/></g>
                <g class="pt-item" style="--d:1.9s;--t:6.5s;--x:9px;--y:-17px;--r:24deg"><ellipse cx="452" cy="120" rx="7" ry="4.6" fill="#6b3d22"/><path d="M446 121c4-3 8-3 12-2" stroke="#e8c49a" stroke-width=".9" fill="none"/></g>
                <g class="pt-item" style="--d:.6s;--t:7.5s;--x:-9px;--y:-19px;--r:-22deg"><ellipse cx="548" cy="66" rx="6" ry="4" fill="#7a4a2c"/><path d="M543 67c3-2 7-2 10-1" stroke="#e8c49a" stroke-width=".9" fill="none"/></g>
                <g class="pt-item" style="--d:1.4s;--t:8s;--x:7px;--y:-15px;--r:28deg"><ellipse cx="112" cy="196" rx="6" ry="4" fill="#6b3d22"/><path d="M107 197c3-2 7-2 10-1" stroke="#e8c49a" stroke-width=".9" fill="none"/></g>
            </g>
        </svg>
    </div>

    <div class="pt-brand">
        <img class="pt-logo" src="{{ asset('images/jimny-logo-round.png') }}" alt="">
        <div class="pt-title">JIMNY COFFEE</div>
        <div class="pt-tag">Nikmati setiap tegukan, rasakan bedanya.</div>
        <div class="pt-line"></div>
    </div>
</div>
