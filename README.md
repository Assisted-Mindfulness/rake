# RAKE

PHP implementation of Rapid Automatic Keyword Exraction algorithm (RAKE) for extracting multi-word phrases from text.



## Installation


```bash
composer require assisted-mindfulness/rake
```

## Usage


```php
$rake = new Rake(4, false);

$text = "UK Sport set a target of three to seven medals but there were
 a number of near-misses and disappointment in the sliding events,
 making it the first time since 2002 that Britain have failed
 to win a skeleton medal.";

$phrases = $rake->extract($text)->sortByScore('desc')->keywords();

/*
array:15 [
  0 => "sport"
  1 => "target"
  2 => "medals"
  3 => "number"
  4 => "near-misses"
  5 => "disappointment"
  6 => "sliding"
  7 => "events"
  8 => "making"
  9 => "time"
  10 => "2002"
  11 => "britain"
  12 => "failed"
  13 => "skeleton"
  14 => "medal"
]
 */
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
