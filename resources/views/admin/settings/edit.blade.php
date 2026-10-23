<x-layouts.admin title="Settings" heading="Settings" breadcrumb="System / Settings">
    <div class="max-w-3xl" x-data="{ chatId: @js(old('telegram_chat_id', $chatId)) }">
        <form method="POST" action="{{ route('admin.settings.update') }}" x-data="{ busy: false }" @submit="busy = true" class="space-y-5">
            @csrf
            @method('PUT')

            <x-panel.card title="Telegram alerts">
                <div class="space-y-5">
                    <p class="text-sm text-muted">Get a message on Telegram when a bot goes offline or comes back, signs in for the first time, or a license is about to expire.</p>

                    <div>
                        <x-form.input name="telegram_bot_token" label="Bot token" type="password" autocomplete="off"
                            placeholder="{{ $tokenHint ? 'Saved ('.$tokenHint.') — leave blank to keep' : '123456789:ABC…' }}" />
                        <p class="mt-1.5 text-xs text-muted">Create a bot by chatting with <strong>@BotFather</strong> on Telegram (send <code>/newbot</code>) and paste the token it gives you.</p>
                        @if ($tokenHint)
                            <div class="mt-2"><x-form.checkbox name="remove_telegram_token" label="Remove the saved token (turns Telegram alerts off)" /></div>
                        @endif
                    </div>

                    <div>
                        <x-form.input name="telegram_chat_id" label="Chat ID" x-model="chatId" :value="$chatId" placeholder="e.g. 123456789" />
                        <p class="mt-1.5 text-xs text-muted">Don't know it? Save the token, message your bot, then use <strong>Find my chat</strong> below. Or message <strong>@userinfobot</strong> on Telegram — it replies with your ID.</p>
                    </div>
                </div>
            </x-panel.card>

            <x-panel.card title="Monitoring">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-form.input name="online_threshold_minutes" label="Offline after (minutes)" type="number" min="1" max="120" :value="$onlineThreshold" required />
                        <p class="mt-1.5 text-xs text-muted">A bot with no signal for this long is marked offline.</p>
                    </div>
                    <div>
                        <x-form.input name="expiring_soon_days" label="Expiring soon (days)" type="number" min="1" max="365" :value="$expiringDays" required />
                        <p class="mt-1.5 text-xs text-muted">Licenses ending within this many days are flagged.</p>
                    </div>
                </div>
            </x-panel.card>

            <x-button>Save settings</x-button>
        </form>

        <x-panel.card title="Telegram tools" class="mt-5">
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.settings.telegram.chats') }}">
                    @csrf
                    <x-button variant="ghost" x-bind:disabled="false">Find my chat</x-button>
                </form>
                <form method="POST" action="{{ route('admin.settings.telegram.test') }}">
                    @csrf
                    <x-button variant="ghost" x-bind:disabled="false">Send test message</x-button>
                </form>
            </div>
            <p class="mt-2 text-xs text-muted">These use the saved settings — save first if you just changed them.</p>

            @if (session('chats'))
                <ul class="mt-4 divide-y divide-line rounded-lg border border-line text-sm">
                    @foreach (session('chats') as $chat)
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <span>{{ $chat['name'] ?: 'Unnamed chat' }} <span class="text-xs text-muted">({{ $chat['type'] }} · {{ $chat['id'] }})</span></span>
                            <button type="button" class="text-brand hover:underline" @click="chatId = @js((string) $chat['id'])">Use this chat</button>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-muted">Then press <strong>Save settings</strong> above.</p>
            @endif
        </x-panel.card>
    </div>
</x-layouts.admin>
