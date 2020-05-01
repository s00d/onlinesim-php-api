<?php

namespace s00d\OnlineSimApi\Exceptions;

use Exception;

class RequestException extends Exception {
    private $errors = [
        'en' => [
            'ACCOUNT_BLOCKED' => 'account blocked',
            'ERROR_WRONG_KEY' => 'wrong apikey',
            'ERROR_NO_KEY' => 'no apikey',
            'ERROR_NO_SERVICE' => 'service not specified',
            'REQUEST_NOT_FOUND' => 'API method not specified',
            'API_ACCESS_DISABLED' => 'api disabled',
            'API_ACCESS_IP' => 'access from this ip is disabled in the profile',

            'WARNING_NO_NUMS' => 'no matching numbers',
            'TZ_INPOOL' => 'waiting for a number to be dedicated to the operation',
            'TZ_NUM_WAIT' => 'waiting for response',
            'TZ_NUM_ANSWER' => 'response has arrived',
            'TZ_OVER_EMPTY' => 'response did not arrive within the specified time',
            'TZ_OVER_OK' => 'operation has been completed',
            'ERROR_NO_TZID' => 'tzid is not specified',
            'ERROR_NO_OPERATIONS' => 'no operations',
            'ACCOUNT_IDENTIFICATION_REQUIRED' => 'You have to go through an identification process: to order a messenger - in any way, for forward - on the passport.',

            'EXCEEDED_CONCURRENT_OPERATIONS' => 'maximum quantity of numbers booked concurrently is exceeded for your account',
            'NO_NUMBER' => 'temporarily no numbers available for the selected service',
            'TIME_INTERVAL_ERROR' => 'delayed SMS reception is not possible at this interval of time',
            'INTERVAL_CONCURRENT_REQUESTS_ERROR' => 'maximum quantity of concurrent requests for number issue is exceeded, try again later',
            'TRY_AGAIN_LATER' => 'temporarily unable to perform the request',
            'NO_FORWARD_FOR_DEFFER' => 'forwarding can be activated only for online reception',
            'NO_NUMBER_FOR_FORWARD' => 'there are no numbers for forwarding',
            'ERROR_LENGTH_NUMBER_FOR_FORWARD' => 'wrong length of the number for forwarding',
            'DUPLICATE_OPERATION' => 'adding operations with identical parameters',

            'ERROR_NO_NUMBER' => 'number is not specified',
            'ERROR_PARAMS' => 'one or both parameters are wrong',
            'LIFICYCLE_NUM_EXPIRED' => 'the number has expired',
            'NEED_EXTENSION_NUMBER' => 'you have to extend the number, see the Extension tab',

            'ERROR_NUMBERS_PARAMS' => 'error in the number format',

            'ERROR_WRONG_TZID' => 'error in the number format',
            'NO_COMPLETE_TZID' => 'unable to complete the operation.',

            'NO_CONFIRM_FORWARD' => 'unable to confirm forwarding',

            'ERROR_NO_SERVICE_REPEAT' => 'no services for repeated reception',
            'SERVICE_TO_NUMBER_EMPTY' => 'no numbers for repeated reception for this service',
        ],
        'ru' => [
            'ACCOUNT_BLOCKED' => 'Аккаунт заблокирован',
            'ERROR_WRONG_KEY' => 'apikey неверный',
            'ERROR_NO_KEY' => 'нет apikey',
            'ERROR_NO_SERVICE' => 'не указан сервис',
            'REQUEST_NOT_FOUND' => 'не указан метода API',
            'API_ACCESS_DISABLED' => 'api выключено',
            'API_ACCESS_IP' => 'доступ с данного ip выключен в профиле',

            'WARNING_NO_NUMS' => 'нет подходящих номеров',
            'TZ_INPOOL' => 'операция ожидает выделения номера',
            'TZ_NUM_WAIT' => 'ожидается ответ',
            'TZ_NUM_ANSWER' => 'поступил ответ',
            'TZ_OVER_EMPTY' => 'ответ не поступил за отведенное время',
            'TZ_OVER_OK' => 'операция завершена',
            'ERROR_NO_TZID' => 'не указан tzid',
            'ERROR_NO_OPERATIONS' => 'нет операций',
            'ACCOUNT_IDENTIFICATION_REQUIRED' => 'Необходимо пройти идентификацию: для заказа мессенджера - любым способом, для переадресации - по паспорту.',

            'EXCEEDED_CONCURRENT_OPERATIONS' => 'превышено количество одновременно заказанных номеров для Вашего аккаунта',
            'NO_NUMBER' => 'для выбранного сервиса свободные номера временно отсутствуют',
            'TIME_INTERVAL_ERROR' => 'отложенный прием СМС не возможен в данный интервал времени',
            'INTERVAL_CONCURRENT_REQUESTS_ERROR' => 'превышено количество одновременных запросов на выдачу номера, повторите запрос позднее',
            'TRY_AGAIN_LATER' => 'запрос временно не может быть выполнен',
            'NO_FORWARD_FOR_DEFFER' => 'активация переадресации возможна только на онлайн приеме',
            'NO_NUMBER_FOR_FORWARD' => 'нет номеров для переадресации',
            'ERROR_LENGTH_NUMBER_FOR_FORWARD' => 'номер для переадресации имеет не верную длину',
            'DUPLICATE_OPERATION' => 'добавление операций с одинаковыми параметрами',

            'ERROR_NO_NUMBER' => 'не указан номер',
            'ERROR_PARAMS' => 'не правильно указан один или оба параметра',
            'LIFICYCLE_NUM_EXPIRED' => 'срок действия номера истек',
            'NEED_EXTENSION_NUMBER' => 'необходимо продлить номер см. вкладку «Продление»',

            'ERROR_NUMBERS_PARAMS' => 'ошибка в формате номера',

            'ERROR_WRONG_TZID' => 'неверный номер операции.',
            'NO_COMPLETE_TZID' => 'невозможно завершить операцию.',

            'NO_CONFIRM_FORWARD' => 'переадресация не может быть подтверждена',

            'ERROR_NO_SERVICE_REPEAT' => 'нет сервисов для повторного приема',
            'SERVICE_TO_NUMBER_EMPTY' => 'нет номеров для повторного приема по данному сервису',
        ]
    ];
    private $locale;

    public function __construct($error, $locale = null) {
        $this->locale = $locale;
        if ($locale) {
            if(!isset($this->errors[$locale])) {
                throw new Exception('bad locale');
            }
            if(isset($this->errors[$locale][$error])) {
                $error = $this->errors[$locale][$error];
            }
        }
        $message = "Error in {$this->getFile()}, line: {$this->getLine()}: {$error}";
        parent::__construct($message);
    }

    public function getLocale() {
        return $this->locale;
    }

}
