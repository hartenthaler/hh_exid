<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use Hartenthaler\Webtrees\Module\ExidModule\Domain\ExternalIdentifierValue;

use function htmlspecialchars;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Render known EXID authorities as safe links and unknown values as text.
 */
final class ExternalIdentifierLinker
{
    public function __construct(private ExternalIdentifierCatalog $catalog)
    {
    }

    public function url(ExternalIdentifierValue $identifier): ?string
    {
        return $identifier->typeUri === null
            ? null
            : $this->catalog->url($identifier->typeUri, $identifier->value);
    }

    public function label(ExternalIdentifierValue $identifier): ?string
    {
        return $identifier->typeUri === null
            ? null
            : ($this->catalog->definition($identifier->typeUri)['label'] ?? null);
    }

    public function html(ExternalIdentifierValue $identifier): string
    {
        $value = htmlspecialchars($identifier->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url   = $this->url($identifier);

        if ($url === null) {
            return $value;
        }

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $value . '</a>';
    }

}
