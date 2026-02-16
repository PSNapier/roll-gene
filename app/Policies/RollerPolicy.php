<?php

namespace App\Policies;

use App\Models\Roller;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RollerPolicy
{
    /**
     * Admin can do anything; policy methods check after.
     */
    private function admin(User $user): bool
    {
        return $user->is_admin === true;
    }

    private function isOwner(User $user, Roller $roller): bool
    {
        return $roller->user_id !== null && $roller->user_id === $user->id;
    }

    private function hasGroupAccess(User $user, Roller $roller, bool $requireEditor): bool
    {
        if ($roller->visibility !== 'group' || $roller->group_id === null) {
            return false;
        }
        $member = $roller->group->members()->where('user_id', $user->id)->first();

        return $member && ($requireEditor ? $member->isEditor() : $member->isViewer());
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, Roller $roller): bool|Response
    {
        if ($roller->visibility === 'public') {
            return true;
        }
        if ($user === null) {
            return false;
        }
        if ($this->admin($user)) {
            return true;
        }
        if ($this->isOwner($user, $roller)) {
            return true;
        }
        if ($this->hasGroupAccess($user, $roller, false)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Roller $roller): bool|Response
    {
        if ($this->admin($user)) {
            return true;
        }
        if ($roller->is_core) {
            return false;
        }
        if ($this->isOwner($user, $roller)) {
            return true;
        }
        if ($this->hasGroupAccess($user, $roller, true)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Roller $roller): bool|Response
    {
        if ($this->admin($user)) {
            return true;
        }
        if ($roller->is_core) {
            return false;
        }

        return $this->isOwner($user, $roller);
    }

    public function restore(User $user, Roller $roller): bool
    {
        return $this->update($user, $roller);
    }

    public function forceDelete(User $user, Roller $roller): bool
    {
        return $this->delete($user, $roller);
    }
}
