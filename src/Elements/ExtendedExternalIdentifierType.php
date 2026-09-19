<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Elements;

use Fisharebest\Webtrees\Elements\ExternalIdentifierType;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;

use function array_key_exists;
use function ksort;

/**
 * An EXID TYPE field with the registered URI choices and a custom URI mode.
 */
final class ExtendedExternalIdentifierType extends ExternalIdentifierType
{
    /** @param array<string,string> $uriLabels */
    public function __construct(string $label, private readonly array $uriLabels)
    {
        parent::__construct($label);
    }

    /** @return array<string,string> */
    public function values(): array
    {
        $values = $this->uriLabels;
        ksort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    public function edit(string $id, string $name, string $value, Tree $tree): string
    {
        $value       = $this->canonical($value);
        $values      = $this->values();
        $known       = array_key_exists($value, $values);
        $customMode  = $value !== '' && !$known;
        $selectId    = $id . '-registered';
        $customId    = $id . '-custom';
        $containerId = $id . '-exid-type';
        $options     = '<option value="">' . e(I18N::translate('Select a defined URI')) . '</option>';

        foreach ($values as $uri => $label) {
            $options .= '<option value="' . e($uri) . '"' . ($known && $uri === $value ? ' selected="selected"' : '') . '>' . e($label) . '</option>';
        }

        return '<div class="input-group" id="' . e($containerId) . '" data-exid-type-control data-exid-type-name="' . e($name) . '">' .
            '<select class="form-select' . ($customMode ? ' d-none' : '') . '" id="' . e($selectId) . '" name="' . ($customMode ? '' : e($name)) . '" data-exid-type-select>' .
            $options .
            '</select>' .
            '<input class="form-control' . ($customMode ? '' : ' d-none') . '" id="' . e($customId) . '" name="' . ($customMode ? e($name) : '') . '" value="' . ($customMode ? e($value) : '') . '" type="text" dir="ltr" autocomplete="off" data-exid-type-custom>' .
            '<button class="btn btn-secondary" type="button" data-exid-type-toggle aria-label="' . e(I18N::translate('Toggle URI selection')) . '">' . ($customMode ? '−' : '+') . '</button>' .
            '</div>';
    }
}
