<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use Hartenthaler\Webtrees\Module\ExidModule\Domain\ExternalIdentifierValue;

use function count;
use function preg_match;
use function preg_split;
use function trim;

/**
 * Parse EXID blocks without inferring an authority from the identifier value.
 *
 * The input should be one GEDCOM block, for example the contents of a _LOC
 * record. Identifiers are returned in source order and may be repeated.
 */
final class ExternalIdentifierParser
{
    /** @return list<ExternalIdentifierValue> */
    public function parse(string $gedcom): array
    {
        $lines       = preg_split('/\R/u', $gedcom) ?: [];
        $identifiers = [];

        for ($index = 0, $count = count($lines); $index < $count; ++$index) {
            if (preg_match('/^(\d+)\s+(_?EXID)\s+(.+)$/u', $lines[$index], $match) !== 1) {
                continue;
            }

            $level = (int) $match[1];
            $type  = null;

            for ($child = $index + 1; $child < $count; ++$child) {
                if (preg_match('/^(\d+)\s+(.*)$/u', $lines[$child], $childMatch) !== 1) {
                    continue;
                }

                $childLevel = (int) $childMatch[1];

                if ($childLevel <= $level) {
                    break;
                }

                if ($childLevel === $level + 1 && preg_match('/^TYPE\s+(.+)$/u', $childMatch[2], $typeMatch) === 1) {
                    $type = trim($typeMatch[1]);
                    break;
                }
            }

            $identifiers[] = new ExternalIdentifierValue(trim($match[3]), $type, $match[2]);
        }

        return $identifiers;
    }
}
