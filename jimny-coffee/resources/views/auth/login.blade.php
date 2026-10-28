<x-guest-layout>
    {{-- JIMNY V11.2 Clean Glass: same auth routes, CSRF, errors and remember token. --}}
    <section class="jimny-login-stage" aria-label="Masuk ke JIMNY COFFEE">
        <header class="jimny-login-topline">
            <div class="jimny-login-theme-wrap">
                <span class="jimny-login-theme-caption">Tema</span>
                @include('components.jimny-theme-toggle')
            </div>
        </header>

        <div class="jimny-login-center">
            <article class="jimny-stage-card login-card">
                <div class="jimny-stage-brand">
                    <div class="jimny-stage-logo-wrap">
                        <img class="jimny-stage-logo" src="{{ asset('images/jimny-logo-round.png') }}" alt="Logo JIMNY COFFEE">
                    </div>
                    <p class="jimny-stage-overline">GOOD COFFEE · BETTER MOOD</p>
                    <h1>JIMNY COFFEE</h1>
                    <p class="jimny-stage-tagline">Coffee Shop Management System</p>
                </div>
                <div class="jimny-stage-form-wrap">
                    <h2>Selamat datang kembali.</h2>
                    <p class="jimny-stage-description">Masuk untuk mengelola operasional coffee shop.</p>
                    <x-auth-session-status class="jimny-login-status" :status="session('status')" />
                    <form id="jimny-login-form" method="POST" action="{{ route('login') }}" class="jimny-login-form">
                        @csrf
                        <div class="jimny-field-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                                   autocomplete="username" placeholder="nama@coffee.com" inputmode="email">
                            <x-input-error :messages="$errors->get('email')" class="jimny-login-validation" />
                        </div>
                        <div class="jimny-field-group">
                            <label for="password">Password</label>
                            <div class="jimny-password-field">
                                <input id="password" name="password" type="password" required autocomplete="current-password"
                                       placeholder="Masukkan password">
                                <button type="button" class="jimny-password-eye" data-jimny-reveal-password
                                        aria-controls="password" aria-pressed="false" aria-label="Tampilkan password">Lihat</button>
                            </div>
                            <p class="jimny-caps-hint" data-jimny-caps-hint hidden>Caps Lock sedang aktif</p>
                            <x-input-error :messages="$errors->get('password')" class="jimny-login-validation" />
                        </div>
                        <div class="jimny-form-options">
                            <label class="jimny-remember"><input type="checkbox" name="remember" id="remember_me" value="1"> Ingat saya</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Lupa password?</a>
                            @endif
                        </div>
                        <button type="submit" class="jimny-login-submit" data-jimny-login-submit>
                            <span data-jimny-login-label>Masuk ke JIMNY</span><span class="jimny-login-arrow" aria-hidden="true">→</span>
                        </button>
                    </form>
                </div>
            </article>
            <p class="jimny-stage-copyright">© {{ date('Y') }} JIMNY COFFEE</p>
        </div>
    </section>
</x-guest-layout>
