<?php

declare(strict_types=1);

namespace JustinHoltWeb\Momentum\Commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Sections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JustinHoltWeb\Momentum\Plugin;
use JustinHoltWeb\Momentum\Props\Transformers\Transformers;
use JustinHoltWeb\Momentum\Settings;
use JustinHoltWeb\Momentum\Types\TypeScriptWriter;

use function CraftCms\Cms\t;

/** Pro: writes TypeScript declarations for every Inertia section's page props. */
class TypesCommand extends Command
{
    use CraftCommand;

    protected $signature = 'craft:momentum:types
        {--output=resources/js/types/momentum.d.ts : Where to write the declarations, relative to the project root}
        {--section=* : Only these section handles (default: every section with an inertia: template)}
        {--stdout : Print the declarations instead of writing a file}';

    protected $description = 'Writes TypeScript types for Momentum page props (Pro)';

    public function handle(Transformers $transformers, Settings $settings): int
    {
        if (! Plugin::isPro()) {
            $this->components->error(t('Typed props are a Momentum Pro feature.', category: 'momentum'));

            return self::FAILURE;
        }

        /** @var list<string> $sections */
        $sections = array_values(array_filter((array) $this->option('section'), is_string(...)));

        foreach ($sections as $handle) {
            if (Sections::getSectionByHandle($handle) === null) {
                $this->components->error(t('Unknown section “{handle}”.', ['handle' => $handle], category: 'momentum'));

                return self::FAILURE;
            }
        }

        $ts = new TypeScriptWriter($transformers, $settings->fieldRules(pro: true), deferEnabled: true)
            ->write($sections === [] ? null : $sections);

        if ($this->option('stdout')) {
            $this->line($ts);

            return self::SUCCESS;
        }

        $output = (string) $this->option('output');

        // Stay inside the project: no absolute paths or `..` segments.
        if ($output === '' || str_starts_with($output, '/') || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $output) || ! str_ends_with($output, '.ts')) {
            $this->components->error(t('--output must be a relative path to a .ts file inside the project.', category: 'momentum'));

            return self::FAILURE;
        }

        $path = base_path($output);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $ts);
        $this->components->info(t('Wrote {path}', ['path' => $output], category: 'momentum'));

        return self::SUCCESS;
    }
}
