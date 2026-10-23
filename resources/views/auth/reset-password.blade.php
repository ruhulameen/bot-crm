<x-layouts.guest heading="Reset your password">
    <form method="POST" action="{{ route('password.store') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-form.input name="email" label="Email address" type="email" :value="$request->email" readonly required />
        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
        <x-button class="w-full">Reset password</x-button>
    </form>
</x-layouts.guest>
