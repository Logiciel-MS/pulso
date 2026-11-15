<?php

declare(strict_types=1);

namespace Pulso\Tests;

use Pulso\UserAgent;

require_once __DIR__ . '/Fixtures.php';

final class UserAgentTest extends TestCase
{
    public function testParsesCommonBrowsers(): void
    {
        $cases = [
            [Fixtures::ANDROID_CHROME, 'mobile', 'Chrome', 'Android'],
            [Fixtures::IPHONE_INSTAGRAM, 'mobile', 'Instagram (app)', 'iOS'],
            [Fixtures::WINDOWS_CHROME, 'desktop', 'Chrome', 'Windows'],
            [Fixtures::WINDOWS_EDGE, 'desktop', 'Edge', 'Windows'],
            [Fixtures::IPAD_SAFARI, 'tablet', 'Safari', 'iOS'],
            [Fixtures::MAC_SAFARI, 'desktop', 'Safari', 'macOS'],
            [Fixtures::SAMSUNG, 'mobile', 'Samsung Internet', 'Android'],
            [Fixtures::FACEBOOK_APP, 'mobile', 'Facebook (app)', 'Android'],
            [Fixtures::FIREFOX_LINUX, 'desktop', 'Firefox', 'Linux'],
        ];
        foreach ($cases as [$ua, $device, $browser, $os]) {
            $this->assertSame(['device' => $device, 'browser' => $browser, 'os' => $os], UserAgent::parse($ua), $ua);
        }
    }

    public function testUnknownIsOtro(): void
    {
        $this->assertSame(['device' => 'desktop', 'browser' => 'Otro', 'os' => 'Otro'], UserAgent::parse('SomethingNew/1.0'));
    }

    public function testDetectsBots(): void
    {
        foreach ([Fixtures::GOOGLEBOT, Fixtures::HEADLESS, Fixtures::WHATSAPP_PREVIEW, 'curl/8.4.0', '', 'python-requests/2.31'] as $ua) {
            $this->assertTrue(UserAgent::isBot($ua), "bot: {$ua}");
        }
        foreach ([Fixtures::ANDROID_CHROME, Fixtures::IPHONE_INSTAGRAM, Fixtures::MAC_SAFARI] as $ua) {
            $this->assertFalse(UserAgent::isBot($ua), "human: {$ua}");
        }
    }
}
