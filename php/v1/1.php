<?php

namespace Manager;

class User
{
    const limit = 10;

    /**
     * Возвращает пользователей старше заданного возраста.
     * @param int $ageFrom
     * @return array
     */
    function getUsers(int $ageFrom): array
    {
        /*
         * `trim/2` принимает на вход `string`. В данном случае входящий аргумент строго типизирован, поэтому сработает приведение типов которое лишнее.
         */
        $ageFrom = (int)trim($ageFrom);

        return \Gateway\User::getUsers($ageFrom);
    }

    /**
     * Возвращает пользователей по списку имен.
     * @return array
     */
    public static function getByNames(): array
    {
        /**
         * Избыточные запросы. Делать запросы в циклах - антипаттерн. PHP на каждый запрос будет открывать новое
         * соединение, и запросы в циклах пожрут много ресурсов.
         * Я бы добавил метод который принимает на вход массив имен и сделал запрос вида `SELECT * FROM WHERE `name` IN ('name1', 'name2', ..., 'nameN')`
         * Ну и по хорошему валидацию, чтобы что-попало не могло проскочить.
         */
        $users = [];
        foreach ($_GET['names'] as $name) {
            $users[] = \Gateway\User::user($name);
        }

        return $users;
    }

    /**
     * Добавляет пользователей в базу данных.
     * @param $users
     * @return array
     */
    public function users($users): array
    {
        /**
         * Код выбьет ошибку.
         * 1. Транзакцию надо вынести в отдельную переменную. Иначе будет ошибка
         * 2. Выполнять запросы в цикле плохая затея, тем более такие тяжеловесные как транзакции
         * 3. Сама конструкция некорректна. На первой итерации транзакция выполнит commit или rollback и на второй
         * выбьет ошибку, потому что активных транзакций не будет.
         * 4. Сам скрипт добавления я бы переделал под массовое добавление одним запросом. Тогда можно избежать транзакций.
         *
         * P.S. А еще в методе `\Gateway\User::add/3` ошибка. Так что всегда будет выбивать ошибку
         *
         * P.P.S. `\Gateway\User::add/3` и так возвращает `id` добавленной записи. Так что
         * `\Gateway\User::getInstance()->lastInsertId();` лишний
         *
         * P.P.P.S. Субъективно, сделал бы `array_reduce/2` и переделал метод `\Gateway\User::add/3` в
         * `\Gateway\User::add/1` чтобы он принимал один аргумент в виде массива
         *
         * P.P.P.P.S. И чаще использовал бы `use` чтобы не загромождать код
         *
         * Вот так (примерно):
         * ```php
         * use \Gateway\User
         * ...
         * return array_reduce($users, fn($acc, $user) => array_merge($acc, array(User::add($user))), []);
         * ```
         *
         * Такое решение вероятно не самое оптимальное по скорости, так как есть масса вариантов реализации без
         * вызовов функций `php` (вроде `array/1` или `array_merge`). Но если мы можем пренебречь минимальными
         * потерями скорости в угоду читаемости кода - оно того стоит. Все от задач зависит.
         */
        $ids = [];
        \Gateway\User::getInstance()->beginTransaction();
        foreach ($users as $user) {
            try {
                \Gateway\User::add($user['name'], $user['lastName'], $user['age']);
                \Gateway\User::getInstance()->commit();
                $ids[] = \Gateway\User::getInstance()->lastInsertId();
            } catch (\Exception $e) {
                \Gateway\User::getInstance()->rollBack();
            }
        }

        return $ids;
    }
}