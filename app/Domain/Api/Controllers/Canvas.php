<?php

/**
 * canvas class - Generic canvas API controller
 */

namespace Leantime\Domain\Api\Controllers;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Str;
use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * @TODO: Could this class be change to abstract? As it is a generic class that should never be initiated!
 */
class Canvas extends Controller
{
    /**
     * Constant that must be redefined
     */
    protected const CANVAS_NAME = '??';

    private ProjectRepository $projects;

    /**
     * @var Closure|mixed|object|null
     */
    private mixed $canvasRepo;

    /**
     * constructor - initialize private variables
     *
     *
     *
     * @throws BindingResolutionException
     */
    public function init(ProjectRepository $projects): void
    {
        // @TODO: project are never used in this class?
        $this->projects = $projects;
        $canvasName = Str::studly(static::CANVAS_NAME).'canvas';
        $repoName = app()->getNamespace()."Domain\\$canvasName\\Repositories\\$canvasName";
        $this->canvasRepo = app()->make($repoName);
    }

    /**
     * get - handle get requests
     */
    public function get(array $params): Response
    {
        if (isset($params['id'])) {
            $item = $this->canvasRepo->getSingleCanvasItem((int) $params['id']);

            if ($item === false) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Canvas item not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $item]);
        }

        if (isset($params['canvasId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->canvasRepo->getCanvasItemsById((int) $params['canvasId']),
            ]);
        }

        if (isset($params['boardId'])) {
            $board = $this->canvasRepo->getSingleCanvas((int) $params['boardId']);

            if ($board === false || count($board) === 0) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Canvas board not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $board]);
        }

        if (isset($params['projectId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->canvasRepo->getAllCanvas((int) $params['projectId']),
            ]);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing canvas lookup parameter'], 400);
    }

    /**
     * post - handle post requests
     */
    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (! isset($params['action'])) {
            return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Action not set'], 400);
        }

        if ($params['action'] === 'createBoard') {
            if (empty($params['title']) || ! isset($params['projectId'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'title and projectId are required'], 400);
            }

            $boardId = $this->canvasRepo->addCanvas([
                'title' => $params['title'],
                'description' => $params['description'] ?? '',
                'author' => session('userdata.id'),
                'projectId' => (int) $params['projectId'],
            ]);

            if ($boardId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['boardId' => (int) $boardId]], 201);
        }

        if ($params['action'] === 'createItem') {
            if (! isset($params['canvasId'], $params['box'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'canvasId and box are required'], 400);
            }

            $itemId = $this->canvasRepo->addCanvasItem([
                'title' => $params['title'] ?? '',
                'description' => $params['description'] ?? '',
                'assumptions' => $params['assumptions'] ?? '',
                'data' => $params['data'] ?? '',
                'conclusion' => $params['conclusion'] ?? '',
                'box' => $params['box'],
                'author' => session('userdata.id'),
                'canvasId' => (int) $params['canvasId'],
                'status' => $params['status'] ?? '',
                'relates' => $params['relates'] ?? '',
                'milestoneId' => $params['milestoneId'] ?? '',
                'kpi' => $params['kpi'] ?? '',
                'data1' => $params['data1'] ?? '',
                'startDate' => $params['startDate'] ?? '',
                'endDate' => $params['endDate'] ?? '',
                'setting' => $params['setting'] ?? '',
                'metricType' => $params['metricType'] ?? '',
                'impact' => $params['impact'] ?? '',
                'effort' => $params['effort'] ?? '',
                'probability' => $params['probability'] ?? '',
                'action' => $params['actionLabel'] ?? ($params['itemAction'] ?? ''),
                'assignedTo' => $params['assignedTo'] ?? '',
                'startValue' => $params['startValue'] ?? '',
                'currentValue' => $params['currentValue'] ?? '',
                'endValue' => $params['endValue'] ?? '',
                'parent' => $params['parent'] ?? '',
                'tags' => $params['tags'] ?? '',
            ]);

            if ($itemId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['itemId' => (int) $itemId]], 201);
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

            if (! $this->canvasRepo->updateCanvas([
                'id' => (int) $params['boardId'],
                'title' => $params['title'],
                'description' => $params['description'] ?? '',
            ])) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (
            ! isset($params['id'])
            || ! $this->canvasRepo->patchCanvasItem((int) $params['id'], $params)
        ) {
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
            $this->canvasRepo->deleteCanvas((int) $params['boardId']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (isset($params['id'])) {
            $this->canvasRepo->delCanvasItem((int) $params['id']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing delete target'], 400);
    }
}
