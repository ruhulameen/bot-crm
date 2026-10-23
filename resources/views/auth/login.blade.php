<x-layouts.guest heading="Login">
    <form method="POST" action="{{ route('login') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" autocomplete="email" required autofocus />
        <div>
            <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />
            <div class="mt-2 text-right text-sm">
                <a href="{{ route('password.request') }}" class="font-semibold text-brand hover:opacity-80">Forgot password?</a>
            </div>
        </div>
        <x-form.checkbox name="remember" label="Remember me" />
        <x-button class="w-full">Login</x-button>
    </form>
</x-layouts.guest>
