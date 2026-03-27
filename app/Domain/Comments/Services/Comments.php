<?php

namespace Leantime\Domain\Comments\Services;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Language as LanguageCore;
use Leantime\Domain\Comments\Repositories\Comments as CommentRepository;
use Leantime\Domain\Notifications\Models\Notification;
use Leantime\Domain\Projects\Services\Projects as ProjectService;

/**
 * @api
 */
class Comments
{
    private CommentRepository $commentRepository;

    private ProjectService $projectService;

    private LanguageCore $language;

    public function __construct(
        CommentRepository $commentRepository,
        ProjectService $projectService,
        LanguageCore $language
    ) {
        $this->commentRepository = $commentRepository;
        $this->projectService = $projectService;
        $this->language = $language;
    }

    /**
     * @api
     */
    public function getComments($module, $entityId, int $commentOrder = 0, int $parent = 0): false|array
    {
        return $this->commentRepository->getComments($module, $entityId, $parent, $commentOrder);
    }

    /**
     * @throws BindingResolutionException
     *
     * @api
     */
    public function addComment($values, $module, $entityId, $entity): bool
    {
        return $this->createComment($values, $module, $entityId, $entity) !== false;
    }

    /**
     * @throws BindingResolutionException
     *
     * @return array<string, mixed>|false
     *
     * @api
     */
    public function createComment($values, $module, $entityId, $entity): array|false
    {
        if (! isset($values['text']) || trim((string) $values['text']) === '' || ! isset($module) || ! isset($entityId) || ! isset($entity)) {
            return false;
        }

        $commentParent = (int) ($values['commentParent'] ?? $values['father'] ?? 0);

        $mapper = [
            'text' => (string) $values['text'],
            'date' => dtHelper()->dbNow()->formatDateTimeForDb(),
            'userId' => (session('userdata.id')),
            'moduleId' => $entityId,
            'commentParent' => $commentParent,
            'status' => $values['status'] ?? '',
        ];

        $comment = $this->commentRepository->addComment($mapper, $module);

        if (! $comment) {
            return false;
        }

        $mapper['id'] = (int) $comment;

        $currentUrl = CURRENT_URL;

        switch ($module) {
            case 'ticket':
                $subject = sprintf($this->language->__('email_notifications.new_comment_todo_with_type_subject'), $this->language->__('label.'.strtolower($entity->type)), $entity->id, strip_tags($entity->headline));
                $message = sprintf($this->language->__('email_notifications.new_comment_todo_with_type_message'), session('userdata.name'), $this->language->__('label.'.strtolower($entity->type)), strip_tags($entity->headline), strip_tags($values['text']));
                $linkLabel = $this->language->__('email_notifications.new_comment_todo_cta');
                $currentUrl = BASE_URL.'#/tickets/showTicket/'.$entity->id;
                break;
            case 'project':
                $subject = sprintf($this->language->__('email_notifications.new_comment_project_subject'), $entityId, strip_tags($entity['name']));
                $message = sprintf($this->language->__('email_notifications.new_comment_project_message'), session('userdata.name'), strip_tags($entity['name']));
                $linkLabel = $this->language->__('email_notifications.new_comment_project_cta');
                break;
            default:
                $subject = $this->language->__('email_notifications.new_comment_general_subject');
                $message = sprintf($this->language->__('email_notifications.new_comment_general_message'), session('userdata.name'));
                $linkLabel = $this->language->__('email_notifications.new_comment_general_cta');
                break;
        }

        $notification = app()->make(Notification::class);

        $urlQueryParameter = str_contains($currentUrl, '?') ? '&' : '?';
        $notification->url = [
            'url' => $currentUrl.$urlQueryParameter.'projectId='.(($entity->projectId ?? session('currentProject')) ?? -1),
            'text' => $linkLabel,
        ];

        $notification->entity = array_merge($mapper, [
            'contextModule' => $module,
            'contextId' => (int) $entityId,
            'projectId' => (int) (($entity->projectId ?? session('currentProject')) ?? -1),
            'type' => is_object($entity) ? ($entity->type ?? '') : ($entity['type'] ?? ''),
        ]);
        $notification->module = 'comments';
        $notification->action = 'commented';
        $notification->projectId = (($entity->projectId ?? session('currentProject')) ?? -1);
        $notification->subject = $subject;
        $notification->authorId = session('userdata.id');
        $notification->message = $message;

        $this->projectService->notifyProjectUsers($notification);

        return [
            'id' => (int) $mapper['id'],
            'module' => (string) $module,
            'moduleId' => (int) $entityId,
            'text' => (string) $mapper['text'],
            'status' => (string) $mapper['status'],
            'commentParent' => (int) $commentParent,
            'userId' => (int) $mapper['userId'],
            'date' => (string) $mapper['date'],
        ];
    }

    /**
     * @throws BindingResolutionException
     *
     * @api
     */
    public function editComment($values, $id): bool
    {
        return $this->commentRepository->editComment($values['text'], $id);
    }

    /**
     * @api
     */
    public function deleteComment($commentId): bool
    {

        return $this->commentRepository->deleteComment($commentId);
    }

    /**
     * @param  ?int  $projectId  Project ID
     * @param  ?int  $moduleId  Id of the entity to pull comments from
     * @return array
     *
     * @api
     */
    public function pollComments(?int $projectId = null, ?int $moduleId = null): array|false
    {

        $comments = $this->commentRepository->getAllAccountComments($projectId, $moduleId);

        foreach ($comments as $key => $comment) {
            if (dtHelper()->isValidDateString($comment['date'])) {
                $comments[$key]['date'] = dtHelper()->parseDbDateTime($comment['date'])->toIso8601ZuluString();
            } else {
                $comments[$key]['date'] = null;
            }
        }

        return $comments;
    }
}
