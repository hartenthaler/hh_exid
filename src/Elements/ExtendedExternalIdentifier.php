<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Elements;

use Fisharebest\Webtrees\Elements\ExternalIdentifier;
use Fisharebest\Webtrees\Tree;

use function htmlspecialchars;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/** Mark an EXID value for URI-aware client-side rendering. */
final class ExtendedExternalIdentifier extends ExternalIdentifier
{
    public function __construct(string $label)
    {
        parent::__construct($label);
    }

    public function value(string $value, Tree $tree): string
    {
        return '<span data-exid-value>' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }

    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $original = parent::edit($id, $name, $value, $tree);
        $html     = str_replace('<input ', '<input data-exid-value-input ', $original, $count);

        return $count > 0 ? $html : $original;
    }
}
