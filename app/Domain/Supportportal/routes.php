<?php

use Illuminate\Support\Facades\Route;
use Leantime\Domain\Supportportal\Controllers\Access;
use Leantime\Domain\Supportportal\Controllers\Home;
use Leantime\Domain\Supportportal\Controllers\Tickets;

Route::match(['get'], '/support', [Home::class, 'get']);
Route::match(['get', 'post'], '/support/login', [Access::class, 'login']);
Route::match(['get', 'post'], '/support/register', [Access::class, 'register']);
Route::post('/support/logout', [Access::class, 'logout']);

Route::match(['get'], '/support/tickets', [Tickets::class, 'index']);
Route::match(['get', 'post'], '/support/tickets/new', [Tickets::class, 'new']);
Route::match(['get', 'post'], '/support/tickets/{id}', [Tickets::class, 'show'])->whereNumber('id');
