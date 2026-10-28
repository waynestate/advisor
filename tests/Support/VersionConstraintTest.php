<?php

declare(strict_types=1);

namespace Waynestate\Advisor\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Waynestate\Advisor\Support\VersionConstraint;

/** The self-contained constraint checker the doctor uses for the PHP floor. */
final class VersionConstraintTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function constraints(): array
    {
        return [
            'star matches anything' => ['*', '8.4.1', true],
            'any matches anything' => ['any', '5.6.0', true],
            'caret satisfied' => ['^8.3', '8.4.1', true],
            'caret at the floor' => ['^8.3', '8.3.0', true],
            'caret below the floor' => ['^8.3', '8.2.99', false],
            'caret excludes the next major' => ['^8.3', '9.0.0', false],
            'caret zero major allows the minor' => ['^0.3', '0.3.9', true],
            'caret zero major excludes the next minor' => ['^0.3', '0.4.0', false],
            'caret zero zero pins the patch' => ['^0.0.3', '0.0.3', true],
            'caret zero zero excludes the next patch' => ['^0.0.3', '0.0.4', false],
            'tilde allows the patch' => ['~8.3', '8.3.9', true],
            'tilde excludes the next minor' => ['~8.3', '8.4.0', false],
            'tilde below the floor' => ['~8.3', '8.2.0', false],
            'tilde three parts allows the patch' => ['~8.3.1', '8.3.9', true],
            'tilde three parts excludes the next minor' => ['~8.3.1', '8.4.0', false],
            'tilde three parts excludes an earlier patch' => ['~8.3.1', '8.3.0', false],
            'tilde one part is a major' => ['~8', '8.9.0', true],
            'tilde one part excludes the next major' => ['~8', '9.0.0', false],
            'greater-or-equal at the floor' => ['>=8.3', '8.3.0', true],
            'greater-or-equal above' => ['>=8.3', '8.4.1', true],
            'greater-or-equal below' => ['>=8.3', '8.2.99', false],
            'greater excludes the floor' => ['>8.3', '8.3.0', false],
            'greater above the floor' => ['>8.3', '8.3.1', true],
            'less-or-equal at the ceiling' => ['<=8.3', '8.3.0', true],
            'less-or-equal below' => ['<=8.3', '8.2.0', true],
            'less-or-equal above' => ['<=8.3', '8.4.0', false],
            'less below the floor' => ['<8.3', '8.2.99', true],
            'less excludes the floor' => ['<8.3', '8.3.0', false],
            'equals the exact version' => ['=8.3', '8.3.0', true],
            'equals rejects a patch' => ['=8.3', '8.3.1', false],
            'double equals the exact version' => ['==8.3', '8.3.0', true],
            'bare version is exact' => ['8.3', '8.3.0', true],
            'bare version rejects a patch' => ['8.3', '8.3.1', false],
            'wildcard matches the patch' => ['8.3.*', '8.3.9', true],
            'wildcard rejects the next minor' => ['8.3.*', '8.4.0', false],
            'and both hold' => ['>=8.3 <9.0', '8.4.1', true],
            'and one fails' => ['>=8.3 <9.0', '9.0.0', false],
            'or first holds' => ['^8.3 || ^9.0', '8.4.1', true],
            'or second holds' => ['^8.3 || ^9.0', '9.1.0', true],
            'or neither holds' => ['^8.3 || ^9.0', '7.0.0', false],
            'v prefix is ignored' => ['^8.3', 'v8.4.1', true],
            'prerelease suffix is ignored' => ['^8.3', '8.4.1-beta', true],
        ];
    }

    #[DataProvider('constraints')]
    public function testSatisfies(string $constraint, string $version, bool $expected): void
    {
        $this->assertSame($expected, VersionConstraint::satisfies($constraint, $version));
    }
}
