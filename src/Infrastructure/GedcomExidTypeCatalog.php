<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use RuntimeException;

use function array_values;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function json_last_error;
use function trim;

use const JSON_ERROR_NONE;

/**
 * The registered EXID.TYPE definitions from the FamilySearch GEDCOM registry.
 */
final class GedcomExidTypeCatalog
{
    /** @var array<string,array{source_file:string,label:string,language:string,uri:string,documentation:list<string>}> */
    private array $types;

    /**
     * @param array<string,array{source_file:string,label:string,language:string,uri:string,documentation:list<string>}> $types
     */
    private function __construct(array $types)
    {
        $this->types = $types;
    }

    public static function fromJsonFile(string $filename): self
    {
        if (!is_file($filename)) {
            throw new RuntimeException('The GEDCOM EXID type catalogue does not exist.');
        }

        $json = file_get_contents($filename);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !is_array($data['types'] ?? null)) {
            throw new RuntimeException('The GEDCOM EXID type catalogue is invalid.');
        }

        $types = [];

        foreach ($data['types'] as $type) {
            if (!is_array($type)
                || !is_string($type['source_file'] ?? null)
                || !is_string($type['label'] ?? null)
                || !is_string($type['language'] ?? null)
                || !is_string($type['uri'] ?? null)
                || !is_array($type['documentation'] ?? null)
                || isset($types[$type['uri']])
            ) {
                continue;
            }

            $types[$type['uri']] = [
                'source_file'   => $type['source_file'],
                'label'         => $type['label'],
                'language'       => $type['language'],
                'uri'            => $type['uri'],
                'documentation' => array_values(array_filter($type['documentation'], 'is_string')),
            ];
        }

        return new self($types);
    }

    /** @return array{source_file:string,label:string,language:string,uri:string,documentation:list<string>}|null */
    public function find(string $uri): ?array
    {
        return $this->types[trim($uri)] ?? null;
    }

    /** @return list<array{source_file:string,label:string,language:string,uri:string,documentation:list<string>}> */
    public function all(): array
    {
        return array_values($this->types);
    }
}
