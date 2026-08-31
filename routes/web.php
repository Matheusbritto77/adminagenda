<?php

use App\Http\Controllers\WhatsAppStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/whatsapp-stream', [WhatsAppStreamController::class, 'stream'])->name('whatsapp.stream');
