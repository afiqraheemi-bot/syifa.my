<?php

declare(strict_types=1);

namespace App\Support\Identity;

interface CurrentUserInterface
{
    public function resolve(): ?AuthenticatedIdentityInterface;
}
