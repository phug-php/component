<?php

namespace Phug\Component;

use Closure;
use Phug\AbstractExtension;
use Phug\Ast\NodeInterface;
use Phug\Compiler\Event\NodeEvent;
use Phug\CompilerEvent;
use Phug\Formatter\Element\KeywordElement;
use Phug\Formatter\Element\MixinElement;
use Phug\Parser\Node\CodeNode;
use Phug\Parser\Node\KeywordNode;
use Phug\Parser\Node\MixinCallNode;
use Phug\Parser\Node\TextNode;
use Phug\Phug;
use Phug\Renderer;
use Phug\RendererModuleInterface;
use Phug\Util\Partial\OptionTrait;

class ComponentExtension extends AbstractExtension implements RendererModuleInterface
{
    use OptionTrait;

    const PUG_SLOT_NAME_VARIABLE = 'pug_component_slot';

    /**
     * @var Renderer
     */
    private $renderer;

    public function __construct(Renderer $renderer)
    {
        $this->renderer = $renderer->setOptions([
            'keywords' => $this->getKeywords(),
        ]);
    }

    public function getContainer(): Renderer
    {
        return $this->renderer;
    }

    public static function enable(): void
    {
        Phug::addExtension(static::class);
    }

    public static function disable(): void
    {
        Phug::removeExtension(static::class);
    }

    public static function slot(string $name, array $definedVariables)
    {
        $children = $definedVariables['__pug_children'] ?? null;

        if (is_object($children) && $children instanceof Closure) {
            $children(array_merge([static::PUG_SLOT_NAME_VARIABLE => $name ?: '__main__'], $definedVariables));

            return false;
        }

        return ($definedVariables[static::PUG_SLOT_NAME_VARIABLE] ?? null) === $name;
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
            'slot' => static function (string $name, KeywordElement $keyword): array {
                return [
                    'begin' => '<?php if ('.static::class.'::slot('.var_export($name, true).', get_defined_vars())) { ?>',
                    'end' => '<?php } ?>',
                ];
            },
        ];
    }

    public function handleNodeEvent(NodeEvent $event): void
    {
        $call = $event->getNode();

        if ($call instanceof MixinCallNode) {
            $call->setChildren(array_merge(
                [(new CodeNode($call->getToken(), null, $call->getLevel(), $call))->setValue('$'.static::PUG_SLOT_NAME_VARIABLE.' = null')],
                array_map(static function (NodeInterface $node) use ($call) {
                    if ($node instanceof KeywordNode && $node->getName() === 'slot') {
                        return $node;
                    }

                    return new CodeNode($node->getToken(), null, $node->getLevel(), $call, [
                        (new TextNode($node->getToken(), null, $node->getLevel()))->setValue('if ($'.static::PUG_SLOT_NAME_VARIABLE.' === "__main__")'),
                        $node,
                    ]);
                }, $call->getChildren())
            ));
        }
    }

    public function attachEvents(): void
    {
        $this->renderer->getCompiler()->attach(CompilerEvent::NODE, [$this, 'handleNodeEvent']);
    }

    public function detachEvents(): void
    {
        $this->renderer->getCompiler()->detach(CompilerEvent::NODE, [$this, 'handleNodeEvent']);
    }
}
