<?php

use Illuminate\Support\Facades\Route;
use Leantime\Domain\Supportcenter\Controllers\Support;

Route::match(['get'], '/support-center', [Support::class, 'index']);
Route::match(['get', 'post'], '/support-center/new', [Support::class, 'new']);
Route::match(['get', 'post'], '/support-center/{id}', [Support::class, 'show'])->whereNumber('id');
Route::post('/support-center/{id}/elevate-github', [Support::class, 'elevateGithub'])->whereNumber('id');
