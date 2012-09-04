<?php

require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

Neddle\Helpers::register('script', function ($value) { return "<script>$value</script>"; });
Neddle\Helpers::register('style', function ($value) { return "<style>$value</style>"; });
Neddle\Helpers::register('escape', function ($value) { return htmlspecialchars($value); });
Neddle\Helpers::register('plain', function ($value) { return $value; });
Neddle\Helpers::register('php', function ($value) { return '<' . "?php $value ?>"; });


$tpl = <<<TPL

.candy
  | x man?
  | fuck yeah

:php
  echo phpversion()

span
  ~ '<escaped text>'
  = call_user_func(~>
    <Fuck yeah!>

This is text and should
be rendered as is

dl
  dt OK

  dd FUUU

.foo

  ul#foo.candy

    li.x

      a#y { href => "#", class => "bar" } = 'Link'

    /li.z

      a.b { href => "#c" } link

      a.link text

      a.link text

      a.link text

  p dos


TPL;


$view = Neddle\Parser::render($tpl);
$test = @eval('ob_start(); ?' . ">$view<" . '?php return ob_get_clean();');
$expect = '<div class="candy">
 x man?
 fuck yeah
</div>
' . phpversion() . '<span>&lt;escaped text&gt;  <Fuck yeah!></span>
This is text and should
be rendered as is
<dl>
  <dt>
    OK
  </dt>
  <dd>
    FUUU
  </dd></dl>
<div class="foo">
  <ul id="foo" class="candy">
    <li class="x">
      <a href="#" class="bar" id="y">Link</a></li>
    <!--
        li.z
          a.b { href => "#c" } link
          a.link text
          a.link text
          a.link text
    --></ul>
  <p>
    dos
  </p></div>';

echo "\nOutput: ";
echo $expect === $test ? 'OK' : 'FAIL';


$tpl = <<<DOC

:title
  Hello World

:description
  Well i dont now what this means but...

:published_at
  September 3, 2012

:keywords
  very simple text indeed

:body
  Well this contents will never breaks

  yeah? this is merely the turth man...
  /this is a comment
  OK, if a want to be parsed?

  OK.... I'll show you...

  dl
    dt title
    dd desc

DOC;



$doc = new stdClass;

foreach (array('title', 'description', 'published_at', 'keywords', 'body') as $item) {
  Neddle\Helpers::register($item, function ($value) use ($doc, $item) { $doc->$item = $value; });
}

$view = Neddle\Parser::render($tpl);

$doc->body = Neddle\Parser::render($doc->body);
$doc->keywords = array_filter(array_map('trim', preg_split('/[,\s]+/', $doc->keywords)));


echo "\nHelpers: ";
echo (($doc->title == 'Hello World') && in_array('simple', $doc->keywords)) ? 'OK' : ' FAIL';



$tpl = <<<'TEXT'

- $bar = 4
- $foo = array(1,2,3)
ul
  - while $bar -= 1
    li = "$bar"
= "A" for $i = 0; $i < 4; $i += 1
= "<p>$bar</p>" foreach $foo as $bar
- $candy = 'does' if !! $foo
= "FTW!!" unless false

- unless true
  p Welcome sir!

TEXT;


$view = Neddle\Parser::render($tpl);
$test = @eval('ob_start(); ?' . ">$view<?" . 'php return ob_get_clean();');
$test = trim(str_replace(' ', '', strip_tags($test)));
$expect = '321AAAA123FTW!!';

echo "\nConditions: ";
echo $test === $expect ? 'OK' : 'FAIL';

echo "\n\n";
