<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform\Gateway;

use Ekyna\Bundle\ColissimoBundle\Platform\ColissimoPlatform;
use Ekyna\Component\Colissimo;
use Ekyna\Component\Commerce\Exception\RuntimeException;
use Ekyna\Component\Commerce\Exception\ShipmentGatewayException;
use Ekyna\Component\Commerce\Shipment;

/**
 * Class RelayGateway
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class RelayGateway extends AbstractGateway
{
    /**
     * @inheritDoc
     */
    public function getCapabilities()
    {
        return static::CAPABILITY_SHIPMENT | static::CAPABILITY_RELAY;
    }

    /**
     * @inheritDoc
     */
    public function getRequirements()
    {
        return static::REQUIREMENT_MOBILE;
    }

    /**
     * @inheritDoc
     */
    public function listRelayPoints(Shipment\Gateway\Model\Address $address, float $weight)
    {
        $request = new Colissimo\Withdrawal\Request\FindPointsRequest();

        // Required
        $hash = $request->zipCode = $address->getPostalCode();
        $hash .= $request->city = $address->getCity();
        $hash .= $request->countryCode = 'FR';
        $request->shippingDate = new \DateTime('+1 day'); // TODO regarding to stock availability
        $hash .= $request->shippingDate->format('Y-m-d');

            // Optional
        $hash .= $request->address = $address->getStreet();
        //$request->weight = 1000; // 1kg
        //$request->filterRelay = true;
        //$request->lang = 'FR';
        //$request->optionInter = true;
        $request->requestId = md5($hash);

        try {
            $response = $this->getApi()->findPoints($request);

            if (!$response->isSuccess()) {
                $messages = $response->getMessages();
                if ($message = reset($messages)) {
                    throw new \Exception($message->getContent());
                }
                throw new \Exception("Colissimo API call failed.");
            }
        } catch (\Exception $e) {
            throw new ShipmentGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        $return = new Shipment\Gateway\Model\ListRelayPointResponse();

        foreach ($response->return->listePointRetraitAcheminement as $point) {
            $return->addRelayPoint($this->transformPointToRelay($point));
        }

        return $return;
    }

    /**
     * @inheritDoc
     */
    public function getRelayPoint(string $number)
    {
        $request = new Colissimo\Withdrawal\Request\FindPointRequest();

        // Required
        $request->id = $number;
        $request->date = new \DateTime('+1 day'); // TODO regarding to stock availability

        // Optional
        //$request->weight = 1000; // 1kg
        //$request->filterRelay = true;
        //$request->reseau = 'R03';
        //$request->langue = 'FR';

        try {
            $response = $this->getApi()->findPoint($request);

            if (!$response->isSuccess()) {
                throw new \Exception("Colissimo API call failed.");
            }
        } catch (\Exception $e) {
            throw new ShipmentGatewayException($e->getMessage(), $e->getCode(), $e);
        }

        $return = new Shipment\Gateway\Model\GetRelayPointResponse();

        $return->setRelayPoint($this->transformPointToRelay($response->return->pointRetraitAcheminement));

        return $return;
    }

    /**
     * Transforms a dpd relay point item to a commerce one.
     *
     * @param Colissimo\Withdrawal\Model\PointRetraitAcheminement $point
     *
     * @return Shipment\Entity\RelayPoint
     */
    protected function transformPointToRelay($point)
    {
        $country = $this->addressResolver->getCountryRepository()->findOneByCode('FR');

        $result = new Shipment\Entity\RelayPoint();

        $complement = trim($point->adresse2);
        $supplement = trim($point->adresse3);

        $result
            ->setPlatformName(ColissimoPlatform::NAME)
            ->setNumber($point->identifiant)
            ->setCompany($point->nom)
            ->setStreet(trim($point->adresse1))
            ->setComplement(empty($complement) ? null : $complement)
            ->setSupplement(empty($supplement) ? null : $supplement)
            ->setPostalCode($point->codePostal)
            ->setCity($point->localite)
            ->setCountry($country)
            ->setDistance($point->distanceEnMetre)
            ->setLongitude($point->coordGeolocalisationLongitude)
            ->setLatitude($point->coordGeolocalisationLatitude)
            ->setPlatformData([
                'type' => $point->typeDePoint,
            ]);

        $days = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ];

        foreach ($days as $number  => $string) {
            if (!isset($point->{'horairesOuverture' . $string})) {
                continue;
            }

            $opening = new Shipment\Model\OpeningHour();
            $opening->setDay($number);

            $ranges = explode(' ', $point->{'horairesOuverture' . $string});
            foreach ($ranges as $range) {
                list($from, $to) = explode('-', $range);

                if ($from != '000:00' && $to != '00:00') {
                    $opening->addRanges($from, $to);
                }
            }

            $result->addOpeningHour($opening);
        }

        return $result;
    }

    /**
     * Creates the generate label request.
     *
     * @param Shipment\Model\ShipmentInterface $shipment
     *
     * @return Colissimo\Postage\Request\GenerateLabelRequest
     */
    protected function createLabelRequest(Shipment\Model\ShipmentInterface $shipment)
    {
        if (null === $relayPoint = $shipment->getRelayPoint()) {
            throw new RuntimeException("Expected shipment with relay point.");
        }

        $request = parent::createLabelRequest($shipment);

        $request->letter->service->productCode = $this->getProductCode($shipment);

        // Relay point identifier
        $request->letter->parcel->pickupLocationId = $relayPoint->getNumber();

        return $request;
    }

    /**
     * @inheritdoc
     */
    protected function getProductCode(Shipment\Model\ShipmentInterface $shipment)
    {
        $type = $this->getRelayPointType($shipment);

        if (in_array($type, ['BPR', 'ACP', 'CDI'], true)) {
            return Colissimo\Postage\Enum\ProductCode::BPR;
        }

        if ($type === 'BDP') {
            return Colissimo\Postage\Enum\ProductCode::BDP;
        }

        if ($type === 'A2P') {
            return Colissimo\Postage\Enum\ProductCode::A2P;
        }

        if ($type === 'PCS') {
            return Colissimo\Postage\Enum\ProductCode::PCS;
        }

        throw new ShipmentGatewayException("Unexpected relay point type.");
    }

    /**
     * Returns the relay point's type.
     *
     * @param Shipment\Model\ShipmentInterface $shipment
     *
     * @return string
     */
    private function getRelayPointType(Shipment\Model\ShipmentInterface $shipment)
    {
        if (null === $relayPoint = $shipment->getRelayPoint()) {
            throw new ShipmentGatewayException("Expected shipment with relay point.");
        }

        if (empty($data = $relayPoint->getPlatformData()) || !isset($data['type'])) {
            throw new ShipmentGatewayException("Undefined relay point type");
        }

        return $data['type'];
    }
}
