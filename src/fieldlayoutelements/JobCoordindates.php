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

        return
            '<span>'.Craft::t('cockpit', 'Latitude').'</span>'.
            Cp::textHtml([
                'name' => 'latitude',
                'value' => $element->latitude,
                'placeholder' => 'Latitude',
                'type' => 'number',
                'step' => 'any',
                'min' => -90,
                'max' => 90,
                'pattern' => '^-?([0-8]?[0-9]|90)(\.[0-9]*)?$',
                'width' => '50%'
            ]) .
            '<span style="display:block;padding-top:4px;">'.Craft::t('cockpit', 'Longitude').'</span>'.
            Cp::textHtml([
                'name' => 'longitude',
                'value' => $element->longitude,
                'placeholder' => 'Longitude',
                'type' => 'number',
                'step' => 'any',
                'min' => -180,
                'max' => 180,
                'pattern' => '^-?([0-9]|[1-9][0-9]|1[0-7][0-9]|180)(\.[0-9]*)?$',
                'width' => '50%'
            ]);
    }
}
