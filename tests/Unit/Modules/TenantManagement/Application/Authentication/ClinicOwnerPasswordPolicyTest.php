<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\ClinicOwnerPasswordPolicy;
use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicOwnerPasswordPolicyTest extends TestCase
{
    #[DataProvider('validPasswords')]
    public function test_it_accepts_passphrases_unicode_spaces_and_no_composition(string $password): void
    {
        (new ClinicOwnerPasswordPolicy)->validate($password);
        self::addToAssertionCount(1);
    }

    /** @return iterable<string, array{string}> */
    public static function validPasswords(): iterable
    {
        yield 'long passphrase with spaces' => ['a long clinic passphrase with spaces'];
        yield 'unicode' => ['klinik selamat 🔐 医療安全'];
        yield 'lowercase only' => ['abcdefghijklmnop'];
        yield 'spaces are not trimmed' => [str_repeat(' ', 15)];
        yield 'at least 64 code points supported' => [str_repeat('é', 64)];
    }

    public function test_it_rejects_fewer_than_fifteen_unicode_code_points(): void
    {
        $this->expectException(InvalidClinicOwnerPasswordException::class);
        (new ClinicOwnerPasswordPolicy)->validate(str_repeat('é', 14));
    }
}
