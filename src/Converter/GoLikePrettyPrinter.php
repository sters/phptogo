<?php

namespace PhpToGo\Converter;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;

class GoLikePrettyPrinter extends Standard
{
    /** @var string */
    private $cannotConvertMessage = ' /* cannot convert */';

    /** @var mixed */
    private $hooks = [];

    /** @var array */
    private $additionalCodes = [];

    /** @var Node */
    private $lastNode = null;

    /** @var Lexer */
    private $phpDocLexer = null;

    /** @var PhpDocParser */
    private $phpDocParser = null;

    /**
     * GoLikePrettyPrinter constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);

        $this->phpDocLexer = new Lexer();
        $this->phpDocParser = new PhpDocParser(new TypeParser(), new ConstExprParser());
    }

    /**
     * @param string $key
     * @param string $code
     */
    public function addAdditionalCode(string $key, string $code)
    {
        if (empty($this->additionalCodes[$key])) {
            $this->additionalCodes[$key] = $code;
        }
    }

    /**
     * @return string
     */
    public function getAdditionalCode(): string
    {
        return implode("\n\n", $this->additionalCodes);
    }

    /**
     * @param string $name
     * @param \Closure $func
     */
    public function addHook(string $name, \Closure $func)
    {
        if (!empty($this->hooks[$name])) {
            $this->hooks[$name] = [];
        }

        $this->hooks[$name][] = $func;
    }

    /**
     * @return string
     */
    public function headCommentBlock(): string
    {
        return <<<EOF
// Code generated. MUST EDIT!


EOF;
    }

    /**
     * @inheritdoc
     */
    public function prettyPrint(array $stmts): string
    {
        $this->lastNode = null;
        return parent::prettyPrint($stmts);
    }

    protected function pParam(Node\Param $node)
    {
        return ''
            . ($node->byRef ? '&' : '')
            . ($node->variadic ? '...' : '')
            . $this->p($node->var)
            . ($node->type ? ' ' . $this->p($node->type) : '');
    }

    /**
     * @inheritdoc
     */
    protected function p(Node $node, $parentFormatPreserved = false): string
    {
        if (!$this->origTokens && !empty($this->hooks[$node->getType()])) {
            $hook = array_reduce(
                array_reverse($this->hooks[$node->getType()]),
                function ($carry, $item) {
                    if (!($item instanceof \Closure)) {
                        return $item;
                    }

                    return function (Node $node, $parentFormatPreserved) use ($carry, $item) {
                        return $item($node, $parentFormatPreserved, $carry);
                    };
                },
                function (Node $node, $parentFormatPreserved) {
                    return parent::p($node, $parentFormatPreserved);
                }
            );

            return $hook($node, $parentFormatPreserved);
        }

        return parent::p($node, $parentFormatPreserved);
    }

    protected function pNullableType(Node\NullableType $node)
    {
        return $this->p($node->type);
    }

    protected function pVarLikeIdentifier(Node\VarLikeIdentifier $node)
    {
        return $node->name;
    }

    protected function pName(Name $node)
    {
        return implode('.', $node->parts);
    }

    protected function pName_FullyQualified(Name\FullyQualified $node)
    {
        return implode('.', $node->parts);
    }

    protected function pName_Relative(Name\Relative $node)
    {
        return 'namespace.' . implode('.', $node->parts);
    }

