<?php

use Illuminate\Support\Facades\Route;

// Chat is the home page
Route::livewire('/', 'pages::chat')->name('chat');
Route::livewire('personas', 'pages::personas')->name('personas');

require __DIR__.'/settings.php';
