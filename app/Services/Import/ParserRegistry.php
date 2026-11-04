<?php

namespace App\Services\Import;

use App\Services\Import\Parsers\MandiriStatementParser;
use App\Models\Provider;
use App\Services\Import\Contracts\ProviderStatementParser;

/** Provider slug → built-in parser. Adding a provider = one class + one entry. */
final readonly class ParserRegistry
{
    /** @var array<string, class-string<ProviderStatementParser>> */
    private const array PARSERS = [
        'mandiri' => MandiriStatementParser::class,
    ];

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
