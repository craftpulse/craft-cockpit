<?php

namespace craftpulse\cockpit\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\behaviors\CustomerAddressBehavior;
use craft\elements\Address;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Cp;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\elements\Job;
use yii\base\InvalidArgumentException;

class AddressCoordinates extends BaseField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'addressCoordinates';
    }

    /**
     * @inheritdoc
     */
    public function mandatory(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasCustomWidth(): bool
    {
        return false;
    }

    protected function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function defaultLabel(ElementInterface $element = null, bool $static = false): ?string
    {
        return Craft::t('cockpit', 'Address Coordinates');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Address) {
//            throw new InvalidArgumentException('Address coordinates can only be used in the address field layout.');
            return null;
        }

        /** @var Address|CustomerAddressBehavior $element */
        $owner = $element->getOwner();

        if (!$owner instanceof Job && !$owner instanceof Department) {
            return null;
        }

        // Normalize input (e.g. convert comma to dot)
        $element->latitude = $this->normalizeDecimal($element->latitude);
        $element->longitude = $this->normalizeDecimal($element->longitude);

        return
            Cp::textHtml([
                'name' => 'latitude',
                'size' => '50%',
                'suffix' => Craft::t('cockpit', 'Latitude'),
                'type' => 'number',
                'step' => 'any',
                'min' => -90,
                'max' => 90,
                'value' => $element->latitude,
            ]) .
            Cp::textHtml([
                'name' => 'longitude',
                'size' => '50%',
                'suffix' => Craft::t('cockpit', 'Longitude'),
                'type' => 'number',
                'step' => 'any',
                'min' => -180,
                'max' => 180,
                'value' => $element->longitude,
            ]);
    }

    /**
     * Ensures the coordinate uses a dot as the decimal separator.
     */
    private function normalizeDecimal($value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return is_numeric($value) ? (float)$value : null;
    }
}
