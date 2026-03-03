<?php

use App\Models\AiSetting;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('AI Configuration')] class extends Component {
    public string $apiKey = '';
    public string $defaultModel = '';
    public array $models = [];
    public bool $isLoading = false;
    public string $statusMessage = '';
    public string $statusType = 'info'; // info, success, error

    public function mount(): void
    {
        $setting = Auth::user()->aiSetting;

        if ($setting) {
            $this->apiKey = $setting->api_key ?? '';
            $this->defaultModel = $setting->default_model ?? '';
        }
    }

    public function saveApiKey(): void
    {
        $this->validate([
            'apiKey' => 'required|string|min:10',
        ]);

        $service = new OpenRouterService();
        $isValid = $service->validateKey($this->apiKey);

        if (! $isValid) {
            $this->statusMessage = 'Invalid API key. Please check and try again.';
            $this->statusType = 'error';
            return;
        }

        $setting = Auth::user()->aiSetting()->firstOrCreate(
            ['user_id' => Auth::id()],
            ['api_key' => $this->apiKey]
        );

        $setting->update(['api_key' => $this->apiKey]);

        $this->statusMessage = 'API key saved and validated successfully!';
        $this->statusType = 'success';

        $this->fetchModels();
    }

    public function fetchModels(): void
    {
        if (empty($this->apiKey)) {
            $this->statusMessage = 'Please enter and save your API key first.';
            $this->statusType = 'error';
            return;
        }

        $this->isLoading = true;
        $this->statusMessage = 'Fetching free models...';
        $this->statusType = 'info';

        try {
            $service = new OpenRouterService();
            $this->models = $service->fetchFreeModels($this->apiKey);

            if (empty($this->models)) {
                $this->statusMessage = 'No free models found. Please check your API key.';
                $this->statusType = 'error';
            } else {
                $this->statusMessage = count($this->models) . ' free models loaded!';
                $this->statusType = 'success';
            }
        } catch (\Exception $e) {
            $this->statusMessage = 'Error fetching models: ' . $e->getMessage();
            $this->statusType = 'error';
        }

        $this->isLoading = false;
    }

    public function selectModel(string $modelId): void
    {
        $this->defaultModel = $modelId;

        $setting = Auth::user()->aiSetting()->firstOrCreate(
            ['user_id' => Auth::id()],
        );

        $setting->update(['default_model' => $modelId]);

        $this->statusMessage = 'Default model updated!';
        $this->statusType = 'success';
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('AI Configuration')" :subheading="__('Connect to OpenRouter and configure your AI models')">
        <div class="my-6 w-full space-y-6">
            {{-- API Key Section --}}
            <div class="space-y-4">
                <flux:heading size="sm">{{ __('OpenRouter API Key') }}</flux:heading>
                <flux:subheading>{{ __('Enter your OpenRouter API key to access free AI models.') }}</flux:subheading>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <flux:input wire:model="apiKey" type="password" placeholder="sk-or-v1-..." />
                    </div>
                    <flux:button variant="primary" wire:click="saveApiKey" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveApiKey">{{ __('Save & Validate') }}</span>
                        <span wire:loading wire:target="saveApiKey" class="flex items-center gap-1">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Validating...') }}
                        </span>
                    </flux:button>
                </div>

                <flux:text class="text-xs">
                    {{ __('Get your free API key from') }}
                    <flux:link href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</flux:link>
                </flux:text>
            </div>

            {{-- Status Message --}}
            @if ($statusMessage)
                <div @class([
                    'rounded-lg px-4 py-3 text-sm',
                    'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' => $statusType === 'success',
                    'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' => $statusType === 'error',
                    'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400' => $statusType === 'info',
                ])>
                    {{ $statusMessage }}
                </div>
            @endif

            <flux:separator />

            {{-- Fetch Models Section --}}
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm">{{ __('Free Models') }}</flux:heading>
                        <flux:subheading>{{ __('Browse and select from available free AI models.') }}</flux:subheading>
                    </div>
                    <flux:button variant="filled" wire:click="fetchModels" wire:loading.attr="disabled" :disabled="empty($apiKey)">
                        <span wire:loading.remove wire:target="fetchModels">{{ __('Fetch Models') }}</span>
                        <span wire:loading wire:target="fetchModels" class="flex items-center gap-1">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            {{ __('Loading...') }}
                        </span>
                    </flux:button>
                </div>

                {{-- Models List --}}
                @if (count($models) > 0)
                    <div class="max-h-[500px] space-y-2 overflow-y-auto pr-1">
                        @foreach ($models as $model)
                            <div
                                wire:click="selectModel('{{ $model['id'] }}')"
                                @class([
                                    'cursor-pointer rounded-lg border p-4 transition-all duration-200 hover:shadow-md',
                                    'border-blue-500 bg-blue-50 shadow-sm dark:border-blue-400 dark:bg-blue-900/20' => $defaultModel === $model['id'],
                                    'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' => $defaultModel !== $model['id'],
                                ])
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <flux:heading size="sm" class="truncate">{{ $model['name'] }}</flux:heading>
                                            @if ($defaultModel === $model['id'])
                                                <flux:badge color="blue" size="sm">{{ __('Selected') }}</flux:badge>
                                            @endif
                                        </div>
                                        <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">
                                            {{ Str::limit($model['description'], 150) }}
                                        </flux:text>
                                        <div class="mt-2 flex items-center gap-3">
                                            <flux:badge size="sm" color="green">{{ __('Free') }}</flux:badge>
                                            @if ($model['context_length'])
                                                <flux:text class="text-xs text-zinc-400">
                                                    {{ number_format($model['context_length']) }} {{ __('tokens') }}
                                                </flux:text>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif (!empty($apiKey))
                    <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                        <flux:icon name="cpu-chip" class="mx-auto h-10 w-10 text-zinc-400" />
                        <flux:text class="mt-3">{{ __('Click "Fetch Models" to load available free models') }}</flux:text>
                    </div>
                @endif
            </div>

            {{-- Current Selection --}}
            @if ($defaultModel)
                <flux:separator />
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-900">
                    <flux:heading size="sm">{{ __('Current Default Model') }}</flux:heading>
                    <flux:text class="mt-1 font-mono text-sm">{{ $defaultModel }}</flux:text>
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
