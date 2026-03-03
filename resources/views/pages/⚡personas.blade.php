<?php

use App\Models\Persona;
use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Personas')] class extends Component {
    // Form fields
    public string $name = '';
    public string $description = '';
    public string $context = '';
    public string $perspective = '';
    public string $responseType = 'detailed';
    public string $responseStyle = 'formal';
    public string $model = '';
    public bool $isActive = true;

    // State
    public ?int $editingId = null;
    public bool $showModal = false;
    public array $availableModels = [];

    public function mount(): void
    {
        $this->loadModels();
    }

    public function loadModels(): void
    {
        $setting = Auth::user()->aiSetting;

        if ($setting && $setting->api_key) {
            try {
                $service = new OpenRouterService();
                $this->availableModels = $service->fetchFreeModels($setting->api_key);
            } catch (\Exception $e) {
                $this->availableModels = [];
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $persona = Auth::user()->personas()->findOrFail($id);

        $this->editingId = $id;
        $this->name = $persona->name;
        $this->description = $persona->description ?? '';
        $this->context = $persona->context ?? '';
        $this->perspective = $persona->perspective ?? '';
        $this->responseType = $persona->response_type;
        $this->responseStyle = $persona->response_style;
        $this->model = $persona->model ?? '';
        $this->isActive = $persona->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'context' => 'nullable|string|max:2000',
            'perspective' => 'nullable|string|max:2000',
            'responseType' => 'required|string',
            'responseStyle' => 'required|string',
            'model' => 'nullable|string',
        ]);

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'context' => $this->context,
            'perspective' => $this->perspective,
            'response_type' => $this->responseType,
            'response_style' => $this->responseStyle,
            'model' => $this->model ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->editingId) {
            Auth::user()->personas()->where('id', $this->editingId)->update($data);
        } else {
            Auth::user()->personas()->create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $persona = Auth::user()->personas()->findOrFail($id);
        $persona->update(['is_active' => ! $persona->is_active]);
    }

    public function delete(int $id): void
    {
        Auth::user()->personas()->where('id', $id)->delete();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->description = '';
        $this->context = '';
        $this->perspective = '';
        $this->responseType = 'detailed';
        $this->responseStyle = 'formal';
        $this->model = '';
        $this->isActive = true;
    }

    public function getPersonasProperty()
    {
        return Auth::user()->personas()->latest()->get();
    }
}; ?>

<div>
    <div class="mx-auto w-full max-w-4xl px-4 py-6">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('AI Personas') }}</flux:heading>
                <flux:subheading>{{ __('Create and manage different AI personalities for your chats.') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('New Persona') }}
            </flux:button>
        </div>

        <flux:separator class="mb-6" />

        {{-- Personas Grid --}}
        @if ($this->personas->count() > 0)
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->personas as $persona)
                    <div @class([
                        'group relative rounded-xl border p-5 transition-all duration-200 hover:shadow-lg',
                        'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800' => $persona->is_active,
                        'border-zinc-200/60 bg-zinc-50 opacity-60 dark:border-zinc-700/60 dark:bg-zinc-800/60' => !$persona->is_active,
                    ])>
                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <flux:heading size="sm" class="truncate">{{ $persona->name }}</flux:heading>
                                    @if ($persona->is_active)
                                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                    @endif
                                </div>
                                @if ($persona->description)
                                    <flux:text class="mt-1 text-sm line-clamp-2">{{ $persona->description }}</flux:text>
                                @endif
                            </div>

                            {{-- Actions Menu --}}
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $persona->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="{{ $persona->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleActive({{ $persona->id }})">
                                        {{ $persona->is_active ? __('Deactivate') : __('Activate') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $persona->id }})" wire:confirm="{{ __('Delete this persona?') }}" wire:loading.attr="disabled">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        {{-- Details --}}
                        <div class="mt-4 space-y-2">
                            @if ($persona->model)
                                <div class="flex items-center gap-2">
                                    <flux:icon name="cpu-chip" variant="mini" class="h-4 w-4 text-zinc-400" />
                                    <flux:text class="truncate text-xs font-mono">{{ $persona->model }}</flux:text>
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <flux:badge size="sm" color="sky">{{ ucfirst($persona->response_type) }}</flux:badge>
                                <flux:badge size="sm" color="violet">{{ ucfirst($persona->response_style) }}</flux:badge>
                            </div>
                            @if ($persona->context)
                                <flux:text class="text-xs text-zinc-400 line-clamp-1">
                                    <span class="font-medium">{{ __('Context:') }}</span> {{ $persona->context }}
                                </flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
                <flux:icon name="users" class="mx-auto h-12 w-12 text-zinc-400" />
                <flux:heading size="sm" class="mt-4">{{ __('No personas yet') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Create your first persona to start chatting with a customized AI.') }}</flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                        {{ __('Create Persona') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </div>

    {{-- Create/Edit Modal --}}
    <flux:modal wire:model.self="showModal" class="w-full max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Persona') : __('Create Persona') }}</flux:heading>
                <flux:subheading>{{ __('Define the personality and behavior of your AI assistant.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Name')" placeholder="{{ __('e.g., Code Reviewer, Creative Writer') }}" required />

                <flux:textarea wire:model="description" :label="__('Description')" placeholder="{{ __('Brief description of this persona...') }}" rows="2" />

                <flux:textarea wire:model="context" :label="__('Context')" placeholder="{{ __('Background knowledge or domain context...') }}" rows="3" />

                <flux:textarea wire:model="perspective" :label="__('Perspective')" placeholder="{{ __('Point of view or approach to take...') }}" rows="2" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="responseType" :label="__('Response Type')">
                        <flux:select.option value="concise">{{ __('Concise') }}</flux:select.option>
                        <flux:select.option value="detailed">{{ __('Detailed') }}</flux:select.option>
                        <flux:select.option value="creative">{{ __('Creative') }}</flux:select.option>
                        <flux:select.option value="analytical">{{ __('Analytical') }}</flux:select.option>
                        <flux:select.option value="step-by-step">{{ __('Step by Step') }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="responseStyle" :label="__('Response Style')">
                        <flux:select.option value="formal">{{ __('Formal') }}</flux:select.option>
                        <flux:select.option value="casual">{{ __('Casual') }}</flux:select.option>
                        <flux:select.option value="technical">{{ __('Technical') }}</flux:select.option>
                        <flux:select.option value="friendly">{{ __('Friendly') }}</flux:select.option>
                        <flux:select.option value="professional">{{ __('Professional') }}</flux:select.option>
                    </flux:select>
                </div>

                <flux:select wire:model="model" :label="__('AI Model')">
                    <flux:select.option value="">{{ __('Use default model') }}</flux:select.option>
                    @foreach ($availableModels as $m)
                        <flux:select.option value="{{ $m['id'] }}">{{ $m['name'] }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (empty($availableModels))
                    <flux:text class="text-xs text-zinc-400">
                        {{ __('Configure your API key in Settings → AI Configuration to load available models.') }}
                    </flux:text>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? __('Update') : __('Create') }}</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-1">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        {{ __('Saving...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
