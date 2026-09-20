<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[
Signature('kalbek:storage-check
{--disk= : Filesystem disk to check, defaults to KALBEK_GENERATED_AUDIO_DISK}
{--path= : Path prefix to check, defaults to KALBEK_GENERATED_AUDIO_PATH}
{--keep : Keep the probe file after a successful check}
')
]
#[Description('Check generated-audio filesystem storage without printing secrets')]
class StorageCheck extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = (string) ($this->option('disk') ?: config('services.kalbek.generated_audio_disk', 'local'));
        $basePath = trim((string) ($this->option('path') ?: config('services.kalbek.generated_audio_path', 'generated-audio')), '/');
        $path = ($basePath === '' ? '' : "{$basePath}/").'storage-check-'.now()->format('YmdHis').'-'.Str::random(8).'.txt';
        $content = 'kalbek storage check '.now()->toIso8601String();
        $diskConfig = config("filesystems.disks.{$disk}", []);

        $this->info("Checking filesystem disk [{$disk}] with probe path [{$path}].");
        $this->line('Configured driver: '.($diskConfig['driver'] ?? 'missing'));

        if (($diskConfig['driver'] ?? null) === 's3') {
            $this->line('AWS_ACCESS_KEY_ID present: '.(filled($diskConfig['key'] ?? null) ? 'yes' : 'no'));
            $this->line('AWS_SECRET_ACCESS_KEY present: '.(filled($diskConfig['secret'] ?? null) ? 'yes' : 'no'));
            $this->line('AWS_DEFAULT_REGION: '.($diskConfig['region'] ?: 'missing'));
            $this->line('AWS_BUCKET: '.($diskConfig['bucket'] ?: 'missing'));
            $this->line('AWS_ENDPOINT: '.($diskConfig['endpoint'] ?: 'missing'));
            $this->line('AWS_USE_PATH_STYLE_ENDPOINT: '.(($diskConfig['use_path_style_endpoint'] ?? false) ? 'true' : 'false'));
        }

        try {
            $stored = Storage::disk($disk)->put($path, $content);

            if ($stored !== true) {
                $this->error('Storage::put returned false. Check bucket name, credentials, endpoint, and write permissions.');

                return 1;
            }

            if (! Storage::disk($disk)->exists($path)) {
                $this->error('Storage::exists returned false after a successful write. Check read/head permissions on the bucket token.');

                return 1;
            }

            if (Storage::disk($disk)->get($path) !== $content) {
                $this->error('Storage::get returned different content after a successful write. Check object read permissions and endpoint routing.');

                return 1;
            }

            if (! $this->option('keep')) {
                Storage::disk($disk)->delete($path);
            }

            $this->info('Storage check passed.');

            return 0;
        } catch (\Throwable $exception) {
            $this->error('Storage check failed.');
            $this->line('Exception: '.$exception::class);
            $this->line('Message: '.$exception->getMessage());

            return 1;
        }
    }
}
