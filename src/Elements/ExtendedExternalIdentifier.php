<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Elements;

use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Tree;
use Hartenthaler\Webtrees\Module\ExidModule\Infrastructure\ExternalIdentifierLinker;

/**
 * Render an external identifier as a safe link whenever its value can be
 * resolved unambiguously by the EXID authority catalogue.
 */
final class ExtendedExternalIdentifier extends ExternalIdentifier
{
    public function __construct(
        string $label,
        private readonly ExternalIdentifierLinker $linker,
    ) {
        parent::__construct($label);
    }

    public function value(string $value, Tree $tree): string
    {
        return $this->linker->htmlForValue($value);
    }
}
