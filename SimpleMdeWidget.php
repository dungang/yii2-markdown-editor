<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 9:24
 */

namespace dungang\simplemde;

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\InputWidget;

class SimpleMdeWidget extends InputWidget
{
    public $uploadUrl = ['/inline-attachment/file'];

    public $clientOptions = [];

    public $inlineAttachmentOptions = [];

    public function run()
    {
        $id = $this->options['id'];
        if (empty($this->clientOptions['spellChecker'])) {
            $this->clientOptions['spellChecker'] = false;
        }
        $this->clientOptions['element'] = new JsExpression("document.getElementById('$id')");
        SimpleMdeAsset::register($this->view);
        $options = Json::encode($this->clientOptions);
        $request = \Yii::$app->getRequest();

        $this->inlineAttachmentOptions['uploadUrl'] = Url::to($this->uploadUrl);
        $this->inlineAttachmentOptions['extraParams'] = [
            $request->csrfParam => $request->getCsrfToken()
        ];
        $attachmentOptions = Json::encode($this->inlineAttachmentOptions);
        $this->view->registerCss("
            .CodeMirror-fullscreen,
            .editor-toolbar.fullscreen {
                z-index: 1034;
            }
        ");
        $this->view->registerJs("
        (function(){ 
            var editor = new SimpleMDE($options);
            inlineAttachment.editors.codemirror4.attach(editor.codemirror,$attachmentOptions);
        })();");
        if ($this->hasModel()) {
            return Html::activeTextarea($this->model, $this->attribute, $this->options);
        } else {
            return Html::textarea($this->name, $this->value, $this->options);
        }
    }
}