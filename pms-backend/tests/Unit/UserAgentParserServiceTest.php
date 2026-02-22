<?php

namespace Tests\Unit;

use App\Services\UserAgentParserService;
use Tests\TestCase;

class UserAgentParserServiceTest extends TestCase
{
    public function test_parse_user_agent_returns_expected_shape(): void
    {
        $service = new UserAgentParserService();
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
            .'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $parsed = $service->parseUserAgent($userAgent, '127.0.0.1');

        $this->assertSame($userAgent, $parsed['user_agent']);
        $this->assertSame('127.0.0.1', $parsed['ip_address']);
        $this->assertArrayHasKey('device_type', $parsed);
        $this->assertArrayHasKey('browser', $parsed);
        $this->assertArrayHasKey('browser_version', $parsed);
        $this->assertArrayHasKey('platform', $parsed);
        $this->assertArrayHasKey('platform_version', $parsed);
        $this->assertNotEmpty($parsed['browser']);
        $this->assertNotEmpty($parsed['platform']);
    }
}

