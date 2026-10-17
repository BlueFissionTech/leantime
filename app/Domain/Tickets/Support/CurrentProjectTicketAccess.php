<?php

namespace Leantime\Domain\Tickets\Support;

use Closure;
use Illuminate\Database\Connection;
use Leantime\Domain\Auth\Models\Roles;

/** Current database policy for the conditional store; not an authentication provider. */
final class CurrentProjectTicketAccess
{
    public function __construct(private ?Closure $roles = null) {}

    public function __invoke(Connection $db, int $actorId, int $projectId, int $ticketId, string $action): bool
    {
        // The caller must own the transaction and serialize policy changes with writes.
        if ($db->transactionLevel() < 1 || ! $db->getPdo()->inTransaction()
            || min($actorId, $projectId, $ticketId) < 1 || ! in_array($action, ['read', 'write'], true)) {
            return false;
        }

        $roles = $this->roles === null ? Roles::getRoles() : ($this->roles)();
        if (! is_array($roles) || count(array_filter($roles, 'is_string')) !== count($roles)
            || count(array_unique($roles)) !== count($roles)) {
            return false;
        }
        foreach ($roles as $key => $name) {
            if (! is_int($key) || $key < 1 || $name === '') {
                return false;
            }
        }

        $user = $db->table('zp_user')->select(['id', 'status', 'role', 'clientId'])->where('id', $actorId)->first();
        $project = $db->table('zp_projects')->select(['id', 'psettings', 'clientId'])->where('id', $projectId)->first();
        if ($user === null || $user->status !== 'a' || $project === null
            || ! $db->table('zp_tickets')->where('id', $ticketId)->where('projectId', $projectId)->exists()) {
            return false;
        }
        $globalKey = $this->roleKey($user->role, $roles);
        if ($globalKey === null) {
            return false;
        }

        $memberships = $db->table('zp_relationuserproject')->select('projectRole')
            ->where('userId', $actorId)->where('projectId', $projectId)->limit(2)->get();
        // An ambiguous membership must not choose an arbitrary privilege level.
        if ($memberships->count() > 1) {
            return false;
        }
        $globalRole = $roles[$globalKey];
        $globalAccess = in_array($globalRole, [Roles::$admin, Roles::$owner], true);
        $clientAccess = $project->psettings === 'clients' && (int) $user->clientId > 0
            && (string) $user->clientId === (string) $project->clientId;
        if (! $globalAccess && $project->psettings !== 'all' && ! $clientAccess && $memberships->isEmpty()) {
            return false;
        }

        $effectiveKey = $globalKey;
        $projectRole = $memberships->first()?->projectRole;
        // Match Auth::getRoleToCheck: manager/admin/owner retain their global role.
        if (! in_array($globalRole, [Roles::$manager, Roles::$admin, Roles::$owner], true)
            && $projectRole !== null && $projectRole !== '' && $projectRole !== 'inherited') {
            $effectiveKey = $this->roleKey($projectRole, $roles);
            if ($effectiveKey === null) {
                return false;
            }
        }
        $threshold = array_search($action === 'write' ? Roles::$editor : Roles::$readonly, $roles, true);

        return $threshold !== false && $effectiveKey >= $threshold;
    }

    private function roleKey(mixed $value, array $roles): ?int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/\A[1-9][0-9]*\z/', (string) $value)) {
            return null;
        }
        $key = (int) $value;

        return (string) $key === (string) $value && isset($roles[$key]) ? $key : null;
    }
}
