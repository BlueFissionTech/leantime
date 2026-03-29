<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Files\Services\Files as FileService;
use Leantime\Domain\Users\Services\Users as UserService;
use Symfony\Component\HttpFoundation\Response;

class Files extends Controller
{
    private UserService $userService;

    private FileService $fileService;

    /**
     * init - initialize private variables
     */
    public function init(FileService $fileService, UserService $userService): void
    {
        $this->userService = $userService;
        $this->fileService = $fileService;
    }

    /**
     * get - handle get requests
     */
    public function get(array $params): Response
    {
        if (isset($params['id'])) {
            $file = $this->fileService->getFile((int) $params['id']);

            if ($file === false) {
                return $this->tpl->displayJson(['status' => 'failure', 'error' => 'File not found'], 404);
            }

            return $this->tpl->displayJson(['status' => 'ok', 'result' => $file]);
        }

        if (isset($params['module'])) {
            return $this->tpl->displayJson([
                'status' => 'ok',
                'result' => $this->fileService->getFilesByModule(
                    (string) $params['module'],
                    isset($params['moduleId']) ? (int) $params['moduleId'] : null,
                    isset($params['userId']) ? (int) $params['userId'] : null
                ),
            ]);
        }

        return $this->tpl->displayJson(['status' => 'failure', 'error' => 'Missing file lookup parameter'], 400);
    }

    /**
     * post - handle post requests
     *
     *
     *
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        // FileUpload
        $module = $params['module'] ?? $_GET['module'] ?? null;
        $id = isset($params['moduleId']) ? (int) $params['moduleId'] : (isset($_GET['moduleId']) ? (int) $_GET['moduleId'] : null);

        if (isset($_FILES['file']) && $module !== null && $id !== null) {
            $module = htmlentities((string) $module);

            $result = $this->fileService->upload($_FILES, $module, $id);
            if (is_string($result)) {
                return $this->tpl->displayJson(['status' => 'error', 'message' => $result], 500);
            } else {
                return $this->tpl->displayJson($result);
            }
        }

        if (isset($_FILES['file'])) {
            // For image paste uploads
            $_FILES['file']['name'] = 'pastedImage.png';
            $file = $this->fileService->upload($_FILES, 'project', session('currentProject'));

            if (is_array($file)) {
                return new Response(BASE_URL.'/files/get?'
                    .http_build_query([
                        'encName' => $file['encName'],
                        'ext' => $file['extension'],
                        'realName' => $file['realName'],
                    ]));
            }

            if (is_string($file)) {
                // If the result is a string, it's an error message
                $this->tpl->displayJson(['status' => 'error', 'message' => $file], 500);
            }
        }

        return $this->tpl->displayJson(['status' => 'Something unexpected'], 500);
    }

    /**
     * put - handle put requests
     */
    public function patch(array $params): Response
    {
        if (isset($params['id'])) {
            if (! AuthService::userIsAtLeast(Roles::$editor)) {
                return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
            }

            if (! $this->fileService->updateFile((int) $params['id'], $params)) {
                return $this->tpl->displayJson(['status' => 'failure'], 500);
            }

            return $this->tpl->displayJson(['status' => 'ok']);
        }

        if (isset($params['patchModalSettings']) && $this->userService->updateUserSettings('modals', $params['settings'], 1)) {
            return $this->tpl->displayJson(['status' => 'ok']);
        }

        return $this->tpl->displayJson(['status' => 'failure'], 500);
    }

    /**
     * delete - handle delete requests
     */
    public function delete(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (! isset($params['id']) || ! $this->fileService->deleteFile((int) $params['id'])) {
            return $this->tpl->displayJson(['status' => 'failure'], 500);
        }

        return $this->tpl->displayJson(['status' => 'ok']);
    }
}
