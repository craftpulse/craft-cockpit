<?php

namespace craftpulse\cockpit\fieldlayoutelements;

use Craft;
use craft\base\ElementInterface;
use craft\commerce\behaviors\CustomerAddressBehavior;
use craft\elements\Address;
use craft\fieldlayoutelements\BaseField;
use craft\helpers\Cp;
use craftpulse\cockpit\elements\Job;
use yii\base\InvalidArgumentException;

class JobCoordindates extends BaseField
{
    /**
     * @inheritdoc
     */
    public function attribute(): string
    {
        return 'jobCoordinates';
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
        return Craft::t('cockpit', 'Job Coordinates');
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Address) {
            throw new InvalidArgumentException('Job Coordinates can only be used in the address field layout.');
        }

        /** @var Address|CustomerAddressBehavior $element */
        $owner = $element->getOwner();

        if (!$owner instanceof Job) {
            return null;
        }

        // Normalize input (e.g. convert comma to dot)
        $element->latitude = $this->normalizeDecimal($element->latitude);
        $element->longitude = $this->normalizeDecimal($element->longitude);

        return
            Cp::textHtml([
                'name' => 'latitude',
                'value' => $element->latitude,
                'type' => 'text',
                'suffix' => Craft::t('cockpit', 'Latitude'),
                'size' => '50%',
            ]) .
            Cp::textHtml([
                'name' => 'longitude',
                'value' => $element->longitude,
                'placeholder' => 'Longitude',
                'type' => 'text',
                'suffix' => Craft::t('cockpit', 'Longitude'),
                'size' => '50%',
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
