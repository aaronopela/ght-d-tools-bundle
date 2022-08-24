<?php

namespace GHT\DevToolsBundle;

use GHT\DevToolsBundle\DependencyInjection\GHTDevToolsExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GHTDevToolsBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            return new GHTDevToolsExtension();
        }
    }
}
