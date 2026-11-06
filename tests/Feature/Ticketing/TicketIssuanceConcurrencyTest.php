<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\Ticket;
use App\Domain\Ticketing\TicketSequence;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsTicketMatrix;
use Tests\ConcurrencyTestCase;

#[Group('t-conc')]
class TicketIssuanceConcurrencyTest extends ConcurrencyTestCase
{
    use SeedsTicketMatrix;

    public function test_parallel_processes_issue_unique_consecutive_numbers(): void
    {
        $this->seedMatrix();

        $database = config('database.connections.mysql');

        $environment = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $database['host'],
            'DB_PORT' => (string) $database['port'],
            'DB_DATABASE' => (string) $database['database'],
            'DB_USERNAME' => (string) $database['username'],
            'DB_PASSWORD' => (string) $database['password'],
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ];

        $processes = collect(range(1, 6))->map(
            fn (): InvokedProcess => Process::path(base_path())
                ->env($environment)
                ->start([PHP_BINARY, 'tests/Support/issue_tickets.php', 'plataforma', 'general', '5']),
        );

        foreach ($processes as $process) {
            $process->wait()->throw();
        }

        $section = $this->section('plataforma');

        $tickets = Ticket::query()
            ->where('section_id', $section->id)
            ->where('code', 'A')
            ->get();

        $this->assertCount(30, $tickets);
        $this->assertSame(
            range(1, 30),
            $tickets->pluck('number')->sort()->values()->all(),
        );
        $this->assertSame(30, $tickets->pluck('label')->unique()->count());

        $sequence = TicketSequence::query()
            ->where('section_id', $section->id)
            ->where('code', 'A')
            ->sole();

        $this->assertSame(30, $sequence->last_number);
    }
}
