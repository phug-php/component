<?php

namespace Phug\Test\Component;

use PHPUnit\Framework\TestCase;

class ComponentExtensionTest extends TestCase
{
    public function getReadmeExamples()
    {
        preg_match_all(
            '/```pug\n(?<pug>[\s\S]+)\n```[\s\S]*```html\n(?<html>[\s\S]+)\n```/U',
            file_get_contents(__DIR__.'/../../../README.md'),
            $examples,
            PREG_SET_ORDER
        );

        foreach ($examples as $example) {
            yield [$example['html'], $example['pug']];
        }
    }

    /**
     * @dataProvider getReadmeExamples
     */
    public function testReadme($htmlCode, $pugCode)
    {
        $this->assertSame($htmlCode, $pugCode);
    }
}
