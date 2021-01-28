<?php

use GuzzleHttp\Client;

class BlueOceanPay
{
    /**
     * Fixed.
     *
     * @var float
     */
    protected $fixed = 0.05;

    /**
     * GuzzleHttp client instance.
     *
     * @var GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * App Id.
     *
     * @var string
     */
    protected $appid;

    /**
     * Key.
     *
     * @var string
     */
    protected $key;

    /**
     * Create BlueOceanPay instance.
     *
     * @param   string  $appid
     * @param   string  $key
     *
     * @return  void
     */
    public function __construct(?string $appid = null, ?string $key = null)
    {
        $this->appid = $appid;
        $this->key = $key;

        $this->httpClient = new Client([
            'base_uri' => 'https://api.hk.blueoceanpay.com',
            'timeout' => 30,
            'headers' => [
                'BoPayPos/1.1.0 NetType/WIFI Language/zh_CN',
            ]
        ]);
    }

    /**
     * WHMCS configuration.
     *
     * @return  array
     */
    public function config()
    {
        return [
            'FriendlyName' => array(
                'Type' => 'System',
                'Value' => 'UnionPay via BlueOcean',
            ),
            'appid' => array(
                'FriendlyName' => 'App Id',
                'Type' => 'text',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter your APP ID here',
            ),

            'key' => array(
                'FriendlyName' => 'Pub Key',
                'Type' => 'text',
                'Size' => '25',
                'Default' => '',
                'Description' => 'Enter your Pub key here',
            ),
        ];
    }

    /**
     * Wrap payload data.
     *
     * @param   array  $parameters
     *
     * @return  array
     */
    protected function payloadWrapper(array $parameters = [])
    {
        $parameters['appid'] = $this->appid;
        $parameters['key'] = $this->key;
        $parameters['sign'] = $this->signData($parameters, $this->key);

        return $parameters;
    }

    /**
     * Send payment request.
     *
     * @param   array  $parameters
     *
     * @return  array
     */
    public function pay(array $parameters = [])
    {
        $parameters['total_fee'] = $this->convertToHKD($parameters['total_fee']);
        $parameters = $this->payloadWrapper($parameters);

        $response = $this->httpClient->post('/payment/pay', [
            'json' => $parameters
        ])->getBody()->getContents();

        return json_decode($response, true);
    }

    /**
     * Refund.
     *
     * @param   array  $parameters
     *
     * @return  array
     */
    public function refund(array $parameters = [])
    {
        $parameters['refund_fee'] = $this->convertToHKD($parameters['refund_fee']);
        $parameters = $this->payloadWrapper($parameters);

        $response = $this->httpClient->post('/payment/pay', [
            'json' => $parameters
        ])->getBody()->getContents();
        var_dump($parameters, $response);die;

        return json_decode($response, true);
    }

    /**
     * Get alipay exchange rate.
     *
     * @return  array
     */
    public function exchangeRate()
    {
        $parameters = $this->payloadWrapper([
            'adapter' => 'alipay',
        ]);

        $exchangeRates = $this->httpClient->post('/exchangerate/fetch', [
            'json' => $parameters,
        ])->getBody()->getContents();

        return json_decode($exchangeRates, true)['data'];
    }

    /**
     * Convert to USD.
     *
     * @param   float   $amount
     * @param   string  $from
     * @param   string  $to
     *
     * @return  float
     */
    public function convertToUSD(float $amount)
    {
        $exchangeRates = $this->exchangeRate();
        $cny = $amount * $exchangeRates['HKD']['rate'];

        $dest = $cny / $exchangeRates['USD']['rate'];

        return round($dest, 2);
    }

    /**
     * Convert to USD.
     *
     * @param   float   $amount
     * @param   string  $from
     * @param   string  $to
     *
     * @return  float
     */
    public function convertToHKD(float $amount)
    {
        $exchangeRates = $this->exchangeRate();
        $cny = $amount * $exchangeRates['USD']['rate'];

        $dest = $cny / $exchangeRates['HKD']['rate'];

        return round($dest, 2) * 100;
    }

    /**
     * 签名.
     *
     * @param   array  $data
     * @param   string  $key
     *
     * @return  string
     */
    public function signData($data, $key)
    {
        $ignoreKeys = ['sign', 'key'];
        ksort($data);
        $signString = '';
        foreach ($data as $k => $v) {
            if (in_array($k, $ignoreKeys)) {
                unset($data[$k]);
                continue;
            }
            $signString .= "{$k}={$v}&";
        }
        $signString .= "key={$key}";

        return strtoupper(md5($signString));
    }
}
