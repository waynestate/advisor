<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Security;

use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Security\EnvAllowlist;
use Waynestate\Advisor\Security\Redactor;

final class EnvAndRedactionTest extends TestCase
{
    public function testOnlyAllowedNamesSurviveForAChildProcess(): void
    {
        $env = EnvAllowlist::forProcess([
            'PATH' => '/usr/bin',
            'SystemRoot' => 'C:\\Windows',
            'APP_KEY' => 'base64:secret',
            'DB_PASSWORD' => 'hunter2',
            'AWS_SECRET_ACCESS_KEY' => 'x',
            'COMPOSER_AUTH' => '{"github-oauth":{"github.com":"tok"}}',
        ]);

        $this->assertSame('/usr/bin', $env['PATH']);
        $this->assertSame('C:\\Windows', $env['SystemRoot']);
        foreach (['APP_KEY', 'DB_PASSWORD', 'AWS_SECRET_ACCESS_KEY', 'COMPOSER_AUTH'] as $secret) {
            $this->assertFalse($env[$secret], $secret . ' must be removed');
        }
        $this->assertArrayNotHasKey('APP_KEY', EnvAllowlist::allowedOnly(['APP_KEY' => 'x', 'PATH' => 'p']));
    }

    public function testRedactsCredentialShapes(): void
    {
        $text = "DB_PASSWORD=hunter22\napi_key: \"abcd1234efgh\"\nAuthorization: Bearer abcdef0123456789\n"
            . "token ghp_abcdefghijklmnopqrstuvwxyz0123456789\nAKIAABCDEFGHIJKLMNOP\nplain words stay";

        $out = Redactor::redact($text);

        foreach (['hunter22', 'abcd1234efgh', 'abcdef0123456789', 'ghp_abcdef', 'AKIAABCDEFGHIJKLMNOP'] as $secret) {
            $this->assertStringNotContainsString($secret, $out);
        }
        $this->assertStringContainsString('plain words stay', $out);
        $this->assertStringContainsString(Redactor::MARK, $out);
    }

    public function testRedactsPrivateKeyBlocks(): void
    {
        $out = Redactor::redact("before\n-----BEGIN RSA PRIVATE KEY-----\nMIIBOgIBAAJB\n-----END RSA PRIVATE KEY-----\nafter");

        $this->assertStringNotContainsString('MIIBOgIBAAJB', $out);
        $this->assertStringContainsString('after', $out);
    }
}
