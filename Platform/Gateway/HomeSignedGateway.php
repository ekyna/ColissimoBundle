<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform\Gateway;

use Ekyna\Component\Colissimo;
use Ekyna\Component\Commerce\Shipment\Model as Shipment;

/**
 * Class HomeSignedGateway
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class HomeSignedGateway extends AbstractGateway
{
    /**
     * @inheritdoc
     */
    protected function getProductCode(Shipment\ShipmentInterface $shipment)
    {
        // TODO Si Outre-Mer:
        // return Colissimo\Postage\Enum\ProductCode::CDS

        return Colissimo\Postage\Enum\ProductCode::DOS;
    }
}
