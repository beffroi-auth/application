<?php

declare(strict_types=1);

namespace App\Configuration;

use function is_string;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

/**
 * Accepts a placeholder for the whole list, which an array node of symfony/config 8.2 refuses.
 */
final class EnvironmentListNode extends PrototypedArrayNode
{
    private bool $wrapsAString = false;

    public static function of(PrototypedArrayNode $built, bool $wrapsAString = false): self
    {
        $list = new ReflectionClass(self::class)->newInstanceWithoutConstructor();
        foreach ([BaseNode::class, ArrayNode::class, PrototypedArrayNode::class] as $class) {
            foreach (new ReflectionClass($class)->getProperties() as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class || ! $property->isInitialized($built)) {
                    continue;
                }
                $property->setValue($list, $property->getValue($built));
            }
        }
        $prototype = $list->getPrototype();
        if ($prototype instanceof BaseNode) {
            new ReflectionProperty(BaseNode::class, 'parent')->setValue($prototype, $list);
        }
        $list->wrapsAString = $wrapsAString;

        return $list;
    }

    protected function allowPlaceholders(): bool
    {
        return true;
    }

    protected function validateType(mixed $value): void
    {
        if ($this->isHandlingPlaceholder() || $this->wrapsAString && is_string($value)) {
            return;
        }
        parent::validateType($value);
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if ($this->isHandlingPlaceholder()) {
            return $value;
        }

        return parent::normalizeValue($this->wrapsAString && is_string($value) ? [$value] : $value);
    }

    protected function mergeValues(mixed $leftSide, mixed $rightSide): mixed
    {
        return $this->isHandlingPlaceholder() ? $rightSide : parent::mergeValues($leftSide, $rightSide);
    }

    protected function finalizeValue(mixed $value): mixed
    {
        return $this->isHandlingPlaceholder() ? $value : parent::finalizeValue($value);
    }
}
