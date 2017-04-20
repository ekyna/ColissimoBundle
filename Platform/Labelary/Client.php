<?php

declare(strict_types=1);

namespace Ekyna\Bundle\ColissimoBundle\Platform\Labelary;

use Ekyna\Component\Commerce\Exception\InvalidArgumentException;
use GuzzleHttp\Client as HttpClient;

/**
 * Class Client
 * @package Ekyna\Bundle\DpdBundle
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class Client
{
    public const ENDPOINT = 'https://api.labelary.com/v1';

    public const ENUM_DPMM_6  = '6dpmm';
    public const ENUM_DPMM_8  = '8dpmm';
    public const ENUM_DPMM_12 = '12dpmm';
    public const ENUM_DPMM_24 = '24dpmm';

    private HttpClient $httpClient;

    public function __construct(HttpClient $httpClient = null)
    {
        $this->httpClient = $httpClient ?: new HttpClient();
    }

    /**
     * Converts ZPL data to PNG image.
     *
     * @see http://labelary.com/service.html#parameters
     *
     * @param string $zpl
     * @param array  $options
     *
     * @return array ['type' => (string), 'content' => (string)]
     */
    public function convert(string $zpl, array $options = []): array
    {
        if (empty($zpl)) {
            throw new InvalidArgumentException('Empty ZPL input.');
        }

        if (!isset($options['dpmm'])) {
            $options['dpmm'] = self::ENUM_DPMM_8;
        }

        if (!isset($options['width'])) {
            $options['width'] = 4;
        }

        if (!isset($options['height'])) {
            $options['height'] = 6;
        }

        if (!isset($options['index'])) {
            $options['index'] = 0;
        }

        if (!isset($options['response'])) {
            $options['response'] = 'image/png';
        }

        $headers = [
            'Accept' => $options['response'],
        ];
        if (isset($options['rotate'])) {
            $headers['X-Rotation'] = (int)$options['rotate'];
        }

        $url = sprintf(
            '%s/printers/%s/labels/%sx%s/%s/',
            self::ENDPOINT,
            $options['dpmm'],
            $options['width'],
            $options['height'],
            $options['index']
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'body'    => $zpl,
        ]);

        return [
            'type'    => $response->getHeaders()['Content-Type'][0],
            'content' => $response->getBody()->getContents(),
        ];
    }
}
