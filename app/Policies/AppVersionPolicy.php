<?php

namespace App\Policies;

use App\Models\AppVersion;
use App\Models\User;

class AppVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('app_versions.viewAny');
    }

    public function view(User $user, AppVersion $appVersion): bool
    {
        return $user->can('app_versions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('app_versions.create');
    }

    public function update(User $user, AppVersion $appVersion): bool
    {
        return $user->can('app_versions.update');
    }

    public function delete(User $user, AppVersion $appVersion): bool
    {
        return $user->can('app_versions.delete');
    }
}
