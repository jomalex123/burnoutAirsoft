<?php

declare(strict_types=1);

require_once __DIR__ . '/errors.php';

function burnout_registration_roles(): array
{
    return ['Fusilero', 'Tirador Selecto', 'Apoyo', 'Francotirador', 'Escudo'];
}

function burnout_validate_registration_roles($values, int $count): array
{
    if (!is_array($values) || array_keys($values) !== range(0, $count - 1)) {
        throw new BurnoutUserException('Selecciona el rol de arma de todos los asistentes.');
    }
    foreach ($values as $value) {
        if (!is_string($value) || !in_array($value, burnout_registration_roles(), true)) {
            throw new BurnoutUserException('Selecciona un rol de arma válido para cada asistente.');
        }
    }
    return $values;
}
