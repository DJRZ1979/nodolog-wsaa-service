<?php

class Logger
{
    public function __construct(private string $basePath) {}

    public function log(string $file, string $msg): void
    {
        $path = rtrim($this->basePath, '/') . '/' . $file;
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND);
    }
}
