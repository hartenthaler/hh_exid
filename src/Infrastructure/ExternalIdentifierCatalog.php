<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\ExidModule\Infrastructure;

use RuntimeException;

use function array_filter;
use function array_values;
use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;
use function json_last_error;
use function preg_match;
use function rawurlencode;
use function rtrim;
use function str_replace;
use function strlen;
use function trim;

use const JSON_ERROR_NONE;

/**
 * Allow-listed metadata for external identifier authorities.
 */
final class ExternalIdentifierCatalog
{
    /** @var list<array{key:string,label:string,type_uris:list<string>,url_template:string,value_pattern:string,allowed_hosts:list<string>}> */
    private array $definitions;

    /**
     * @param list<array{key:string,label:string,type_uris:list<string>,url_template:string,value_pattern:string,allowed_hosts:list<string>}> $definitions
     */
    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public static function fromJsonFile(string $filename): self
    {
        if (!is_file($filename)) {
            throw new RuntimeException('The EXID authority catalogue does not exist.');
        }

        $json = file_get_contents($filename);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !is_array($data['authorities'] ?? null)) {
            throw new RuntimeException('The EXID authority catalogue is invalid.');
        }

        $definitions = [];

        foreach ($data['authorities'] as $definition) {
            if (!is_array($definition)
                || !is_string($definition['key'] ?? null)
                || !is_string($definition['label'] ?? null)
                || !is_array($definition['type_uris'] ?? null)
                || !is_string($definition['url_template'] ?? null)
                || !is_string($definition['value_pattern'] ?? null)
                || !is_array($definition['allowed_hosts'] ?? null)
            ) {
                continue;
            }

            $typeUris = array_values(array_filter($definition['type_uris'], 'is_string'));
            $hosts    = array_values(array_filter($definition['allowed_hosts'], 'is_string'));

            if ($typeUris === [] || $hosts === [] || !str_contains($definition['url_template'], '{value}')) {
                continue;
            }

            $definitions[] = [
                'key'           => $definition['key'],
                'label'         => $definition['label'],
                'type_uris'     => $typeUris,
                'url_template'  => $definition['url_template'],
                'value_pattern' => $definition['value_pattern'],
                'allowed_hosts' => $hosts,
            ];
        }

        return new self($definitions);
    }

    /** @return array{key:string,label:string,type_uris:list<string>,url_template:string,value_pattern:string,allowed_hosts:list<string>}|null */
    public function definition(string $typeUri): ?array
    {
        $typeUri = trim($typeUri);

        foreach ($this->definitions as $definition) {
            foreach ($definition['type_uris'] as $knownUri) {
                if ($this->sameUri($knownUri, $typeUri)) {
                    return $definition;
                }
            }
        }

        return null;
    }

    public function url(string $typeUri, string $value): ?string
    {
        $definition = $this->definition($typeUri);
        $value      = trim($value);

        if ($definition === null || $value === '' || strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return null;
        }

        if (preg_match('/\A' . $definition['value_pattern'] . '\z/u', $value) !== 1) {
            return null;
        }

        $url   = str_replace('{value}', rawurlencode($value), $definition['url_template']);
        $parts = parse_url($url);

        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || !is_string($parts['host'] ?? null)
            || !in_array(strtolower($parts['host']), array_map('strtolower', $definition['allowed_hosts']), true)
            || isset($parts['user'], $parts['pass'], $parts['port'])
        ) {
            return null;
        }

        return $url;
    }

    /** @return list<array{key:string,label:string,type_uris:list<string>,url_template:string,value_pattern:string,allowed_hosts:list<string>}> */
    public function all(): array
    {
        return $this->definitions;
    }

    private function sameUri(string $left, string $right): bool
    {
        return rtrim(trim($left), '/') === rtrim(trim($right), '/');
    }
}
