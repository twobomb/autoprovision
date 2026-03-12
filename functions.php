<?php
// functions.php
function get_profile_fields_with_inheritance($database, $profile_id) {
    $fields = [];
    $processedProfiles = [];

    // Собираем все поля, поднимаясь по иерархии родителей
    while ($profile_id && !in_array($profile_id, $processedProfiles)) {
        $processedProfiles[] = $profile_id;
        $profileFields = $database->select('profile_fields', '*', ['profile_id' => $profile_id,"ORDER"=>["sort_order"=>"ASC"]]);
        foreach ($profileFields as $field) {
            // Если поле с таким ключом ещё не добавлено (более приоритетное уже есть)
            if (!isset($fields[$field['field_key']])) {
                $fields[$field['field_key']] = $field;
            }
        }
        // Получаем родителя
        $profile = $database->get('profiles', '*', ['id' => $profile_id]);
        $profile_id = $profile ? $profile['parent_id'] : null;
    }

    // Возвращаем как индексный массив
    return array_values($fields);
}


/**
 * Форматирует время последнего обращения в удобочитаемый вид
 *
 * @param string|DateTime|null $datetime Дата и время в формате, понятном для DateTime, или null
 * @return string Отформатированная строка
 */
function formatLastSeen($datetime): string
{
    if (empty($datetime)) {
        return 'никогда';
    }

    // Создаём объект DateTime, если передана строка
    if (!$datetime instanceof DateTime) {
        try {
            $datetime = new DateTime($datetime, new DateTimeZone('Europe/Moscow'));
        } catch (Exception $e) {
            return 'неверная дата';
        }
    }


    $now = new DateTime('now', new DateTimeZone('Europe/Moscow'));
    $interval = $now->getTimestamp() - $datetime->getTimestamp();


    if ($interval < 0) {
        // Дата в будущем – маловероятно, но на всякий случай
        return $datetime->format('d.m.Y H:i');
    }

    if ($interval < 60) {
        return 'только что';
    }

    if ($interval < 3600) {
        $minutes = floor($interval / 60);
        return $minutes . ' ' . pluralForm($minutes, ['минута', 'минуты', 'минут']) . ' назад';
    }

    if ($interval < 86400) {
        $hours = floor($interval / 3600);
        $minutes = floor(($interval % 3600) / 60);
        $result = $hours . ' ' . pluralForm($hours, ['час', 'часа', 'часов']);
        if ($minutes > 0) {
            $result .= ' ' . $minutes . ' ' . pluralForm($minutes, ['минута', 'минуты', 'минут']);
        }
        return $result . ' назад';
    }

    // Больше суток
    return $datetime->format('d.m.Y H:i');
}

/**
 * Вспомогательная функция для склонения существительных после числительных
 *
 * @param int $number Число
 * @param array $forms Формы для 1, 2-4, 5-0 (например, ['минута', 'минуты', 'минут'])
 * @return string
 */
function pluralForm(int $number, array $forms): string
{
    $number = abs($number) % 100;
    if ($number > 10 && $number < 20) {
        return $forms[2];
    }
    $number %= 10;
    if ($number == 1) {
        return $forms[0];
    }
    if ($number >= 2 && $number <= 4) {
        return $forms[1];
    }
    return $forms[2];
}
?>