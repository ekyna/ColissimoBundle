<?php

declare(strict_types=1);

namespace Ekyna\Bundle\ColissimoBundle\Platform;

use Ekyna\Component\Commerce\Exception\InvalidArgumentException;

/**
 * Class Service
 * @package Ekyna\Bundle\ColissimoBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
final class Service
{
    public const HOME_UNSIGNED = 'HomeUnsigned';
    public const HOME_SIGNED   = 'HomeSigned';
    public const RELAY         = 'Relay';
    public const RETURN        = 'Return';

    // TODO (see colissimo_ws_affranchissement.pdf page 43)
    //public const NEXT_DAY       = 'NextDay';
    //public const OM_ECO       = 'OmEco';
    //public const OM_RETURN       = 'OmReturn';
    //public const EXPERT       = 'Expert';


    /**
     * Returns the available services codes.
     *
     * @return array|string[]
     */
    public static function getCodes(): array
    {
        return [
            self::HOME_UNSIGNED,
            self::HOME_SIGNED,
            self::RELAY,
            self::RETURN,
        ];
    }

    /**
     * Returns whether the given code is valid.
     */
    public static function isValid(string $code, bool $throw = true): bool
    {
        if (in_array($code, self::getCodes())) {
            return true;
        }

        if ($throw) {
            throw new InvalidArgumentException('Unexpected Colissmo service code.');
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
    public static function getLabel(string $code): string
    {
        self::isValid($code);

        switch ($code) {
            case self::HOME_UNSIGNED:
                return 'Colissimo Domicile sans signature';
            case self::HOME_SIGNED:
                return 'Colissimo Domicile avec signature';
            case self::RELAY:
                return 'Colissimo Point retrait';
            case self::RETURN:
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
    public static function getChoices(): array
    {
        $choices = [];

        foreach (self::getCodes() as $code) {
            $choices[self::getLabel($code)] = $code;
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
