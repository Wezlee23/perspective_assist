<?php

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Persona;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chat')] class extends Component {
    public ?int $conversationId = null;
    public ?int $selectedPersonaId = null;
    public string $message = '';
    public bool $isThinking = false;
    public string $errorMessage = '';

    public function mount(): void
    {
        // Select first active persona by default
        $firstPersona = Auth::user()->personas()->where('is_active', true)->first();
        if ($firstPersona) {
            $this->selectedPersonaId = $firstPersona->id;
        }
    }

    #[Computed]
    public function conversations()
    {
        return Auth::user()->chatConversations()
            ->with('persona:id,name')
            ->latest()
            ->get();
    }

    #[Computed]
    public function personas()
    {
        return Auth::user()->personas()->where('is_active', true)->get();
    }

    #[Computed]
    public function hasApiKey(): bool
    {
        $setting = Auth::user()->aiSetting;
        return $setting && !empty($setting->api_key);
    }

    #[Computed]
    public function hasDefaultModel(): bool
    {
        $setting = Auth::user()->aiSetting;
        return $setting && !empty($setting->default_model);
    }

    #[Computed]
    public function currentMessages()
    {
        if (! $this->conversationId) {
            return collect();
        }

        return ChatMessage::where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function currentConversation()
    {
        if (! $this->conversationId) {
            return null;
        }

        return ChatConversation::find($this->conversationId);
    }

    public function selectConversation(int $id): void
    {
        $conversation = Auth::user()->chatConversations()->findOrFail($id);
        $this->conversationId = $id;
        $this->selectedPersonaId = $conversation->persona_id;
        $this->errorMessage = '';
        unset($this->currentMessages, $this->currentConversation);
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
        $this->errorMessage = '';
        unset($this->currentMessages, $this->currentConversation);
    }

    public function deleteConversation(int $id): void
    {
        Auth::user()->chatConversations()->where('id', $id)->delete();

        if ($this->conversationId === $id) {
            $this->conversationId = null;
        }

        unset($this->conversations, $this->currentMessages, $this->currentConversation);
    }

    public function sendMessage(): void
    {
        if (empty(trim($this->message))) {
            return;
        }

        $this->errorMessage = '';

        // Check API key
        $setting = Auth::user()->aiSetting;
        if (! $setting || empty($setting->api_key)) {
            $this->errorMessage = 'Please configure your OpenRouter API key in Settings → AI Configuration.';
            return;
        }

        // Get persona
        $persona = null;
        if ($this->selectedPersonaId) {
            $persona = Auth::user()->personas()->find($this->selectedPersonaId);
        }

        // Determine model
        $model = $persona?->model ?? $setting->default_model;
        if (empty($model)) {
            $this->errorMessage = 'No model selected. Please set a default model in Settings → AI Configuration or assign one to your persona.';
            return;
        }

        // Create conversation if needed
        if (! $this->conversationId) {
            $conversation = Auth::user()->chatConversations()->create([
                'persona_id' => $this->selectedPersonaId,
                'title' => Str::limit($this->message, 60),
            ]);
            $this->conversationId = $conversation->id;
        }

        // Save user message
        ChatMessage::create([
            'conversation_id' => $this->conversationId,
            'role' => 'user',
            'content' => $this->message,
        ]);

        $userMessage = $this->message;
        $this->message = '';
        $this->isThinking = true;

        // Build messages array for API
        $apiMessages = [];

        // Add system prompt from persona
        if ($persona) {
            $systemPrompt = $persona->buildSystemPrompt();
            if (! empty($systemPrompt)) {
                $apiMessages[] = ['role' => 'system', 'content' => $systemPrompt];
            }
        }

        // Add conversation history
        $history = ChatMessage::where('conversation_id', $this->conversationId)
            ->orderBy('created_at')
            ->get();

        foreach ($history as $msg) {
            $apiMessages[] = ['role' => $msg->role, 'content' => $msg->content];
        }

        // Call OpenRouter API
        try {
            $service = new OpenRouterService();
            $response = $service->chat($setting->api_key, $model, $apiMessages);

            // Save assistant response
            ChatMessage::create([
                'conversation_id' => $this->conversationId,
                'role' => 'assistant',
                'content' => $response ?? 'No response received.',
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();

            // Save error as system message
            ChatMessage::create([
                'conversation_id' => $this->conversationId,
                'role' => 'assistant',
                'content' => '⚠️ Error: ' . $e->getMessage(),
            ]);
        }

        $this->isThinking = false;
        unset($this->currentMessages, $this->conversations, $this->currentConversation);

        $this->dispatch('scroll-to-bottom');
    }
}; ?>

<div class="flex h-[calc(100vh-6rem)] overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" x-data="{
    scrollToBottom() {
        this.$nextTick(() => {
            const el = document.getElementById('messages-container');
            if (el) el.scrollTop = el.scrollHeight;
        });
    }
}" x-init="scrollToBottom()" @scroll-to-bottom.window="scrollToBottom()">
    {{-- Conversations Sidebar --}}
    <div class="hidden w-72 flex-shrink-0 flex-col border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 md:flex">
        {{-- Sidebar Header --}}
        <div class="flex items-center justify-between border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Conversations') }}</flux:heading>
            <flux:button variant="ghost" size="sm" icon="plus" wire:click="newConversation" />
        </div>

        {{-- Conversations List --}}
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            @forelse ($this->conversations as $conv)
                <div
                    wire:click="selectConversation({{ $conv->id }})"
                    @class([
                        'group flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 transition-colors',
                        'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' => $conversationId === $conv->id,
                        'hover:bg-zinc-100 dark:hover:bg-zinc-800' => $conversationId !== $conv->id,
                    ])
                >
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium">{{ $conv->title }}</div>
                        @if ($conv->persona)
                            <div class="truncate text-xs text-zinc-400">{{ $conv->persona->name }}</div>
                        @endif
                    </div>
                    <flux:button
                        variant="ghost"
                        size="xs"
                        icon="trash"
                        wire:click.stop="deleteConversation({{ $conv->id }})"
                        wire:confirm="{{ __('Delete this conversation?') }}"
                        class="opacity-0 group-hover:opacity-100 transition-opacity"
                    />
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <flux:text class="text-sm text-zinc-400">{{ __('No conversations yet') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Main Chat Area --}}
    <div class="flex flex-1 flex-col min-w-0">
        {{-- Chat Header --}}
        <div class="flex items-center gap-4 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <div class="flex items-center gap-3 flex-1 min-w-0">
                <flux:icon name="chat-bubble-left-right" class="h-5 w-5 text-zinc-400" />
                @if ($this->currentConversation)
                    <flux:heading size="sm" class="truncate">{{ $this->currentConversation->title }}</flux:heading>
                @else
                    <flux:heading size="sm">{{ __('New Chat') }}</flux:heading>
                @endif
            </div>

            {{-- Persona Selector --}}
            <div class="flex-shrink-0 w-48">
                <flux:select wire:model.live="selectedPersonaId" size="sm" placeholder="{{ __('Select Persona') }}">
                    <flux:select.option value="">{{ __('No Persona') }}</flux:select.option>
                    @foreach ($this->personas as $persona)
                        <flux:select.option value="{{ $persona->id }}">{{ $persona->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Error Banner --}}
        @if ($errorMessage)
            <div class="mx-4 mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- Messages --}}
        <div id="messages-container" class="flex-1 overflow-y-auto px-4 py-6 space-y-4" wire:poll.visible.30s>
            @if ($this->currentMessages->isEmpty() && !$conversationId)
                {{-- Welcome / Onboarding Screen --}}
                <div class="flex h-full flex-col items-center justify-center text-center px-4">
                    <div class="rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 p-4 shadow-xl shadow-blue-500/20">
                        <flux:icon name="sparkles" class="h-10 w-10 text-white" />
                    </div>
                    <flux:heading size="lg" class="mt-6">{{ __('Welcome to PetraAI') }}</flux:heading>

                    @if (!$this->hasApiKey)
                        {{-- Step 1: Setup API Key --}}
                        <flux:text class="mt-2 max-w-md">
                            {{ __('Let\'s get started! First, connect your free OpenRouter API key.') }}
                        </flux:text>

                        <div class="mt-6 w-full max-w-md space-y-4 text-left">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <flux:heading size="sm" class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                                    {{ __('Get a Free API Key') }}
                                </flux:heading>
                                <div class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <p>{{ __('Visit OpenRouter and create a free account:') }}</p>
                                    <ol class="list-decimal ml-5 space-y-1">
                                        <li>{{ __('Go to') }} <a href="https://openrouter.ai" target="_blank" class="text-blue-600 underline dark:text-blue-400">openrouter.ai</a></li>
                                        <li>{{ __('Sign up with Google or email (free)') }}</li>
                                        <li>{{ __('Navigate to') }} <a href="https://openrouter.ai/keys" target="_blank" class="text-blue-600 underline dark:text-blue-400">{{ __('Keys') }}</a> {{ __('in your dashboard') }}</li>
                                        <li>{{ __('Click "Create Key" and copy it') }}</li>
                                    </ol>
                                </div>
                            </div>

                            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <flux:heading size="sm" class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                                    {{ __('Add Key to PetraAI') }}
                                </flux:heading>
                                <flux:text class="mt-2 text-sm">
                                    {{ __('Paste your API key in the AI Settings page and select a free model.') }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="mt-6">
                            <flux:button variant="primary" :href="route('ai-settings.edit')" wire:navigate icon="cog-6-tooth">
                                {{ __('Go to AI Settings') }}
                            </flux:button>
                        </div>

                    @elseif ($this->personas->isEmpty())
                        {{-- Step 2: Create Persona --}}
                        <flux:text class="mt-2 max-w-md">
                            {{ __('API key is set! Now create a persona to define how your AI assistant should behave.') }}
                        </flux:text>

                        <div class="mt-6 w-full max-w-md text-left">
                            <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                                <div class="flex items-center gap-2 text-green-700 dark:text-green-400">
                                    <flux:icon name="check-circle" variant="mini" class="h-5 w-5" />
                                    <flux:text class="font-medium text-sm">{{ __('OpenRouter connected successfully!') }}</flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6">
                            <flux:button variant="primary" :href="route('personas')" wire:navigate icon="users">
                                {{ __('Create Your First Persona') }}
                            </flux:button>
                        </div>

                    @else
                        {{-- Ready to chat --}}
                        <flux:text class="mt-2 max-w-md">
                            {{ __('Select a persona above and start chatting. Your AI assistant is ready!') }}
                        </flux:text>
                    @endif
                </div>
            @else
                @foreach ($this->currentMessages as $msg)
                    <div @class([
                        'flex gap-3',
                        'justify-end' => $msg->role === 'user',
                    ])>
                        @if ($msg->role === 'assistant')
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-blue-600 shadow-md">
                                <flux:icon name="sparkles" variant="mini" class="h-4 w-4 text-white" />
                            </div>
                        @endif

                        <div @class([
                            'max-w-[75%] rounded-2xl px-4 py-3 text-sm leading-relaxed',
                            'bg-blue-600 text-white' => $msg->role === 'user',
                            'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200' => $msg->role === 'assistant',
                        ])>
                            @if ($msg->role === 'assistant')
                                <div class="prose prose-sm dark:prose-invert max-w-none">
                                    {!! Str::markdown($msg->content) !!}
                                </div>
                            @else
                                {{ $msg->content }}
                            @endif
                        </div>

                        @if ($msg->role === 'user')
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <flux:icon name="user" variant="mini" class="h-4 w-4 text-zinc-600 dark:text-zinc-300" />
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Thinking Indicator --}}
                @if ($isThinking)
                    <div class="flex gap-3">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-blue-600 shadow-md">
                            <flux:icon name="sparkles" variant="mini" class="h-4 w-4 text-white animate-pulse" />
                        </div>
                        <div class="rounded-2xl bg-zinc-100 px-4 py-3 dark:bg-zinc-800">
                            <div class="flex items-center gap-1">
                                <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400 [animation-delay:0ms]"></div>
                                <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400 [animation-delay:150ms]"></div>
                                <div class="h-2 w-2 animate-bounce rounded-full bg-zinc-400 [animation-delay:300ms]"></div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- Input Area --}}
        <div class="border-t border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
            {{-- Loading bar --}}
            <div wire:loading wire:target="sendMessage" class="mb-3 flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ __('AI is thinking...') }}
            </div>
            <form wire:submit="sendMessage" class="flex gap-3">
                <div class="flex-1">
                    <flux:input
                        wire:model="message"
                        placeholder="{{ __('Type your message...') }}"
                        autofocus
                        autocomplete="off"
                        :disabled="$isThinking"
                    />
                </div>
                <flux:button
                    type="submit"
                    variant="primary"
                    :disabled="$isThinking || empty(trim($message))"
                >
                    <span wire:loading.remove wire:target="sendMessage" class="flex items-center gap-1">
                        <flux:icon name="paper-airplane" variant="mini" class="h-4 w-4" />
                        <span class="hidden sm:inline">{{ __('Send') }}</span>
                    </span>
                    <span wire:loading wire:target="sendMessage" class="flex items-center gap-1">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        <span class="hidden sm:inline">{{ __('Sending...') }}</span>
                    </span>
                </flux:button>
            </form>
        </div>
    </div>
</div>
