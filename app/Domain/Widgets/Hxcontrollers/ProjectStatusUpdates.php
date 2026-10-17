<?php

namespace Leantime\Domain\Widgets\Hxcontrollers;

use Leantime\Core\Controller\HtmxController;
use Leantime\Domain\Comments\Services\Comments as CommentService;

class ProjectStatusUpdates extends HtmxController
{
    protected static string $view = 'widgets::partials.projectStatusUpdates';

    private CommentService $commentService;

    public function init(CommentService $commentService): void
    {
        $this->commentService = $commentService;
        session(['lastPage' => BASE_URL.'/dashboard/home']);
    }

    public function get(): void
    {
        $limit = (int) ($this->incomingRequest->query->get('limit') ?? 8);
        $this->tpl->assign('updates', $this->commentService->getRecentProjectStatusUpdates($limit));
    }
}
