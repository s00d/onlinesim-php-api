<?php

namespace s00d\OnlineSimApi\Apis;

use Exception;
use RuntimeException;
use s00d\OnlineSimApi\Exceptions\NoNumberException;
use s00d\OnlineSimApi\Exceptions\RequestException;
use s00d\OnlineSimApi\Responses\GetRent\Close;
use s00d\OnlineSimApi\Responses\GetRent\Get;
use s00d\OnlineSimApi\Responses\GetRent\PortReload;
use s00d\OnlineSimApi\Responses\GetRent\State;
use s00d\OnlineSimApi\Responses\GetRent\Tariffs;

class GetRent extends GetUser
{
    /**
     * https://onlinesim.ru/docs/api/ru#getrentnum
     * @param int $country
     * @param int $days
     * @param bool $extension
     * @return Get
     * @throws NoNumberException
     * @throws RequestException
     */
    public function get($country = 7, $days = 1, $extension = false) {
        $data = [
            'country' => $country,
            'days' => $days,
            'extension' => $extension,
            'pagination' => false,
        ];

        return new Get($this->request->send('rent/getRentNum', $data, 'GET')['item']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getrentstate
     * @param int $tzid
     * @return State
     * @throws RequestException
     * @throws Exception
     */
    public function state() {
        $data = [
            'pagination' => false,
        ];
        try {
            return new State($this->request->send('rent/getRentState', $data, 'GET')['list']);
        } catch (NoNumberException $e) {
            return new State([]);
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getrentstate
     * @param int $tzid
     * @return Get|null
     * @throws RequestException
     * @throws Exception
     */
    public function stateOne($tzid) {
        $data = [
            'tzid' => $tzid,
            'pagination' => false,
        ];

        try {
            return new Get($this->request->send('rent/getRentState', $data, 'GET')['list'][0]);
        } catch (NoNumberException $e) {
            return null;
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#extendrentstate
     * @param int $tzid
     * @param int $days
     * @return Get|null
     * @throws RequestException
     */
    public function extend($tzid, $days = 1) {
        $data = [
            'tzid' => $tzid,
            'days' => $days,
        ];

        return new Get($this->request->send('rent/extendRentState', $data, 'GET')['item']);
    }

    /**
     * https://onlinesim.ru/docs/api/ru#portreload
     * @param int $tzid
     * @return PortReload|null
     * @throws RequestException
     */
    public function portReload($tzid) {
        $data = [
            'tzid' => $tzid,
        ];

        return new PortReload($this->request->send('rent/portReload', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#tariffsrent
     * @param null|int $country
     * @return Tariffs
     * @throws RequestException
     */
    public function tariffs($country = null) {
        $data = [];
        if($country) {
            $data['country'] = $country;
        }

        return New Tariffs($this->request->send('rent/tariffsRent', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#closerent
     * @param null|int $tzid
     * @return Close
     * @throws RequestException
     */
    public function close($tzid = null) {
        $data = [
            'tzid' => $tzid
        ];

        return new Close($this->request->send('rent/closeRentNum', $data, 'GET'));
    }

}
