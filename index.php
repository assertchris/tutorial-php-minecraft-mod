<?php

require __DIR__ . "/vendor/autoload.php";

use Amp\File\Driver;
use Theory\Builder\Client;

function getContents(Driver $filesystem, $path, &$previous) {
    $next = yield $filesystem->mtime($path);

    if ((string) $previous !== (string) $next) {
        $previous = $next;

        return yield $filesystem->get($path);
    }

    return null;
}

function executeCommand($builder, $raw) {
    $command = trim(
        substr($raw, stripos($raw, "> >") + 3)
    );

    if (stripos($command, "build") === 0) {
        $parts = explode(" ", $command);

        if (count($parts) < 4) {
            print "invalid coordinates";
            return;
        }

        $x = $parts[1];
        $y = $parts[2];
        $z = $parts[3];

        $blocks = [
        	[2, 3, 1],
        	[2, 3, 2],
        	[3, 3, 2],
        	[4, 3, 2],
        ];

        $builder->exec("/say building...");

        foreach ($blocks as $block) {
            $dx = $block[0] + $x;
            $dy = $block[1] + $y;
            $dz = $block[2] + $z;

            $builder->exec("/setblock {$dx} {$dy} {$dz} dirt");
            usleep(500000);
        }
    }
}

define("LOG_PATH", "/path/to/logs/latest.log");

Amp\run(function() {
    $builder = new Client("127.0.0.1", 25575, "<RCON password>");

    $filesystem = Amp\File\filesystem();

    // get reference data
    $commands = [];
    $timestamp = yield $filesystem->mtime(LOG_PATH);

    // listen for player requests
    Amp\repeat(function() use ($builder, $filesystem, &$commands, &$timestamp) {
        $contents = yield from getContents(
            $filesystem, LOG_PATH, $timestamp
        );

        if (!empty($contents)) {
            $lines = array_reverse(explode(PHP_EOL, $contents));

            foreach ($lines as $line) {
                $isCommand = stristr($line, "> >") !== false;
                $isNotRepeat = !in_array($line, $commands);

                if ($isCommand && $isNotRepeat) {
                    array_push($commands, $line);
                    executeCommand($builder, $line);
                    break;
                }
            }
        }
    }, 500);
});
