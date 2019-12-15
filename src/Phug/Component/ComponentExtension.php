<?php

namespace Phug\Component;

use Phug\AbstractExtension;
use Phug\Compiler\Event\NodeEvent;
use Phug\Formatter\Element\KeywordElement;
use Phug\Formatter\Element\MixinElement;
use Phug\Phug;
use Phug\Renderer;
use Phug\RendererModuleInterface;
use Phug\Util\Partial\OptionTrait;

class ComponentExtension extends AbstractExtension implements RendererModuleInterface
{
    use OptionTrait;

    /**
     * @var Renderer
     */
    private $renderer;

    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer->setOptions($this->getEvents());
        $lexer = $renderer->getCompiler()->getParser()->getLexer();
        $lexer->setOption(['scanners', 'mixin_call'], ComponentScanner::class);
    }

    public function getContainer(): Renderer
    {
        return $this->renderer;
    }

    public static function enable(): void
    {
        Phug::addExtension(static::class);
    }

    public function getKeywords(): array
    {
        return [
            'component' => function (string $name, KeywordElement $keyword): string {
                $mixin = new MixinElement;
                $mixin->setName($name);
                $mixin->setChildren($keyword->getChildren());
                $keyword->removeChildren();

                return $this->renderer->getCompiler()->getFormatter()->format($mixin);
            },
            'slot' => function (string $name, KeywordElement $keyword): string {
                return 'slot:'.$name;
            },
        ];
    }

    public function getEvents(): array
    {
        return [
            'keywords' => $this->getKeywords(),
            // 'on_node' => [$this, 'handleNodeEvent'],
        ];
    }

    public function handleNodeEvent(NodeEvent $event): void
    {
        /* @var CommentNode $node */
        if (($node = $event->getNode()) instanceof CommentNode &&
            !$node->isVisible() &&
            $node->hasChildAt(0) &&
            ($firstChild = $node->getChildAt(0)) instanceof TextNode
        ) {
            echo trim($firstChild->getValue());
            exit;
        }
    }

    public function attachEvents(): void
    {
        // Handled in __construct
    }

    public function detachEvents(): void
    {
        // Handled in __construct
    }
}
