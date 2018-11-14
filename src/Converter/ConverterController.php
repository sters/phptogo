<?php

namespace PhpToGo\Converter;

use PhpParser\ParserFactory;

class ConverterController
{
    /** @var bool */
    public $addHeadCommentBlock = true;
    /** @var bool */
    public $addAdditionalCode = true;
    /** @var GoLikePrettyPrinter */
    private $printer;

    public function __construct()
    {
        $this->printer = new GoLikePrettyPrinter;
    }

    public function registerHook(Hook $instance)
    {
        $instance->register($this->printer);
    }

    /**
     * @param string $phpcode
     * @return string
     */
    public function convert(string $phpcode): string
    {
        // keep blank line hack
        $blankLineDummy = '// ITS DUMMY LINE //';
        $phpcode = preg_replace('/^\s*$/m', $blankLineDummy, $phpcode);

        // convert
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($phpcode);
        $code = $this->printer->prettyPrint($ast);

        // additional codes
        $result = '';
        if ($this->addHeadCommentBlock) {
            $result .= $this->printer->headCommentBlock();
        }
        if ($this->addAdditionalCode) {
            $result .= $this->printer->getAdditionalCode() . "\n\n";
        }
        $result .= $code;

        // revert blank line
        $result = preg_replace('/' . preg_quote($blankLineDummy, '/') . '/', "", $result);

        return $result;
    }
}
