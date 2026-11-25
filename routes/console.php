<?php

use App\Services\EssayGuidanceRetriever;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('essay:rag-index', function (EssayGuidanceRetriever $retriever): int {
    try {
        $counts = $retriever->rebuild();
    } catch (JsonException|RuntimeException $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $this->info("Indexed {$counts['guides']} writing guides.");
    if ($counts['references'] > 0) {
        $this->info("Indexed {$counts['references']} annotated essay examples.");
    }

    return Command::SUCCESS;
})->purpose('Build the local index for essay revision guidance');
