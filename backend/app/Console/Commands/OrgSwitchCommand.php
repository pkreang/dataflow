<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class OrgSwitchCommand extends Command
{
    protected $signature = 'org:switch {vertical : factory|school}';

    protected $description = 'Set ORG_VERTICAL in .env and clear config cache. Use composer switch:factory / switch:school for a full demo reset.';

    private const ALLOWED = ['factory', 'school'];

    private const CREDS = [
        'factory' => ['email' => 'somchai@nteq.test',       'password' => 'Nteq1234!',  'label' => 'NTEQ Polymer (โรงงาน)'],
        'school' => ['email' => 'teacher.thai@bodin.test', 'password' => 'Bodin1234!', 'label' => 'Bodindecha (โรงเรียน)'],
    ];

    public function handle(): int
    {
        $vertical = strtolower((string) $this->argument('vertical'));

        if (! in_array($vertical, self::ALLOWED, true)) {
            $this->error('vertical must be one of: '.implode(', ', self::ALLOWED));

            return 1;
        }

        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error(".env not found at {$envPath}");

            return 1;
        }

        $contents = file_get_contents($envPath);
        $line = "ORG_VERTICAL={$vertical}";

        if (preg_match('/^ORG_VERTICAL=.*$/m', $contents)) {
            $contents = preg_replace('/^ORG_VERTICAL=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n\n{$line}\n";
        }

        file_put_contents($envPath, $contents);
        $this->info("Set ORG_VERTICAL={$vertical} in .env");

        Artisan::call('config:clear');
        Artisan::call('view:clear');
        $this->info('Cleared config + view cache.');

        $creds = self::CREDS[$vertical];
        $this->newLine();
        $this->line("  Vertical : {$vertical}  ({$creds['label']})");
        $this->line("  Login    : {$creds['email']}");
        $this->line("  Password : {$creds['password']}");
        $this->newLine();
        $this->comment('If `composer dev` is already running, restart it so the new ORG_VERTICAL takes effect.');

        return 0;
    }
}
