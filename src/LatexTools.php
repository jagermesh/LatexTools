<?php

namespace LatexTools;

class LatexTools
{
  const PRE_FORMATTED_MESSAGE = '<pre>%s</pre>';

  private $pathToLatexTool = '';
  private $pathToDviPngTool = '';

  private $cachePath;
  private $tempPath;

  private $density = 160;
  private $fallbackToImage = true;
  private $fallbackImageFontName = __DIR__ . '/fonts/PT_Serif-Web-Regular.ttf';
  private $fallbackImageFontSize = 16;

  public function __construct($params = [])
  {
    if (array_key_exists('pathToLatexTool', $params) && file_exists($params['pathToLatexTool'])) {
      $this->pathToLatexTool = $params['pathToLatexTool'];
    } elseif (file_exists('/Library/TeX/texbin/latex')) {
      // Mac OS
      $this->pathToLatexTool = '/Library/TeX/texbin/latex';
    } elseif (file_exists('/usr/bin/latex')) {
      // linux
      $this->pathToLatexTool = '/usr/bin/latex';
    } else {
      throw new LatexToolsException('latex not installed');
    }

    if (array_key_exists('pathToDviPngTool', $params) && file_exists($params['pathToDviPngTool'])) {
      $this->pathToDviPngTool = $params['pathToDviPngTool'];
    } elseif (file_exists('/Library/TeX/texbin/dvipng')) {
      // Mac OS
      $this->pathToDviPngTool = '/Library/TeX/texbin/dvipng';
    } elseif (file_exists('/usr/bin/dvipng')) {
      // linux
      $this->pathToDviPngTool = '/usr/bin/dvipng';
    } else {
      throw new LatexToolsException('dvipng not installed');
    }

    if (array_key_exists('cachePath', $params)) {
      $this->setCachePath($params['cachePath']);
    }

    if (array_key_exists('tempPath', $params)) {
      $this->setTempPath($params['tempPath']);
    }

    if (array_key_exists('density', $params)) {
      $this->density = $params['density'];
    }

    if (array_key_exists('fallbackToImage', $params)) {
      $this->fallbackToImage = $params['fallbackToImage'];
    }

    if (array_key_exists('fallbackImageFontName', $params)) {
      $this->fallbackImageFontName = $params['fallbackImageFontName'];
    }

    if (array_key_exists('fallbackImageFontSize', $params)) {
      $this->fallbackImageFontSize = $params['fallbackImageFontSize'];
    }
  }

  private function assembleParams($params = [])
  {
    $result = $params;

    if (!is_array($result)) {
      $result = [];
    }

    $result['density'] = array_key_exists('density', $result) ? $result['density'] : $this->density;
    $result['fallbackToImage'] = array_key_exists('fallbackToImage', $result) ? $result['fallbackToImage'] : $this->fallbackToImage;
    $result['fallbackImageFontName'] = array_key_exists('fallbackImageFontName', $result) ? $result['fallbackImageFontName'] : $this->fallbackImageFontName;
    $result['fallbackImageFontSize'] = array_key_exists('fallbackImageFontSize', $result) ? $result['fallbackImageFontSize'] : $this->fallbackImageFontSize;
    $result['checkOnly'] = array_key_exists('checkOnly', $result) ? $result['checkOnly'] : false;
    $result['debug'] = array_key_exists('debug', $result) ? $result['debug'] : false;

    if ($result['checkOnly']) {
      $result['fallbackToImage'] = false;
    }

    return $result;
  }

  private function getFormulaHash($formula, $params = [])
  {
    $params = $this->assembleParams($params);
    return hash('sha256', $formula . '|' . serialize($params));
  }

