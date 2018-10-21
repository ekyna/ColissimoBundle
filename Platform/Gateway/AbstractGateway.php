<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform\Gateway;

use Ekyna\Bundle\ColissimoBundle\Platform\Labelary\Client as LabelaryClient;
use Ekyna\Bundle\SettingBundle\Manager\SettingsManagerInterface;
use Ekyna\Component\Colissimo;
use Ekyna\Component\Commerce\Common\Model\AddressInterface;
use Ekyna\Component\Commerce\Common\Model\SaleAddressInterface;
use Ekyna\Component\Commerce\Common\Util\Money;
use Ekyna\Component\Commerce\Exception\ShipmentGatewayException;
use Ekyna\Component\Commerce\Order\Entity\OrderShipmentLabel;
use Ekyna\Component\Commerce\Shipment\Gateway;
use Ekyna\Component\Commerce\Shipment\Model as Shipment;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Class AbstractGateway
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
abstract class AbstractGateway extends Gateway\AbstractGateway
{
    /**
     * @var SettingsManagerInterface
     */
    protected $settingManager;

    /**
     * @var Colissimo\Api
     */
    protected $api;

    /**
     * @var LabelaryClient
     */
    private $labelary;

    /**
     * @var PhoneNumberUtil
     */
    private $phoneUtil;


    /**
     * Sets the setting manager.
     *
     * @param SettingsManagerInterface $settingManager
     */
    public function setSettingManager(SettingsManagerInterface $settingManager)
    {
        $this->settingManager = $settingManager;
    }

