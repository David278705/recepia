<?php

namespace App\Services\Meta;

use RuntimeException;

class OnboardingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $metaErrorCode = null,
        public readonly ?string $metaMessage = null,
        public readonly bool $requiresConfirmation = false,
    ) {
        parent::__construct($message);
    }
}
