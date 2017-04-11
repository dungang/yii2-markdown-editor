<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 9:20
 */

namespace dungang\simplemde;


use yii\web\AssetBundle;

class SimpleMdeAsset extends AssetBundle
{

    public $depends = ['dungang\inlineattachment\assets\CodeMirror4InlineAttachmentAsset'];

    public function init()
    {
        if (YII_DEBUG) {
            $this->sourcePath = "@bower/simplemde/debug";
            $this->css  = ['simplemde.css'];
            $this->js  = ['simplemde.js'];

        } else {
            $this->sourcePath = "@bower/simplemde/dist";
            $this->css  = ['simplemde.min.css'];
            $this->js  = ['simplemde.min.js'];
        }
    }
}