<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

// Shared cross-file test helpers (scenario pairs, page-id extraction).
require __DIR__ . '/Support/helpers.php';

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()
    ->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature', 'Integration', 'Browser');

pest()->browser()->timeout(5000);

pest()->group('architecture')->in('Architecture');
pest()->group('browser')->in('Browser');
pest()->group('feature')->in('Feature');
pest()->group('integration')->in('Integration');
pest()->group('unit')->in('Unit');

pest()->group('feature:auth')->in('Feature/Auth');
pest()->group('feature:app')->in('Feature/App');

/*
 * Module groups — the relevance axis for "I changed X, run its tests".
 * A module label spans suites: --group=module:transfer runs the feature
 * tests AND TransferServiceTest. Feature files map by module directory;
 * integration files map per file.
 */
pest()->group('module:auth')->in('Feature/Auth');
pest()->group('module:account')->in('Feature/App/Account');
pest()->group('module:transaction')->in('Feature/App/Transaction');
pest()->group('module:transfer')->in('Feature/App/Transfer');
pest()->group('module:tag')->in('Feature/App/Tag');
pest()->group('module:template')->in('Feature/App/Template');
pest()->group('module:dashboard')->in('Feature/App/Dashboard');
pest()->group('module:statistics')->in('Feature/App/Statistics');
pest()->group('module:settings')->in('Feature/App/Settings');

pest()->group('module:transfer')->in('Integration/Services/TransferServiceTest.php');
pest()->group('module:tag')->in('Integration/Services/TagServiceTest.php', 'Integration/Models/TagsTest.php');
pest()->group('module:transaction')->in('Integration/Observers/TransactionObserverTest.php');
pest()->group('module:account')->in(
    'Integration/Services/AccountServiceTest.php',
    'Integration/Services/BalanceServiceTest.php',
    'Integration/Services/InitialBalanceSyncTest.php',
);
pest()->group('module:statistics')->in('Integration/Services/SpendingServiceTest.php');
pest()->group('module:template')->in('Integration/Services/TransactionTemplateServiceTest.php');
pest()->group('module:decorations')->in('Integration/Models/DecorationsTest.php');
pest()->group('module:import')->in('Integration/Services/Import/MandiriStatementParserTest.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different conditions. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', fn () => $this->toBe(1));
