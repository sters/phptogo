<?php

use PhpParser\Node\Expr\FuncCall;
use PhpToGo\Converter\GoLikePrettyPrinter;
use PhpToGo\Converter\Hook;

class ExampleHook implements Hook
{
    public function register(GoLikePrettyPrinter $printer)
    {
        $printer->addHook('Expr_FuncCall', function (FuncCall $node, $parentFormatPreserved, \Closure $next) use ($printer) {
            $hookMethodName = 'hook_Expr_FuncCall_' . $node->name->toString();
            if (method_exists($this, $hookMethodName)) {
                return $this->$hookMethodName($node, $parentFormatPreserved, $next, $printer);
            }
            return $next($node, $parentFormatPreserved);
        });
    }

    private function hook_Expr_FuncCall_var_dump(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        return 'fmt.Printf("%+v\n", ' . $printer->pMaybeMultiline($node->args) . ')';
    }

    private function hook_Expr_FuncCall_count(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        return 'len(' . $printer->pMaybeMultiline($node->args) . ')';
    }

    private function hook_Expr_FuncCall_getopt(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        $printer->addAdditionalCode(
            $node->name->toString(),
            implode("\n", [
                ' // FIXME: error handle',
                'func getopt(args string) map[string]string {',
                '    ptr := map[string]*string{}',
                '',
                '    for _, arg := range strings.Split(args, ":") {',
                '        ptr[arg] = flag.String(arg, "", "")',
                '    }',
                '    flag.Parse()',
                '',
                '    result := map[string]string{}',
                '    for k, v := range ptr {',
                '        result[k] = *v',
                '    }',
                '',
                '    return result',
                '}',
            ])
        );

        return 'getopt(' . $printer->pMaybeMultiline($node->args) . ')';
    }

    private function hook_Expr_FuncCall_file_exists(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        $printer->addAdditionalCode(
            $node->name->toString(),
            implode("\n", [
                'func fileExists(filename string) bool {',
                '    _, err := os.Stat(filename)',
                '    return err == nil',
                '}',
            ])
        );

        return 'fileExists(' . $printer->pMaybeMultiline($node->args) . ')';
    }

    private function hook_Expr_FuncCall_file_put_contents(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        $printer->addAdditionalCode(
            $node->name->toString(),
            implode("\n", [
                'func filePutContents(filename string, text string) error {',
                '    return ioutil.WriteFile(filename, []byte(text), 0644)',
                '}',
            ])
        );

        return 'filePutContents(' . $printer->pMaybeMultiline($node->args) . ')';
    }

    private function hook_Expr_FuncCall_file_get_contents(FuncCall $node, $parentFormatPreserved, \Closure $next, GoLikePrettyPrinter $printer)
    {
        $printer->addAdditionalCode(
            $node->name->toString(),
            implode("\n", [
                'func fileGetContents(path string) string {',
                '    tmp, _ := ioutil.ReadFile(path) // FIXME: error handle',
                '    return string(tmp)',
                '}',
            ])
        );

        return 'fileGetContents(' . $printer->pMaybeMultiline($node->args) . ')';
    }
}
