<?php

namespace craftpulse\cockpit\validators;

use yii\validators\Validator;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

class PhoneNumberValidator extends Validator
{
    public $countryAttribute; // e.g. 'country'

    public function validateAttribute($model, $attribute)
    {
        $phone = $model->$attribute;

        /** @var Phone $field */
        $field = $model->getField()->getField();

        // Get selected country from the Phone field’s country dropdown
        $country = $model->getFieldParam('country');

        if (!$phone || !$country) {
            $model->addError($attribute, 'Missing phone number or country.');
            return;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberProto = $phoneUtil->parse($phone, $country);

            if (!$phoneUtil->isValidNumberForRegion($numberProto, $country)) {
                $model->addError($attribute, 'Invalid phone number for selected country.');
            }
        } catch (NumberParseException $e) {
            $model->addError($attribute, 'Invalid phone number format.');
        }
    }
}
