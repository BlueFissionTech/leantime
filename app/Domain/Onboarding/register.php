<?php

use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener('leantime.domain.menu.repositories.menu.menuStructures.default', function (array $menu) {
    if (! session()->exists('userdata.id') || ! session()->exists('currentProject')) {
        return $menu;
    }

    if (trim((string) env('LEAN_MANIFEST_BASE_URL', '')) === '') {
        return $menu;
    }

    if (! isset($menu[30]['submenu']) || ! is_array($menu[30]['submenu'])) {
        return $menu;
    }

    $menu[30]['submenu'][90] = [
        'type' => 'item',
        'module' => 'onboarding',
        'title' => 'menu.onboarding_discovery',
        'icon' => 'fa fa-fw fa-compass',
        'tooltip' => 'menu.onboarding_discovery_tooltip',
        'href' => '/onboarding/project',
        'active' => ['project'],
        'role' => 'editor',
    ];

    ksort($menu[30]['submenu']);

    return $menu;
});
