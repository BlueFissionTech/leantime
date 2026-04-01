<?php

namespace Leantime\Domain\Supportportal\Services;

use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Users\Repositories\Users as UserRepository;

class PortalAccess
{
    public function __construct(
        private UserRepository $userRepository,
        private ProjectRepository $projectRepository,
        private ProjectService $projectService,
        private AuthService $authService,
    ) {}

    public function ensurePortalSession(array $portal): bool
    {
        if (empty(session('userdata.id')) || empty($portal['projectId'])) {
            return false;
        }

        return $this->projectService->changeCurrentSessionProject((int) $portal['projectId']);
    }

    public function registerAndLogin(array $portal, array $payload): array
    {
        $email = trim(strtolower($payload['email'] ?? ''));
        $password = $payload['password'] ?? '';
        $firstName = trim($payload['firstName'] ?? '');
        $lastName = trim($payload['lastName'] ?? '');

        if ($email === '' || $password === '' || $firstName === '') {
            return ['ok' => false, 'message' => 'Please provide your name, email, and password.'];
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Please provide a valid email address.'];
        }

        $existingUser = $this->userRepository->getUserByEmail($email);

        if ($existingUser !== false) {
            $userId = (int) $existingUser['id'];

            if ((int) ($existingUser['clientId'] ?? 0) > 0 && (int) $existingUser['clientId'] !== (int) $portal['clientId']) {
                return ['ok' => false, 'message' => 'That email is already attached to a different client account.'];
            }

            if ((int) ($existingUser['clientId'] ?? 0) === 0 && (int) $portal['clientId'] > 0) {
                $this->userRepository->patchUser($userId, ['clientId' => (int) $portal['clientId']]);
            }

            if (! $this->projectRepository->isUserMemberOfProject($userId, (int) $portal['projectId'])) {
                $this->projectRepository->addProjectRelation($userId, (int) $portal['projectId'], 10);
            }
        } else {
            $userId = $this->userRepository->addUser([
                'firstname' => $firstName,
                'lastname' => $lastName,
                'user' => $email,
                'password' => $password,
                'status' => 'a',
                'role' => 10,
                'clientId' => $portal['clientId'] ?? null,
                'source' => 'supportportal',
            ]);

            if ($userId === false) {
                return ['ok' => false, 'message' => 'We could not create your support account.'];
            }

            $this->projectRepository->addProjectRelation((int) $userId, (int) $portal['projectId'], 10);
        }

        if (! $this->authService->login($email, $password)) {
            return ['ok' => false, 'message' => 'Your account was found, but the password did not match.'];
        }

        $this->ensurePortalSession($portal);

        return ['ok' => true];
    }

    public function loginAndScope(array $portal, string $email, string $password): array
    {
        $email = trim(strtolower($email));

        if ($email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Email and password are required.'];
        }

        $user = $this->userRepository->getUserByEmail($email);
        if ($user === false) {
            return ['ok' => false, 'message' => 'We could not find that support account.'];
        }

        if (! $this->projectRepository->isUserAssignedToProject((int) $user['id'], (int) $portal['projectId'])) {
            return ['ok' => false, 'message' => 'This account does not have access to this support portal.'];
        }

        if (! $this->authService->login($email, $password)) {
            return ['ok' => false, 'message' => 'Email or password was incorrect.'];
        }

        $this->ensurePortalSession($portal);

        return ['ok' => true];
    }
}
