<?php

namespace App\Services;

use App\Contracts\SessionCommandSigner;

class PendingSessionCommandSigner implements SessionCommandSigner
{
    public function sign(array $payload): string
    {
        return '';
    }
}
