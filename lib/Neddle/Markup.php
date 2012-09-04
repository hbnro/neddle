<?php

namespace Neddle;

class Markup
{

  private static $empty = array(
                    'hr', 'br', 'img', 'base', 'link', 'meta', 'input',
                    'embed', 'param', 'source', 'track', 'area',
                  );



  public static function render($tag, array $args, $text = '')
  {
    $tmp   = array();
    $merge = FALSE;

    foreach ($args as $key => $val) {
      if (is_numeric($key)) {
        $tmp []= "array($val)";
        unset($args[$key]);
      }
    }


    if ( ! empty($args)) {
      $tmp []= var_export($args, TRUE);
    }

    $args = join(',', $tmp);
    $hash = substr(md5($tag . $args . microtime(TRUE)), 0, 7);
    $out  = in_array($tag, static::$empty) ? "$hash<$tag>" : "$hash<$tag>$text</$tag>";

    $repl = "<$tag<?php echo Neddle\Markup::attrs($args); ?>";

    $args && $out = str_replace("$hash<$tag", $repl, $out);

    $out  = str_replace($hash, '', $out);

    return $out;
  }

  public static function attrs(array $args) {
    if (func_num_args() > 1) {
      $set = array_slice(func_get_args(), 1);
      foreach ($set as $one) {
        is_array($one) && $args = array_merge($args, $one);
      }
    }


    $out = array('');

    foreach ($args as $key => $value) {
      if (is_bool($value)) {
        if ($value) {
          $out []= $key;
        }
      } elseif (is_array($value)) { // TODO: nested attributes for data-*, etc.
        foreach ($value as $index => $test) {
          $out []= $key . '-' . $index . '="' . (string) $test . '"';
        }
      } elseif ( ! is_numeric($key)) {
        $out []= $key . '="' . Helpers::quote($value) . '"';
      }
    }

    $out = join(' ', $out);

    return $out;
  }

}
