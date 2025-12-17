<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ElasticStaticScan extends Command
{
    protected $signature = 'elastic:scan-destructive {--path=app}';

    protected $description = 'Fail if destructive ES calls are present outside dev-tools';

    public function handle(): int
    {
        $root = base_path();
        $path = $this->option('path');
        $scanDir = realpath($root.DIRECTORY_SEPARATOR.$path) ?: $root.DIRECTORY_SEPARATOR.$path;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scanDir));
        $badPatterns = [
            'indices()->delete',
            '_delete_by_query',
            'delete_index',
            'drop_index',
            'reindex',
        ];
        $violations = [];
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
            if (str_contains($rel, 'dev-tools')) {
                continue;
            }
            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            foreach ($badPatterns as $pat) {
                if (stripos($content, $pat) !== false) {
                    $violations[] = [$rel, $pat];
                }
            }
        }
        if (! empty($violations)) {
            $this->error('Destructive ES patterns found:');
            foreach ($violations as [$rel, $pat]) {
                $this->line(" - {$rel} contains {$pat}");
            }

            return 1;
        }
        $this->info('No destructive ES patterns detected.');

        return 0;
    }
}
