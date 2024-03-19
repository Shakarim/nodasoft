<?php

namespace Gateway;

use PDO;

class User
{
    /**
     * @var PDO
     */
    public static $instance;

    /**
     * Реализация singleton
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        /*
         * Конфигурации подключения к базе я бы вынес в отдельный конфиг файл а в идеале вообще в переменные
         * окружения. Так будет удобнее и безопаснее юзать их если приложение будет развернуто под Docker-ом и/или
         * Kubernetes
         */
        if (is_null(self::$instance)) {
//            $dsn = 'mysql:dbname=db;host=127.0.0.1';
//            $user = 'dbuser';
//            $password = 'dbpass';
            $dsn = 'mysql:dbname=ping;host=127.0.0.1';
            $user = 'admin';
            $password = 'admin1234';
            self::$instance = new PDO($dsn, $user, $password);
        }

        return self::$instance;
    }

    /**
     * Возвращает список пользователей старше заданного возраста.
     * @param int $ageFrom
     * @return array
     */
    public static function getUsers(int $ageFrom): array
    {
        // Конкатенировать передаваемые данные в SQL запрос небезопасно. Необходимо использовать экранирование иначе есть риск SQL-injection
        $stmt = self::getInstance()->prepare("SELECT id, name, lastName, from, age, settings FROM Users WHERE age > {$ageFrom} LIMIT " . \Manager\User::limit);
        /*
         * Взаимодействие с PDO я бы вообще вынес в отдельный класс, который обеспечивает взаимодействие с базой.
         */
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        /**
         * Вместо foreach предпочел бы использовать array_map. Вкусовщина конечно, но декларативность в таких случаях выглядит более компактно и элегантно на мой взгляд.
         *
         * Добавить ниже методы:
         * ```php
         * private static function renderUser(array $row): array
         * {
         *      $settings = $row['settings'] ? json_decode($row['settings'], true) : [];
         *      return [
         *          'id' => self::getValue($row, 'id'),
         *          'name' => self::getValue($row, 'name'),
         *          'lastName' => self::getValue($row, 'lastName'),
         *          'from' => self::getValue($row, 'from'),
         *          'age' => self::getValue($row, 'age'),
         *          'key' => self::getValue($settings, 'key'),
         *      ];
         * }
         *
         * public static function getValue(array $row, string|int $key)
         * {
         *      return $row[$key] ?? null;
         * }
         * ```
         *
         * а foreach заменил на:
         * ```php
         * return array_map(self::renderUser(...), $rows);
         * ```
         */
        $users = [];
        foreach ($rows as $row) {
            $settings = json_decode($row['settings']);
            $users[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'lastName' => $row['lastName'],
                'from' => $row['from'],
                'age' => $row['age'],
                'key' => $settings['key'],
            ];
        }

        return $users;
    }

    /**
     * Возвращает пользователя по имени.
     * @param string $name
     * @return array
     */
    public static function user(string $name): array
    {
        // Снова небезопасная передача аргументов в запрос
        $stmt = self::getInstance()->prepare("SELECT id, name, lastName, from, age, settings FROM Users WHERE name = {$name}");
        $stmt->execute();
        $user_by_name = $stmt->fetch(PDO::FETCH_ASSOC);

        /**
         * Тут можно использовать `renderUser/1` которую я привел в примере выше. В данном случае количество ключей
         * отличается, но это можно решить путем расширения `renderUser/1` и передавать туда второй аргумент с
         * парамерами выборки. Например добавить массив ключей для выборки или наоборот для исключения.
         *
         * P.S. во всех примерах скрипта обращение к ключам массива указано без проверок, что чревато ошибками, если
         * ключи отсутствут.
         *
         * Я бы сделал так (за исключением параметризации):
         * ```php
         * return $rows ? self::renderUser($rows[0]) : null;
         * ```
         */
        return [
            'id' => $user_by_name['id'],
            'name' => $user_by_name['name'],
            'lastName' => $user_by_name['lastName'],
            'from' => $user_by_name['from'],
            'age' => $user_by_name['age'],
        ];
    }

    /**
     * Добавляет пользователя в базу данных.
     * @param string $name
     * @param string $lastName
     * @param int $age
     * @return string
     */
    public static function add(string $name, string $lastName, int $age): string
    {
        /*
         * В данном случае небезопасная конкатенация + ошибка в порядке передаваемых аргументов. `INSERT` предполагает
         * соответствие порядка ключей и значений. Вд анном случае СУБД попробует записать `age` в `lastName` а
         * `lastName` в `age`.
         *
         * Так как структура базы нам точно неизвестна может произойти следующее:
         * 1. Все поля `string` и данные запишутся но неправильно
         * 2. Поле `age` имеет тип отличный от `string` (логически туда напрашивается `tinyint`. Вряд ли кто-то из нынеживущих доживет до 2^7-1 лет.)
         */
        $sth = self::getInstance()->prepare("INSERT INTO Users (name, lastName, age) VALUES (:name, :age, :lastName)");
        $sth->execute([':name' => $name, ':age' => $age, ':lastName' => $lastName]);

        return self::getInstance()->lastInsertId();
    }
}