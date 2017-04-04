<?php

namespace GHT\DevToolsBundle;

use GHT\DevToolsBundle\DependencyInjection\GHTDevToolsExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GHTDevToolsBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            return new GHTDevToolsExtension();
        }
    }
}
