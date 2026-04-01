<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Supportcenter\Services\SupportProjects;

EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.menuStructures.personal', function (array $menu) {
    if (! session()->exists('userdata.id')) {
        return $menu;
    }

    $projects = app(SupportProjects::class)->getAccessibleProjectsForUser(
        (int) session('userdata.id'),
        is_numeric(session('userdata.clientId')) ? (int) session('userdata.clientId') : null
    );

    if (count($projects) === 0) {
        return $menu;
    }

    $menu[18] = [
        'type' => 'item',
        'module' => 'supportcenter',
        'title' => 'Support Center',
        'icon' => 'fa fa-life-ring',
        'tooltip' => 'Support Center',
        'href' => '/support-center',
        'active' => ['index', 'new', 'show'],
    ];

    ksort($menu);

    return $menu;
});
