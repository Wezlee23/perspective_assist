<?php

use Illuminate\Support\Facades\Route;

Route::redirect('settings', 'settings/ai');
Route::livewire('settings/ai', 'pages::settings.ai')->name('ai-settings.edit');
Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
