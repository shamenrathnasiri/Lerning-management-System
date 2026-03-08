<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Placeholder dashboard routes for role-based redirects
Route::get('/admin/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'admin'])->name('admin.dashboard');

Route::get('/instructor/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'instructor'])->name('instructor.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── Public Category & Tag Browsing ──────────────────
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
Route::get('/tags/{tag}', [TagController::class, 'show'])->name('tags.show');

// ── Category & Tag AJAX (authenticated) ─────────────
Route::middleware('auth')->group(function () {
    Route::get('/api/categories/search', [CategoryController::class, 'search'])->name('categories.search');
    Route::get('/api/tags/search', [TagController::class, 'search'])->name('tags.search');
});

// ── Category Management (admin) ─────────────────────
Route::middleware(['auth', 'permission:manage categories'])->prefix('admin')->name('categories.')->group(function () {
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    Route::post('/categories/quick', [CategoryController::class, 'quickStore'])->name('quick-store');
    Route::post('/categories/order', [CategoryController::class, 'updateOrder'])->name('update-order');
});

// ── Tag Management (admin) ──────────────────────────
Route::middleware(['auth', 'permission:manage tags'])->prefix('admin')->name('tags.')->group(function () {
    Route::get('/tags/create', [TagController::class, 'create'])->name('create');
    Route::post('/tags', [TagController::class, 'store'])->name('store');
    Route::get('/tags/{tag}/edit', [TagController::class, 'edit'])->name('edit');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('update');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('destroy');
    Route::post('/tags/quick', [TagController::class, 'quickStore'])->name('quick-store');
    Route::post('/tags/bulk-delete', [TagController::class, 'bulkDestroy'])->name('bulk-destroy');
});

require __DIR__.'/auth.php';
