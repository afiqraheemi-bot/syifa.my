<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Operations\CheckoutCommit;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

final class CheckoutCommitTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/syifa_checkout_commit_'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/.git/refs/heads', 0700, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_it_resolves_the_commit_from_a_symbolic_head(): void
    {
        $commit = str_repeat('a', 40);
        file_put_contents($this->directory.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->directory.'/.git/refs/heads/main', $commit."\n");

        self::assertSame($commit, $this->resolver()->resolve());
    }

    public function test_it_resolves_a_packed_reference(): void
    {
        $commit = str_repeat('b', 40);
        file_put_contents($this->directory.'/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($this->directory.'/.git/packed-refs', "# pack-refs\n$commit refs/heads/main\n");

        self::assertSame($commit, $this->resolver()->resolve());
    }

    public function test_it_rejects_an_unsafe_or_malformed_head(): void
    {
        file_put_contents($this->directory.'/.git/HEAD', "ref: refs/heads/../../.env\n");

        self::assertNull($this->resolver()->resolve());
    }

    private function resolver(): CheckoutCommit
    {
        return new CheckoutCommit(new Application($this->directory));
    }
}
