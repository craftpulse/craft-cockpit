<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craftpulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\assetbundles\cockpit;

use Craft;
use craft\helpers\Json;
use craft\web\assets\cp\CpAsset;
use craft\web\AssetBundle;
use craft\web\View;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;

use Throwable;
use yii\base\InvalidConfigException;
use yii\web\JqueryAsset;

/**
 * Class CockpitCpAsset
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class CockpitCpAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@craftpulse/cockpit/web/assets/dist';

        $this->depends = [
            CpAsset::class,
            JqueryAsset::class,
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        // Define the Craft.Cockpit object
        $cockpitData = $this->_cockpitData();
        $editableMatchFieldTypes = Json::encode($cockpitData['editableMatchFieldTypes']);

        $js = <<<JS
if (typeof window.Craft.Cockpit === typeof undefined) {
    window.Craft.Cockpit = {};
}
window.Craft.Cockpit.editableMatchFieldTypes = {$editableMatchFieldTypes};
JS;
        $view->registerJs($js, View::POS_HEAD);
    }

    /**
     * @throws InvalidConfigException|Throwable
     */
    private function _cockpitData(): array
    {
        return [
            'editableMatchFieldTypes' => array_map(fn(MatchFieldModel $matchFieldType) => [
                'id' => $matchFieldType->id,
                'uid' => $matchFieldType->uid,
                'name' => Craft::t('site', $matchFieldType->name),
                'handle' => $matchFieldType->handle,
            ], Cockpit::$plugin->getMatchFields()->getCreatableMatchFields()),
        ];
    }
}
