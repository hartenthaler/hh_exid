<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

/**
 * Contexts in which an EXID TYPE can be entered.
 *
 * An empty context list (or the wildcard) means that an authority is not
 * classified and is therefore offered everywhere.  This is intentional for
 * administrator-defined authorities and for future registry entries that have
 * not yet been classified.
 */
final class ExidContextCatalog
{
    public const ALL = '*';
    public const PLAC = 'PLAC';
    public const SHARED_NOTE = 'SNOTE';

    /** @return list<string> */
    public static function registryContexts(string $sourceFile): array
    {
        return match ($sourceFile) {
            'AFN.yaml', 'RFN.yaml', 'RIN.yaml' => ['INDI'],
            'BillionGraves-CemeteryId.yaml', 'FindAGrave-CemeteryId.yaml' => [self::PLAC, '_LOC'],
            'BillionGraves-GraveId.yaml', 'FindAGrave-MemorialId.yaml' => ['INDI', 'OBJE'],
            'FamilySearch-MemoryId.yaml' => ['OBJE'],
            'FamilySearch-PersonId.yaml', 'WikiTree-PersonId.yaml' => ['INDI'],
            'FamilySearch-PlaceId.yaml', 'GOV-ID.yaml', 'GeoNames.yaml' => [self::PLAC, '_LOC'],
            'FamilySearch-SourceDescriptionId.yaml' => ['SOUR'],
            'FamilySearch-UserId.yaml' => ['SUBM'],
            default => [self::ALL],
        };
    }

    /** @param mixed $contexts @return list<string> */
    public static function normalize(mixed $contexts): array
    {
        if (!is_array($contexts)) {
            return [self::ALL];
        }

        $contexts = array_values(array_filter($contexts, static fn (mixed $context): bool => is_string($context) && trim($context) !== ''));

        if ($contexts === []) {
            return [self::ALL];
        }

        $normalized = array_values(array_unique(array_map(
            static function (string $context): string {
                $context = strtoupper(trim($context));

                // Migrate the provisional name used by the first Issue-8
                // implementation without invalidating saved administrator data.
                return $context === 'PLACE' ? self::PLAC : $context;
            },
            $contexts,
        )));

        if (!in_array(self::ALL, $normalized, true) && !in_array(self::SHARED_NOTE, $normalized, true)) {
            $normalized[] = self::SHARED_NOTE;
        }

        return $normalized;
    }

    /** @param array{contexts?:mixed} $definition */
    public static function applies(array $definition, string $context): bool
    {
        $contexts = self::normalize($definition['contexts'] ?? null);

        return in_array(self::ALL, $contexts, true) || in_array($context, $contexts, true);
    }
}
