<?php

declare(strict_types=1);

namespace Ekyna\Bundle\ColissimoBundle\Platform;

use Ekyna\Bundle\SettingBundle\Manager\SettingManagerInterface;
use Ekyna\Component\Commerce\Exception\InvalidArgumentException;
use Ekyna\Component\Commerce\Shipment\Gateway\AbstractPlatform;
use Ekyna\Component\Commerce\Shipment\Gateway\GatewayInterface;
use Ekyna\Component\Commerce\Shipment\Gateway\PlatformActions;
use Symfony\Component\Config\Definition;

/**
 * Class ColissimoPlatform
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class ColissimoPlatform extends AbstractPlatform
{
    public const NAME = 'Colissimo';

    protected SettingManagerInterface $settingManager;
    protected array $config;

    public function __construct(SettingManagerInterface $settingManager, array $config)
    {
        $this->settingManager = $settingManager;
        $this->config = $config;
    }

    public function getName(): string
    {
        return static::NAME;
    }

    public function getActions(): array
    {
        return [
            PlatformActions::PRINT_LABELS,
        ];
    }

    public function getConfigDefaults(): array
    {
        return [
            'login'    => $this->config['login'],
            'password' => $this->config['password'],
            'service'  => Service::HOME_UNSIGNED,
        ];
    }

    public function createGateway(string $name, array $config = []): GatewayInterface
    {
        $config = array_replace($this->config, $this->processGatewayConfig($config));

        $class = sprintf('Ekyna\Bundle\ColissimoBundle\Platform\Gateway\%sGateway', $config['service']);
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf("Unexpected service '%s'", $config['service']));
        }

        /** @var \Ekyna\Bundle\ColissimoBundle\Platform\Gateway\AbstractGateway $gateway */
        $gateway = new $class($this, $name, $config);

        $gateway->setSettingManager($this->settingManager);

        return $gateway;
    }

    protected function createConfigDefinition(Definition\Builder\ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->scalarNode('login')
                    ->info('Numéro client')
                    ->isRequired()
                ->end()
                ->scalarNode('password')
                    ->info('Mot de passe')
                    ->isRequired()
                ->end()
                ->enumNode('service')
                    ->info('Service')
                    ->values(Service::getChoices())
                    ->isRequired()
                ->end()
            ->end();
    }
}
