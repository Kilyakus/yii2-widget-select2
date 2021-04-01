<?php
namespace kilyakus\select2;

use kilyakus\widgets\AssetBundle;

class Select2Asset extends AssetBundle
{
    public function init()
    {
        $this->setSourcePath(__DIR__ . '/assets');
        $this->setupAssets('css', ['css/widget-select2.min']);
        $this->setupAssets('js', ['js/select2.min', 'js/select2-krajee']);
        parent::init();
    }
}
