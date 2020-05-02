<?php

namespace s00d\OnlineSimApi\Apis;

use s00d\OnlineSimApi\Exceptions\NoNumberException;
use s00d\OnlineSimApi\Exceptions\RequestException;
use RuntimeException;
use Exception;
use s00d\OnlineSimApi\Responses\GetProxy\ChangeIp;
use s00d\OnlineSimApi\Responses\GetProxy\ChangeType;
use s00d\OnlineSimApi\Responses\GetProxy\Get;
use s00d\OnlineSimApi\Responses\GetProxy\SetComment;
use s00d\OnlineSimApi\Responses\GetProxy\State;

class GetProxy extends GetUser
{
    /**
     * https://onlinesim.ru/docs/api/ru#getproxy
     * @param string $class - days or traffic
     * @param string $type
     * @param string $connect
     * @param int $count
     * @param null|string $operator
     * @param int $country
     * @param string $city
     * @param int $port_count
     * @param bool $session
     * @return Get
     * @throws RequestException
     */
    public function get($class = 'days', $type = 'private', $connect = 'https', $count = 1, $operator = null, $country = 7, $city = 'any', $port_count = 1, $session = true) {
        $data = [
            'class' => $class,
            'type' => $type,
            'connect' => $connect,
            'count' => $count,
            'country' => $country,
            'city' => $city,
            'port_count' => $port_count,
            'session' => $session,
        ];
        if($operator) {
            $data['operator'] = $operator;
        }

        return new Get($this->request->send('proxy/getProxy', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate34
     * @param string $orderby
     * @return State|null
     * @throws Exception
     */
    public function state($orderby = 'ASC') {
        $data = [
            'orderby' => $orderby,
        ];
        try {
            return new State($this->request->send('proxy/getState', $data, 'GET')['list']);
        } catch (NoNumberException $e) {
            return new State([]);
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#getstate34
     * @param int $tzid
     * @return Get|null
     * @throws RequestException
     * @throws \Exception
     */
    public function stateOne($tzid) {
        $data = [
            'tzid' => $tzid
        ];

        try {
            return new Get($this->request->send('proxy/getState', $data, 'GET')[0]);
        } catch (NoNumberException $e) {
            return null;
        } catch (RequestException $e) {
            throw new RequestException($e->getMessage(), $e->getLocale());
        } catch (Exception $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * https://onlinesim.ru/docs/api/ru#changeip
     * @param int $tzid
     * @return ChangeIp
     * @throws RequestException
     */
    public function changeIp($tzid) {
        $data = [
            'tzid' => $tzid
        ];
        return new ChangeIp($this->request->send('proxy/changeIp', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#changetype
     * @param int $tzid
     * @return ChangeType
     * @throws RequestException
     */
    public function changeType($tzid) {
        $data = [
            'tzid' => $tzid
        ];
        return new ChangeType($this->request->send('proxy/changeType', $data, 'GET'));
    }

    /**
     * https://onlinesim.ru/docs/api/ru#setcomment
     * @param int $tzid
     * @param string $comment
     * @return SetComment
     * @throws RequestException
     */
    public function setComment($tzid, $comment = '') {
        $data = [
            'tzid' => $tzid,
            'comment' => $comment
        ];
        return new SetComment($this->request->send('proxy/setComment', $data, 'GET'));
    }

}
