<?php namespace Golonka\BBCode;

use \Golonka\BBCode\Traits\ArrayTrait;
use Thunder\Shortcode\HandlerContainer\HandlerContainer;
use Thunder\Shortcode\Parser\RegularParser;
use Thunder\Shortcode\Processor\Processor;
use Thunder\Shortcode\Processor\ProcessorInterface;
use Thunder\Shortcode\Shortcode\ProcessedShortcode;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class BBCodeParser
{
    use ArrayTrait;

    /** @var ProcessorInterface */
    private $shortcode;
    /** @var HandlerContainer */
    private $handlers;

    public function __construct()
    {
        $this->shortcode = new Processor(new RegularParser(), $this->createHandlers());
    }

    private function createHandlers()
    {
        $handlers = new HandlerContainer();

        $handlers->add('b', function(ShortcodeInterface $s) {
            return '<strong>'.$s->getContent().'</strong>';
        });
        $handlers->add('i', function(ShortcodeInterface $s) {
            return '<em>'.$s->getContent().'</em>';
        });
        $handlers->add('u', function(ShortcodeInterface $s) {
            return '<u>'.$s->getContent().'</u>';
        });
        $handlers->add('s', function(ShortcodeInterface $s) {
            return '<strike>'.$s->getContent().'</strike>';
        });
        $handlers->add('size', function(ShortcodeInterface $s) {
            return '<font size="'.$s->getBbCode().'">'.$s->getContent().'</font>';
        });
        $handlers->add('color', function(ShortcodeInterface $s) {
            return '<font color="#'.$s->getBbCode().'">'.$s->getContent().'</font>';
        });
        $handlers->add('center', function(ShortcodeInterface $s) {
            return '<div style="text-align:center;">'.$s->getContent().'</div>';
        });
        $handlers->add('left', function(ShortcodeInterface $s) {
            return '<div style="text-align:left;">'.$s->getContent().'</div>';
        });
        $handlers->add('right', function(ShortcodeInterface $s) {
            return '<div style="text-align:right;">'.$s->getContent().'</div>';
        });
        $handlers->add('quote', function(ShortcodeInterface $s) {
            return '<blockquote>'.($s->getBbCode() ? '<small>'.$s->getBbCode().'</small>' : '').$s->getContent().'</blockquote>';
        });
        $handlers->add('url', function(ShortcodeInterface $s) {
            return '<a href="'.($s->getBbCode() ?: $s->getContent()).'">'.$s->getContent().'</a>';
        });
        $handlers->add('img', function(ShortcodeInterface $s) {
            return '<img src="'.$s->getContent().'">';
        });
        $handlers->add('list', function(ShortcodeInterface $s) {
            $items = '';
            $listItems = array_filter(array_map('trim', explode('[*]', $s->getContent())));
            foreach($listItems as $item) {
                $items .= '<li>'.$item.'</li>';
            }

            if('1' === $s->getBbCode()) {
                return '<ol>'.$items.'</ol>';
            }

            if('a' === $s->getBbCode()) {
                return '<ol type="a">'.$items.'</ol>';
            }

            return '<ul>'.$items.'</ul>';
        });
        $handlers->add('code', function(ProcessedShortcode $s) {
            return '<code>'.$s->getTextContent().'</code>';
        });
        $handlers->add('youtube', function(ShortcodeInterface $s) {
            return '<iframe width="560" height="315" src="//www.youtube.com/embed/'.$s->getContent().'" frameborder="0" allowfullscreen></iframe>';
        });
        $handlers->add('linebreak', function(ShortcodeInterface $s) {
            return '<br />';
        });

        $this->handlers = $handlers;

        return $handlers;
    }

    /**
     * Parses the BBCode string
     * @param  string $source String containing the BBCode
     * @return string Parsed string
     */
    public function parse($source, $caseInsensitive = false)
    {
        if($caseInsensitive) {
            $handlers = $this->handlers;

            $this->handlers->setDefault(function(ProcessedShortcode $s) use($handlers) {
                $handler = $handlers->get(strtolower($s->getName()));

                return $handler ? call_user_func_array($handler, array($s)) : $s->getShortcodeText();
            });
        }

        return $this->shortcode->process($source);
    }

    /**
     * Remove all BBCode
     * @param  string $source
     * @return string Parsed text
     */
    public function stripBBCodeTags($source)
    {
        $handlers = new HandlerContainer();
        $handlers->setDefault(function(ShortcodeInterface $s) { return $s->getContent(); });
        $processor = new Processor(new RegularParser(), $handlers);

        return $processor->process($source);
    }
    /**
     * Searches after a specified pattern and replaces it with provided structure
     * @param  string $pattern Search pattern
     * @param  string $replace Replacement structure
     * @param  string $source Text to search in
     * @return string Parsed text
     */
    protected function searchAndReplace($pattern, $replace, $source)
    {
        while (preg_match($pattern, $source)) {
            $source = preg_replace($pattern, $replace, $source);
        }

        return $source;
    }

    /**
     * Helper function to parse case sensitive
     * @param  string $source String containing the BBCode
     * @return string Parsed text
     */
    public function parseCaseSensitive($source)
    {
        return $this->parse($source, false);
    }

    /**
     * Helper function to parse case insensitive
     * @param  string $source String containing the BBCode
     * @return string Parsed text
     */
    public function parseCaseInsensitive($source)
    {
        return $this->parse($source, true);
    }

    /**
     * Limits the parsers to only those you specify
     * @param  mixed $only parsers
     * @return object BBCodeParser object
     */
    public function only($only = null)
    {
        if(null === $only) {
            return $this;
        }

        $handlers = new HandlerContainer();

        foreach(func_get_args() as $name) {
            $handlers->add($name, $this->handlers->get($name));
        }

        $this->handlers = $handlers;
        $this->shortcode = new Processor(new RegularParser(), $this->handlers);

        return $this;
    }

    /**
     * Removes the parsers you want to exclude
     * @param  mixed $except parsers
     * @return object BBCodeParser object
     */
    public function except($except = null)
    {
        if(null === $except) {
            return $this;
        }

        foreach(func_get_args() as $name) {
            $this->handlers->remove($name);
        }

        $this->shortcode = new Processor(new RegularParser(), $this->handlers);

        return $this;
    }

    /**
     * List of chosen parsers
     * @return array array of parsers
     */
    public function getParsers()
    {
        return $this->handlers->getNames();
    }

    /**
     * Sets the parser pattern and replace.
     * This can be used for new parsers or overwriting existing ones.
     * @param string $name Parser name
     * @param string $handler handler
     * @return void
     */
    public function setParser($name, $handler)
    {
        $this->handlers->add($name, $handler);
        $this->shortcode = new Processor(new RegularParser(), $this->handlers);
    }
}
