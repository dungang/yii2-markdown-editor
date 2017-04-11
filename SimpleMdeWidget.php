<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 9:24
 */

namespace dungang\simplemde;

use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\InputWidget;

class SimpleMdeWidget extends InputWidget
{

    public $clientOptions = [];

    public function run()
    {
        $id = $this->options['id'];
        $this->clientOptions['element'] = "document.getElementById('$id')";
        SimpleMdeAsset::register($this->view);
        $options = Json::encode($this->clientOptions);
        $this->view->registerJs("new SimpleMDE($options)");
        if ($this->hasModel()) {
            return Html::activeTextarea($this->model, $this->attribute, $this->options);
        } else {
            return Html::textarea($this->name, $this->value, $this->options);
        }
    }
}