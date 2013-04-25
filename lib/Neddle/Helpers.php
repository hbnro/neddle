<?php

namespace Neddle;

class Helpers
{

  private static $qt = array(
                    '\\' => '!uß;',
                    "'" => '!u€;',
                    '$' => '!u£;',
                  );

  private static $fix = array(
                    '/\s*<\/pre>/s' => '</pre>',
                    '/(?<![:\w]):([_a-zA-Z][\w-]*)(?=\s*(?:[,)\];]|$|=>))/' => "'\\1'",
                    '/([,([]\s*)([a-z][\w:-]*)\s*=>\s*/' => "\\1'\\2' => ",
                    '/<\?=\s*(.+?)\s*;?\s*\?>/' => '<?php echo \\1; ?>',
                    '/\?>\s*<\?php\s+(?=else|finally|catch)/s' => '',
                    '/#\{(.+?)\}/' => '<?php echo \\1; ?>',
                    '/!Æ;/' => "\n",
                  );

  private static $prep = array(
                    '/\s*\|/m' => '!Æ;',
                    '/[\r\n]/' => "\n",
                    "/\s*, *\n+\s*/" => ', ',
                    "/\s*\\\\ *\n+\s*/" => ' ',
                  );

  private static $args_expr = '/(?:^|\s+)(?:([\w:-]+)\s*=\s*([\'"]?)(.*?)\\2|[\w:-]+)(?=\s+|$)/';

  private static $filters = array();

  public static function register($name, \Closure $lambda)
  {
    static::$filters[$name] = $lambda;
  }

  public static function execute($filter, $value)
  {
    if ( ! isset(static::$filters[$filter])) {
      throw new \Exception("Unknown ':$filter' filter");
    }

    $callback = static::$filters[$filter];
    $value    = static::unescape($value);

    if (preg_match('/^ +/', $value, $match)) {
      $max   = strlen($match[0]);
      $value = preg_replace("/^ {{$max}}/m", '', $value);
    }

    return $callback($value);
  }

  public static function escape($text, $rev = FALSE)
  {
    return strtr($text, $rev ? array_flip(static::$qt) : static::$qt);
  }

  public static function unescape($text)
  {
    return static::escape($text, TRUE);
  }

  public static function flatten($set, $out = array())
  {
    foreach ($set as $one) {
      is_array($one) ? $out = static::flatten($one, $out) : $out []= $one;
    }

    return $out;
  }

  public static function prepare($source)
  {
    return preg_replace(array_keys(static::$prep), static::$prep, $source);
  }

  public static function repare($code)
  {
    return preg_replace(array_keys(static::$fix), static::$fix, $code);
  }

  public static function quote($text)
  {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', FALSE);
  }

  public static function args($text)
  {
    $out = array();

    preg_match_all(static::$args_expr, $text, $match);

    foreach ($match[1] as $i => $key) {
      if (empty($key)) {
        $out []= trim($match[0][$i]);
        continue;
      }

      $val = htmlspecialchars($match[3][$i]);
      $key = strtolower($key);

      $out[$key] = $val;
    }

    return $out;
  }

}
