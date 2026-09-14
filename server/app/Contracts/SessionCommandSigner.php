<?php

namespace App\Contracts;

interface SessionCommandSigner
{
    public function sign(array $payload): string;
}
