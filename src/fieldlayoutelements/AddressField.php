<?php

namespace craftpulse\cockpit\fieldlayoutelements;

use Craft;
use craft\elements\Address;
use craft\fieldlayoutelements\BaseNativeField;
use craft\base\ElementInterface;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\assets\tablesettings\TableSettingsAsset;
use craft\web\assets\timepicker\TimepickerAsset;
use craftpulse\teamleader\elements\Company;
use InvalidArgumentException;

class AddressField extends BaseNativeField
{
    public bool $mandatory = true;

    /**
     * @inheritdoc
     */
    public bool $required = false;

    /**
     * @inheritdoc
     */
    public ?string $name = null;

    public string $field = '';

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        $config = [
            'showInGrid' => true,
            'canCreate' => true,
        ];

        return $element->getAddressManager()->getCardsHtml($element, $config);
    }
}
