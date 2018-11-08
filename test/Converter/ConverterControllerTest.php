<?php

namespace Test\PhpToGo\Converter;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\ParserFactory;
use PhpToGo\Converter\ConverterController;
use PHPUnit\Framework\TestCase;

class ConverterControllerTest extends TestCase
{
    const INDENT = "    ";

    /** @var ConverterController */
    private $converter;

    public function setUp(): void
    {
        parent::setUp();

        $this->converter = new ConverterController();
        $this->converter->addAdditionalCode = false;
        $this->converter->addHeadCommentBlock = false;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->converter = null;
    }

    /**
     * @param string $code
     * @param string $expectCode
     *
     * @dataProvider providerString
     */
    public function testString($code, $expectCode)
    {
        $ast = $this->getSimpleAst($code)[0];
        if (!($ast instanceof Expression)) {
            $this->fail('Cannot convert to expression: ' . $code);
        }
        if (!($ast->expr instanceof String_)) {
            $this->fail('Cannot convert to array: ' . $code);
        }

        $result = $this->converter->convert("<?php\n" . $code . ";\n");

        $this->assertEquals($expectCode, $result);
    }

    /**
     * @param string $code
     * @return null|\PhpParser\Node\Stmt[]
     */
    private function getSimpleAst(string $code)
    {
        try {
            $tryCode = "<?php\n{$code};\n";

            /** @var \PhpParser\Parser\Multiple $parser */
            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            return $parser->parse($tryCode);

        } catch (\Throwable $ex) {
            $this->fail($this->m(
                "Failed parse: {$ex->getMessage()}",
                "----------",
                $tryCode,
                "----------"
            ));

            return null;
        }
    }

    private function m(...$strings)
    {
        return implode("\n", $strings);
    }

    /**
     * @return array
     */
    public function providerString(): array
    {
        return [
            'single quote' => [
                "'hoge hoge'",
                "\"hoge hoge\"",
            ],
            'double quote' => [
                "\"foo bar\"",
                "\"foo bar\"",
            ],
        ];
    }

    /**
     * @param string $code
     * @param string $expectCode
     *
     * @dataProvider providerArray
     */
    public function testArray($code, $expectCode)
    {
        $ast = $this->getSimpleAst($code)[0];
        if (!($ast instanceof Expression)) {
            $this->fail('cannot convert to expression: ' . $code);
        }
        if (!($ast->expr instanceof Array_)) {
            $this->fail('cannot convert to array: ' . $code);
        }

        $result = $this->converter->convert("<?php\n" . $code . ";\n");

        $this->assertEquals($expectCode, $result);
    }

    /**
     * @return array
     */
    public function providerArray(): array
    {
        return [
            'simple map' => [
                "['key' => 'value', 'hoge' => 'huga']",
                $this->m(
                    'map[string]interface{}{',
                    self::INDENT . '"key" : "value",',
                    self::INDENT . '"hoge" : "huga",',
                    '}'
                ),
            ],
            'simple list' => [
                "[1, 2, 3, 'hoge', \"huga\", 'foo', 4, 5, 6]",
                $this->m(
                    "[]interface{}{",
                    self::INDENT . "1,",
                    self::INDENT . "2,",
                    self::INDENT . "3,",
                    self::INDENT . "\"hoge\",",
                    self::INDENT . "\"huga\",",
                    self::INDENT . "\"foo\",",
                    self::INDENT . "4,",
                    self::INDENT . "5,",
                    self::INDENT . "6,",
                    "}"
                ),
            ],
            'old array' => [
                "array('key' => 'value', 'hoge' => 'huga')",
                $this->m(
                    'map[string]interface{}{',
                    self::INDENT . '"key" : "value",',
                    self::INDENT . '"hoge" : "huga",',
                    '}'
                ),
            ],
            'nested map' => [
                "['key' => ['inner' => 'value'], 'hoge' => ['huga' => ['foo' => 'bar']]]",
                $this->m(
                    'map[string]interface{}{',
                    self::INDENT . '"key" : map[string]interface{}{',
                    self::INDENT . self::INDENT . '"inner" : "value",',
                    self::INDENT . '},',
                    self::INDENT . '"hoge" : map[string]interface{}{',
                    self::INDENT . self::INDENT . '"huga" : map[string]interface{}{',
                    self::INDENT . self::INDENT . self::INDENT . '"foo" : "bar",',
                    self::INDENT . self::INDENT . '},',
                    self::INDENT . '},',
                    '}'
                ),
            ],
            'nested list' => [
                "[1, [2, 3], [[[['foo']], 'bar']]]",
                $this->m(
                    '[]interface{}{',
                    self::INDENT . '1,',
                    self::INDENT . '[]interface{}{',
                    self::INDENT . self::INDENT . '2,',
                    self::INDENT . self::INDENT . '3,',
                    self::INDENT . '},',
                    self::INDENT . '[]interface{}{',
                    self::INDENT . self::INDENT . '[]interface{}{',
                    self::INDENT . self::INDENT . self::INDENT . '[]interface{}{',
                    self::INDENT . self::INDENT . self::INDENT . self::INDENT . '[]interface{}{',
                    self::INDENT . self::INDENT . self::INDENT . self::INDENT . self::INDENT . '"foo",',
                    self::INDENT . self::INDENT . self::INDENT . self::INDENT . '},',
                    self::INDENT . self::INDENT . self::INDENT . '},',
                    self::INDENT . self::INDENT . self::INDENT . '"bar",',
                    self::INDENT . self::INDENT . '},',
                    self::INDENT . '},',
                    '}'
                ),
            ],
        ];
    }

    /**
     * @param string $code
     * @param string $expectCode
     *
     * @dataProvider providerMethodCall
     */
    public function testMethodCall($code, $expectCode)
    {
        $ast = $this->getSimpleAst($code)[0];
        if (!($ast instanceof Expression)) {
            $this->fail('Cannot convert to expression: ' . $code);
        }
        if (!($ast->expr instanceof MethodCall)) {
            $this->fail('Cannot convert to method call: ' . $code);
        }

        $result = $this->converter->convert("<?php\n" . $code . ";\n");

        $this->assertEquals($expectCode, $result);
    }

    /**
     * @return array
     */
    public function providerMethodCall(): array
    {
        return [
            'simple call' => [
                '$this->get()',
                'this.get()',
            ],
            'call with some args' => [
                '$this->get(1, 2, \'hoge\', $dammy, [1, [\'hoge\' => false]])',
                $this->m(
                    'this.get(1, 2, "hoge", dammy, []interface{}{',
                    self::INDENT . '1,',
                    self::INDENT . 'map[string]interface{}{',
                    self::INDENT . self::INDENT . '"hoge" : false,',
                    self::INDENT . '},',
                    '})'
                ),
            ],
            'nested call' => [
                '$this->get($this->get(\'dammy\', $arg))',
                'this.get(this.get("dammy", arg))',
            ],
        ];
    }
}