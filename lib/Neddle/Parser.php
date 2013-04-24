<?php

namespace Neddle;

class Parser
{

  private static $indent = 2;

  private static $attrid = '/^(?:#([a-z_][\da-z_-]*))?(?:.?([\d.a-z_-]+))?/i';

  private static $ifthen = '(?:(?:else\s*?)?if|unless|while|switch|for(?:each)?|catch)';
  private static $lambda = '(?:\(([^()]*?)\))?\s*~>(.*)?';
  private static $block = '(?:else|do|try|finally)';

  private static $tags = array(
                    'hr', 'br', 'img', 'base', 'link', 'meta', 'input', 'embed', 'param',
                    'source', 'track', 'area', 'html', 'head', 'title', 'base', 'link', 'main',
                    'meta', 'style', 'script', 'noscript', 'body', 'section', 'nav', 'article',
                    'aside', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup', 'header', 'footer', 'address',
                    'p', 'hr', 'pre', 'blockquote', 'ol', 'ul', 'li', 'dl', 'dt', 'dd', 'figure', 'figcaption',
                    'div', 'a', 'em', 'strong', 'small', 's', 'cite', 'q', 'dfn', 'abbr', 'time', 'code', 'var',
                    'samp', 'kbd', 'sub', 'sup', 'i', 'b', 'mark', 'ruby', 'rt', 'rp', 'bdi', 'bdo', 'span',
                    'br', 'wbr', 'ins', 'del', 'iframe', 'embed', 'object', 'param', 'video', 'audio',
                    'source', 'track', 'canvas', 'map', 'area', 'table', 'caption', 'colgroup', 'col',
                    'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'form', 'fieldset', 'legend', 'label',
                    'button', 'select', 'datalist', 'optgroup', 'option', 'textarea', 'keygen' ,
                    'output', 'progress', 'meter', 'details', 'summary', 'command', 'menu', 'device',
                  );

  public static function render($text)
  {
    $out = static::fix(static::tree(\Neddle\Helpers::prepare($text)));
    $out = \Neddle\Helpers::unescape(\Neddle\Helpers::repare($out));

    return $out;
  }

  private static function tree($source)
  {
    static::$indent = 2;

    if (preg_match('/^ +(?=\S)/m', $source, $match)) {
      static::$indent = strlen($match[0]);
    }

    $code  = '';
    $stack = array();
    $lines = explode("\n", $source);
    $lines = array_values(array_filter(array_map('rtrim', $lines), 'strlen'));

    foreach ($lines as $i => $line) {
      $key    = '$out';
      $tab    = strlen($line) - strlen(ltrim($line));
      $next   = isset($lines[$i + 1]) ? $lines[$i + 1] : NULL;
      $indent = strlen($next) - strlen(ltrim($next));

      if ($tab && ($tab % static::$indent)) {
        continue;// TODO: guessing?
      }

      if ($indent > $tab) {
        $stack []= substr(mt_rand(), 0, 7);
      }

      foreach ($stack as $top) {
        $key .= "['#$top']";
      }

      if ($indent < $tab) {
        $dec = $tab - $indent;

        while ($dec > 0) {
          array_pop($stack);
          $dec -= static::$indent;
        }
      }

      $code .= $key;

      $line  = \Neddle\Helpers::escape($line);

      $code .= $indent > $tab ? "=array(-1=>'$line')" : "[]='$line'";
      $code .= ";";
    }

    @eval($code);

    if (empty($out)) {
      return FALSE;
    }

    return $out;
  }

  private static function open($value)
  {
    $out = array();

    $block  = '/^\s*-\s*' . static::$block . '\b/i';
    $ifthen  = '/(?<![[({<])\b(' . static::$ifthen . ')\b/i';

    if ( ! is_scalar($value)) {
      return $value;
    }

    if (preg_match($ifthen, $value, $test, PREG_OFFSET_CAPTURE)) {
      @list($lft, $rgt) = array(substr($value, 0, $test[1][1]), substr($value, $test[1][1]));

      $parts = array_map('trim', explode(trim($test[1][0]), $rgt, 2));
      $expr  = join('', array_filter($parts, 'strlen'));

      $block = $test[1][0];
      $value = static::line($lft);

      if (strpos($block, 'unless') !== FALSE) {
        $expr = "! ($expr)";
        $block = 'if';
      }
      if (trim($lft, '-~= ')) {
        return "- $block ($expr) : ?" . ">$value<" . "?php end$block";
      } else {
        return "- $block ($expr) {";
      }
    } elseif (preg_match($block, $value)) {
      return "$value {";
    }

    return $value;
  }

  private static function close($value)
  {
    $out = array();

    $block  = '/^\s*-\s*(?:' . static::$ifthen . '|' . static::$block . ')\b/i';
    $lambda = '/' . static::$lambda . '/';

    if ( ! is_scalar($value)) {
      return;
    }

    if (preg_match($lambda, $value)) {
      $lft = substr_count($value, '(');
      $rgt = substr_count($value, ')');

      $close = str_repeat(')', $lft - $rgt);

      return '<' . "?php } : false$close; ?" . '>';
    } elseif (preg_match($block, $value)) {
      return '<' . '?php } ?' . '>';
    }
  }

