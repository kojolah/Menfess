<?php

namespace App\Policies;

use App\Models\Menfess;
use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Access\Response;

class MenfessPolicy
{
    public function view(User $user, Menfess $menfess): Response
    {
        if ($menfess->user_id === $user->id || $user->hasRole(UserRole::Moderator, UserRole::SuperAdmin)) {
            return Response::allow();
        }

        if ($user->hasRole(UserRole::Publisher) && in_array($menfess->status, ['approved', 'published'], true)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function delete(User $user, Menfess $menfess): Response
    {
        return $user->hasRole(UserRole::SuperAdmin)
            ? Response::allow()
            : Response::deny('Hanya super admin yang dapat menghapus menfess.');
    }
}
