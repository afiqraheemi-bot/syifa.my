<?php

declare(strict_types=1);

namespace App\Http\Operations;

use Illuminate\Foundation\Application;

final readonly class CheckoutCommit
{
    public function __construct(
        private Application $application,
    ) {}

    public function resolve(): ?string
    {
        $gitDirectory = $this->gitDirectory($this->application->basePath('.git'));

        if ($gitDirectory === null) {
            return null;
        }

        $head = $this->read($gitDirectory.'/HEAD');

        if ($head === null) {
            return null;
        }

        if ($this->isCommit($head)) {
            return $head;
        }

        if (! str_starts_with($head, 'ref: ')) {
            return null;
        }

        $reference = substr($head, 5);

        if (! preg_match('#^refs/[A-Za-z0-9._/-]+$#', $reference) || str_contains($reference, '..')) {
            return null;
        }

        $commit = $this->read($gitDirectory.'/'.$reference);

        if ($commit !== null && $this->isCommit($commit)) {
            return $commit;
        }

        return $this->packedReference($gitDirectory.'/packed-refs', $reference);
    }

    private function gitDirectory(string $gitPath): ?string
    {
        if (is_dir($gitPath)) {
            return realpath($gitPath) ?: null;
        }

        $pointer = $this->read($gitPath);

        if ($pointer === null || ! str_starts_with($pointer, 'gitdir: ')) {
            return null;
        }

        $directory = substr($pointer, 8);

        if (! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = dirname($gitPath).DIRECTORY_SEPARATOR.$directory;
        }

        return realpath($directory) ?: null;
    }

    private function packedReference(string $file, string $reference): ?string
    {
        $contents = $this->read($file);

        if ($contents === null) {
            return null;
        }

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }

            [$commit, $name] = array_pad(explode(' ', $line, 2), 2, null);

            if ($name === $reference && is_string($commit) && $this->isCommit($commit)) {
                return $commit;
            }
        }

        return null;
    }

    private function read(string $file): ?string
    {
        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if (! is_string($contents)) {
            return null;
        }

        $contents = trim($contents);

        return $contents !== '' ? $contents : null;
    }

    private function isCommit(string $value): bool
    {
        return preg_match('/^[a-f0-9]{40}$/', $value) === 1;
    }
}
