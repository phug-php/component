<?php

namespace Phug\Component;

use Phug\Lexer\Scanner\ClassScanner;
use Phug\Lexer\Scanner\MixinCallScanner;
use Phug\Lexer\Scanner\SubScanner;
use Phug\Lexer\State;
use Phug\Lexer\Token\MixinCallToken;

class ComponentScanner extends MixinCallScanner
{
    public function scan(State $state)
    {
        foreach ($state->scanToken(
            MixinCallToken::class,
            '[+@][ \t]*(?<name>('.
            '[a-zA-Z_][a-zA-Z0-9\-_]*|'.
            '#\\{(?:(?>"(?:\\\\[\\S\\s]|[^"\\\\])*"|\'(?:\\\\[\\S\\s]|[^\'\\\\])*\'|[^{}\'"]++|(?-1))*+)\\}'.
            '))'
        ) as $token) {
            yield $token;

            foreach ($state->scan(ClassScanner::class) as $subToken) {
                yield $subToken;
            }

            foreach ($state->scan(SubScanner::class) as $subToken) {
                yield $subToken;
            }
        }
    }
}
