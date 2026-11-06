<?php

use App\Domain\Ticketing\Actions\IssueTicket;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$section = $argv[1] ?? 'plataforma';
$category = $argv[2] ?? 'general';
$count = (int) ($argv[3] ?? 1);

$action = $app->make(IssueTicket::class);

for ($index = 0; $index < $count; $index++) {
    echo $action->handle($section, $category)->label."\n";
}
