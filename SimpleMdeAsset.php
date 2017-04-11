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
    public $sourcePath = "@bower/dist";

    public $js  = ['simplemde.min.js'];

    public $css  = ['simplemde.min.css'];

    public $depends = [ 'yii\web\JqueryAsset'];
}