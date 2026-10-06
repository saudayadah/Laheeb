<?php

namespace App\Services\Imports;

use InvalidArgumentException;

class ImporterRegistry
{
    /** @var array<string, class-string<BaseImporter>> */
    private const IMPORTERS = [
        'customers' => CustomersImporter::class,
        'products' => ProductsImporter::class,
        'standing_orders' => StandingOrdersImporter::class,
        'opening_balances' => OpeningBalancesImporter::class,
    ];

    public static function types(): array
    {
        return array_keys(self::IMPORTERS);
    }

    public static function make(string $type): BaseImporter
    {
        if (! isset(self::IMPORTERS[$type])) {
            throw new InvalidArgumentException("Unknown import type [{$type}].");
        }

        $class = self::IMPORTERS[$type];

        return new $class;
    }
}