  private function htmlToText($html)
  {
    $result = $html;

    $result = preg_replace('~<!DOCTYPE[^>]*?>~ism', '', $result);
    $result = preg_replace('~<head[^>]*?>.*?</head>~ism', '', $result);
    $result = preg_replace('~<style[^>]*?>.*?</style>~ism', '', $result);
    $result = preg_replace('~<script[^>]*?>.*?</script>~ism', '', $result);
    $result = preg_replace('~&nbsp;~ism', ' ', $result);
    $result = preg_replace("~<br[^>]*>[\n]+~ism", "\n", $result);
    $result = preg_replace("~<br[^>]*>~ism", "\n", $result);
    $result = preg_replace('~<[A-Z][^>]*?>~ism', '', $result);
    $result = preg_replace('~<\/[A-Z][^>]*?>~ism', '', $result);
    $result = preg_replace('~<!--.*?-->~ism', ' ', $result);
    $result = preg_replace('~^[ ]+$~ism', '', $result);
    $result = preg_replace('~^[ ]+~ism', '', $result);
    $result = preg_replace("~^(\n\r){2,}~ism", "\n", $result);
    $result = preg_replace("~^(\r\n){2,}~ism", "\n", $result);
    $result = preg_replace("~^(\n){2,}~ism", "\n", $result);
    $result = preg_replace("~^(\r){2,}~ism", "\n", $result);

    $flags = ENT_COMPAT;

    if (defined('ENT_HTML401')) {
      $flags = $flags | ENT_HTML401;
    }
    $result = html_entity_decode($result, $flags, 'UTF-8');

    return trim($result);
  }

  private function renderSimpleImage($formula, $params = [])
  {
    $params = $this->assembleParams($params);
    $params['format'] = 'fallback';

    $formula = $this->htmlToText($formula);

    $formula = str_replace('\\ ', ' ', $formula);
    $formula = str_replace('\\\\', "\n", $formula);

    $formulaHash = $this->getFormulaHash($formula, $params);

    if (array_key_exists('outputFile', $params)) {
      $outputFile = $params['outputFile'];
    } else {
      $outputFileName = 'latex-' . $formulaHash . '.png';
      $outputFile = $this->getCachePath() . $outputFileName;
    }

    if (file_exists($outputFile)) {
      return $outputFile;
    } else {
      if (!function_exists('imagettfbbox')) {
        throw new LatexToolsException('GD library not installed');
      }

      $fontSize = $params['fallbackImageFontSize'];
      $fontName = $params['fallbackImageFontName'];

      $formula = wordwrap($formula, 60);

      if ($box = @imagettfbbox($fontSize, 0, $fontName, $formula)) {
        $deltaY = abs($box[5]) + 2;
        $width = $box[2];
        $height = $box[1] + $deltaY + 4;

        $image = imagecreatetruecolor($width, $height);

        try {
          if (!@imagesavealpha($image, true)) {
            throw new LatexToolsException('Alpha channel not supported');
          }

          $transparentColor = imagecolorallocatealpha($image, 0, 0, 0, 127);

          imagefill($image, 0, 0, $transparentColor);

          $black = imagecolorallocate($image, 0, 0, 0);

          $retval = @imagettftext($image, $fontSize, 0, 0, $deltaY, $black, $fontName, $formula);

          if (!$retval) {
            throw new LatexToolsException('Can not render formula using provided font');
          }

          $retval = @imagepng($image, $outputFile);

          if (!$retval || !file_exists($outputFile) || (0 === filesize($outputFile))) {
            throw new LatexToolsException('Can not save output image');
          }
        } finally {
          imagedestroy($image);
        }
      } else {
        throw new LatexToolsException('Font ' . $fontName . ' not found');
      }

      return $outputFile;
    }
  }

  private function processImages($formula)
  {
    $result = ['formula' => $formula, 'tempFiles' => []];

    while (preg_match('/[\\\]includegraphics[{].*?data:image\/([a-z]+);base64,([^}]+?)[}]/ism', $result['formula'], $matches)) {
      try {
        $imageType = $matches[1];
        $packedImage = $matches[2];
        $fileName = hash('sha256', $packedImage) . '.' . $imageType;
        $filePath = $this->getTempPath() . $fileName;
        if (file_put_contents($filePath, base64_decode($packedImage))) {
          $result['tempFiles'][] = $filePath;
          if ($image = @imagecreatefrompng($filePath)) {
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
            $result['formula'] = str_replace(
              $matches[0],
              '\includegraphics[natwidth=' . $imageWidth . ',natheight=' . $imageHeight . ']{' . $filePath . '}\\\\', $result['formula']
            );
          } else {
            throw new LatexToolsException('Error');
          }
        } else {
          throw new LatexToolsException('Error');
        }
      } catch (\Exception $e) {
        $result['formula'] = str_replace($matches[0], '', $result['formula']);
      }
    }

    return $result;
  }

