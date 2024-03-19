<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     *
     *  Метод `doOperation/0` объявлен как `void`, `void` не должен возвращать ничего. В данном сулчае есть `return`.
     *  Результатом будет фатальная ошибка.
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        /**
         * Вызов без проверки. Если по этому ключу ничего нет - выбьет warning.
         * Данные надо проверять.
         */
        $resellerId = $data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        if (empty((int)$resellerId)) {
            /**
             * Снова вызов без проверки. Можно спровоцировать фатальную ошибку, в случае если
             * `$result['notificationClientBySms']` содержит некорректный тип. Допустим если $result имеет вид:
             *
             * ```php
             * $result = ['notificationClientBySms' => 123];
             * ```
             *
             * Мы получим фатальную ошибку: `Cannot use a scalar value as an array`
             */
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        /**
         * Приведение типов тут лишнее ибо оно уже выполняется в коде выше.
         * В данном случае я бы поставил более явную проверку, так как если значение `0` то `empty/1` вернет `true`,
         * что некорректно, ибо 0 тоже валидное значение для типа `int`.
         *
         * Более корректный подход либо парсить значение и ожидать `?int` проверяя значение на `null`
         */
        if (empty((int)$notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        /**
         * В данном примере `$reseller` никогда не будет `null`.
         *
         * Задача не определена однозначно, но исходя из контекста могу предположить что классы наследники
         * `Contractor` должны имплементировать `__construct/1` и переопределять значение `$this->id` на значение
         * полученного аргумента
         */
        $reseller = Seller::getById((int)$resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        /**
         * В данном примере `$client` никогда не будет `null`.
         *
         * Вызов `$client->Seller` (если все остальные условия отработают) выбьет ошибку, так как ни класс
         * `Contractor` ни его наследники в данном случае не имеют такого свойства.
         */
        $client = Contractor::getById((int)$data['clientId']);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('сlient not found!', 400);
        }

        /**
         * В данном случае лишнее нагромождение кода. В классе `Contractor` метод `getFullName/0` вернет пустую строку
         * только в случае если свойства `name` и `id` пустые. Соответственно вызов `$client->name` не имеет смысла.
         */
        $cFullName = $client->getFullName();
        if (empty($client->getFullName())) {
            $cFullName = $client->name;
        }

        /**
         * Снова вызов априори ненулёвого значения.
         * Снова использование непроверенного ключа.
         */
        $cr = Employee::getById((int)$data['creatorId']);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        /**
         * Снова вызов априори ненулёвого значения.
         * Снова использование непроверенного ключа.
         */
        $et = Employee::getById((int)$data['expertId']);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        /**
         * Синтаксическая ошибка выбьет фатальную ошибку:
         * Fatal error: Uncaught Error: Call to undefined function NW\WebService\References\Operations\Notification\__()
         *
         * Теоретически это мог бы быть вызов gettext, но `_()` принимает на вход только один аргумент
         *
         * Есть функция `wp-includes/l10n.php` в wordpress, которая (если мне не изменяет память) генерит переводы. Но
         * она принимает на вход только два аргумента.
         *
         * В данном примере функция не объявлена, так что будет ошибка.
         */
        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        /**
         * Снова использование непроверенных данных. Возможны ошибки.
         */
        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $cr->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $et->getFullName(),
            'CLIENT_ID' => (int)$data['clientId'],
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        /**
         * В данном случае вместо перебора массива в цикле более предпочтительна следующая логика:
         * переработать $templateData таким образом, чтобы значение было проверяемо на null;
         * То есть, там где есть значения - записываем их, если нет - записываем null;
         *
         * Далее:
         * ```php
         * if ($emptyKey = array_search(null, $templateData) {
         *     throw new \Exception("Template Data ({$emptyKey}) is empty!", 500);
         * }
         * ```
         */
        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        /**
         * 1. Много проверок. Я бы переработал `getEmailsByPermit` так, чтобы он всегда возвращал массив.
         * 2. Проверку `(!empty($emailFrom) && count($emails) > 0)` устранил вовсе. На пустой массив будет 0 итераций.
         * 3. Самое чистое решение было бы вообще вынести рассылку email в отдельный класс или хотябы метод.
         * 4. В таком виде будет снова вызов неизвестной функции `__()`
         */
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;
            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        /**
         * Код нагроможден. К этому моменту отслеживание $data становтся проблематичным. Изменения предпочтительнее
         * было бы вынести в отдельный класс, который будет их отслеживать.
         *
         * По хорошему вообще все ивенты регистрировать в виде экземпляров отдельных классов и передавать их
         * асинхронно в какую нибудь очередь.
         *
         * В данном блоке так же присутствует отправка на мобилки. Лучше было бы это нарезать на отдельные сервисы,
         * каждый из которых отвечает за рассылку чего-то одного.
         */
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                        'emailFrom' => $emailFrom,
                        'emailTo' => $client->email,
                        'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                /**
                 * Снова используется переопределение переменных без проверки.
                 * В конкретно данном случае мы заведомо знаем структуру массива и это в некоторой степени защищает
                 * нас от проблем, тем не менее такой подход не безопасен и проблематичен для расширения.
                 */
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
    /**
     * В целом код свален в одну кучу и как минимум нарушает соглашение о Single Responsibility:
     * 1. Требуется как минимум разделение логики на отдельные методы.
     * 2. В идеале разделить все на отдельные классы и/или компоненты/сервисы.
     * 3. Однозначно не определены контракты, из контекста понятно что структура массива определена однозначно, но это
     * не защищает нас от возможных ошибок из за высокой когнитивной сложности. Не хватает инкапсуляции.
     * 4. Код, который может быть исполнен асинхонно сгружен в синхронный блок что чревато проблемами.
     * 5. Тестирование такого кода практически невозможно.
     */
}
