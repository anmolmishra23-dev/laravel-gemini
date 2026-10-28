<?php

namespace AnmolMishra\Gemini\Console;

use AnmolMishra\Gemini\Exceptions\GeminiException;
use AnmolMishra\Gemini\Gemini;
use Illuminate\Console\Command;

class GenerateImageCommand extends Command
{
    protected $signature = 'gemini:image
        {prompt : What to generate}
        {--model= : Override the image model}
        {--aspect-ratio= : e.g. 16:9}
        {--size= : e.g. 1K, 2K, 4K}
        {--disk= : Storage disk to save to}
        {--dir=gemini : Directory to save to}
        {--public : Make the file viewable by anyone with the URL}';

    protected $description = 'Generate an image with Gemini and save it to storage';

    public function handle(Gemini $gemini): int
    {
        $request = $gemini->image($this->argument('prompt'))
            ->when($this->option('model'), fn ($request, $model) => $request->model($model))
            ->when($this->option('aspect-ratio'), fn ($request, $ratio) => $request->aspectRatio($ratio))
            ->when($this->option('size'), fn ($request, $size) => $request->size($size));

        try {
            $response = $request->generate();
            $path = $response->store($this->option('dir'), $this->option('disk'), $this->option('public') ? 'public' : null);
        } catch (GeminiException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Image saved to [{$path}].");

        // Not every disk can make URLs (FTP, for example), so this is best effort.
        rescue(fn () => $this->components->info("URL: {$response->url()}"), report: false);

        return self::SUCCESS;
    }
}
