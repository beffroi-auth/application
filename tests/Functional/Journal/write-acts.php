<?php

declare(strict_types=1);

/*
 * A writer of the journal of its own, as a server or the worker of a deployment is: its own kernel, its own
 * connection, and as many acts as its argument says, written one after another. TheJournalUnderConcurrentWritersTest
 * starts several at once.
 */

use App\Doctrine\TenantProvider;
use App\Kernel;
use Beffroi\Core\Domain\Journal\JournalEntry;
use Beffroi\Core\Domain\Session\RequestContext;
use Beffroi\Core\Port\JournalInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

new Dotenv()->bootEnv(dirname(__DIR__, 3) . '/.env');

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$tenants = $container->get('test.beffroi.tenant_provider');
$journal = $container->get('test.beffroi.journal');
if (! $tenants instanceof TenantProvider || ! $journal instanceof JournalInterface) {
    fwrite(STDERR, 'The kernel of the suite does not name the journal.' . PHP_EOL);

    exit(1);
}
$tenant = $tenants->provide();
for ($written = 0; $written < (int) ($argv[1] ?? 0); ++$written) {
    $now = new DateTimeImmutable();
    $journal->record(new JournalEntry(Uuid::v7(), $tenant, 'user.authenticated', $now, new RequestContext($now, '203.0.113.7', 'a writer of the journal')));
}
$kernel->shutdown();
