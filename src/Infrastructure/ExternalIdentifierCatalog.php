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
use function json_encode;
use function json_last_error;
use function preg_match;
use function rawurlencode;
use function parse_url;
use function rtrim;
use function strlen;
use function trim;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Allow-listed metadata for external identifier authorities.
 */
final class ExternalIdentifierCatalog
{
    /** @var list<array{key:string,label:string,type_uris:list<string>,value_pattern:string,allowed_hosts:list<string>}> */
    private array $definitions;

    private int $defaultsVersion;

    /**
     * @param list<array{key:string,label:string,type_uris:list<string>,value_pattern:string,allowed_hosts:list<string>}> $definitions
     */
    public function __construct(array $definitions, int $defaultsVersion = 1)
    {
        $this->definitions = $definitions;
        $this->defaultsVersion = $defaultsVersion;
    }

    public static function fromJsonFile(string $filename): self
    {
        if (!is_file($filename)) {
            throw new RuntimeException('The EXID authority catalogue does not exist.');
        }

        $json = file_get_contents($filename);

        return self::fromJson(is_string($json) ? $json : '');
    }

    /**
     * Parse and validate a module-owned authority catalogue.
     *
     * This is deliberately stricter than the runtime reader.  The catalogue
     * is editable through the administrator UI, so malformed or duplicate
     * definitions must never be written back to disk.
     */
    public static function fromJson(string $json): self
    {
        if (strlen($json) > 200000) {
            throw new RuntimeException('The EXID authority catalogue is too large.');
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !is_array($data['authorities'] ?? null)) {
            throw new RuntimeException('The EXID authority catalogue is invalid.');
        }

        if (($data['version'] ?? null) !== 1) {
            throw new RuntimeException('The EXID authority catalogue must use version 1.');
        }

        $defaultsVersion = $data['defaults_version'] ?? 1;
        if (!is_int($defaultsVersion) || $defaultsVersion < 1 || $defaultsVersion > 1000) {
            throw new RuntimeException('The EXID authority catalogue contains an invalid defaults version.');
        }

        $definitions = [];
        $uris = [];

        foreach ($data['authorities'] as $definition) {
            if (!is_array($definition)
                || !is_string($definition['key'] ?? null)
                || !is_string($definition['label'] ?? null)
                || !is_array($definition['type_uris'] ?? null)
                || !is_string($definition['value_pattern'] ?? null)
                || !is_array($definition['allowed_hosts'] ?? null)
            ) {
                throw new RuntimeException('The EXID authority catalogue contains an invalid authority definition.');
            }

            $typeUris = array_values(array_filter($definition['type_uris'], 'is_string'));
            $hosts    = array_values(array_filter($definition['allowed_hosts'], 'is_string'));

            if ($typeUris === [] || $hosts === []
                || preg_match('/\A[a-z0-9][a-z0-9:_-]{0,79}\z/', $definition['key']) !== 1
                || strlen($definition['label']) > 200
                || strlen($definition['value_pattern']) > 200
            ) {
                throw new RuntimeException('The EXID authority catalogue contains an invalid authority definition.');
            }

            foreach ($typeUris as $typeUri) {
                $parts = parse_url(trim($typeUri));
                if (($parts['scheme'] ?? '') !== 'https' || !is_string($parts['host'] ?? null) || isset($uris[trim($typeUri)])) {
                    throw new RuntimeException('The EXID authority catalogue contains an invalid or duplicate TYPE URI.');
                }

                $uris[trim($typeUri)] = true;
            }

            foreach ($hosts as $host) {
                if (preg_match('/\A[a-z0-9.-]{1,253}\z/i', $host) !== 1) {
                    throw new RuntimeException('The EXID authority catalogue contains an invalid host.');
                }
            }

            // Compile the configured expression before it can be saved.
            if (@preg_match('/\A(?:' . $definition['value_pattern'] . ')\z/u', '') === false) {
                throw new RuntimeException('The EXID authority catalogue contains an invalid value pattern.');
            }

            $definitions[] = [
                'key'           => $definition['key'],
                'label'         => $definition['label'],
                'type_uris'     => $typeUris,
                'value_pattern' => $definition['value_pattern'],
                'allowed_hosts' => $hosts,
            ];
        }

        return new self($definitions, $defaultsVersion);
    }

    public function toJson(): string
    {
        $json = json_encode([
            'version'         => 1,
            'defaults_version' => $this->defaultsVersion,
            'authorities'     => $this->definitions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($json)) {
            throw new RuntimeException('The EXID authority catalogue could not be encoded.');
        }

        return $json . "\n";
    }

    /**
     * Add bundled defaults from a newer seed without changing administrator
     * definitions. A migration is applied only once for each seed version;
     * this also means that an administrator can deliberately remove a
     * bundled authority after the migration.
     */
    public function mergeMissingDefaults(self $defaults): bool
    {
        if ($this->defaultsVersion >= $defaults->defaultsVersion) {
            return false;
        }

        $knownKeys = [];
        foreach ($this->definitions as $definition) {
            $knownKeys[$definition['key']] = true;
        }

        foreach ($defaults->definitions as $definition) {
            if (!isset($knownKeys[$definition['key']])) {
                $this->definitions[] = $definition;
                $knownKeys[$definition['key']] = true;
            }
        }

        $this->defaultsVersion = $defaults->defaultsVersion;

        return true;
    }

    public function mergeRegistry(GedcomExidTypeCatalog $registry): self
    {
        foreach ($registry->all() as $type) {
            $uri   = trim($type['uri']);
            $parts = parse_url($uri);

            if (($parts['scheme'] ?? '') !== 'https'
                || !is_string($parts['host'] ?? null)
            ) {
                continue;
            }

            // Registered GEDCOM types are authoritative. Remove any local
            // provider definition for the same URI before adding registry data.
            $this->definitions = array_values(array_filter(
                $this->definitions,
                static function (array $definition) use ($uri): bool {
                    foreach ($definition['type_uris'] as $knownUri) {
                        if (rtrim(trim($knownUri), '/') === rtrim($uri, '/')) {
                            return false;
                        }
                    }

                    return true;
                }
            ));

            $this->definitions[] = [
                'key'           => 'gedcom:' . $type['source_file'],
                'label'         => $type['label'],
                'type_uris'     => [$uri],
                'value_pattern' => '[^\x00-\x1F\x7F]{1,200}',
                'allowed_hosts' => [$parts['host']],
            ];
        }

        return $this;
    }

    /** @return array{key:string,label:string,type_uris:list<string>,value_pattern:string,allowed_hosts:list<string>}|null */
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
        $typeUri   = trim($typeUri);
        $definition = $this->definition($typeUri);
        $value      = trim($value);

        if ($definition === null || $value === '' || strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return null;
        }

        if (preg_match('/\A' . $definition['value_pattern'] . '\z/u', $value) !== 1) {
            return null;
        }

        $url   = $typeUri . rawurlencode($value);
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

    /** @return list<array{key:string,label:string,type_uris:list<string>,value_pattern:string,allowed_hosts:list<string>}> */
    public function all(): array
    {
        return $this->definitions;
    }

    private function sameUri(string $left, string $right): bool
    {
        return rtrim(trim($left), '/') === rtrim(trim($right), '/');
    }
}
