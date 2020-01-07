<?php

namespace Phug\Test\Component;

use PHPUnit\Framework\TestCase;
use Phug\Compiler\Event\NodeEvent;
use Phug\CompilerEvent;
use Phug\Component\ComponentExtension;
use Phug\Formatter\Element\KeywordElement;
use Phug\Formatter\Element\TextElement;
use Phug\Parser\Node\ElementNode;
use Phug\Parser\Node\KeywordNode;
use Phug\Parser\Node\MixinCallNode;
use Phug\Parser\Node\TextNode;
use Phug\Phug;
use Phug\Renderer;
use Phug\Util\Partial\ValueTrait;
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

    /**
     * @var Formatter
     */
    protected $htmlFormatter;

    protected function renderAndFormat(string $code): string
    {
        $html = trim($this->htmlFormatter->format(Phug::render($code)));
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

    protected function setUp(): void
    {
        $this->htmlFormatter = new Formatter;
        $this->htmlFormatter->setSpacesIndentationMethod(2);

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
        $this->assertSame(
            preg_replace('/\n{2,}/', "\n", $htmlCode),
            $this->renderAndFormat($pugCode)
        );
    }

    /**
     * @covers ::__construct
     * @covers ::getContainer
     */
    public function testGetContainer()
    {
        $renderer = new Renderer();

        $this->assertSame($renderer, (new ComponentExtension($renderer))->getContainer());
    }

    /**
     * @covers ::enable
     */
    public function testEnable()
    {
        ComponentExtension::enable();

        $this->assertTrue(Phug::hasExtension(ComponentExtension::class));
    }

    /**
     * @covers ::disable
     */
    public function testDisable()
    {
        ComponentExtension::disable();

        $this->assertFalse(Phug::hasExtension(ComponentExtension::class));
    }

    /**
     * @covers ::attachEvents
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

        $arguments = [];
        $children = static function (...$args) use (&$arguments) {
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
    public function testComponentKeyword()
    {
        $renderer = new Renderer();
        ['component' => $component] = (new ComponentExtension($renderer))->getKeywords();
        $keyword = new KeywordElement('component', '', null, null, [(new TextElement)->setValue('Hello')]);

        $this->assertFalse($renderer->getCompiler()->getFormatter()->getMixins()->has('foobar'));

        $this->assertSame('', $component('foobar', $keyword));

        $this->assertTrue($renderer->getCompiler()->getFormatter()->getMixins()->has('foobar'));
    }

    /**
     * @covers ::getKeywords
     */
    public function testSlotKeyword()
    {
        ['slot' => $slot] = (new ComponentExtension(new Renderer()))->getKeywords();
        $keyword = new KeywordElement('slot', '', null, null, [(new TextElement)->setValue('Hello')]);

        $this->assertSame([
            'begin' => '<?php if (Phug\Component\ComponentExtension::slot(\'foobar\', get_defined_vars())) { ?>',
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
        $this->assertSame('Hello', $this->renderAndFormat(implode("\n", [
            'mixin foo',
            '  block',
            '+foo',
            '  | Hello',
        ])));
    }
}
