<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform\Gateway;

use Ekyna\Component\Colissimo;
use Ekyna\Component\Commerce\Shipment\Model as Shipment;

/**
 * Class ReturnGateway
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class ReturnGateway extends AbstractGateway
{
    /**
     * @inheritDoc
     */
    public function getCapabilities()
    {
        return static::CAPABILITY_RETURN;
    }

    /**
     * @inheritDoc
     */
    protected function createLabelRequest(Shipment\ShipmentInterface $shipment)
    {
        // TODO Use API getProductInter method for international
        // TODO Use API planPickup method

        $request = new Colissimo\Postage\Request\GenerateLabelRequest();

        $request->outputFormat->outputPrintingType = Colissimo\Postage\Enum\OutputPrintingType::ZPL_10x15_203dpi;
        // TODO Product 7R (international return): Colissimo\Postage\Enum\OutputPrintingType::PDF_A4_300dpi;
        $request->outputFormat->returnType = Colissimo\Postage\Enum\ReturnType::SendPDFByMail;

        // Sender
        $shipper = $this->addressResolver->resolveReceiverAddress($shipment, true);
        $request->letter->sender->address = $this->createAddress($shipper);
        //$request->letter->sender->senderParcelRef = $shipment->getSale()->getNumber();

        // Receiver
        $receiver = $this->addressResolver->resolveSenderAddress($shipment, true);
        $request->letter->addressee->address = $this->createAddress($receiver);
        $request->letter->addressee->address->email = $this->settingManager->getParameter('general.admin_email');
        $request->letter->addressee->addresseeParcelRef = $shipment->getSale()->getNumber();
        // TODO $request->letter->addressee->codeBarForReference = null;
        // TODO $request->letter->addressee->serviceInfo = null;

        // Parcel
        if (0 >= $weight = $shipment->getWeight()) {
            $weight = $this->weightCalculator->calculateShipment($shipment);
        }
        $request->letter->parcel->weight = round($weight, 2); // kg
        // TODO  $request->letter->parcel->insuranceValue;
        // TODO $request->letter->parcel->nonMachinable;

        // Service
        $request->letter->service->productCode = $this->getProductCode($shipment);
        $request->letter->service->depositDate = new \DateTime('+1 day');
        // TODO Use getListMailBoxPickingDates API methods (need configuration form field)
        // TODO $request->letter->service->mailBoxPickingDate = 1;
        // TODO $request->letter->service->mailBoxPicking = new \DateTime('+1 day');
        // TODO $request->letter->service->totalAmount

        // Customs declaration
        // TODO $request->letter->customsDeclarations

        return $request;
    }

    /**
     * @inheritDoc
     */
    protected function getProductCode(Shipment\ShipmentInterface $shipment)
    {
        // TODO Si Outre-Mer :
        // return Colissimo\Postage\Enum\ProductCode::CORI // Colissimo Retour OM
        // return Colissimo\Postage\Enum\ProductCode::ECO // Colissimo Eco OM

        return Colissimo\Postage\Enum\ProductCode::CORE;
    }

    /**
     * @inheritDoc
     */
    public function supportShipment(Shipment\ShipmentDataInterface $shipment, $throw = true)
    {
        // TODO Only France / Outre mer

        return parent::supportShipment($shipment, $throw);
    }
}
