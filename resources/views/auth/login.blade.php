<x-guest-layout>
    <div class="login-stage relative min-h-screen w-screen overflow-hidden bg-[#F8F5F0]">
        {{-- Coffee Pattern Background --}}
        <div class="login-decoration absolute inset-0" style="
            background-image: url('{{ asset('images/coffee-pattern.jpg') }}');
            background-repeat: repeat;
            background-size: 430px auto;
            background-position: center;
            opacity: 0.42;
        ">
        </div>

        {{-- Soft Cream Overlay --}}
        <div class="login-decoration absolute inset-0" style="
            background: linear-gradient(
                135deg,
                rgba(248, 245, 240, 0.72),
                rgba(248, 245, 240, 0.58),
                rgba(217, 176, 140, 0.38)
            );
        ">
        </div>

        {{-- Decorative Circles --}}
        <div class="pointer-events-none absolute -left-28 -top-28 h-80 w-80 rounded-full bg-[#7B4B2A]/10 blur-3xl">
        </div>
        <div class="pointer-events-none absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-[#C98A4A]/20 blur-3xl">
        </div>

        <div class="relative z-10 flex min-h-screen w-full items-center justify-center px-6 py-10">
            <div class="w-full max-w-md">
                {{-- Card --}}
                <div
                    class="login-card overflow-hidden rounded-[2rem] border border-[#D9B08C]/60 bg-white/95 shadow-2xl shadow-[#4B2E1F]/15 backdrop-blur">
                    {{-- Header --}}
                    <div
                        class="border-b border-[#E8D8C7] bg-gradient-to-br from-white/95 to-[#F8F5F0]/95 px-8 py-8 text-center">
                        <div class="login-logo">
                            <img src="{{ asset('images/jimny-logo-round.png') }}" alt="Logo JIMNY COFFEE">
                        </div>

                        <h1 class="text-3xl font-black tracking-tight text-[#4B2E1F]">
                            JIMNY COFFEE
                        </h1>

                        <p class="mt-2 text-sm font-semibold text-[#7B4B2A]/80">
                            Coffee Shop Management System
                        </p>
                    </div>

                    {{-- Body --}}
                    <div class="px-8 py-8">
                        {{-- Session Status --}}
                        <x-auth-session-status
                            class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-700"
                            :status="session('status')" />

                        {{-- Form --}}
                        <form method="POST" action="{{ route('login') }}" class="space-y-5">
                            @csrf

                            <div>
                                <label for="email" class="mb-2 block text-sm font-black text-[#4B2E1F]">
                                    Email
                                </label>

                                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                                    autofocus autocomplete="username" placeholder="Masukkan email"
                                    class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                                <x-input-error :messages="$errors->get('email')"
                                    class="mt-2 text-xs font-bold text-red-600" />
                            </div>

                            <div>
                                <label for="password" class="mb-2 block text-sm font-black text-[#4B2E1F]">
                                    Password
                                </label>

                                <input id="password" type="password" name="password" required
                                    autocomplete="current-password" placeholder="Masukkan password"
                                    class="w-full rounded-2xl border border-[#D9B08C] bg-[#FFFDF9] px-4 py-3 text-sm font-semibold text-black outline-none transition placeholder:text-[#9B8574] focus:border-[#7B4B2A] focus:bg-white focus:ring-4 focus:ring-[#D9B08C]/40">

                                <x-input-error :messages="$errors->get('password')"
                                    class="mt-2 text-xs font-bold text-red-600" />
                            </div>

                            <button type="submit"
                                class="w-full rounded-2xl bg-[#7B4B2A] px-5 py-3 text-sm font-black text-white shadow-lg shadow-[#7B4B2A]/25 transition hover:bg-[#4B2E1F] active:scale-[0.98]">
                                Masuk
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Footer kecil --}}
                <p class="mt-6 text-center text-xs font-semibold text-[#7B4B2A]/70">
                    © {{ date('Y') }} JIMNY COFFEE
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>