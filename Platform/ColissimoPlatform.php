<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform;

use Ekyna\Bundle\SettingBundle\Manager\SettingsManagerInterface;
use Ekyna\Component\Commerce\Exception\InvalidArgumentException;
use Ekyna\Component\Commerce\Shipment\Gateway\AbstractPlatform;
use Ekyna\Component\Commerce\Shipment\Gateway\PlatformActions;
use Symfony\Component\Config\Definition;

/**
 * Class ColissimoPlatform
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class ColissimoPlatform extends AbstractPlatform
{
    const NAME = 'Colissimo';

    /**
     * @var SettingsManagerInterface
     */
    protected $settingManager;

    /**
     * @var array
     */
    protected $config;


    /**
     * Constructor.
     *
     * @param SettingsManagerInterface $settingManager
     * @param array                    $config
     */
    public function __construct(SettingsManagerInterface $settingManager, array $config)
    {
        $this->settingManager = $settingManager;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * @inheritDoc
     */
    public function getActions()
    {
        return [
            PlatformActions::PRINT_LABELS,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getConfigDefaults()
    {
        return [
            'login'    => $this->config['login'],
            'password' => $this->config['password'],
            'service'  => Service::HOME_UNSIGNED,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createGateway($name, array $config = [])
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

    /**
     * @inheritDoc
     */
    protected function createConfigDefinition(Definition\Builder\NodeDefinition $rootNode)
    {
        /** @noinspection PhpUndefinedMethodInspection */
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