  private static function fix($tree, $indent = 0)
  {
    $out = array();
    $span = static::span($indent);

    if ( ! empty($tree[-1])) {

      ($overwrite = static::open($tree[-1])) && $tree[-1] = $overwrite;

      $sub[$tree[-1]] = array_slice($tree, 1);

      if ($suffix = static::close($tree[-1])) {
        $sub[$tree[-1]] []= $suffix;
      }

      $out []= static::fix($sub, $indent + 1);
    } elseif ($tree) {
      foreach ($tree as $key => $value) {
        if (is_string($value)) {
          ($overwrite = static::lambda($value, TRUE)) && $value = $overwrite;
          ($overwrite = static::open($value)) && $value = $overwrite;

          $out []= static::line(substr($value, -1) === '{' ? $value = "$value }" : trim($value), '', $indent);
        } else {
          ($overwrite = static::lambda($key)) && $key = $overwrite;

          $key = preg_match('/#\d{1,7}/', $key) ? FALSE : trim($key);

          if (substr($key, 0, 3) === 'pre') {
            $value = preg_replace("/^$span/m", '|', join("\n", \Neddle\Helpers::flatten($value)));
          } elseif (substr($key, 0, 1) === ':') {
            $value = \Neddle\Helpers::execute(substr($key, 1), join("\n", \Neddle\Helpers::flatten($value)));
            $key   = FALSE;
          } elseif (substr($key, 0, 1) === '/') {
            $key   = join("\n", \Neddle\Helpers::flatten($value));
            $key   = '/' . trim(preg_replace('/^/m', static::span($indent + 1), $key));
            $value = '';
          } else {
            $value = static::fix($value, $indent);
          }

          $out []= $span . static::line($key, $value, $indent);
        }
      }
    }

    $out = join("\n", $out);

    return $out;
  }

  private static function line($key, $text = '', $indent = 0)
  {
    $key = trim($key);
    $peak = substr($key, 0, 1);
    $later = trim(substr($key, 1));

    switch ($peak) {
      // HTML-comments
      case '/';

        return "<!--$later$text-->";
      break;
      // HTML-tag
      case '<';

        return $key . $text;
      break;
      // PHP-tags
      case '-';

        return '<' . '?php ' . "$later ?>$text";
      break;
      case '=';

        return '<' . '?php echo ' . "$later ?>$text";
      break;
      case '~';

        return '<' . "?php echo \\Neddle\\Helpers::quote($later); ?>$text";
      case ';';
        continue;
      break;
      default;
        $tag  = '';
        $args = array();
        $tags = join('|', static::$tags);

        // tag name
        preg_match("/^($tags)(?=\b)/", $key, $match);

        if ( ! empty($match[0])) {
          $key = substr($key, strlen($match[0]));
          $tag = $match[1];
        }

        // attributes (raw)
        preg_match('/^[#.](?:[a-z][.\w-]*)+/', $key, $match);

        if ( ! empty($match[0])) {
          $key = substr($key, strlen($match[0]));

          preg_match(static::$attrid, $match[0], $match);

          ! empty($match[1]) && $args['id'] = $match[1];
          ! empty($match[2]) && $args['class'] = strtr($match[2], '.', ' ');
        }

        // raw attributes ( key="val" )
        preg_match('/^\s*\((.+?)\)/', $key, $match);

        if ( ! empty($match[0])) {
          $key   = str_replace($match[0], '', $key);
          $tmp   = \Neddle\Helpers::args($match[1]);

          $args += $tmp;
        }

        // attributes { hash => val }
        preg_match('/^\s*\{(.+?)\}/', $key, $match);

        if ( ! empty($match[0])) {
          $key    = str_replace($match[0], '', $key);
          $args []= $match[1];
        }

        // output
        preg_match('/^\s*[-~=:]/', $key, $match);

        if ( ! empty($match[0])) {
          $text = static::line(trim($key), $text, $indent);
        } else {
          $text = $key . $text;
        }

        $out = ($tag OR $args) ? \Neddle\Markup::render($tag ?: 'div', $args, $text) : $text;

        return $out;
      break;
    }
  }

  private static function span($indent = 0)
  {
    return $indent > 0 ? str_repeat(str_repeat(' ', static::$indent), $indent) : '';
  }

  private static function lambda($value, $inline = FALSE)
  {
    $closure = '/' . static::$lambda . '/';

    if (preg_match($closure, $value, $test)) {
      @list($prefix, $suffix) = explode($test[0], $value);

      $import = preg_match('/^[\s\w$]+$/', $test[2]) ? strtr($test[2], array(' ' => '', '$' => ', &$')) : '';

      $function  = "!! (\$__ = get_defined_vars()) | 1 ? function ($test[1]) use (\$__$import) {";
      $function .= " extract(\$__, EXTR_SKIP | EXTR_REFS); unset(\$__);";

      if ($inline) {
        $lft = substr_count($value, '(');
        $rgt = substr_count($value, ')');

        $close = str_repeat(')', $lft - $rgt);

        $suffix = trim($test[2]);
        $suffix = $suffix ? " $suffix;" : '';

        return "$prefix $function$suffix } : false$close";
      } else {
        return "$prefix $function";
      }
    }
  }

}
