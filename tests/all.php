<?php

require dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';

Neddle\Helpers::register('script', function ($value) { return "<script>$value</script>"; });
Neddle\Helpers::register('style', function ($value) { return "<style>$value</style>"; });
Neddle\Helpers::register('escape', function ($value) { return htmlspecialchars($value); });
Neddle\Helpers::register('plain', function ($value) { return $value; });
Neddle\Helpers::register('php', function ($value) { return '<' . "?php $value ?>"; });


$clean = function ($str) {
    return preg_replace('/\s+/', '', $str);
  };


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

    /
      a.b { href => "#c" } link
      a.link text
      a.link text
      a.link text

    li lol

  p dos

- \$foo = 'bar'
span ( key="#{\$foo}" ) etc.

p { foo => :bar,
    :baz => 'buzz' } this is,
  some large\
  text in there...

TPL;


$view = Neddle\Parser::render($tpl);
$test = @eval('ob_start(); ?' . ">$view<" . '?php return ob_get_clean();');
$expect = '<divclass="candy">xman?fuckyeah</div>' . phpversion() . '<span>&lt;escapedtext&gt;<Fuckyeah!></span>';
$expect .= 'Thisistextandshouldberenderedasis<dl><dt>OK</dt><dd>FUUU</dd></dl><divclass="foo"><ulid="foo"class="candy">';
$expect .= '<liclass="x"><ahref="#"class="bar"id="y">Link</a></li><!--<ahref="#c"class="b">link</a><aclass="link">text</a><aclass="link">text</a><aclass="link">text</a>--><li>lol</li></ul><p>dos</p></div><spankey="&lt;?phpecho$foo;?&gt;">etc.</span>';
$expect .= '<pfoo="bar"baz="buzz">thisis,somelargetextinthere...</p>';

echo "\nOutput: ";
echo $expect === $clean($test) ? 'OK' : 'FAIL';

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

  yeah? this is merely the truth man...
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
  li = "$bar" @while $bar -= 1
= "A" for $i = 0; $i < 4; $i += 1
= "<p>$bar</p>" foreach $foo as $bar
- $candy = 'does' if !! $foo
= "FTW!!" unless false

- unless true
  p Welcome sir!

- try
  Loooolz
  - throw new \Exception
- catch \Exception $e
  nothing to do here

TEXT;


$view = Neddle\Parser::render($tpl);
$test = @eval('ob_start(); ?' . ">$view<?" . 'php return ob_get_clean();');
$test = $clean(strip_tags($test));
$expect = '321AAAA123FTW!!Loooolznothingtodohere';

echo "\nConditions: ";
echo $test === $expect ? 'OK' : 'FAIL';

echo "\n\n";
