<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null)
    {
        if ($guard === null && $user instanceof User) {
            $guard = $user->isAdmin() ? 'admin' : 'staff';
        }

        return parent::actingAs($user, $guard);
    }
}
