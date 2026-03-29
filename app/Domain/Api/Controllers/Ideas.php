<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Ideas\Repositories\Ideas as IdeaRepository;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Ideas
 *
 * This class represents a controller for handling ideas related requests.
 */
class Ideas extends Controller
{
    private ProjectRepository $projects;

    private IdeaRepository $ideaAPIRepo;

    /**
     * init - initialize private variables
     */
    public function init(ProjectRepository $projects, IdeaRepository $ideaAPIRepo): void
    {
        // @TODO: projects is never used in this class?
        $this->projects = $projects;
        $this->ideaAPIRepo = $ideaAPIRepo;
    }

    /**
     * get - handle get requests
     */
    public function get(array $params): Response
    {
        if (isset($params['id'])) {
            $item = $this->ideaAPIRepo->getSingleCanvasItem((int) $params['id']);

            if ($item === false) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Idea item not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $item]);
        }

        if (isset($params['canvasId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->ideaAPIRepo->getCanvasItemsById((int) $params['canvasId']),
            ]);
        }

        if (isset($params['boardId'])) {
            $board = $this->ideaAPIRepo->getSingleCanvas((int) $params['boardId']);

            if ($board === false || count($board) === 0) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Idea board not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $board]);
        }

        if (isset($params['projectId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->ideaAPIRepo->getAllCanvas((int) $params['projectId']),
            ]);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing idea lookup parameter'], 400);
    }

    /**
     * post - handle post requests
     */
    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (isset($params['action']) && $params['action'] === 'createBoard') {
            if (empty($params['title']) || ! isset($params['projectId'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'title and projectId are required'], 400);
            }

            $boardId = $this->ideaAPIRepo->addCanvas([
                'title' => $params['title'],
                'author' => session('userdata.id'),
                'projectId' => (int) $params['projectId'],
            ]);

            if ($boardId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['boardId' => (int) $boardId]], 201);
        }

        if (isset($params['action']) && $params['action'] === 'createItem') {
            if (! isset($params['canvasId'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'canvasId is required'], 400);
            }

            $itemId = $this->ideaAPIRepo->addCanvasItem([
                'description' => $params['description'] ?? '',
                'assumptions' => $params['assumptions'] ?? '',
                'data' => $params['data'] ?? '',
                'conclusion' => $params['conclusion'] ?? '',
                'box' => $params['box'] ?? 'idea',
                'author' => session('userdata.id'),
                'canvasId' => (int) $params['canvasId'],
                'status' => $params['status'] ?? '',
                'milestoneId' => $params['milestoneId'] ?? '',
            ]);

            if ($itemId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['itemId' => (int) $itemId]], 201);
        }

        if (isset($params['action']) && $params['action'] == 'ideaSort' && isset($params['payload']) === true) {
            if (! $this->ideaAPIRepo->updateIdeaSorting($params['payload'])) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (isset($params['action']) && $params['action'] == 'statusUpdate' && isset($params['payload']) === true) {
            if (! $this->ideaAPIRepo->bulkUpdateIdeaStatus($params['payload'])) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Unsupported action'], 400);
    }

    /**
     * put - handle put requests
     */
    public function patch(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (isset($params['boardId'])) {
            if (empty($params['title'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'title is required'], 400);
            }

            if (! $this->ideaAPIRepo->updateCanvas([
                'id' => (int) $params['boardId'],
                'title' => $params['title'],
            ])) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (! isset($params['id']) || ! $this->ideaAPIRepo->patchCanvasItem((int) $params['id'], $params)) {
            return $this->tpl->displayJson(['status' => 'failure'], 500);
        }

        return $this->tpl->displayJson(['status' => 'ok']);
    }

    /**
     * delete - handle delete requests
     */
    public function delete(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (isset($params['boardId'])) {
            $this->ideaAPIRepo->deleteCanvas((int) $params['boardId']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (isset($params['id'])) {
            $this->ideaAPIRepo->delCanvasItem((int) $params['id']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing delete target'], 400);
    }
}
