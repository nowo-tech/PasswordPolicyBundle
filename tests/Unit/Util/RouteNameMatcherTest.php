<?php

declare(strict_types=1);

namespace Nowo\PasswordPolicyBundle\Tests\Unit\Util;

use Nowo\PasswordPolicyBundle\Util\RouteNameMatcher;
use Nowo\PasswordPolicyBundle\Tests\UnitTestCase;

final class RouteNameMatcherTest extends UnitTestCase
{
    public function testLiteralMatch(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('app_profile', 'app_profile'));
        $this->assertFalse(RouteNameMatcher::matches('app_profile', 'app_profile_edit'));
    }

    public function testGlobWildcard(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('admin_*', 'admin_dashboard'));
        $this->assertFalse(RouteNameMatcher::matches('admin_*', 'user_dashboard'));
    }

    public function testGlobQuestionMark(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('route_?', 'route_1'));
        $this->assertFalse(RouteNameMatcher::matches('route_?', 'route_10'));
    }

    public function testPcreDelimited(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('~^app_operator\.~', 'app_operator.dashboard'));
        $this->assertFalse(RouteNameMatcher::matches('~^app_operator\.~', 'app_admin.dashboard'));
    }

    public function testPcreHashDelimiter(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('#^reset_#', 'reset_password'));
    }

    public function testEmptyPatternNeverMatches(): void
    {
        $this->assertFalse(RouteNameMatcher::matches('', 'any'));
    }

    public function testWhitespaceTrimmed(): void
    {
        $this->assertTrue(RouteNameMatcher::matches('  app_profile  ', 'app_profile'));
    }
}
