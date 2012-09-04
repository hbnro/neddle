Tailor's skills!
================

Neddle is inspired from HAML/Jade and CoffeeScript, isn’t really a full
parser but it handles your templates very well.

Ok, it might have issues and do not throw any warnings about syntax errors.


## The basics

Like other haml-like templating engines, it uses white space to nest
your blocks. Also has minimal support for inline expressions,
closures and almost all php code.

**inline-comments**

    / html
    - # php
    ; invisible

**block-comments**

    /
      large and
      nested html comments

**html-blocks**

    .class#id { attr => $value }
      a { href => '#' } link text

    p
      nested text
      here

    pre
      |and the pipe
      |    preserves the white-space
      | in the block

    <table>
      <tbody>
        <tr>
          <td>also you can use raw html</td>
        </tr>
      </tbody>
    </table>

    <?php echo 'and raw php code'; ?>

    \a string value (not parsed)
    a string value (parsed)

    :some-filter
      any block of text (not parsed)

**php-blocks**

    - if $condition
      = 'success'

    ul
      - for $i = 0; $i < 10; $i += 1
      li = "Item $i"

    - $all = array(1, 2, 3)
    = "<p>$one</p>" while $one = array_shift($all)

    - unless false
      some text

    - print_r(call_user_func(~>
      - return range(0, 10)

    - $lambda = ($value) ~>
      - return $value

    = $lambda('some value')
    ~ $lambda('<p>escaped text</p>')

As you can see the syntax is very simple and compatible with php. ;-)


## Usage

Append it to your composer.json, then run `$ php composer.phar install`
to get the latest version.

    {
      "require": {
        "habanero/tailor": "dev-master"
      }
    }

Remember that neddle will not handle your views, it only pre-compile your
templates into php code. You should save and execute the produced code.

    <?php

    require 'neddle/vendor/autoload.php';

    # raw handling
    $tpl = 'a { href => "#" } = "link"';
    $out = Neddle\Parser::render($tpl);

    eval('; ?' . ">$out"); // <a href="#">link</a>


    # using as helper
    function view($file, array $vars = array()) {
      ob_start();

      $tpl = file_get_contents($file);
      $_tpl = tempnam('/tmp', md5($file));
      file_put_contents($_tpl, Neddle\Parser::render($tpl));

      extract($vars);
      require $_tpl;
      unlink($_tpl);

      $out = ob_get_clean();

      return $out;
    }


    # full-view rendering
    $tpl = 'example.neddle';
    $out = view($tpl, array('name' => 'Joe'));

    echo $out; // <p>Hello Joe!</p>

And this is the source for the **example.neddle** file.

    p = "Hello $name!"


## Want to collaborate?

If you like, fork, download and use it.
