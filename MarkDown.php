<?php
/**
 * Author: dungang
 * Date: 2017/4/11
 * Time: 15:57
 */

namespace dungang\simplemde;


use cebe\markdown\block\FencedCodeTrait;
use cebe\markdown\block\TableTrait;
use cebe\markdown\inline\StrikeoutTrait;
use cebe\markdown\inline\UrlLinkTrait;
use yii\helpers\ArrayHelper;

class MarkDown extends \cebe\markdown\Markdown
{
    // include block element parsing using traits
    use TableTrait;
    use FencedCodeTrait;

    // include inline element parsing using traits
    use StrikeoutTrait;
    use UrlLinkTrait;

    /**
     * @var boolean whether to interpret newlines as `<br />`-tags.
     * This feature is useful for comments where newlines are often meant to be real new lines.
     */
    public $enableNewlines = false;

    /**
     * @inheritDoc
     */
    protected $escapeCharacters = [
        // from Markdown
        '\\', // backslash
        '`', // backtick
        '*', // asterisk
        '_', // underscore
        '{', '}', // curly braces
        '[', ']', // square brackets
        '(', ')', // parentheses
        '#', // hash mark
        '+', // plus sign
        '-', // minus sign (hyphen)
        '.', // dot
        '!', // exclamation mark
        '<', '>',
        // added by GithubMarkdown
        ':', // colon
        '|', // pipe
    ];



    /**
     * Consume lines for a paragraph
     *
     * Allow headlines, lists and code to break paragraphs
     */
    protected function consumeParagraph($lines, $current)
    {
        // consume until newline
        $content = [];
        for ($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if ($line === ''
                || ltrim($line) === ''
                || !ctype_alpha($line[0]) && (
                    $this->identifyQuote($line, $lines, $i) ||
                    $this->identifyFencedCode($line, $lines, $i) ||
                    $this->identifyUl($line, $lines, $i) ||
                    $this->identifyOl($line, $lines, $i) ||
                    $this->identifyHr($line, $lines, $i)
                )
                || $this->identifyHeadline($line, $lines, $i))
            {
                break;
            } elseif ($this->identifyCode($line, $lines, $i)) {
                // possible beginning of a code block
                // but check for continued inline HTML
                // e.g. <img src="file.jpg"
                //           alt="some alt aligned with src attribute" title="some text" />
                if (preg_match('~<\w+([^>]+)$~s', implode("\n", $content))) {
                    $content[] = $line;
                } else {
                    break;
                }
            } else {
                $content[] = $line;
            }
        }
        $block = [
            'paragraph',
            'content' => $this->parseInline(implode("\n", $content)),
        ];
        return [$block, --$i];
    }

    /**
     * @inheritdocs
     *
     * Parses a newline indicated by two spaces on the end of a markdown line.
     */
    protected function renderText($text)
    {
        if ($this->enableNewlines) {
            $br = $this->html5 ? "<br>\n" : "<br />\n";
            return strtr($text[1], ["  \n" => $br, "\n" => $br]);
        } else {
            return parent::renderText($text);
        }
    }

    protected function renderImage($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookupReference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                return $block['orig'];
            }
        }
        return '<img class="inline-markdown-image" src="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
        . ' alt="' . htmlspecialchars($block['text'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
        . ($this->html5 ? '>' : ' />');
    }

    private static $_instance;

    public static function marked($string,$config=[])
    {
        if (!self::$_instance) {
            self::$_instance = new MarkDown();
            $config = ArrayHelper::merge([
                'html5' => true,
                'enableNewlines' => true,
            ],$config);
            foreach($config as $name=>$value){
                self::$_instance->$name = $value;
            }
        }
        return self::$_instance->parse($string);
    }
}