  private function render($formula, $params = [])
  {
    $params = $this->assembleParams($params);
    $params['format'] = 'image';

    $formula = iconv("UTF-8", "ISO-8859-1//IGNORE", $formula);
    $formula = iconv("ISO-8859-1", "UTF-8", $formula);

    $formula = str_replace('\\text{img_}', '', $formula);
    $formula = str_replace('´', "'", $formula);

    $images = $this->processImages($formula);

    $formula = $images['formula'];
    $tempFiles = $images['tempFiles'];

    $formula = str_replace('#', '\\#', $formula);

    $latexDocument = '';
    $latexDocument .= '\documentclass{article}' . "\n";
    $latexDocument .= '\usepackage%' . "\n" .
      '[%' . "\n" .
      'left=0cm,' . "\n" .
      'right=0cm,' . "\n" .
      'top=0cm,' . "\n" .
      'bottom=0cm,' . "\n" .
      'a5paper' . "\n" .
      ']{geometry}' . "\n";
    $latexDocument .= '\usepackage[utf8]{inputenc}' . "\n";
    $latexDocument .= '\usepackage{amsmath}' . "\n";
    $latexDocument .= '\usepackage{amsfonts}' . "\n";
    $latexDocument .= '\usepackage{amsthm}' . "\n";
    $latexDocument .= '\usepackage{amssymb}' . "\n";
    $latexDocument .= '\usepackage{amstext}' . "\n";
    $latexDocument .= '\usepackage{color}' . "\n";
    $latexDocument .= '\usepackage{pst-plot}' . "\n";
    $latexDocument .= '\usepackage{graphicx}' . "\n";
    $latexDocument .= '\begin{document}' . "\n";
    $latexDocument .= '\pagestyle{empty}' . "\n";

    $latexDocuments = [];
    if (count($images['tempFiles']) < 2) {
      $latexDocuments[] = $latexDocument . "\n" .
        trim($formula) . "\n" .
        '\end{document}' . "\n";
    }
    $latexDocuments[] = $latexDocument . "\n" .
      '\begin{gather*}' . "\n" .
      trim($formula) . "\n" .
      '\end{gather*}' . "\n" .
      '\end{document}' . "\n";

    $exception = null;

    foreach ($latexDocuments as $latexDocument) {
      if ($params['debug']) {
        echo sprintf(self::PRE_FORMATTED_MESSAGE, $latexDocument);
      }

      $formulaHash = $this->getFormulaHash($latexDocument, $params);

      if (array_key_exists('outputFile', $params)) {
        $outputFile = $params['outputFile'];
      } else {
        $outputFileName = 'latex-' . $formulaHash . '.png';
        $outputFile = $this->getCachePath() . $outputFileName;
      }

      if (!$params['debug'] && !$params['checkOnly'] && file_exists($outputFile) && (filesize($outputFile) > 0)) {
        return $outputFile;
      } else {
        $tempFileName = 'latex-' . $formulaHash . '.tex';
        $tempFile = $this->getTempPath() . $tempFileName;
        $tempFiles[] = $tempFile;

        $auxFileName = 'latex-' . $formulaHash . '.aux';
        $auxFile = $this->getTempPath() . $auxFileName;
        $tempFiles[] = $auxFile;

        $logFileName = 'latex-' . $formulaHash . '.log';
        $logFile = $this->getTempPath() . $logFileName;
        $tempFiles[] = $logFile;

        $dviFileName = 'latex-' . $formulaHash . '.dvi';
        $dviFile = $this->getTempPath() . $dviFileName;
        $tempFiles[] = $dviFile;

        try {
          if (@file_put_contents($tempFile, $latexDocument) === false) {
            throw new LatexToolsException('Can not create temporary formula file at ' . $tempFile);
          }

          try {
            $command = 'cd ' . $this->getTempPath() . '; ' . $this->pathToLatexTool . ' --interaction=nonstopmode ' . $tempFileName . ' < /dev/null';
            $output = '';
            $retval = '';

            if ($params['debug']) {
              echo sprintf(self::PRE_FORMATTED_MESSAGE, $formula);
              echo sprintf(self::PRE_FORMATTED_MESSAGE, $command);
            }

            exec($command, $output, $retval);

            if ($params['debug']) {
              echo sprintf(self::PRE_FORMATTED_MESSAGE, json_encode($output, JSON_PRETTY_PRINT));
            }

            $output = join('\n', $output);

            if (($retval > 0) || preg_match('/Emergency stop/i', $output) || !file_exists($dviFile) || (0 === filesize($dviFile))) {
              throw new LatexToolsException('Can not compile LaTeX formula');
            }
          } catch (\Exception $e) {
            continue;
          }

          $command = $this->pathToDviPngTool . ' -q ' . $params['density'] . ' -o ' . $outputFile . ' ' . $dviFile;

          $output = '';
          $retval = '';

          if ($params['debug']) {
            echo sprintf(self::PRE_FORMATTED_MESSAGE, $command);
          }

          exec($command, $output, $retval);

          if ((($retval > 0) || !file_exists($outputFile) || (0 === filesize($outputFile))) && (!file_exists($outputFile) || (0 === filesize($outputFile)))) {
            $exception = new LatexToolsException('Can not convert DVI file to PNG');
            continue;
          }

          if ($params['debug']) {
            exit();
          }

          return $outputFile;
        } finally {
          foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
              @unlink($tempFile);
            }
          }
        }
      }
    }

    if ($params['fallbackToImage']) {
      return $this->renderSimpleImage($formula, $params);
    } else {
      throw $exception;
    }
  }

  public function isValidLaTeX($formula)
  {
    try {
      $this->check($formula);
      return true;
    } catch (\Exception $e) {
      return false;
    }
  }

  public function check($formula)
  {
    return $this->render($formula, ['checkOnly' => true]);
  }

  public function renderIntoFile($formula, $params = [])
  {
    return $this->render($formula, $params);
  }

  public function renderIntoResponse($formula, $params = [])
  {
    $imageFile = $this->render($formula, $params);

    $debug = isset($params['debug']) && $params['debug'];

    if (!$debug) {
      header('Content-Type: image/png');
      header('Content-Length: ' . filesize($imageFile));

      readfile($imageFile);
    }
  }

  public function setCachePath($value)
  {
    $this->cachePath = rtrim($value, '/') . '/';
  }

  public function makeDir($path, $access = 0777)
  {
    if (file_exists($path)) {
      return true;
    }

    try {
      return @mkdir($path, $access, true);
    } catch (\Exception $e) {
      return false;
    }
  }

  public function getCachePath()
  {
    if ($this->cachePath) {
      $result = $this->cachePath;
    } else {
      $result = sys_get_temp_dir();
    }

    if (!is_dir($result)) {
      $this->makeDir($result, 0777);
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
      if ($this->cachePath) {
        $result .= hash('sha256', $this->cachePath) . '/';
      }
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
    }

    return $result;
  }

  public function setTempPath($value)
  {
    $this->tempPath = rtrim($value, '/') . '/';
  }

  public function getTempPath()
  {
    if ($this->tempPath) {
      $result = $this->tempPath;
    } else {
      $result = sys_get_temp_dir();
    }

    if (!is_dir($result)) {
      $this->makeDir($result, 0777);
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
      if ($this->tempPath) {
        $result .= hash('sha256', $this->tempPath) . '/';
      }
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
    }

    return $result;
  }

  public function setFallbackToImage($value)
  {
    $this->fallbackToImage = $value;
  }

  public function getFallbackToImage()
  {
    return $this->fallbackToImage;
  }

  public function setFallbackImageFontName($value)
  {
    $this->fallbackImageFontName = $value;
  }

  public function getFallbackImageFontName()
  {
    return $this->fallbackImageFontName;
  }

  public function setFallbackImageFontSize($value)
  {
    $this->fallbackImageFontSize = $value;
  }

  public function getFallbackImageFontSize()
  {
    return $this->fallbackImageFontSize;
  }

  public function setDensity($value)
  {
    $this->density = $value;
  }

  public function getDensity()
  {
    return $this->density;
  }
}
