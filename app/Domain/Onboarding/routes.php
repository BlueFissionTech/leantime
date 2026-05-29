<?php

use Illuminate\Support\Facades\Route;
use Leantime\Domain\Onboarding\Controllers\Project;

Route::match(['get', 'post'], '/onboarding/project', [Project::class, 'project']);
