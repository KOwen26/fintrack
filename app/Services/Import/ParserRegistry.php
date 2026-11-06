<?php

namespace App\Services\Import;

use App\Models\Provider;
use App\Services\Import\Contracts\ProviderStatementParser;
use App\Services\Import\Parsers\MandiriStatementParser;

/** Provider slug → built-in parser. Adding a provider = one class + one entry. */
final readonly class ParserRegistry
{
    /** @var array<string, class-string<ProviderStatementParser>> */
    private const array PARSERS = [
        'mandiri' => MandiriStatementParser::class,
    ];

    /** @return list<string> Provider slugs with a built-in statement parser. */
    public static function supportedProviders(): array
    {
        return array_keys(self::PARSERS);
    }

    public function supports(?Provider $provider): bool
    {
        return $provider !== null && isset(self::PARSERS[$provider->slug]);
    }

    public function resolve(Provider $provider): ProviderStatementParser
    {
        $parser = self::PARSERS[$provider->slug] ?? null;

        abort_if($parser === null, 422, 'Statement import is not available for this provider yet.');

        return new $parser;
    }
}
