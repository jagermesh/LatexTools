# LatexTools

Library to render LaTeX formula into image

## Requirements

### Linux

- Install TexLive from [https://tug.org/texlive/](https://tug.org/texlive/)

### Mac OS

- Install MacTex from [https://tug.org/mactex/](https://tug.org/mactex/)
- Install dvipng
```
$ sudo tlmgr update --self
$ sudo tlmgr install dvipng
```

## Installation

Easiest way is to use composer

```
composer require jagermesh/latex-tools
```

## Usage

```php
<?php
  require_once(__DIR__ . '/vendor/autoload.php');

  // Or library directly
  // require_once('LatexTools.php');

  $latexTools = new \LatexTools\LatexTools();
  $latexTools->renderIntoResponse('(\frac{\beta }{\mu})^\beta {\Gamma(\beta )} \,  e^{-\frac{V\,\beta }{\mu }} \label{gamma}');
```

Will render this nice formula :)

## Functions reference

Render formula and return image in response

```php
$latexTools->renderIntoResponse($formula, $params = []);
```

Render image into file

```php
$fileName = $latexTools->renderIntoFile($formula, $params = []);
```

In above calls `$params` could be any of these values:

- `density` - formula density
- `fallbackToImage` - if need to render regular image for incorrect formula (`true` by default)
- `fallbackImageFontName` - font name for regular image (`fonts/PlayfairDisplay-Regular.ttf` by default)
- `fallbackImageFontSize` - font size for regular image (`16` by default)

Also these paramatares could be set globally. Please see below.

Set formula density (`160` by default)

```php
$latexTools->setDensity($value);
```

By default regular image will be rendered if formula incorrect (`true` by default)

```php
$latexTools->setFallbackToImage(true|false);
```

Example

```php
<?php
  require_once(__DIR__ . '/vendor/autoload.php');

  $latexTools = new \LatexTools\LatexTools();
  $latexTools->renderIntoResponse('x^2');
```

Font name for regular image (`fonts/PlayfairDisplay-Regular.ttf` by default)

```php
$latexTools->setFallbackImageFontName($value);
```

Font size for regular image (`16` by default)

```php
$latexTools->setFallbackImageFontName($value);
```

Check if formula correct

```php
$result = $latexTools->isValidLaTeX($formula);
```

Set directory for cache files (`/tmp` by default)

```php
$latexTools->setCachePath($value);
```

Set directory for temp files (`/tmp` by default)

```php
$latexTools->setTempPath($value);
```
