<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header class="pt-2">
                <x-app-logo :sidebar="true" href="{{ route('chat') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Menu')" class="grid">
                    <flux:sidebar.item icon="chat-bubble-left-right" :href="route('chat')" :current="request()->routeIs('chat')" wire:navigate>
                        {{ __('Chat') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="users" :href="route('personas')" :current="request()->routeIs('personas')" wire:navigate>
                        {{ __('Personas') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Settings')" class="grid">
                    <flux:sidebar.item icon="cog-6-tooth" :href="route('ai-settings.edit')" :current="request()->routeIs('ai-settings.edit')" wire:navigate>
                        {{ __('AI Configuration') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="paint-brush" :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate>
                        {{ __('Appearance') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>
        </flux:sidebar>

        <!-- Mobile Header -->
        <flux:header class="lg:hidden !pt-6">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:navbar>
                <flux:navbar.item icon="cog-6-tooth" :href="route('ai-settings.edit')" wire:navigate />
            </flux:navbar>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
