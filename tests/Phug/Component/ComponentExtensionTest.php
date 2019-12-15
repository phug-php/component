<?php

namespace Phug\Test\Component;

use PHPUnit\Framework\TestCase;
use Phug\Phug;

class ComponentExtensionTest extends TestCase
{
    /**
     * @var string
     */
    protected $readme = null;

    protected function getReadmeContents(): string
    {
        if ($this->readme === null) {
            $this->readme = file_get_contents(__DIR__ . '/../../../README.md');
        }

        return $this->readme;
    }

    protected function setUp(): void
    {
        preg_match(
            '/```php\n(?<php>[\s\S]+)\n```/U',
            $this->getReadmeContents(),
            $install
        );

        eval($install['php']);
    }

    public function getReadmeExamples()
    {
        preg_match_all(
            '/```pug\n(?<pug>[\s\S]+)\n```[\s\S]*```html\n(?<html>[\s\S]+)\n```/U',
            $this->getReadmeContents(),
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
        $this->assertSame($htmlCode, Phug::render($pugCode));
    }
}