    /**
     * @inheritDoc
     */
    public function getActions()
    {
        return [
            Gateway\GatewayActions::SHIP,
            Gateway\GatewayActions::CANCEL,
            Gateway\GatewayActions::PRINT_LABEL,
            Gateway\GatewayActions::TRACK,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getMaxWeight()
    {
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function ship(Shipment\ShipmentInterface $shipment)
    {
        $this->supportShipment($shipment);

        if ($this->hasTrackingNumber($shipment)) {
            return false;
        }

        if (!$this->doShipment($shipment)) {
            return false;
        }

        $this->persister->persist($shipment);

        return parent::ship($shipment);
    }

    /**
     * @inheritDoc
     */
    public function track(Shipment\ShipmentDataInterface $shipment)
    {
        if (!$this->supportAction(Gateway\GatewayActions::TRACK)) {
            return null;
        }

        $this->supportShipment($shipment);

        if (empty($number = $shipment->getTrackingNumber())) {
            return null;
        }

        // TODO
    }

    /**
     * @inheritDoc
     */
    public function printLabel(Shipment\ShipmentDataInterface $shipment, array $types = null)
    {
        $this->supportShipment($shipment);

        if ($shipment instanceof Shipment\ShipmentParcelInterface) {
            $s = $shipment->getShipment();
        } else {
            $s = $shipment;
        }

        /** @var Shipment\ShipmentInterface $s */
        $this->ship($s);

        if (empty($types)) {
            $types = $this->getDefaultLabelTypes();
        }

        $labels = [];

        if ($shipment instanceof Shipment\ShipmentInterface) {
            if ($shipment->hasParcels()) {
                foreach ($shipment->getParcels() as $parcel) {
                    $this->addShipmentLabel($labels, $parcel, $types);
                }
            } else {
                $this->addShipmentLabel($labels, $shipment, $types);
            }
        } elseif ($shipment instanceof Shipment\ShipmentParcelInterface) {
            $this->addShipmentLabel($labels, $shipment, $types);
        } else {
            throw new ShipmentGatewayException(
                "Expected instance of " . Shipment\ShipmentInterface::class
                . " or " . Shipment\ShipmentParcelInterface::class
            );
        }

        return $labels;
    }

    /**
     * Performs the shipment through Colissimo API.
     *
     * @param Shipment\ShipmentInterface $shipment
     *
     * @return bool
     */
    protected function doShipment(Shipment\ShipmentInterface $shipment)
    {
        $request = $this->createLabelRequest($shipment);

        try {
            $response = $this->getApi()->generateLabel($request);

        } catch (\Exception $e) {
            throw new ShipmentGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        if (!$response->isSuccess()) {
            $message = "Colissimo API call failed";

            /** @var Colissimo\Base\Response\Message $error */
            if (false !== $error = reset($response->getMessages())) {
                $message .= sprintf("\n[%s] %s", $error->getId(), $error->getContent());
            }

            throw new ShipmentGatewayException($message);
        }

        $shipment->setTrackingNumber($response->labelV2Response->parcelNumber);

        $this->buildLabels($response, $shipment);

        return true;
    }

    /**
     * Creates the generate label request.
     *
     * @param Shipment\ShipmentInterface $shipment
     *
     * @return Colissimo\Postage\Request\GenerateLabelRequest
     */
    protected function createLabelRequest(Shipment\ShipmentInterface $shipment)
    {
        $request = new Colissimo\Postage\Request\GenerateLabelRequest();

        $request->outputFormat->outputPrintingType = Colissimo\Postage\Enum\OutputPrintingType::ZPL_10x15_203dpi;

        // Sender
        $shipper = $this->addressResolver->resolveSenderAddress($shipment, true);
        $request->letter->sender->address = $this->createAddress($shipper);
        $request->letter->sender->address->email = $this->settingManager->getParameter('general.admin_email');
        $request->letter->sender->senderParcelRef = $shipment->getSale()->getNumber();

        // Receiver
        $receiver = $this->addressResolver->resolveReceiverAddress($shipment, true);
        $request->letter->addressee->address = $this->createAddress($receiver);

        // Parcel
        if (0 >= $weight = $shipment->getWeight()) {
            $weight = $this->weightCalculator->calculateShipment($shipment);
        }
        $request->letter->parcel->weight = round($weight, 2); // kg
        // TODO $request->letter->parcel->nonMachinable;        // Colis non mécanisable
        // Only for products DOS (France), COL, BPR, A2P, CDS, CORE, CORI and COLI
        // TODO $request->letter->parcel->insuranceValue;       // Incompatible with recommendationLevel
        // TODO $request->letter->parcel->recommendationLevel;  // Incompatible with insuranceValue
        // Only for products DOS (France) and COL
        // TODO $request->letter->parcel->COD;                  // Contre remboursement
        // TODO $request->letter->parcel->CODAmount;            // Montant attendu pour contre remboursement
        // TODO $request->letter->parcel->returnReceipt;        // Avis de réception
        // TODO $request->letter->parcel->instructions;         // Indications complétaire ou motif du retour
        // Only for product CDS
        // TODO $request->letter->parcel->ftd;                  // Franc de taxes et droits (obligatoire pour Outre Mer)

        // Service
        $request->letter->service->productCode = $this->getProductCode($shipment);
        $request->letter->service->depositDate = new \DateTime('now');
        $request->letter->service->commercialName = $this->settingManager->getParameter('general.site_name');
        // TODO $request->letter->service->totalAmount

        // Customs declaration
        // TODO $total = $this->buildCustomsDeclaration($request->letter->customsDeclarations, $shipment);

        return $request;
    }

    /**
     * Builds the customs declarations.
     *
     * @param Colissimo\Postage\Model\CustomsDeclarations $declaration
     * @param Shipment\ShipmentInterface $shipment
     *
     * @return float The customs total amount.
     */
    protected function buildCustomsDeclaration(
        Colissimo\Postage\Model\CustomsDeclarations $declaration,
        Shipment\ShipmentInterface $shipment
    ) {
        $total = 0;
        $currency = $shipment->getSale()->getCurrency()->getCode();

        $declaration->includeCustomsDeclarations = true;

        foreach ($shipment->getItems() as $item) {
            $saleItem = $item->getSaleItem();

            $article = new Colissimo\Postage\Model\Article();

            $article->description = substr($saleItem->getDesignation(), 0, 64);
            $article->quantity = $item->getQuantity();
            $article->weight = $saleItem->getWeight();
            $article->value = $price = Money::round($saleItem->getNetPrice(), $currency); // TODO VAT
            // TODO $article->hsCode;
            // TODO $article->originCountry;
            // TODO $article->currency;
            // TODO $article->artref;
            // TODO $article->originalIdent;

            $declaration->contents->article[] = $article;

            $total += Money::round($price * $item->getQuantity(), $currency);
        }

        // TODO $declaration->contents->category;
        $declaration->importersReference;
        if ($shipment->isReturn()) {
            $declaration->flowTransport = 'IMPORT';
        }
        //$declaration->importersContact;
        //$declaration->officeOrigin;
        //$declaration->comments;
        $declaration->invoiceNumber;
        $declaration->licenceNumber;
        $declaration->certificatNumber;
        $declaration->importerAddress;

        return $total;
    }

    /**
     * Builds the shipment's labels from the API response.
     *
     * @param Colissimo\Base\Response\ResponseInterface $response
     * @param Shipment\ShipmentInterface                $shipment
     */
    protected function buildLabels(
        Colissimo\Base\Response\ResponseInterface $response,
        Shipment\ShipmentInterface $shipment
    ) {
        foreach ($response->getAttachments() as $attachment) {
            if ($attachment->getType() === 'label') {
                $type = $shipment->isReturn() ? OrderShipmentLabel::TYPE_RETURN : OrderShipmentLabel::TYPE_SHIPMENT;
                $format = OrderShipmentLabel::FORMAT_PNG;
                $size = OrderShipmentLabel::SIZE_A6;

                try {
                    $labelResponse = $this->getLabelary()->convert($attachment->getContent());
                } catch (\Exception $e) {
                    throw new ShipmentGatewayException("Failed to create shipment label from ZPL data: " . $e->getMessage());
                }
                $content = $labelResponse['content'];

            } elseif ($attachment->getType() === 'cn23') {
                $type = OrderShipmentLabel::TYPE_CUSTOMS;
                $format = OrderShipmentLabel::FORMAT_PDF;
                $size = OrderShipmentLabel::SIZE_A4;
                $content = $attachment->getContent();
            } else {
                continue;
            }

            $shipment->addLabel($this->createLabel($content, $type, $format, $size));
        }
    }

    /**
     * Creates a Colissimo address from the given shipment address.
     *
     * @param AddressInterface $address
     *
     * @return Colissimo\Postage\Model\Address
     */
    protected function createAddress(AddressInterface $address)
    {
        $return = new Colissimo\Postage\Model\Address();

        if (!empty($data = $address->getCompany())) {
            $return->companyName = $data;
        }

        $return->lastName = $address->getLastName();
        $return->firstName = $address->getFirstName();

        if (!empty($data = $address->getSupplement())) {
            $return->line0 = $data;
        }
        if (!empty($data = $address->getComplement())) {
            $return->line1 = $data;
        }
        $return->line2 = $address->getStreet();
        if (!empty($data = $address->getExtra())) {
            $return->line3 = $data;
        }

        $return->countryCode = $address->getCountry()->getCode();
        $return->city = $address->getCity();
        $return->zipCode = $address->getPostalCode();

        if (!empty($data = $address->getPhone())) {
            $return->phoneNumber = $this->formatPhoneNumber($data);
        }
        if (!empty($data = $address->getMobile())) {
            $return->mobileNumber = $this->formatPhoneNumber($data);
        }

        if (!empty($data = $address->getDigicode1())) {
            $return->doorCode1 = $data;
        }
        if (!empty($data = $address->getDigicode2())) {
            $return->doorCode2 = $data;
        }
        if ($address instanceof SaleAddressInterface && null !== $sale = $address->getSale()) {
            $return->email = $sale->getEmail();
        }
        if (!empty($data = $address->getIntercom())) {
            $return->intercom = $data;
        }

        // TODO $return->language

        return $return;
    }

    /**
     * Creates and adds the shipment label to the given list.
     *
     * @param array                          $labels
     * @param Shipment\ShipmentDataInterface $shipment
     * @param array                          $types
     */
    protected function addShipmentLabel(array &$labels, Shipment\ShipmentDataInterface $shipment, array $types)
    {
        if (!$shipment->hasLabels()) {
            throw new ShipmentGatewayException("Failed to retrieve shipment labels.");
        }

        foreach ($shipment->getLabels() as $label) {
            if (in_array($label->getType(), $types, true)) {
                $labels[] = $label;
            }
        }
    }

    /**
     * Returns the default label types.
     *
     * @return array
     */
    protected function getDefaultLabelTypes()
    {
        return [Shipment\ShipmentLabelInterface::TYPE_SHIPMENT];
    }

    /**
     * Returns the api.
     *
     * @return Colissimo\Api
     */
    protected function getApi()
    {
        if ($this->api) {
            return $this->api;
        }

        return $this->api = new Colissimo\Api($this->config);
    }

    /**
     * Returns the labelary client.
     *
     * @return LabelaryClient
     */
    protected function getLabelary()
    {
        if ($this->labelary) {
            return $this->labelary;
        }

        return $this->labelary = new LabelaryClient();
    }

    /**
     * Formats the phone number.
     *
     * @param mixed $number
     *
     * @return string
     */
    protected function formatPhoneNumber($number)
    {
        if ($number instanceof PhoneNumber) {
            if (null === $this->phoneUtil) {
                $this->phoneUtil = PhoneNumberUtil::getInstance();
            }

            return str_replace(' ', '', $this->phoneUtil->format($number, PhoneNumberFormat::INTERNATIONAL));
        }

        return (string)$number;
    }

    /**
     * Returns the product code for the given shipment.
     *
     * @see colissimo_ws_affranchissement.pdf page 37
     * @see colissimo_ws_livraison.pdf page 34
     *
     * @param Shipment\ShipmentInterface $shipment
     *
     * @return string
     */
    abstract protected function getProductCode(Shipment\ShipmentInterface $shipment);
}
