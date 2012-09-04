<?php

namespace Neddle;

class Helpers
{

  private static $qt = array(
                    '\\' => '<!--#BS#-->',
                    '"' => '<!--#QUOT#-->',
                    "'" => '<!--#APOS#-->',
                  );

  private static $fix = array(
                    '/<!--#HASH\d+#-->/' => '',
                    '/\s*<\/pre>/s' => "\n</pre>",
                    '/(?<=>|^) *\|| *?<!--#PRE#-->/m' => '',
                    '/([,([]\s*)([a-z][\w:-]+)\s*=>\s*/' => "\\1'\\2' => ",
                    '/<\?=\s*(.+?)\s*;?\s*\?>/' => '<?php echo \\1; ?>',
                    '/<\?php\s+(?!echo\s+|\})/' => "<?php ",
                    '/#\{(.+?)\}/' => '<?php echo \\1; ?>',
                    '/\?>\s*<\?php\s+(?=else)/s' => '',
                  );

  private static $filters = array();



  public static function register($name, \Closure $lambda) {
    static::$filters[$name] = $lambda;
  }

  public static function execute($filter, $value) {
    if ( ! isset(static::$filters[$filter])) {
      return "$filter:$value";// TODO: warn about?
    }

    $callback = static::$filters[$filter];
    $value    = static::repare(static::unescape($value));

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

  public static function flatten($set, $out = array()) {
    foreach ($set as $one) {
      is_array($one) ? $out = static::flatten($one, $out) : $out []= $one;
    }
    return $out;
  }

  public static function indent($text, $max = 0)
  {
    $repl  = str_repeat(' ', $max);
    $test  = explode("\n", $text);
    $last  = array_pop($test);

    $text  = join("\n$repl", array_filter($test));
    $text .= substr($last, 0, 1) === '<' ? "\n$last" : $last;

    return $text;
  }

  public static function repare($code)
  {
    return preg_replace(array_keys(static::$fix), static::$fix, $code);
  }

  public static function quote($text)
  {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', FALSE);
  }

}
