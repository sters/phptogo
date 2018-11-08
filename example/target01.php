<?php

/**
 * @param int $count
 * @return array
 */
function fibonacci($count)
{
    switch ($count) {
        case 1:
            return [1];

        case 2:
            return [1, 1];

        default:
            $tmp = fibonacci($count - 1);
            $tmp[] = $tmp[count($tmp) - 1] + $tmp[count($tmp) - 2];
            return $tmp;
    }
}

var_dump(fibonacci(10));