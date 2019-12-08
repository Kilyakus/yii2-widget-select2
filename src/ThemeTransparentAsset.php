<?php
namespace kilyakus\select2;

use kilyakus\widgets\AssetBundle;

class ThemeTransparentAsset extends ThemeAsset
{
    public function init()
    {
        $this->setSourcePath(__DIR__ . '/assets');
        $this->setupAssets('css', ['css/select2-transparent']);
        parent::init();
    }
}
