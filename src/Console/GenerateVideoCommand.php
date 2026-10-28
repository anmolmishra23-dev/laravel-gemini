<?php

namespace AnmolMishra\Gemini\Console;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Gemini;
use Illuminate\Console\Command;

class GenerateVideoCommand extends Command
{
    protected $signature = 'gemini:video
        {prompt : What to generate}
        {--model= : Override the video model}
        {--aspect-ratio= : 16:9 or 9:16}
        {--resolution= : 360p, 720p, 1080p or 4k}
        {--image= : Local image to animate}
        {--disk= : Storage disk to save to}
        {--dir=gemini : Directory to save to}
        {--public : Make the file viewable by anyone with the URL}';

    protected $description = 'Generate a video with Gemini and save it to storage';

    public function handle(Gemini $gemini): int
    {
        $request = $gemini->video($this->argument('prompt'))
            ->when($this->option('model'), fn ($request, $model) => $request->model($model))
            ->when($this->option('aspect-ratio'), fn ($request, $ratio) => $request->aspectRatio($ratio))
            ->when($this->option('resolution'), fn ($request, $resolution) => $request->resolution($resolution))
            ->when($this->option('image'), fn ($request, $image) => $request->withImage($image));

        $this->components->info('Generating video. This can take a few minutes...');

        try {
            $response = $request->generate();
            $path = $response->store($this->option('dir'), $this->option('disk'), $this->option('public') ? 'public' : null);
        } catch (GeminiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Video saved to [{$path}].");

        // Not every disk can make URLs (FTP, for example), so this is best effort.
        rescue(fn () => $this->components->info("URL: {$response->url()}"), report: false);

        return self::SUCCESS;
    }
}
