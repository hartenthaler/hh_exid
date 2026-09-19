<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Domain;

/**
 * An EXID value together with its optional authority URI.
 */
final readonly class ExternalIdentifierValue
{
    public function __construct(
        public string $value,
        public ?string $typeUri,
        public string $tag,
    ) {
    }
}
