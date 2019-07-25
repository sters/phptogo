# PHP to Go

[![CircleCI](https://circleci.com/gh/sters/phptogo.svg?style=svg)](https://circleci.com/gh/sters/phptogo)

This is transpiler for PHP code to Go like something code.

## Example and Usage

Let's see `example` directory, and try this.

First, execute `git clone` and `composer install`.

```
$ composer run -l
scripts:
  test             Runs the test script as defined in composer.json.
  convert          Runs the convert script as defined in composer.json.
  convert-example  Runs the convert-example script as defined in composer.json.

$ composer run convert-example
> cd example/simple-convert && rm result.go && php main.php -i target.php -o result.go
Starting: target.php

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


$ composer run convert
> php example/simple-convert/main.php
You must need some options:
	 -i input-file. If missing, program exit.
	 -o output-file. If empty, output to stdout.
Script php example/simple-convert/main.php handling the convert event returned with error code 1
```
