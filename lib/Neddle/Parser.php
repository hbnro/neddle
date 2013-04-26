<?php

namespace Neddle;

class Parser
{

  private static $indent = 2;

  private static $attrid = '/^(?:#([a-z_][\da-z_-]*))?(?:.?([\d.a-z_-]+))?/i';

  private static $ifthen = '(?:(?:else\s*?)?if|unless|while|switch|for(?:each)?|catch)';
  private static $lambda = '\s*(?:\(([^()]*?)\))?\s*~>';
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
    $tree = \Neddle\Helpers::prepare($text);

    $tree = static::tree($tree);
    $tree = static::fix($tree);
    $tree = static::build($tree);

    return \Neddle\Helpers::repare($tree);
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

      $line  = trim(\Neddle\Helpers::escape($line));

      $code .= $indent > $tab ? "=array('@'=>'$line')" : "[]='$line'";
      $code .= ";";
    }

    @eval($code);

    if (empty($out)) {
      return FALSE;
    }

    return $out;
  }

  private static function open($value, $inline = FALSE)
  {
    $out = array();
    $pre = $inline ? '\s+(?!@)' : '\s*[-~=]\s*';

    $block  = '/^\s*-\s*(' . static::$block . ')\b/i';
    $ifthen  = "/$pre(" . static::$ifthen . ')\b/i';

    if ( ! is_scalar($value)) {
      return $value;
    }

    if (preg_match($ifthen, $value, $test, PREG_OFFSET_CAPTURE)) {
      @list($lft, $rgt) = array(substr($value, 0, $test[1][1]), substr($value, $test[1][1]));

      $parts = array_map('trim', explode(trim($test[1][0]), $rgt, 2));
      $expr  = join('', array_filter($parts, 'strlen'));

      $block = $test[1][0];

      if (strpos($block, 'unless') !== FALSE) {
        $expr = "! ($expr)";
        $block = 'if';
      }

      if ($inline) {
        $lft   = static::line($lft);
        $value = "- $block ($expr) : ?" . ">$lft<" . "?php end$block";
      } else {
        $value = "- $block ($expr) {";
      }
    } elseif (preg_match($block, $value, $match)) {
      $value = "- $match[1] {";
    }

    return $value;
  }

  private static function close($value)
  {
    $block  = '/\s+(?:' . static::$ifthen . '|' . static::$block . ')\b/i';
    $lambda = '/' . static::$lambda . '/';

    if ( ! is_scalar($value)) {
      return;
    }

    if (preg_match($lambda, $value)) {
      $lft = substr_count($value, '(');
      $rgt = substr_count($value, ')');

      $close = str_repeat(')', $lft - $rgt);

      return "- } : false$close";
    } elseif (preg_match($block, $value)) {
      return '- }';
    }
  }

  private static function fix($tree)
  {
    $tmp = array();

    foreach ($tree as $key => $value) {
      is_array($value) && $tree[$key] = static::fix($value);
    }


    if ( ! empty($tree['@'])) {
      if ($suffix = static::close($tree['@'])) {
        $tree []= $suffix;
      }

      ($overwrite = static::open($tree['@'])) && $tree['@'] = $overwrite;
      ($overwrite = static::lambda($tree['@'])) && $tree['@'] = $overwrite;

      $tree = array($tree['@'] => array_slice($tree, 1));
    }

    return $tree;
  }

  private static function build($tree, $indent = 0)
  {
    $out = array();

    foreach ($tree as $key => $value) {
      if (is_string($value)) {
        ($overwrite = static::open($value, TRUE)) && $value = $overwrite;

        $out []= static::line($value);
      } else {
        $key = preg_match('/#\d{1,7}/', $key) ? FALSE : trim($key);

        if (substr($key, 0, 3) === 'pre') {
          $value = join("\n", \Neddle\Helpers::flatten($value));
        } elseif (substr($key, 0, 1) === ':') {
          $value = \Neddle\Helpers::execute(substr($key, 1), join("\n", \Neddle\Helpers::flatten($value)));
          $key   = FALSE;
        } else {
          $value = static::build($value, $indent + 1);
        }

        $out []= static::line($key, $value);
      }
    }

    $out = join("\n", $out);

    return $out;
  }

  private static function line($key, $text = '')
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

        return static::php($later) . $text;
      break;
      case '=';

        return static::php("echo $later") . $text;
      break;
      case '~';

        return static::php("echo \\Neddle\\Helpers::quote($later)") . $text;
      case '|';

        return substr($key, 1);
      break;
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
          $text = static::line(trim($key), $text);
        } else {
          $text = $key . $text;
        }

        $out = ($tag OR $args) ? \Neddle\Markup::render($tag ?: 'div', $args, $text) : $text;

        return $out;
      break;
    }
  }

  private static function php($code)
  {
    return '<' . "?php $code ?" . '>';
  }

  private static function span($indent = 0)
  {
    return $indent > 0 ? str_repeat(str_repeat(' ', static::$indent), $indent) : '';
  }

  private static function lambda($value)
  {
    $closure = '/' . static::$lambda . '/';

    if (preg_match($closure, $value, $test)) {
      @list($prefix, $suffix) = explode($test[0], $value);

      $tmp = isset($test[1]) ? $test[1] : '';
      $tmp = array_map('trim', explode(';', $tmp));

      $locals = array_pop($tmp);
      $import = array_shift($tmp);
      $import = $import ? str_replace('$', '&$', ", $import") : '';

      $function  = "!! (\$__ = get_defined_vars()) | 1 ? function ($locals) use (\$__$import) {";
      $function .= " extract(\$__, EXTR_SKIP | EXTR_REFS); unset(\$__);";

      return "$prefix $function";
    }
  }

}
