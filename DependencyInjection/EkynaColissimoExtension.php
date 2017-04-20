<?php

declare(strict_types=1);

namespace Ekyna\Bundle\ColissimoBundle\DependencyInjection;

use Ekyna\Bundle\ColissimoBundle\Platform\ColissimoPlatform;
use Ekyna\Component\Commerce\Bridge\Symfony\DependencyInjection\ShipmentGatewayRegistryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class EkynaColissimoExtension
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class EkynaColissimoExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $platformDef = new Definition(ColissimoPlatform::class);
        $platformDef->addTag(ShipmentGatewayRegistryPass::PLATFORM_TAG);
        $platformDef->addArgument(new Reference('ekyna_setting.manager'));
        $platformDef->addArgument($config);
        $container->setDefinition('ekyna_colissimo.gateway_platform', $platformDef);
    }
}
