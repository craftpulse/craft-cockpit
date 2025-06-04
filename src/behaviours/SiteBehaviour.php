<?php

namespace craftpulse\cockpit\behaviours;

use yii\base\Behavior;

class SiteBehaviour extends Behavior
{
    public function getCountry(): ?string
    {
        return 'Belgium';
    }
}
