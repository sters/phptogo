<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/ExampleHook.php';

use PhpToGo\Converter\ConverterController;

function out($msg)
{
    echo "{$msg}\n";
}

function showUsage()
{
    out('You must need some options:');
    out("\t -i input-file. If missing, program exit.");
    out("\t -o output-file. If empty, output to stdout.");
}

function parseOption($argv)
{
    if (count($argv) <= 1) {
        showUsage();
        exit(1);
    }

    $options = getopt('i:o:');
    $options = [
        'input' => $options['i'] ?? '',
        'output' => $options['o'] ?? null,
    ];

    if (!file_exists($options['input'])) {
        out("File not found: {$options['input']}");
        showUsage();
        exit(1);
    }

    if (!is_null($options['output'])) {
        if (file_exists($options['output'])) {
            out("File already exists: {$options['output']}");
            showUsage();
            exit(1);
        }
    } else {
        $options['output'] = 'php://stdout';
    }

    return $options;
}

function main($argv)
{
    $options = parseOption($argv);

    out("Starting: {$options['input']}");

    $converter = new ConverterController();
    $converter->registerHook(new ExampleHook);
    $result = $converter->convert(file_get_contents($options['input']));

    file_put_contents($options['output'], $result);
}

main($argv);