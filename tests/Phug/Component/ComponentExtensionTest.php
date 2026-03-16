<?php

namespace Phug\Test\Component;

use Exception;
use PHPUnit\Framework\TestCase;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Component\ComponentExtension;
use Phug\Formatter\Element\KeywordElement;
use Phug\Formatter\Element\TextElement;
use Phug\Parser\Node\ElementNode;
use Phug\Parser\Node\KeywordNode;
use Phug\Parser\Node\MixinCallNode;
use Phug\Parser\Node\TextNode;
use Phug\Phug;
use Phug\PhugException;
use Phug\Renderer;
use Phug\RendererException;
use Phug\Util\Partial\ValueTrait;
use Pug\Pug;
use ReflectionException;
use XhtmlFormatter\Formatter;

/**
 * @coversDefaultClass \Phug\Component\ComponentExtension
 */
class ComponentExtensionTest extends TestCase
{
    /**
     * @var string
     */
    protected $readme = null;

    protected function setUp(): void
    {
        preg_match(
            '/```php\n(?<php>[\s\S]+)\n```/U',
            $this->getReadmeContents(),
            $install
        );

        eval($install['php']);
    }

    protected function renderAndFormat(string $code): string
    {
        return $this->format(Phug::render($code));
    }

    protected function format(string $html): string
    {
        $htmlFormatter = new Formatter;
        $htmlFormatter->setSpacesIndentationMethod(2);
        $html = trim($htmlFormatter->format($html));
        $html = preg_replace('/\s+<em>\s*(.*\S)\s*<\/em>\s+/', ' <em>$1</em>', $html);
        $html = preg_replace('/<p>\s*(.*\S)\s*<\/p>/', '<p>$1</p>', $html);

        return $html;
    }

