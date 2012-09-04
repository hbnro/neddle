<?php

namespace Neddle;

class Parser
{

  private static $indent = 2;

  private static $fn = '(?:\s*\(([^()]+?)\)\s*|())\s*~\s*>(?=\b|$)';

  private static $id = '/^(?:#([a-z_][\da-z_-]*))?(?:.?([\d.a-z_-]+))?/i';
  private static $open = '\b(?:if|unless|else(?:\s*if)?|while|switch|for(?:each)?)\b';

  private static $tags = array(
                    'hr', 'br', 'img', 'base', 'link', 'meta', 'input', 'embed', 'param',
                    'source', 'track', 'area', 'html', 'head', 'title', 'base', 'link',
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
    $out = static::build(static::tree($text));
    $out = Helpers::repare($out);

    return $out;
  }



  private static function tree($source)
  {
    static::$indent = 2;

    $source = preg_replace('/[\r\n]{2,}/', "\n", $source);

    if (preg_match('/^ +(?=\S)/m', $source, $match)) {
      static::$indent = strlen($match[0]);
    }


    $code  = '';
    $stack = array();
    $lines = explode("\n", $source);
    $lines = array_filter(array_map('rtrim', $lines));

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
        $key .= "['<!--#HASH$top#-->']";
      }

      if ($indent < $tab) {
        $dec = $tab - $indent;

        while ($dec > 0) {
          array_pop($stack);
          $dec -= static::$indent;
        }
      }

      $code .= $key;

      $line  = Helpers::escape($line);

      $code .= $indent > $tab ? "=array(-1=>'$line')" : "[]='$line'";
      $code .= ";";
    }


    @eval($code);

    if (empty($out)) {
      return FALSE;
    }

    return $out;
  }


  private static function build($tree)
  {
    $span  = str_repeat(' ', static::$indent);
    $open  = '/^\s*-\s*' . static::$open . '/';
    $block = '/[-=].+?' . static::$fn . '/';
    $out   = array();

    if ( ! empty($tree[-1])) {
      $sub[$tree[-1]] = array_slice($tree, 1);

      if (preg_match($block, $tree[-1])) {
        $lft = substr_count($tree[-1], '(');
        $rgt = substr_count($tree[-1], ')');

        $close = str_repeat(')', $lft - $rgt);

        $sub[$tree[-1]] []= "<?php }$close; ?>";
      } elseif (preg_match($open, $tree[-1])) {
        $sub[$tree[-1]] []= '<?php } ?>';
      }

      $out []= static::build($sub);
    } else {
      foreach ($tree as $key => $value) {
        if ( ! is_scalar($value)) {
          continue;
        } elseif (preg_match($block, $value)) {
          $lft = substr_count($value, '(');
          $rgt = substr_count($value, ')');

          $close = str_repeat(')', $lft - $rgt);

          $tree []= "<?php }$close; ?>";
        } elseif (preg_match($open, $value)) {
          $tree []= '<?php } ?>';
        }
      }


      foreach ($tree as $key => $value) {
        $indent = strlen($key) - strlen(ltrim($key));

        if (is_string($value)) {
          $out []= static::line($value, '', $indent - static::$indent);
          continue;
        } elseif (substr(trim($key), 0, 1) === ':') {
          $value = join("\n", Helpers::flatten($value));
          $out []= Helpers::execute(substr(trim($key), 1), $value);
          continue;
        } elseif (substr(trim($key), 0, 1) === '/') {
          $key   = preg_replace("/ {{$indent}}\//", str_repeat(' ', $indent), $key);
          $value = join("\n", Helpers::flatten($value));
          $out []= "<!--\n$key\n$value\n-->";
          continue;
        } elseif (substr(trim($key), 0, 3) === 'pre') {
          $value = join("\n", Helpers::flatten($value));
          $value = preg_replace("/^$span\s{{$indent}}/m", '<!--#PRE#-->', $value);
        }

        $value = is_array($value) ? static::build($value) : $value;
        $out []= static::line($key, $value, $indent);
      }
    }

    $out = join("\n", $out);

    return $out;
  }

  private static function block($line, $prefix = '')
  {
    $suffix = ';';

    if (preg_match('/' . static::$fn . '/', $line, $match)) {
      $suffix = '';
      $prefix = "\$__=get_defined_vars();$prefix";

      $args   = ! empty($match[1]) ? $match[1] : '';
      $line   = str_replace($match[0], "function ($args)", $line);
      $line  .= 'use($__){extract($__,EXTR_SKIP|EXTR_REFS);unset($__);';
    } elseif (preg_match('/\b' . static::$open . '\b/', $line, $match)) {
      $test   = explode($match[0], $line);

      $after  = trim(array_pop($test));
      $before = trim(array_pop($test));

      $line   = $match[0] === 'unless' ? "if ( ! ($after)) " : "$match[0] ($after) ";
      $suffix = '{';

      if ($before) {
        $suffix .= " $prefix$before; }";
        $prefix  = '';
      }
    }

    return "$prefix$line$suffix";
  }

  private static function line($key, $text = '', $indent = 0)
  {
    $key  = Helpers::unescape(trim($key));
    $text = Helpers::unescape($text);

    switch (substr($key, 0, 1)) {
      case '/';
        // <!-- ... -->
        return '<!--' . substr($key, 1) . "-->$text";
      break;
      case '<';
        // html
        return $key . $text;
      break;
      case '-';
        // php
        $key = rtrim(substr($key, 1), ';');
        $key = preg_replace('/\belse\s*;/', 'else{', static::block($key));

        return "<?php $key ?>$text";
      break;
      case '~';
        $key = rtrim(trim(substr($key, 1)), ';');
        return "<?php echo Neddle\Helpers::quote($key); ?>";
      case '=';
        // print
        $key = trim(substr($key, 1));
        $key = static::block(rtrim($key, ';'), 'echo ');

        return "<?php $key ?>$text";
      break;
      case '\\';
        return substr($key, 1) . $text;
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

          preg_match(static::$id, $match[0], $match);

          ! empty($match[1]) && $args['id'] = $match[1];
          ! empty($match[2]) && $args['class'] = strtr($match[2], '.', ' ');

          // TODO: nested tags :foo#candy:.bar
        }

        // attributes { hash => val }
        preg_match('/^\s*\{(.+?)\}/', $key, $match);

        if ( ! empty($match[0])) {
          $key    = str_replace($match[0], '', $key);
          $args []= $match[1];
        }

        // output
        preg_match('/^\s*=.+?/', $key, $match);

        if ( ! empty($match[0])) {
          $text = static::line($key);
        } else {
          $text = trim($key) . $text;
          $text = "\n$text\n";
        }

        $out = ($tag OR $args) ? Markup::render($tag ?: 'div', $args, $text) : $text;
        $out = Helpers::indent($out, static::$indent);

        return $out;
      break;
    }
  }

}
