<x-layouts.guest heading="Forgot your password?" subheading="Enter your email and we'll send you a reset link.">
    <form method="POST" action="{{ route('password.email') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
        @csrf
        <x-form.input name="email" label="Email address" type="email" autocomplete="email" required autofocus />
        <x-button class="w-full">Email reset link</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}">Back to login</a>
    </x-slot:footer>
</x-layouts.guest>
