<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/user-scope.php';

class UserScopeTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testGetCurrentUserIdReturnsNullWhenSessionIsEmpty(): void
    {
        $this->assertNull(getCurrentUserId());
    }

    public function testGetCurrentUserIdReturnsIntFromSession(): void
    {
        $_SESSION['user'] = ['id' => '12345'];

        $this->assertSame(12345, getCurrentUserId());
    }

    public function testAppendUserIdParamWithNullUserId(): void
    {
        $url = appendUserIdParam('https://api.example.com/v1/notifications/', null);

        $this->assertSame('https://api.example.com/v1/notifications/?', $url);
    }

    public function testAppendUserIdParamIncludesUserId(): void
    {
        $url = appendUserIdParam('https://api.example.com/v1/notifications/', 42);

        $this->assertSame('https://api.example.com/v1/notifications/?userId=42', $url);
    }
}
