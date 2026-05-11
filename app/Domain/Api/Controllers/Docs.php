<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Wiki\Models\Article;
use Leantime\Domain\Wiki\Models\Wiki as WikiModel;
use Leantime\Domain\Wiki\Repositories\Wiki as WikiRepository;
use Leantime\Domain\Wiki\Services\Wiki as WikiService;
use Symfony\Component\HttpFoundation\Response;

class Docs extends Controller
{
    private WikiService $wikiService;

    private WikiRepository $wikiRepository;

    public function init(WikiService $wikiService, WikiRepository $wikiRepository): void
    {
        $this->wikiService = $wikiService;
        $this->wikiRepository = $wikiRepository;
    }

    public function get(array $params): Response
    {
        if (isset($params['activityForId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->wikiService->getArticleActivity((int) $params['activityForId']),
            ]);
        }

        if (isset($params['id'])) {
            if (! isset($params['projectId'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'projectId is required for article lookup'], 400);
            }

            $article = $this->wikiService->getArticle((int) $params['id'], (int) $params['projectId']);

            if ($article === false) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Document not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $article]);
        }

        if (isset($params['canvasId'])) {
            $userId = isset($params['userId']) ? (int) $params['userId'] : (int) (session('userdata.id') ?? 0);

            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->wikiService->getAllWikiHeadlines((int) $params['canvasId'], $userId),
            ]);
        }

        if (isset($params['boardId'])) {
            $wiki = $this->wikiService->getWiki((int) $params['boardId']);

            if ($wiki === false) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Doc board not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $wiki]);
        }

        if (isset($params['projectId'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->wikiService->getAllProjectWikis((int) $params['projectId']),
            ]);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing docs lookup parameter'], 400);
    }

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

            $wiki = new WikiModel;
            $wiki->title = $params['title'];
            $wiki->projectId = (int) $params['projectId'];
            $wiki->author = (int) session('userdata.id');

            $boardId = $this->wikiService->createWiki($wiki);

            if ($boardId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['boardId' => (int) $boardId]], 201);
        }

        if ($params['action'] === 'createItem') {
            if (! isset($params['canvasId']) || empty($params['title'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'canvasId and title are required'], 400);
            }

            $article = new Article;
            $article->title = $params['title'];
            $article->description = $params['description'] ?? '';
            $article->data = $params['data'] ?? '';
            $article->canvasId = (int) $params['canvasId'];
            $article->parent = (int) ($params['parent'] ?? 0);
            $article->tags = $params['tags'] ?? '';
            $article->status = $params['status'] ?? 'draft';
            $article->author = (int) session('userdata.id');
            $article->milestoneId = $params['milestoneId'] ?? '';

            $itemId = $this->wikiService->createArticle($article);

            if ($itemId === false) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => ['itemId' => (int) $itemId]], 201);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Unsupported action'], 400);
    }

    public function patch(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (isset($params['boardId'])) {
            if (empty($params['title'])) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'title is required'], 400);
            }

            $wiki = new WikiModel;
            $wiki->title = $params['title'];

            if (! $this->wikiService->updateWiki($wiki, (int) $params['boardId'])) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (! isset($params['id'], $params['projectId'])) {
            return $this->tpl->displayJson(['status' => 'failure', 'error' => 'id and projectId are required'], 400);
        }

        $existing = $this->wikiService->getArticle((int) $params['id'], (int) $params['projectId']);
        if ($existing === false) {
            return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Document not found'], 404);
        }

        $article = new Article;
        $article->id = (int) $params['id'];
        $article->title = $params['title'] ?? $existing->title;
        $article->description = $params['description'] ?? $existing->description;
        $article->data = $params['data'] ?? $existing->data;
        $article->parent = (int) ($params['parent'] ?? $existing->parent);
        $article->tags = $params['tags'] ?? $existing->tags;
        $article->status = $params['status'] ?? $existing->status;
        $article->milestoneId = $params['milestoneId'] ?? $existing->milestoneId;

        if (! $this->wikiService->updateArticle($article, $existing)) {
            return $this->tpl->displayJson(['status' => 'failure'], 500);
        }

        return $this->tpl->displayJson(['status' => 'ok']);
    }

    public function delete(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (isset($params['boardId'])) {
            $this->wikiRepository->delWiki((int) $params['boardId']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (isset($params['id'])) {
            $this->wikiRepository->delArticle((int) $params['id']);

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing delete target'], 400);
    }
}
