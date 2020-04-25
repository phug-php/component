<?php

namespace Phug\Component;

use Closure;
use Phug\AbstractPlugin;
use Phug\Ast\NodeInterface;
use Phug\Compiler\Event\ElementEvent;
use Phug\Compiler\Event\NodeEvent;
use Phug\Compiler\Event\OutputEvent;
use Phug\CompilerEvent;
use Phug\Formatter\Element\CodeElement;
use Phug\Parser\NodeInterface as ParserNodeInterface;
use Phug\Parser\Node\CodeNode;
use Phug\Parser\Node\KeywordNode;
use Phug\Parser\Node\MixinCallNode;
use Phug\Parser\Node\TextNode;
use Phug\Renderer;

class ComponentExtension extends AbstractPlugin
{
    const PUG_SLOT_NAME_VARIABLE = 'pug_component_slot';

    public function __construct(Renderer $renderer)
    {
        parent::__construct($renderer);

        $renderer->setOptions($this->getOptions());
    }

    public static function slot(string $name, array $definedVariables)
    {
        $children = $definedVariables['__pug_children'] ?? null;
        $callbackName = static::PUG_SLOT_NAME_VARIABLE.'_'.$name;

        if (is_object($children) && $children instanceof Closure) {
            $called = false;
            $children(array_merge([
                static::PUG_SLOT_NAME_VARIABLE => $name ?: '__main__',
                $callbackName => static function () use (&$called) {
                    $called = true;
                }
            ], $definedVariables));

            return !$called;
        }

        if (($definedVariables[static::PUG_SLOT_NAME_VARIABLE] ?? null) === $name) {
            $callback = $definedVariables[$callbackName] ?? null;

            if ($callback && $callback instanceof Closure) {
                $callback();
            }

            return true;
        }

        return false;
    }

    public function getOptions()
    {
        $name = 'mixin_keyword';
        $renderer = $this->getRenderer();
        $mixinKeywords = (array) ($renderer->hasOption($name) ? $renderer->getOption($name) : 'mixin');
        $mixinKeywords[] = 'component';

        return [
            'mixin_keyword' => $mixinKeywords,
            'keywords' => $this->getKeywords(),
        ];
    }

    public function getKeywords(): array
    {
        return [
            'slot' => static function (string $name): array {
                return [
                    'begin' => '<?php if (\\'.static::class.'::slot('.var_export($name, true).', get_defined_vars())) { ?>',
                    'end' => '<?php } ?>',
                ];
            },
        ];
    }

    protected function getCodeNode(NodeInterface $linkedNode, ParserNodeInterface $parentNode = null, $value = null, array $children = null)
    {
        $code = new CodeNode($linkedNode->getToken(), null, $linkedNode->getLevel(), $parentNode, $children);

        if ($value !== null) {
            $code->setValue($value);
        }

        $code->preventFromTransformation();

        return $code;
    }

    public function handleNodeEvent(NodeEvent $event): void
    {
        $call = $event->getNode();

        if ($call instanceof MixinCallNode) {
            $call->setChildren(array_merge(
                [$this->getCodeNode($call, $call, '$'.static::PUG_SLOT_NAME_VARIABLE.' = null')],
                array_map(function (NodeInterface $node) use ($call) {
                    if ($node instanceof KeywordNode && $node->getName() === 'slot') {
                        return $node;
                    }

                    return $this->getCodeNode($node, $call, null, [
                        (new TextNode($node->getToken(), null, $node->getLevel()))->setValue('if (!isset($'.static::PUG_SLOT_NAME_VARIABLE.') || $'.static::PUG_SLOT_NAME_VARIABLE.' === "__main__")'),
                        $this->getCodeNode($node, null, '// main slot'),
                        $node,
                    ]);
                }, $call->getChildren())
            ));
        }
    }

    public function handleOutputEvent(OutputEvent $event): void
    {
        $event->prependCode(implode("\n", [
            '$firstMixin = function (string ...$names) use (&$__pug_mixins) {',
            '  foreach ($names as $name) {',
            '    if (isset($__pug_mixins[$name])) {',
            '      return $name;',
            '    }',
            '  }',
            '  throw new \\InvalidArgumentException("No defined mixin/component in [".implode(", ", $names)."]");',
            '};',
            '$firstComponent = $firstMixin;',
        ]));
    }

    public function attachEvents(): void
    {
        $compiler = $this->getCompiler();
        $compiler->attach(CompilerEvent::NODE, [$this, 'handleNodeEvent']);
        $compiler->attach(CompilerEvent::OUTPUT, [$this, 'handleOutputEvent']);
    }

    public function detachEvents(): void
    {
        $compiler = $this->getCompiler();
        $compiler->detach(CompilerEvent::NODE, [$this, 'handleNodeEvent']);
        $compiler->detach(CompilerEvent::OUTPUT, [$this, 'handleOutputEvent']);
    }
}
