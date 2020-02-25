<?php
namespace kilyakus\select2;

use kilyakus\widgets\AddonTrait;
use kilyakus\widgets\InputWidget;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Inflector;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\web\View;

class Select2 extends InputWidget
{
    use AddonTrait;

    const LARGE = 'lg';
    const MEDIUM = 'md';
    const SMALL = 'sm';

    const THEME_DEFAULT = 'default';
    const THEME_CLASSIC = 'classic';
    const THEME_BOOTSTRAP = 'bootstrap';
    const THEME_TRANSPARENT = 'transparent';
    const THEME_KRAJEE = 'krajee';
    const THEME_KRAJEE_BS4 = 'krajee-bs4';

    public $data;
    public $language;
    public $theme;
    public $initValueText;
    public $changeOnReset = true;
    public $hideSearch = false;
    public $maintainOrder = false;
    public $showToggleAll = true;
    public $toggleAllSettings = [];
    public $addon = [];
    public $size = self::MEDIUM;
    public $options = [];
    public $pluginName = 'select2';
    public $accesskey = null;

    protected $_s2OptionsVar;
    protected $_msgCat = 'kvselect';

    protected static $_inbuiltThemes = [
        self::THEME_DEFAULT,
        self::THEME_CLASSIC,
        self::THEME_BOOTSTRAP,
        self::THEME_TRANSPARENT,
        self::THEME_KRAJEE,
        self::THEME_KRAJEE_BS4,
    ];

    public function run()
    {
        parent::run();
        $this->renderWidget();
    }

    public function renderWidget()
    {
        if (!isset($this->theme)) {
            $this->theme = $this->isBs4() ? self::THEME_KRAJEE_BS4 : self::THEME_DEFAULT;
        }
        $this->initI18N(__DIR__);
        $this->pluginOptions['theme'] = $this->theme . ' ' . (!$this->pluginOptions['class'] ? '' : $this->pluginOptions['class']);
        $multiple = ArrayHelper::getValue($this->pluginOptions, 'multiple', false);
        unset($this->pluginOptions['multiple']);
        $multiple = ArrayHelper::getValue($this->options, 'multiple', $multiple);
        $this->options['multiple'] = $multiple;
        if (empty($this->pluginOptions['width'])) {
            if ($this->theme !== self::THEME_KRAJEE_BS4) {
                $this->pluginOptions['width'] = '100%';
            } elseif (empty($this->addon)) {
                $this->pluginOptions['width'] = 'auto';
            }
        }
        if ($this->hideSearch) {
            $this->pluginOptions['minimumResultsForSearch'] = new JsExpression('Infinity');
        }
        if(empty($this->pluginOptions['escapeMarkup'])){
            $this->pluginOptions['escapeMarkup'] = new JsExpression("function(data) { return data; }");
        }

        $this->initPlaceholder();
        if (!isset($this->data)) {
            if (!isset($this->value) && !isset($this->initValueText)) {
                $this->data = [];
            } else {
                if ($multiple) {
                    $key = isset($this->value) && is_array($this->value) ? $this->value : [];
                } else {
                    $key = isset($this->value) ? $this->value : '';
                }
                $val = isset($this->initValueText) ? $this->initValueText : $key;
                $this->data = $multiple ? array_combine((array)$key, (array)$val) : [$key => $val];
            }
        }
        $this->initLanguage('language', true);
        $this->renderToggleAll();
        $this->registerAssets();
        $this->renderInput();
    }

    protected function renderToggleAll()
    {
        if (!$this->options['multiple'] || !$this->showToggleAll || !empty($this->pluginOptions['ajax'])) {
            return;
        }
        $unchecked = '<i class="glyphicon glyphicon-unchecked"></i>';
        $checked = '<i class="glyphicon glyphicon-check"></i>';
        if ($this->isBs4()) {
            $unchecked = '<i class="far fa-square"></i>';
            $checked = '<i class="far fa-check-square"></i>';
        }
        $settings = array_replace_recursive([
            'selectLabel' => $unchecked . Yii::t('kvselect', 'Select all'),
            'unselectLabel' => $checked . Yii::t('kvselect', 'Unselect all'),
            'selectOptions' => [],
            'unselectOptions' => [],
            'options' => ['class' => 's2-togall-button'],
        ], $this->toggleAllSettings);
        $sOptions = $settings['selectOptions'];
        $uOptions = $settings['unselectOptions'];
        $options = $settings['options'];
        $prefix = 's2-togall-';
        Html::addCssClass($options, "{$prefix}select");
        Html::addCssClass($sOptions, "s2-select-label");
        Html::addCssClass($uOptions, "s2-unselect-label");
        $options['id'] = $prefix . $this->options['id'];
        $labels = Html::tag('span', $settings['selectLabel'], $sOptions) .
            Html::tag('span', $settings['unselectLabel'], $uOptions);
        $out = Html::tag('span', $labels, $options);
        if (!is_null($this->accesskey)) {
            $accesskey = substr($this->accesskey, 0, 1);
            echo Html::tag('button', '', [
                'accesskey' => $accesskey,
                'style' => 'background: transparent;border: none !important;font-size:0;',
                'onfocus' => '$("#' . $this->options['id'] . '").select2("open");',
            ]);
        }
        echo Html::tag('span', $out, ['id' => 'parent-' . $options['id'], 'style' => 'display:none']);
    }

