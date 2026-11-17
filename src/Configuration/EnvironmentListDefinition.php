<?php

declare(strict_types=1);

namespace App\Configuration;

use function sprintf;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ExprBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\PrototypedArrayNode;

final class EnvironmentListDefinition extends ArrayNodeDefinition
{
    private bool $wrapsAString = false;

    public function wrapsAString(): static
    {
        $this->wrapsAString = true;

        return $this;
    }

    /**
     * @throws InvalidConfigurationException
     *
     * @return array<array-key, mixed>
     */
    public function normalized(mixed $list, string $path): array
    {
        try {
            $normalized = new Processor()->process($this->getNode(true), [$list]);
        } catch (InvalidConfigurationException $refusal) {
            throw new InvalidConfigurationException(
                sprintf(
                    'The "beffroi" configuration key "%s" carried by the environment is refused: %s',
                    $path,
                    $refusal->getMessage(),
                ),
                0,
                $refusal,
            );
        }

        return $normalized;
    }

    /**
     * @throws InvalidConfigurationException
     */
    protected function createNode(): NodeInterface
    {
        // A placeholder for the whole list is a string, which the reference and the schema then show as allowed.
        $this->allowedTypes = [...($this->allowedTypes ?? []), ExprBuilder::TYPE_STRING];
        $node = parent::createNode();
        if (! $node instanceof PrototypedArrayNode) {
            throw new InvalidConfigurationException(sprintf('The list "%s" of the configuration declares the shape of its entries.', $this->name ?? ''));
        }

        return EnvironmentListNode::of($node, $this->wrapsAString);
    }
}
