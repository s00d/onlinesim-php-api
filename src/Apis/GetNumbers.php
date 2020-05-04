<?php

namespace s00d\OnlineSimApi\Apis;

use RuntimeException;
use s00d\OnlineSimApi\Exceptions\NoNumberException;
use s00d\OnlineSimApi\Exceptions\RequestException;
use Exception;
use s00d\OnlineSimApi\Responses\GetNumbers\Close;
use s00d\OnlineSimApi\Responses\GetNumbers\Get;
use s00d\OnlineSimApi\Responses\GetNumbers\Next;
use s00d\OnlineSimApi\Responses\GetNumbers\Price;
use s00d\OnlineSimApi\Responses\GetNumbers\Repeat;
use s00d\OnlineSimApi\Responses\GetNumbers\Service;
use s00d\OnlineSimApi\Responses\GetNumbers\ServiceNumber;
use s00d\OnlineSimApi\Responses\GetNumbers\State;
use s00d\OnlineSimApi\Responses\GetNumbers\StateOne;
use s00d\OnlineSimApi\Responses\GetNumbers\TariffCountryOne;
use s00d\OnlineSimApi\Responses\GetNumbers\Tariffs;

class GetNumbers extends GetUser
{
    /**
     * @param string $service - https://onlinesim.ru/docs/api/ru#getnum
     * @param int $country
     * @return Price
     * @throws RequestException|NoNumberException
     */
    public function price($service, $country = 7) {
        $data = [
            'service' => $service,
            'country' => $country,
        ];
        return new Price($this->request->send('getPrice', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnum
     * @param string $service - https://onlinesim.ru/docs/api/ru#getnum
     * @param int $country
     * @param array $reject
     * @param bool $extension
     * @return Get
     * @throws RequestException|NoNumberException
     */
    public function get($service, $country = 7, $reject = [], $extension = false) {
        $data = [
            'service' => $service,
            'country' => $country,
            'reject' => $reject,
            'extension' => $extension?2:0,
        ];
        return new Get($this->request->send('getNum', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate
     * @param int $message_to_code - 0|1
     * @param string $orderby - ASC|DESC
     * @param bool $msg_list
     * @param bool $clean
     * @param bool $repeat
     * @return State
     * @throws RequestException
     * @throws Exception
     */
    public function state($message_to_code = 1, $orderby = 'ASC', $msg_list = true, $clean = true, $repeat = false) {
        $data = [
            'message_to_code' => $message_to_code,
            'orderby' => $orderby,
            'msg_list' => $msg_list?1:0,
            'clean' => $clean?1:0,
            'type' => $repeat?'repeat':'index',
        ];

        try {
            return new State($this->request->send('getState', $data, 'GET') || []);
        } catch (NoNumberException $e) {
            return new State();
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate
     * @param int $tzid
     * @param int $message_to_code - 0|1
     * @param bool $msg_list
     * @param bool $clean
     * @param bool $repeat
     * @return StateOne|null
     * @throws RequestException
     * @throws Exception
     */
    public function stateOne($tzid, $message_to_code = 1, $msg_list = true, $clean = true, $repeat = false) {
        $data = [
            'message_to_code' => $message_to_code,
            'msg_list' => $msg_list?1:0,
            'clean' => $clean?1:0,
            'tzid' => $tzid,
            'type' => $repeat?'repeat':'index',
        ];
        try {
            return new StateOne($this->request->send('getState', $data, 'GET')[0]);
        } catch (NoNumberException $e) {
            return null;
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setoperationrevise
     * @param int $tzid
     * @return Next
     * @throws RequestException|NoNumberException
     */
    public function next($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new Next($this->request->send('setOperationRevise', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setoperationok
     * @param int $tzid
     * @return Close
     * @throws RequestException|NoNumberException
     */
    public function close($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new Close($this->request->send('setOperationOk', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnumrepeat
     * @param string $service
     * @param int $number
     * @return Repeat
     * @throws RequestException|NoNumberException
     */
    public function repeat($service, $number) {
        $data = [
            'service' => $service,
            'number' => $number,
        ];
        return new Repeat($this->request->send('getNumRepeat', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getnumbersstats
     * @param string|int $country
     * @return Tariffs|TariffCountryOne
     * @throws RequestException|NoNumberException
     */
    public function tariffs($country = 'all') {
        $data = [];
        if($country) {
            $data['country'] = $country;
        }
        if($country === 'all') {
            return new Tariffs($this->request->send('getNumbersStats', $data, 'GET'));
        }
        return new TariffCountryOne($this->request->send('getNumbersStats', $data, 'GET'));
    }

    /**
     * https://on.test/docs/api/ru#getservice
     * @return Service
     * @throws RequestException|NoNumberException
     */
    public function service() {
        return new Service($this->request->send('getService', [], 'GET'));
    }

    /**
     * https://on.test/docs/api/ru#getservicenumber
     * @param string $service
     * @return ServiceNumber
     * @throws RequestException|NoNumberException
     */
    public function serviceNumber($service) {
        $data = [
            'service' => $service,
        ];
        return new ServiceNumber($this->request->send('getServiceNumber', $data, 'GET'));
    }
}
