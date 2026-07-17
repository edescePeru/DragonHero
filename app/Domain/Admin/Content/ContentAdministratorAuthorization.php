<?php

namespace App\Domain\Admin\Content;

use App\Models\User;

final class ContentAdministratorAuthorization
{
    public function allows(User $user = null)
    {
        if (!$user) {
            return false;
        }

        $allowedEmails = array_map(function ($email) {
            return strtolower(trim((string) $email));
        }, config('game_admin.emails', []));

        return in_array(strtolower(trim((string) $user->email)), $allowedEmails, true);
    }
}