    protected function initPlaceholder()
    {
        $isMultiple = ArrayHelper::getValue($this->options, 'multiple', false);
        if (isset($this->options['prompt']) && !isset($this->pluginOptions['placeholder'])) {
            $this->pluginOptions['placeholder'] = $this->options['prompt'];
            if ($isMultiple) {
                unset($this->options['prompt']);
            }
            return;
        }
        if (isset($this->options['placeholder'])) {
            $this->pluginOptions['placeholder'] = $this->options['placeholder'];
            unset($this->options['placeholder']);
        }
        if (isset($this->pluginOptions['placeholder']) && is_string($this->pluginOptions['placeholder']) && !$isMultiple) {
            $this->options['prompt'] = $this->pluginOptions['placeholder'];
        }
    }

    protected function embedAddon($input)
    {
        if (empty($this->addon)) {
            return $input;
        }
        $isBs4 = $this->isBs4();
        $group = ArrayHelper::getValue($this->addon, 'groupOptions', []);
        $css = ['input-group', 's2-input-group'];
        if (isset($this->size)) {
            $css[] = 'input-group-' . $this->size;
        }
        Html::addCssClass($group, $css);
        if ($this->pluginLoading) {
            Html::addCssClass($group, 'kv-input-group-hide');
            Html::addCssClass($group, 'group-' . $this->options['id']);
        }
        $prepend = $this->getAddonContent('prepend', $isBs4);
        $append = $this->getAddonContent('append', $isBs4);
        if (!$isBs4 && isset($this->addon['prepend']) && is_array($this->addon['prepend'])) {
            Html::addCssClass($group, 'select2-bootstrap-prepend');
        }
        if (!$isBs4 && isset($this->addon['append']) && is_array($this->addon['append'])) {
            Html::addCssClass($group, 'select2-bootstrap-append');
        }
        $addonText = $prepend . $input . $append;
        $contentBefore = ArrayHelper::getValue($this->addon, 'contentBefore', '');
        $contentAfter = ArrayHelper::getValue($this->addon, 'contentAfter', '');
        return Html::tag('div', $contentBefore . $addonText . $contentAfter, $group);
    }

    protected function renderInput()
    {
        if ($this->pluginLoading) {
            $this->_loadIndicator = '<div class="kv-plugin-loading loading-' . $this->options['id'] . ' select2-loading">&nbsp;</div>';
            Html::addCssStyle($this->options, 'display:none');
        }
        Html::addCssClass($this->options, 'form-control');
        $input = $this->getInput('dropDownList', true);
        echo $this->_loadIndicator . $this->embedAddon($input);
    }

    protected static function parseBool($var)
    {
        return new JsExpression($var ? 'true' : 'false');
    }

    public function registerAssetBundle()
    {
        $view = $this->getView();
        $lang = isset($this->language) ? $this->language : '';
        Select2Asset::register($view)->addLanguage($lang, '', 'js/i18n');
        if (in_array($this->theme, self::$_inbuiltThemes)) {
            /**
             * @var ThemeAsset $bundleClass
             */
            $bundleClass = __NAMESPACE__ . '\Theme' . Inflector::id2camel($this->theme) . 'Asset';
            $bundleClass::register($view);
        }
    }

    public function registerAssets()
    {
        $id = $this->options['id'];
        $this->registerAssetBundle();
        $isMultiple = isset($this->options['multiple']) && $this->options['multiple'];
        $options = Json::encode([
            'themeCss' => ".select2-container--{$this->theme}",
            'sizeCss' => empty($this->addon) && $this->size !== self::MEDIUM ? ' input-' . $this->size : '',
            'doReset' => static::parseBool($this->changeOnReset),
            'doToggle' => static::parseBool($isMultiple && $this->showToggleAll),
            'doOrder' => static::parseBool($isMultiple && $this->maintainOrder),
        ]);
        $this->_s2OptionsVar = 's2options_' . hash('crc32', $options);
        $this->options['data-s2-options'] = $this->_s2OptionsVar;
        $this->options['class'] = 'form-control';
        $view = $this->getView();
        $view->registerJs("var {$this->_s2OptionsVar} = {$options};", View::POS_HEAD);
        if ($this->maintainOrder) {
            $val = Json::encode(is_array($this->value) ? $this->value : [$this->value]);
            $view->registerJs("initS2Order('{$id}',{$val});");
        }
        $this->registerPlugin($this->pluginName, "jQuery('#{$id}')", "initS2Loading('{$id}','{$this->_s2OptionsVar}')");
    }
}
