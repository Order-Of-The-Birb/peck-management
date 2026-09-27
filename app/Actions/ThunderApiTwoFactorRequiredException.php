<?php

namespace App\Actions;

class ThunderApiTwoFactorRequiredException extends ThunderApiException
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public readonly array $types = [],
        public readonly ?string $requestId = null,
        public readonly ?int $userId = null,
    ) {
        parent::__construct('ThunderAPI account requires two-factor authentication.');
    }
}
