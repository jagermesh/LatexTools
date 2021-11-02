<?php

require_once(__DIR__ . '/vendor/autoload.php');

$latexTools = new \LatexTools\LatexTools();
$latexTools->renderIntoResponse('(\frac{\beta }{\mu})^\beta {\Gamma(\beta )} \,  e^{-\frac{V\,\beta }{\mu }} \label{gamma}');
