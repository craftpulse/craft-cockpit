<?php

namespace craftpulse\cockpit\models;

use craft\base\Model;

/**
 * @property string $id
 * @property array $postcodeMapping;
 */
class PostcodeMappingModel extends Model
{
    public int $id = 0;
    public array $postcodeMapping = [];

    public function rules(): array
    {
        return [
            ['id', 'integer'],
            ['postcodeMapping', 'array']
        ];
    }
}
