<?php

namespace Tests\Unit\Staff;

use App\Http\Middleware\EnsureStaffRole;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StaffRoleAuthorizationTest extends TestCase
{
    public function test_admin_can_access_all_operational_roles(): void
    {
        $user = $this->staffUser(User::ROLE_ADMIN);

        foreach ([
            User::ROLE_DESIGNER,
            User::ROLE_PRINTING,
            User::ROLE_PACKING,
        ] as $requiredRole) {
            $response = $this->runMiddleware($user, $requiredRole);

            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_designer_can_access_design_role_only(): void
    {
        $user = $this->staffUser(User::ROLE_DESIGNER);

        $this->assertAllowed($user, User::ROLE_DESIGNER);
        $this->assertForbidden($user, User::ROLE_PRINTING);
        $this->assertForbidden($user, User::ROLE_PACKING);
    }

    public function test_operation_management_is_a_valid_staff_role(): void
    {
        $user = $this->staffUser(User::ROLE_OM);

        $this->assertTrue($user->isActiveStaff());
        $this->assertAllowed($user, User::ROLE_OM);
        $this->assertAllowed(
            $user,
            User::ROLE_PACKING,
            User::ROLE_OM
        );
        $this->assertForbidden($user, User::ROLE_DESIGNER);
    }

    public function test_printing_can_access_printing_role_only(): void
    {
        $user = $this->staffUser(User::ROLE_PRINTING);

        $this->assertForbidden($user, User::ROLE_DESIGNER);
        $this->assertAllowed($user, User::ROLE_PRINTING);
        $this->assertForbidden($user, User::ROLE_PACKING);
    }

    public function test_packing_can_access_packing_role_only(): void
    {
        $user = $this->staffUser(User::ROLE_PACKING);

        $this->assertForbidden($user, User::ROLE_DESIGNER);
        $this->assertForbidden($user, User::ROLE_PRINTING);
        $this->assertAllowed($user, User::ROLE_PACKING);
    }

    public function test_inactive_staff_is_forbidden(): void
    {
        $user = $this->staffUser(
            User::ROLE_DESIGNER,
            false
        );

        $this->assertForbidden($user, User::ROLE_DESIGNER);
    }

    public function test_user_without_role_is_forbidden(): void
    {
        $user = $this->staffUser(null);

        $this->assertForbidden($user, User::ROLE_DESIGNER);
    }

    public function test_user_with_unknown_role_is_forbidden(): void
    {
        $user = $this->staffUser('UNKNOWN_ROLE');

        $this->assertForbidden($user, User::ROLE_DESIGNER);
    }

    private function staffUser(
        ?string $role,
        bool $isActive = true
    ): User {
        return User::factory()->make([
            'role' => $role,
            'is_active' => $isActive,
        ]);
    }

    private function runMiddleware(
        User $user,
        string ...$roles
    ): Response {
        $request = Request::create('/staff/test', 'GET');

        $request->setUserResolver(
            fn () => $user
        );

        return (new EnsureStaffRole())->handle(
            $request,
            fn () => new Response('OK', 200),
            ...$roles
        );
    }

    private function assertAllowed(
        User $user,
        string ...$roles
    ): void {
        $response = $this->runMiddleware($user, ...$roles);

        $this->assertSame(200, $response->getStatusCode());
    }

    private function assertForbidden(
        User $user,
        string ...$roles
    ): void {
        try {
            $this->runMiddleware($user, ...$roles);

            $this->fail(
                'Expected staff role authorization to return HTTP 403.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
