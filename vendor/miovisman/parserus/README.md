# Parserus

[![MIT licensed](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

BBCode parser.

## Requirements

* PHP 5.4.0

## Installation

Include `Parserus.php` or install [the composer package](https://packagist.org/packages/MioVisman/Parserus).

## Example

``` php
$parser = new Parserus();

echo $parser->addBBCode([
    'tag' => 'b',
    'handler' => function($body) {
        return '<b>' . $body . '</b>';
    }
])->addBBcode([
    'tag' => 'i',
    'handler' => function($body) {
        return '<i>' . $body . '</i>';
    },
])->parse("[i]Hello\n[b]World[/b]![/i]")
->getHTML();

#output: <i>Hello<br><b>World</b>!</i>
```

More examples in [the wiki](https://github.com/MioVisman/Parserus/wiki).

## License

This project is under MIT license. Please see the [license file](LICENSE) for details.