    protected function pScalar_MagicConst_Class(MagicConst\Class_ $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Dir(MagicConst\Dir $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_File(MagicConst\File $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Function(MagicConst\Function_ $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Line(MagicConst\Line $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Method(MagicConst\Method $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Namespace(MagicConst\Namespace_ $node)
    {
        return '';
    }

    protected function pScalar_MagicConst_Trait(MagicConst\Trait_ $node)
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    protected function pScalar_String(Scalar\String_ $node)
    {
        $kind = $node->getAttribute('kind', Scalar\String_::KIND_SINGLE_QUOTED);
        switch ($kind) {
            case Scalar\String_::KIND_NOWDOC:
            case Scalar\String_::KIND_HEREDOC:
                $label = $node->getAttribute('docLabel');
                if ($label && !$this->containsEndLabel($node->value, $label)) {
                    if ($node->value === '') {
                        $escaped = $this->escapeString($label, "`");
                    } else {
                        $escaped = $this->escapeString($node->value, "`");
                    }
                    return "`\n$escaped\n`";
                }
                break;

            case Scalar\String_::KIND_SINGLE_QUOTED:
            case Scalar\String_::KIND_DOUBLE_QUOTED:
                return '"' . $this->escapeString($node->value, '"') . '"';
        }

        throw new \RuntimeException('Invalid string kind');
    }

    protected function pScalar_Encapsed(Scalar\Encapsed $node)
    {
        if ($node->getAttribute('kind') === Scalar\String_::KIND_HEREDOC) {
            $label = $node->getAttribute('docLabel');
            if ($label && !$this->encapsedContainsEndLabel($node->parts, $label)) {
                if (count($node->parts) === 1
                    && $node->parts[0] instanceof Scalar\EncapsedStringPart
                    && $node->parts[0]->value === ''
                ) {
                    $escaped = $this->escapeString($label, "`");
                } else {
                    $escaped = $this->escapeString($this->pEncapsList($node->parts, null), "`");
                }
                return "`\n$escaped\n`";
            }
        }

        return '"' . $this->pEncapsList($node->parts, '"') . '"';
    }

    protected function pExpr_AssignRef(Expr\AssignRef $node)
    {
        return $this->pInfixOp(Expr\AssignRef::class, $node->var, ' = &', $node->expr);
    }

    protected function pExpr_AssignOp_Concat(AssignOp\Concat $node)
    {
        return $this->pInfixOp(AssignOp\Concat::class, $node->var, ' += ', $node->expr);
    }

    protected function pExpr_AssignOp_Pow(AssignOp\Pow $node)
    {
        return $this->pInfixOp(AssignOp\Pow::class, $node->var, ' = math.Pow(DUMMY, ', $node->expr)
            . ') // FIXME **= not support';
    }

    protected function pExpr_BinaryOp_Pow(BinaryOp\Pow $node)
    {
        return 'math.Pow(' . $this->pInfixOp(BinaryOp\Pow::class, $node->left, ', ', $node->right) . ')';
    }

    protected function pExpr_BinaryOp_LogicalAnd(BinaryOp\LogicalAnd $node)
    {
        return $this->pInfixOp(BinaryOp\LogicalAnd::class, $node->left, ' & ', $node->right);
    }

    protected function pExpr_BinaryOp_LogicalOr(BinaryOp\LogicalOr $node)
    {
        return $this->pInfixOp(BinaryOp\LogicalOr::class, $node->left, ' | ', $node->right);
    }

    protected function pExpr_BinaryOp_LogicalXor(BinaryOp\LogicalXor $node)
    {
        return $this->pInfixOp(BinaryOp\LogicalXor::class, $node->left, ' ^ ', $node->right);
    }

    protected function pExpr_BinaryOp_Coalesce(BinaryOp\Coalesce $node)
    {
        return parent::pExpr_BinaryOp_Coalesce($node) . ' /* connot convert */';
        // NOTE: like this.
        // $code = "if " . $this->p($node->left) . " == null {\n";
        // $this->indent();
        // $code .= $this->p($node->right) . "\n";
        // $this->outdent();
        // $code .= "}";
    }

    protected function pExpr_Instanceof(Expr\Instanceof_ $node)
    {
        return parent::pExpr_Instanceof($node) . $this->cannotConvertMessage;
        // NOTE: like this.
        // "if instance, ok := " . $this->p($node->expr) . ".(" . $this->p($node->class) . "); ok {\n// write your code\n}"
    }

    protected function pExpr_BitwiseNot(Expr\BitwiseNot $node)
    {
        return $this->pPrefixOp(Expr\BitwiseNot::class, '^', $node->expr);
    }

    protected function pExpr_ErrorSuppress(Expr\ErrorSuppress $node)
    {
        return $this->pPrefixOp(Expr\ErrorSuppress::class, '', $node->expr) . ' /* NOTE: suppress error */';
    }

    protected function pExpr_YieldFrom(Expr\YieldFrom $node)
    {
        return parent::pExpr_YieldFrom($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Print(Expr\Print_ $node)
    {
        return $this->pPrefixOp(Expr\Print_::class, 'fmt.Print(', $node->expr) . ')';
    }

    protected function pExpr_Cast_Int(Cast\Int_ $node)
    {
        return $this->pPrefixOp(Cast\Int_::class, 'int(', $node->expr) . ')';
    }

    protected function pExpr_Cast_Double(Cast\Double $node)
    {
        return $this->pPrefixOp(Cast\Double::class, 'double(', $node->expr) . ')';
    }

    protected function pExpr_Cast_String(Cast\String_ $node)
    {
        return $this->pPrefixOp(Cast\String_::class, 'string(', $node->expr) . ')';
    }

    protected function pExpr_Cast_Array(Cast\Array_ $node)
    {
        return parent::pExpr_Cast_Array($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Cast_Object(Cast\Object_ $node)
    {
        return parent::pExpr_Cast_Object($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Cast_Bool(Cast\Bool_ $node)
    {
        return $this->pPrefixOp(Cast\Bool_::class, 'bool(', $node->expr) . ')';
    }

    protected function pExpr_Cast_Unset(Cast\Unset_ $node)
    {
        return parent::pExpr_Cast_Unset($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_FuncCall(Expr\FuncCall $node)
    {
        return $this->pCallLhs($node->name)
            . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    /**
     * NOTE: Copy from parent class. But always trailingComma = true
     * @param array $nodes
     * @return bool
     */
    public function pMaybeMultiline(array $nodes)
    {
        if (!$this->hasNodeWithComments($nodes)) {
            return $this->pCommaSeparated($nodes);
        } else {
            return $this->pCommaSeparatedMultiline($nodes, true) . $this->nl;
        }
    }

    /**
     * NOTE: Copy from parent class.
     * @param array $nodes
     * @return bool
     */
    private function hasNodeWithComments(array $nodes)
    {
        foreach ($nodes as $node) {
            if ($node && $node->getComments()) {
                return true;
            }
        }
        return false;
    }

    protected function pExpr_MethodCall(Expr\MethodCall $node)
    {
        return $this->pDereferenceLhs($node->var) . '.' . $this->pObjectProperty($node->name)
            . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_StaticCall(Expr\StaticCall $node)
    {
        return $this->pDereferenceLhs($node->class) . '.'
            . ($node->name instanceof Expr
                ? $this->p($node->name) : $node->name)
            . '(' . $this->pMaybeMultiline($node->args) . ')';
    }

    protected function pExpr_Empty(Expr\Empty_ $node)
    {
        return parent::pExpr_Empty($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Isset(Expr\Isset_ $node)
    {
        return parent::pExpr_Isset($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Eval(Expr\Eval_ $node)
    {
        return parent::pExpr_Eval($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Include(Expr\Include_ $node)
    {
        return parent::pExpr_Include($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_List(Expr\List_ $node)
    {
        return $this->pCommaSeparated($node->items);
    }

    protected function pExpr_Variable(Expr\Variable $node)
    {
        if ($node->name instanceof Expr) {
            return $this->p($node->name);
        } else {
            return $node->name;
        }
    }

    protected function pExpr_Array(Expr\Array_ $node)
    {
        $results = [];
        $isMap = false;

        // convert recursive array
        $this->indent();
        foreach ($node->items as $item) {
            if (!$isMap && !is_null($item->key)) {
                $isMap = true;
            }
            if (is_null($item->key)) {
                $results[] = $this->getIndent() . $this->p($item->value);
            } else {
                $results[] = $this->getIndent() . $this->p($item->key) . ' => ' . $this->p($item->value);
            }
        }
        $this->outdent();

        // check is map
        if ($isMap) {
            $type = 'map[string]interface{}';
        } else {
            $type = '[]interface{}';
        }

        // build to Go like code
        return str_replace(
            '=>',
            ':',
            "{$type}{\n" . implode(",\n", $results) . ",{$this->nl}}"
        );
    }

    protected function getIndent($indentLevel = null)
    {
        return str_repeat(' ', $indentLevel ?? $this->indentLevel);
    }

    protected function pExpr_ArrayItem(Expr\ArrayItem $node)
    {
        return (null !== $node->key ? $this->p($node->key) . ' : ' : '')
            . ($node->byRef ? '&' : '') . $this->p($node->value);
    }

    protected function pExpr_ClassConstFetch(Expr\ClassConstFetch $node)
    {
        return $this->p($node->class) . '.' . $this->p($node->name);
    }

    protected function pExpr_PropertyFetch(Expr\PropertyFetch $node)
    {
        return $this->pDereferenceLhs($node->var) . '.' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_StaticPropertyFetch(Expr\StaticPropertyFetch $node)
    {
        return $this->pDereferenceLhs($node->class) . '.' . $this->pObjectProperty($node->name);
    }

    protected function pExpr_ShellExec(Expr\ShellExec $node)
    {
        return parent::pExpr_ShellExec($node) . $this->cannotConvertMessage;
    }

    protected function pExpr_Closure(Expr\Closure $node)
    {
        return ''
            . 'func ' . ($node->byRef ? '&' : '')
            . '(' . $this->pCommaSeparated($node->params) . ')'
            . (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')' : '')
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';

    }

    protected function pExpr_New(Expr\New_ $node)
    {
        if ($node->class instanceof Stmt\Class_) {
            return parent::pExpr_New($node) . $this->cannotConvertMessage;
        }
        return '&' . $this->p($node->class) . '{' . $this->pMaybeMultiline($node->args) . '}';
    }

    protected function pExpr_Clone(Expr\Clone_ $node)
    {
        return $this->p($node->expr);
    }

    protected function pExpr_Ternary(Expr\Ternary $node)
    {
        $code = "if " . $this->p($node->cond) . " {" . $this->nl;
        $this->indent();
        $code .= $this->p($node->if) . $this->nl;
        $this->outdent();
        $code .= "} else {" . $this->nl;
        $this->indent();
        $code .= $this->p($node->else);
        $this->outdent();
        $code .= "}";

        return $code;
    }

    protected function pExpr_Exit(Expr\Exit_ $node)
    {
        return 'os.Exit('
            . (null !== $node->expr ? $this->p($node->expr) : '')
            . ')';
    }

    protected function pExpr_Yield(Expr\Yield_ $node)
    {
        return parent::pExpr_Yield($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_Namespace(Stmt\Namespace_ $node)
    {
        if ($this->canUseSemicolonNamespaces) {
            return 'package ' . $this->p($node->name) . ';'
                . $this->nl . $this->pStmts($node->stmts, false);
        } else {
            return 'package' . (null !== $node->name ? ' ' . $this->p($node->name) : '')
                . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
        }
    }

    protected function pStmt_Use(Stmt\Use_ $node)
    {
        return parent::pStmt_Use($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_GroupUse(Stmt\GroupUse $node)
    {
        return parent::pStmt_GroupUse($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_UseUse(Stmt\UseUse $node)
    {
        return parent::pStmt_UseUse($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_Interface(Stmt\Interface_ $node)
    {
        return 'type ' . $node->name . ' interface'
            . $this->nl . '{'
            . (!empty($node->extends) ? $this->pMaybeMultiline($node->extends) : '')
            . $this->pStmts($node->stmts)
            . $this->nl . '}';
    }

    protected function pStmt_Trait(Stmt\Trait_ $node)
    {
        return parent::pStmt_Trait($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_TraitUse(Stmt\TraitUse $node)
    {
        return parent::pStmt_TraitUse($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_TraitUseAdaptation_Precedence(Stmt\TraitUseAdaptation\Precedence $node)
    {
        return parent::pStmt_TraitUseAdaptation_Precedence($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_TraitUseAdaptation_Alias(Stmt\TraitUseAdaptation\Alias $node)
    {
        return parent::pStmt_TraitUseAdaptation_Alias($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_Property(Stmt\Property $node)
    {
        return 'var (' . $this->nl . $this->pMaybeMultiline($node->props) . $this->nl . ')';
    }

    protected function pStmt_PropertyProperty(Stmt\PropertyProperty $node)
    {
        return $node->name
            . (null !== $node->default ? ' = ' . $this->p($node->default) : '');

    }

    protected function pStmt_ClassConst(Stmt\ClassConst $node)
    {
        return $this->pModifiers($node->flags)
            . 'const ' . $this->pCommaSeparated($node->consts);
    }

    protected function pStmt_ClassMethod(Stmt\ClassMethod $node)
    {
        return $this->pFunctionLike($node);
    }

    protected function pStmt_Function(Stmt\Function_ $node)
    {
        return $this->pFunctionLike($node);
    }

    protected function pStmt_Const(Stmt\Const_ $node)
    {
        return 'const ' . $this->pCommaSeparated($node->consts);
    }

    protected function pStmt_Declare(Stmt\Declare_ $node)
    {
        return parent::pStmt_Declare($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_DeclareDeclare(Stmt\DeclareDeclare $node)
    {
        return parent::pStmt_DeclareDeclare($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_If(Stmt\If_ $node)
    {
        return 'if ' . $this->p($node->cond) . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}'
            . ($node->elseifs ? ' ' . $this->pImplode($node->elseifs, ' ') : '')
            . (null !== $node->else ? ' ' . $this->p($node->else) : '');

    }

    protected function pStmt_ElseIf(Stmt\ElseIf_ $node)
    {
        return 'else if (' . $this->p($node->cond) . ') {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_For(Stmt\For_ $node)
    {
        return 'for '
            . $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '')
            . $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '')
            . $this->pCommaSeparated($node->loop)
            . ' {' . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Foreach(Stmt\Foreach_ $node)
    {
        return 'for '
            . (null !== $node->keyVar ? $this->p($node->keyVar) : '_')
            . ', '
            . ($node->byRef ? '&' : '') . $this->p($node->valueVar)
            . ' := range ' . $this->p($node->expr) . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_While(Stmt\While_ $node)
    {
        return 'for ' . $this->p($node->cond) . ' {'
            . $this->pStmts($node->stmts) . $this->nl . '}';
    }

    protected function pStmt_Do(Stmt\Do_ $node)
    {
        return 'for {' . $this->pStmts($node->stmts) . $this->nl
            . $this->nl
            . 'if ' . $this->p($node->cond) . ' { break }' . $this->nl
            . '}';
    }

    protected function pStmt_TryCatch(Stmt\TryCatch $node)
    {
        return '// TODO: error trap' . $this->nl
            . $this->pStmts($node->stmts);
    }

    protected function pStmt_Catch(Stmt\Catch_ $node)
    {
        return '/* // TODO: error trap'
            . parent::pStmt_Catch($node)
            . ' */';
    }

    protected function pStmt_Finally(Stmt\Finally_ $node)
    {
        return '/* // TODO: error trap'
            . parent::pStmt_Finally($node)
            . ' */';
    }

    protected function pStmt_Return(Stmt\Return_ $node)
    {
        return 'return' . (null !== $node->expr ? ' ' . $this->p($node->expr) : '');
    }

    protected function pStmt_Throw(Stmt\Throw_ $node)
    {
        return 'return errors.New(' . $this->p($node->expr) . ');';
    }

    protected function pStmt_Expression(Stmt\Expression $node)
    {
        return $this->p($node->expr);
    }

    protected function pStmt_Echo(Stmt\Echo_ $node)
    {
        return 'fmt.Print(' . $this->pCommaSeparated($node->exprs) . ')';
    }

    protected function pStmt_Static(Stmt\Static_ $node)
    {
        return parent::pStmt_Static($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_Global(Stmt\Global_ $node)
    {
        return parent::pStmt_Global($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_Unset(Stmt\Unset_ $node)
    {
        return parent::pStmt_Unset($node) . $this->cannotConvertMessage;
    }

    protected function pStmt_InlineHTML(Stmt\InlineHTML $node)
    {
        return '/* NOTE: Inline HTML' . $this->nl . $node->value . $this->nl . '*/';
    }

    protected function pStmt_HaltCompiler(Stmt\HaltCompiler $node)
    {
        return 'panic();' . $node->remaining;
    }

    protected function pClassCommon(Stmt\Class_ $node, $afterClassToken)
    {
        return '// TODO: Think structure strategy.'
            . parent::pClassCommon($node, $afterClassToken);
    }

    protected function pSingleQuotedString(string $string)
    {
        return '"' . addcslashes($string, '"\\') . '"';
    }

    /**
     * @param string $input
     * @return PhpDocNode
     */
    private function parseDocComment(string $input): PhpDocNode
    {
        $tokens = new TokenIterator($this->phpDocLexer->tokenize($input));
        return $this->phpDocParser->parse($tokens);
    }

    /**
     * @param Stmt\Function_|Stmt\ClassMethod $node
     * @return string
     */
    private function pFunctionLike($node): string
    {
        $comment = $this->parseDocComment($node->getDocComment() ?? '/** */');
        $paramsComment = $comment->getParamTagValues();

        $params = [];
        foreach ($node->params as $paramNode) {
            if (null === $paramNode) {
                $params[] = '';
                continue;
            }

            $typeString = null;
            foreach($paramsComment as $paramComment) {
                if ($paramComment->parameterName === ('$' . $paramNode->var->name)) {
                    $typeString = $paramComment->type;
                    break;
                }
            }

            $params[] = $this->p($paramNode) . ($typeString != null ? ' ' . $typeString : '');
        }
        $paramsString = implode(', ', $params);

        return 'func ' . ($node->byRef ? '&' : '') . $node->name
            . '(' . $paramsString . ')'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . (null !== $node->stmts
                ? ' {' . $this->pStmts($node->stmts) . $this->nl . '}'
                : ' {}');
    }
}