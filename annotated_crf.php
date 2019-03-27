<?php
if (is_null($module) || !($module instanceof MCRI\AnnotatedCRF\AnnotatedCRF)) { exit(); }
$module->renderAnnotatedPdf();