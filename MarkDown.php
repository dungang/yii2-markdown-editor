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

// work around https://github.com/facebook/hhvm/issues/1120
defined('ENT_HTML401') || define('ENT_HTML401', 0);

class MarkDown extends \cebe\markdown\Markdown
{
    // include block element parsing using traits
    use TableTrait;
    use FencedCodeTrait;

    // include inline element parsing using traits
    use StrikeoutTrait;
    use UrlLinkTrait;

    // include inline element parsing using traits
    // TODO

    /**
     * @var bool whether special attributes on code blocks should be applied on the `<pre>` element.
     * The default behavior is to put them on the `<code>` element.
     */
    public $codeAttributesOnPre = false;

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

    // block parsing

    protected function identifyReference($line)
    {
        return ($line[0] === ' ' || $line[0] === '[') && preg_match('/^ {0,3}\[(.+?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $line);
    }

    /**
     * Consume link references
     */
    protected function consumeReference($lines, $current)
    {
        while (isset($lines[$current]) && preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*('.$this->_specialAttributesRegex.')?\s*$/', $lines[$current], $matches)) {
            $label = strtolower($matches[1]);

            $this->references[$label] = [
                'url' => $this->replaceEscape($matches[2]),
            ];
            if (isset($matches[3])) {
                $this->references[$label]['title'] = $matches[3];
            } else {
                // title may be on the next line
                if (isset($lines[$current + 1]) && preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
                    $this->references[$label]['title'] = $matches[1];
                    $current++;
                }
            }
            if (isset($matches[5])) {
                $this->references[$label]['attributes'] = $matches[5];
            }
            $current++;
        }
        return [false, --$current];
    }


    /**
     * Consume lines for a fenced code block
     */
    protected function consumeFencedCode($lines, $current)
    {
        // consume until ```
        $block = [
            'code',
        ];
        $line = rtrim($lines[$current]);
        if (($pos = strrpos($line, '`')) === false) {
            $pos = strrpos($line, '~');
        }
        $fence = substr($line, 0, $pos + 1);
        $block['attributes'] = substr($line, $pos);
        $content = [];
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            if (rtrim($line = $lines[$i]) !== $fence) {
                $content[] = $line;
            } else {
                break;
            }
        }
        $block['content'] = implode("\n", $content);
        return [$block, $i];
    }

    protected function renderCode($block)
    {
        $attributes = $this->renderAttributes($block);
        return ($this->codeAttributesOnPre ? "<pre$attributes><code>" : "<pre><code$attributes>")
        . htmlspecialchars($block['content'] . "\n", ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</code></pre>\n";
    }


    /**
     * Renders a headline
     */
    protected function renderHeadline($block)
    {
        foreach($block['content'] as $i => $element) {
            if ($element[0] === 'specialAttributes') {
                unset($block['content'][$i]);
                $block['attributes'] = $element[1];
            }
        }
        $tag = 'h' . $block['level'];
        $attributes = $this->renderAttributes($block);
        return "<$tag$attributes>" . rtrim($this->renderAbsy($block['content']), "# \t") . "</$tag>\n";
    }

    protected function renderAttributes($block)
    {
        $html = [];
        if (isset($block['attributes'])) {
            $attributes = preg_split('/\s+/', $block['attributes'], -1, PREG_SPLIT_NO_EMPTY);
            foreach($attributes as $attribute) {
                if ($attribute[0] === '#') {
                    $html['id'] = substr($attribute, 1);
                } else {
                    $html['class'][] = substr($attribute, 1);
                }
            }
        }
        $result = '';
        foreach($html as $attr => $value) {
            if (is_array($value)) {
                $value = trim(implode(' ', $value));
            }
            if (!empty($value)) {
                $result .= " $attr=\"$value\"";
            }
        }
        return $result;
    }



    // inline parsing


    /**
     * @marker {
     */
    protected function parseSpecialAttributes($text)
    {
        if (preg_match("~$this->_specialAttributesRegex~", $text, $matches)) {
            return [['specialAttributes', $matches[1]], strlen($matches[0])];
        }
        return [['text', '{'], 1];
    }

    protected function renderSpecialAttributes($block)
    {
        return '{' . $block[1] . '}';
    }

    protected function parseInline($text)
    {
        $elements = parent::parseInline($text);
        // merge special attribute elements to links and images as they are not part of the final absy later
        $relatedElement = null;
        foreach($elements as $i => $element) {
            if ($element[0] === 'link' || $element[0] === 'image') {
                $relatedElement = $i;
            } elseif ($element[0] === 'specialAttributes') {
                if ($relatedElement !== null) {
                    $elements[$relatedElement]['attributes'] = $element[1];
                    unset($elements[$i]);
                }
                $relatedElement = null;
            } else {
                $relatedElement = null;
            }
        }
        return $elements;
    }

    protected function renderLink($block)
    {
        if (isset($block['refkey'])) {
            if (($ref = $this->lookupReference($block['refkey'])) !== false) {
                $block = array_merge($block, $ref);
            } else {
                return $block['orig'];
            }
        }
        $attributes = $this->renderAttributes($block);
        return '<a href="' . htmlspecialchars($block['url'], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"'
        . (empty($block['title']) ? '' : ' title="' . htmlspecialchars($block['title'], ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8') . '"')
        . $attributes . '>' . $this->renderAbsy($block['text']) . '</a>';
    }

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