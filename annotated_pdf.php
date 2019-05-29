<?php
if (is_null($module) || !($module instanceof MCRI\AnnotatedPDF\AnnotatedPDF)) { exit(); }
$module->renderAnnotatedPdf();