    protected function getReadmeContents(): string
    {
        if ($this->readme === null) {
            $this->readme = file_get_contents(__DIR__ . '/../../../README.md');
        }

        return $this->readme;
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
     *
     * @param string $htmlCode
     * @param string $pugCode
     */
    public function testReadme(string $htmlCode, string $pugCode)
    {
        $this->assertSame(
            preg_replace('/\n{2,}/', "\n", $htmlCode),
            $this->renderAndFormat($pugCode)
        );
    }

    public function testGetContainer()
    {
        $renderer = new Renderer();

        $this->assertSame($renderer, (new ComponentExtension($renderer))->getContainer());
    }

    /**
     * @throws PhugException
     */
    public function testEnable()
    {
        ComponentExtension::enable();

        $this->assertTrue(Phug::hasExtension(ComponentExtension::class));
    }

    /**
     * @throws PhugException
     */
    public function testDisable()
    {
        ComponentExtension::disable();

        $this->assertFalse(Phug::hasExtension(ComponentExtension::class));
    }

    /**
     * @covers ::attachEvents
     *
     * @throws ReflectionException
     * @throws RendererException
     */
    public function testAttachEvents()
    {
        $renderer = new Renderer();
        $extension = new ComponentExtension($renderer);

        $this->assertNotContains(
            [$extension, 'handleNodeEvent'],
            iterator_to_array($renderer->getCompiler()->getEventListeners()[CompilerEvent::NODE])
        );

        $extension->attachEvents();

        $this->assertContains(
            [$extension, 'handleNodeEvent'],
            iterator_to_array($renderer->getCompiler()->getEventListeners()[CompilerEvent::NODE])
        );
    }

    /**
     * @covers ::detachEvents
     *
     * @throws ReflectionException
     * @throws RendererException
     */
    public function testDetachEvents()
    {
        $renderer = new Renderer();
        $extension = new ComponentExtension($renderer);
        $renderer->getCompiler()->attach(CompilerEvent::NODE, [$extension, 'handleNodeEvent']);

        $this->assertContains(
            [$extension, 'handleNodeEvent'],
            iterator_to_array($renderer->getCompiler()->getEventListeners()[CompilerEvent::NODE])
        );

        $extension->detachEvents();

        $this->assertNotContains(
            [$extension, 'handleNodeEvent'],
            iterator_to_array($renderer->getCompiler()->getEventListeners()[CompilerEvent::NODE])
        );
    }

    /**
     * @covers ::slot
     */
    public function testSlot()
    {
        $this->assertFalse(ComponentExtension::slot('foo', []));
        $this->assertFalse(ComponentExtension::slot('__main__', []));
        $this->assertFalse(ComponentExtension::slot('foo', ['pug_component_slot' => '__main__']));
        $this->assertFalse(ComponentExtension::slot('__main__', ['pug_component_slot' => 'foo']));

        $this->assertTrue(ComponentExtension::slot('foo', ['pug_component_slot' => 'foo']));
        $this->assertTrue(ComponentExtension::slot('__main__', ['pug_component_slot' => '__main__']));

        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };

        $this->assertTrue(ComponentExtension::slot('foo', ['pug_component_slot' => 'foo', 'pug_component_slot_foo' => $callback]));
        $this->assertTrue($called);

        $called = false;
        $this->assertTrue(ComponentExtension::slot('__main__', ['pug_component_slot' => '__main__', 'pug_component_slot_foo' => $callback]));
        $this->assertFalse($called);

        $called = false;
        $this->assertTrue(ComponentExtension::slot('foo', ['pug_component_slot' => 'foo', 'pug_component_slot___main__' => $callback]));
        $this->assertFalse($called);

        $called = false;
        $this->assertTrue(ComponentExtension::slot('__main__', ['pug_component_slot' => '__main__', 'pug_component_slot___main__' => $callback]));
        $this->assertTrue($called);

        $arguments = [];
        $children = static function (...$args) use (&$arguments) {
            if (isset($args[0]['pug_component_slot_foo'])) {
                unset($args[0]['pug_component_slot_foo']);
            }

            if (isset($args[0]['pug_component_slot___main__'])) {
                unset($args[0]['pug_component_slot___main__']);
            }

            $arguments = $args;
        };

        $this->assertTrue(ComponentExtension::slot('foo', ['pug_component_slot' => 'foo', '__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => 'foo', '__pug_children' => $children]], $arguments);
        $this->assertTrue(ComponentExtension::slot('__main__', ['pug_component_slot' => '__main__', '__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => '__main__', '__pug_children' => $children]], $arguments);
        $this->assertTrue(ComponentExtension::slot('foo', ['__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => 'foo', '__pug_children' => $children]], $arguments);
        $this->assertTrue(ComponentExtension::slot('__main__', ['__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => '__main__', '__pug_children' => $children]], $arguments);

        $arguments = [];
        $children = static function (...$args) use (&$arguments) {
            if (isset($args[0]['pug_component_slot_foo'])) {
                $args[0]['pug_component_slot_foo']();
                unset($args[0]['pug_component_slot_foo']);
            }

            if (isset($args[0]['pug_component_slot___main__'])) {
                $args[0]['pug_component_slot___main__']();
                unset($args[0]['pug_component_slot___main__']);
            }

            $arguments = $args;
        };

        $this->assertFalse(ComponentExtension::slot('foo', ['pug_component_slot' => 'foo', '__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => 'foo', '__pug_children' => $children]], $arguments);
        $this->assertFalse(ComponentExtension::slot('__main__', ['pug_component_slot' => '__main__', '__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => '__main__', '__pug_children' => $children]], $arguments);
        $this->assertFalse(ComponentExtension::slot('foo', ['__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => 'foo', '__pug_children' => $children]], $arguments);
        $this->assertFalse(ComponentExtension::slot('__main__', ['__pug_children' => $children]));
        $this->assertSame([['pug_component_slot' => '__main__', '__pug_children' => $children]], $arguments);
    }

    /**
     * @covers ::getKeywords
     */
    public function testSlotKeyword()
    {
        ['slot' => $slot] = (new ComponentExtension(new Renderer()))->getKeywords();
        $keyword = new KeywordElement('slot', '', null, null, [(new TextElement)->setValue('Hello')]);

        $this->assertSame([
            'begin' => '<?php if (\Phug\Component\ComponentExtension::slot(\'foobar\', get_defined_vars())) { ?>',
            'end' => '<?php } ?>',
        ], $slot('foobar', $keyword));
    }

    protected function getValue($object)
    {
        $this->assertTrue(method_exists($object, 'getValue'), (is_object($object) ? get_class($object) : gettype($object)).' unexpected.');

        /** @var ValueTrait $node */
        $node = $object;

        return $node->getValue();
    }

    /**
     * @covers ::handleNodeEvent
     * @covers ::getCodeNode
     *
     * @throws ReflectionException
     * @throws RendererException
     */
    public function testHandleNodeEvent()
    {
        $extension = new ComponentExtension(new Renderer());
        $children = [
            (new KeywordNode)->setName('foo')->setValue('bar'),
            (new KeywordNode)->setName('slot')->setValue('header'),
            new TextNode(),
        ];
        $tag = new ElementNode(null, null, null, null, $children);
        $extension->handleNodeEvent(new NodeEvent($tag));

        $this->assertSame($children, $tag->getChildren());

        $call = new MixinCallNode(null, null, null, null, $children);
        $extension->handleNodeEvent(new NodeEvent($call));

        $this->assertCount(5, $call->getChildren());
        $this->assertSame('$pug_component_slot = null', $this->getValue($call->getChildAt(0)));
        $this->assertSame('if (!isset($pug_component_slot) || $pug_component_slot === "__main__")', $this->getValue($call->getChildAt(1)->getChildAt(0)));
        $this->assertSame($children[0], $call->getChildAt(1)->getChildAt(2));
        $this->assertSame($children[1], $call->getChildAt(2));
        $this->assertSame('if (!isset($pug_component_slot) || $pug_component_slot === "__main__")', $this->getValue($call->getChildAt(3)->getChildAt(0)));
        $this->assertSame($children[2], $call->getChildAt(3)->getChildAt(2));
    }

    public function testBasicMixinAreStillFine()
    {
        $this->assertSame("<p>5</p>\nHello", $this->renderAndFormat(implode("\n", [
            'mixin foo($num)',
            '  p=$num',
            '  block',
            '+foo(5)',
            '  | Hello',
        ])));
    }

    /**
     * @covers ::handleOutputEvent
     */
    public function testFirstMixin()
    {
        $this->assertSame("<p>5</p>\nHello", $this->renderAndFormat(implode("\n", [
            'mixin foo($num)',
            '  p=$num',
            '  block',
            '+#{$firstMixin("biz", "foo", "bar")}(5)',
            '  | Hello',
        ])));
        $this->assertSame("<p>5</p>\nHello", $this->renderAndFormat(implode("\n", [
            'component foo($num)',
            '  p=$num',
            '  block',
            '+#{$firstComponent("biz", "foo", "bar")}(5)',
            '  | Hello',
        ])));
    }

    /**
     * @covers ::__construct
     * @covers ::getOptions
     * @covers ::enable
     *
     * @throws PhugException
     * @throws Exception
     */
    public function testWithPug()
    {
        $pug = new Pug();
        ComponentExtension::enable($pug);

        $this->assertSame(implode("\n", [
            'Title',
            '<article data-attr="5">',
            '  Content',
            '</article>',
        ]), $this->format($pug->render(implode("\n", [
            'mixin foobar(obj)',
            '  slot title',
            '  article(data-attr=obj.a): slot',
            '+foobar({a: 5})',
            '  slot title',
            '    | Title',
            '  slot __main__',
            '    | Content',
        ]))));
    }

    /**
     * @throws PhugException
     */
    public function testNamespace()
    {
        $pug = new Pug([
            'on_output' => function (OutputEvent $event) {
                $event->prependCode('namespace pug;');
            },
        ]);
        ComponentExtension::enable($pug);

        $this->assertSame(implode("\n", [
            'Title',
            '<article data-attr="5">',
            '  Content',
            '</article>',
        ]), $this->format($pug->render(implode("\n", [
            'mixin foobar(obj)',
            '  slot title',
            '  article(data-attr=obj.a): slot',
            '+foobar({a: 5})',
            '  slot title',
            '    | Title',
            '  slot __main__',
            '    | Content',
        ]))));
    }

    public function getPugPhpTestsTemplates(): array
    {
        return array_map(function ($file) {
            return [$file, substr($file, 0, -5).'.pug'];
        }, glob(__DIR__.'/../../templates/*.html'));
    }

    /**
     * @dataProvider getPugPhpTestsTemplates
     *
     * @covers ::attachEvents
     * @covers ::parseOutput
     *
     * @param string $htmlFile Expected output template file
     * @param string $pugFile  Input template file
     *
     * @throws Exception
     */
    public function testPugPhpTestsTemplates(string $htmlFile, string $pugFile)
    {
        $pug = new Pug([
            'debug' => false,
            'pretty' => true,
        ]);
        ComponentExtension::enable($pug);

        $this->assertSame(
            $this->rawHtml(file_get_contents($htmlFile)),
            $this->rawHtml($pug->renderFile($pugFile, [])),
            basename($pugFile)
        );
    }

    private function rawHtml($html)
    {
        $html = strtr($html, [
            "'" => '"',
            "\r" => '',
        ]);
        $html = preg_replace('`\n{2,}`', "\n", $html);
        $html = preg_replace('`(?<!\n) {2,}`', ' ', $html);
        $html = preg_replace('` *$`m', '', $html);
        $html = $this->format($html);
        $html = preg_replace_callback('`(<(?:style|script)(?:[^>]*)>)([\s\S]+)(</(?:style|script)>)`', function ($matches) {
            [, $start, $content, $end] = $matches;
            $content = trim(preg_replace('`^ *`m', '', $content));

            return "$start\n$content\n$end";
        }, $html);

        return $html;
    }
}
