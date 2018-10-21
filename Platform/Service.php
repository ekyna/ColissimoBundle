<?php

namespace Ekyna\Bundle\ColissimoBundle\Platform;

use Ekyna\Component\Commerce\Exception\InvalidArgumentException;

/**
 * Class Service
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
abstract class Service
{
    const HOME_UNSIGNED = 'HomeUnsigned';
    const HOME_SIGNED   = 'HomeSigned';
    const RELAY         = 'Relay';
    const RETURN        = 'Return';

    // TODO (see colissimo_ws_affranchissement.pdf page 43)
    //const NEXT_DAY       = 'NextDay';
    //const OM_ECO       = 'OmEco';
    //const OM_RETURN       = 'OmReturn';
    //const EXPERT       = 'Expert';


    /**
     * Returns the available services codes.
     *
     * @return array|string[]
     */
    static public function getCodes()
    {
        return [
            static::HOME_UNSIGNED,
            static::HOME_SIGNED,
            static::RELAY,
            static::RETURN,
        ];
    }

    /**
     * Returns whether or not the given code is valid.
     *
     * @param string $code
     * @param bool   $throw
     *
     * @return bool
     */
    static public function isValid($code, $throw = true)
    {
        if (in_array($code, static::getCodes())) {
            return true;
        }

        if ($throw) {
            throw new InvalidArgumentException("Unexpected Colissmo service code.");
        }

        return false;
    }

    /**
     * Returns the label for the given product code.
     *
     * @param string $code
     *
     * @return string
     */
    static public function getLabel($code)
    {
        static::isValid($code);

        switch ($code) {
            case static::HOME_UNSIGNED:
                return 'Colissimo Domicile sans signature';
            case static::HOME_SIGNED:
                return 'Colissimo Domicile avec signature';
            case static::RELAY:
                return 'Colissimo Point retrait';
            case static::RETURN:
                return 'Colissimo Retour';
            default:
                return 'Colissimo Domicile';
        }
    }

    /**
     * Returns the choices.
     *
     * @return array
     */
    static public function getChoices()
    {
        $choices = [];

        foreach (static::getCodes() as $code) {
            $choices[static::getLabel($code)] = $code;
        }

        return $choices;
    }

    /**
     * Disabled constructor.
     */
    private function __construct()
    {
    }
}