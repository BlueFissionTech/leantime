<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;

$renderExportLink = static function (): void {
    if (! Auth::userIsAtLeast(Roles::$manager, true)) {
        return;
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';
    $href = BASE_URL.'/taskcsvexport/export';

    if ($query !== '') {
        $href .= '?'.$query;
    }

    echo '<a class="btn btn-default" style="margin-left:8px;" href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">'.
        '<i class="fa fa-download" aria-hidden="true"></i> Export Team CSV'.
        '</a>';
};

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showAll.filters.afterRighthandSectionOpen',
    $renderExportLink
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showList.filters.afterLefthandSectionOpen',
    $renderExportLink
);
