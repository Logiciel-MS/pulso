<?php

declare(strict_types=1);

namespace Pulso\Tests;

use Pulso\Referrer;

final class ReferrerTest extends TestCase
{
    public function testGroupsHostsIntoChannels(): void
    {
        $cases = [
            'https://www.google.com/search?q=x' => 'Google',
            'https://www.google.com.co/' => 'Google',
            'https://l.instagram.com/?u=x' => 'Instagram',
            'https://lm.facebook.com/l.php' => 'Facebook',
            'https://m.facebook.com/' => 'Facebook',
            'https://t.co/abc' => 'X (Twitter)',
            'https://www.linkedin.com/feed' => 'LinkedIn',
            'https://www.tiktok.com/@x' => 'TikTok',
            'https://chatgpt.com/' => 'ChatGPT',
            'https://web.whatsapp.com/' => 'WhatsApp',
            'https://blog.otrositio.com/post' => 'blog.otrositio.com',
        ];
        foreach ($cases as $url => $source) {
            $this->assertSame($source, Referrer::source(Referrer::host($url), null), $url);
        }
    }

    public function testUtmSourceWins(): void
    {
        $this->assertSame('Instagram', Referrer::source('google.com', 'ig'));
        $this->assertSame('Facebook', Referrer::source(null, 'FB'));
        $this->assertSame('volante-feria', Referrer::source(null, 'volante-feria'));
    }

    public function testNoReferrerIsDirecto(): void
    {
        $this->assertSame('Directo', Referrer::source(null, null));
        $this->assertSame(null, Referrer::host(''));
        $this->assertSame(null, Referrer::host('not a url'));
    }
}
