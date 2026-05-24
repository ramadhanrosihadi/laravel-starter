<?php

namespace App\Policies;

use App\Models\AppConfig;
use App\Models\User;

class AppConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('app_configs.viewAny');
    }

    public function view(User $user, AppConfig $appConfig): bool
    {
        return $user->can('app_configs.view');
    }

    public function create(User $user): bool
    {
        return $user->can('app_configs.create');
    }

    public function update(User $user, AppConfig $appConfig): bool
    {
        return $user->can('app_configs.update');
    }

    public function delete(User $user, AppConfig $appConfig): bool
    {
        return $user->can('app_configs.delete');
    }
}
