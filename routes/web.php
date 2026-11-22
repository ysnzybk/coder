<?php

use App\Http\Controllers\SiteController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

// Takip: e-posta açılma pikseli, tıklama yönlendirmesi, ret (opt-out)
Route::get('/t/o/{token}.gif', [TrackingController::class, 'open'])->name('track.open')->middleware('throttle:240,1');
Route::get('/t/c/{token}', [TrackingController::class, 'click'])->name('track.click')->middleware('throttle:120,1');
Route::get('/u/{token}', [TrackingController::class, 'optOutForm'])->name('optout.form')->middleware('throttle:60,1');
Route::post('/u/{token}', [TrackingController::class, 'optOut'])->name('optout.store')->middleware('throttle:20,1');

// Admin: onaylı WhatsApp mesajını aç (gönderildi işaretler)
Route::get('/admin/wa/{message}', WhatsAppController::class)->middleware('auth')->name('admin.whatsapp');

// Satılan sitenin kendi alan adı → ana sayfa; aksi halde panele yönlendirir
Route::get('/', [SiteController::class, 'home'])->name('home');

// Firma siteleri: domain.com/{firma-slug} — en sonda olmalı
$reserved = implode('|', array_map(fn ($s) => preg_quote($s, '/'), config('leadgen.reserved_slugs')));
Route::get('/{slug}', [SiteController::class, 'show'])
    ->where('slug', "(?!(?:{$reserved})$)[a-z0-9]+(?:-[a-z0-9]+)*")
    ->name('site.show');
