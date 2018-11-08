# PHP to Go

This is transpiler for PHP code to Go like something code.

## Example and Usage

Let's see `example` directory, and try this.

```
$ composer run -l
scripts:
  test             Runs the test script as defined in composer.json.
  convert-example  Runs the convert-example script as defined in composer.json.


$ composer run convert-example
> php example/main.php
You must need some options:
         -i input-file. If missing, program exit.
         -o output-file. If empty, output to stdout.
Script php example/main.php handling the convert-example event returned with error code 1


$ composer run convert-example -- -i example/target01.php -o example/result01.go
> php example/main.php "-i" "example/target01.php" "-o" "example/result01.go"
Starting: example/target01.php


$ diff --ignore-all-space --side-by-side example/target01.php example/result01.go
<?php                                                         | // Code generated. MUST EDIT!
                                                              >
                                                              >
                                                              >

/**                                                             /**
 * @param int $count                                             * @param int $count
 * @return array                                                 * @return array
 */                                                              */
function fibonacci($count)                                    | func fibonacci(count){
{                                                             |     switch (count) {
    switch ($count) {                                         <
        case 1:                                                         case 1:
            return [1];                                       |             return []interface{}{
                                                              >                 1,
                                                              >             }

        case 2:                                                         case 2:
            return [1, 1];                                    |             return []interface{}{
                                                              >                 1,
                                                              >                 1,
                                                              >             }

        default:                                                        default:
            $tmp = fibonacci($count - 1);                     |             tmp = fibonacci(count - 1)
            $tmp[] = $tmp[count($tmp) - 1] + $tmp[count(      |             tmp[] = tmp[len(tmp) - 1] + tmp[len(tmp) - 2]
            return $tmp;                                      |             return tmp
    }                                                               }
}                                                               }

var_dump(fibonacci(10));                                      | fmt.Printf("%+v\n", fibonacci(10))
```
