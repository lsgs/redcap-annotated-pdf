<?php
/**
 * REDCap External Module: Annotated PDF
 * Export PDF of all forms (blank) with metadata annotations.
 * @author Luke Stevens, Murdoch Children's Research Institute
 */
namespace MCRI\AnnotatedPDF;

use ExternalModules\AbstractExternalModule;

class AnnotatedPDF extends AbstractExternalModule
{
        public function redcap_every_page_top($project_id) {
                if (PAGE==='Design/data_dictionary_codebook.php') {
                        $url = $this->getUrl('annotated_pdf.php');
                        ?>
                        <button id="annotated-crf-btn" class="btn btn-defaultrc btn-xs">
                            <img src="<?php echo APP_PATH_IMAGES;?>/pdf.gif" style="vertical-align:middle;"/> 
                            <span style="font-size:12px;vertical-align:middle;">Annotated PDF</span>
                        </button>
                        <style type="text/css">
                            #annotated-crf-btn { display: none; margin-top:5px; }
                        </style>
                        <script type="text/javascript">
                            $(document).ready(function() {
                                $('#annotated-crf-btn')
                                    .on('click', function() {
                                        window.location.href='<?php echo $url;?>';
                                    })
                                    .insertAfter('button[onclick*="window.print()"]')
                                    .show();
                            });
                        </script>
                        <?php
                }
        }
        
        public function renderAnnotatedPdf() {
                global $app_title;

                // replicating font stuff from PDF/index.php
                // Must have PHP extention "mbstring" installed in order to render UTF-8 characters properly AND also the PDF unicode fonts installed
                $pathToPdfUtf8Fonts = APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS . "unifont" . DS;
                if (function_exists('mb_convert_encoding') && is_dir($pathToPdfUtf8Fonts)) {
                        // Define the UTF-8 PDF fonts' path
                        define("FPDF_FONTPATH",   APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
                        define("_SYSTEM_TTFONTS", APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
                        // Set contant
                        define("USE_UTF8", true);
                        // Use tFPDF class for UTF-8 by default
                        if ($project_encoding == 'chinese_utf8') {
                                require_once APP_PATH_LIBRARIES . "PDF_Unicode.php";
                        } else {
                                require_once APP_PATH_LIBRARIES . "tFPDF.php";
                        }
                } else {
                        // Set contant
                        define("USE_UTF8", false);
                        // Use normal FPDF class
                        require_once APP_PATH_LIBRARIES . "FPDF.php";
                }
                // If using language 'Japanese', then use MBFPDF class for multi-byte string rendering
                if ($project_encoding == 'japanese_sjis')
                {
                        require_once APP_PATH_LIBRARIES . "MBFPDF.php"; // Japanese
                        // Make sure mbstring is installed
                        if (!function_exists('mb_convert_encoding'))
                        {
                                exit("ERROR: In order for multi-byte encoded text to render correctly in the PDF, you must have the PHP extention \"mbstring\" installed on your web server.");
                        }
                }
                
                require_once APP_PATH_DOCROOT . "PDF/functions.php"; // This MUST be included AFTER we include the FPDF class
                
                // Remove special characters from title for using as filename
                $filename .= str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9]/", " ", $app_title)));
                // Make sure filename is not too long
                if (strlen($filename) > 30) {
                        $filename = substr($filename, 0, 30);
                }
                
                $acknowledgement = getAcknowledgement($_GET['pid'], (isset($_GET['instrument']))?$_GET['instrument']:'');

                header('Content-Type: application/x-download');
                header('Content-Disposition: attachment; filename="'.$filename.'Annotated.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');

                $metadata = $this->getMetadataForPdf();
                
                renderPDF($metadata, $acknowledgement, strip_tags(label_decode($app_title).'_annotated'), array(''=>array(''=>null)), false);
        }
        
        protected function getMetadataForPdf() {
                global $project_id, $Proj, $table_pk;
                
                // replicating metadata read from PDF/index.php
                // Save fields into metadata array
                $draftMode = false;
                if (isset($_GET['instrument'])) {
                        // Check if we should get metadata for draft mode or not
                        $draftMode = ($status > 0 && isset($_GET['draftmode']));
                        $metadata_table = ($draftMode) ? "redcap_metadata_temp" : "redcap_metadata";
                        // Make sure form exists first
                        if ((!$draftMode && !isset($Proj->forms[$_GET['instrument']])) || ($draftMode && !isset($Proj->forms_temp[$_GET['instrument']]))) {
                                exit('ERROR!');
                        }
                        $Query = "select * from $metadata_table where project_id = $project_id and ((form_name = '{$_GET['instrument']}'
                                          and field_name != concat(form_name,'_complete')) or field_name = '$table_pk') order by field_order";
                } else {
                        $Query = "select * from redcap_metadata where project_id = $project_id and
                                          (field_name != concat(form_name,'_complete') or field_name = '$table_pk') order by field_order";
                }
                $QQuery = db_query($Query);
                $metadata = array();
                while ($row = db_fetch_assoc($QQuery))
                {
                        // If field is an "sql" field type, then retrieve enum from query result
                        if ($row['element_type'] == "sql") {
                                $row['element_enum'] = getSqlFieldEnum($row['element_enum'], PROJECT_ID, $_GET['id'], $_GET['event_id'], $_GET['instance'], null, null, $_GET['instrument']);
                        }
                        // If PK field...
                        if ($row['field_name'] == $table_pk) {
                                // Ensure PK field is a text field
                                $row['element_type'] = 'text';
                                // When pulling a single form other than the first form, change PK form_name to prevent it being on its own page
                                if (isset($_GET['instrument'])) {
                                        $row['form_name'] = $_GET['instrument'];
                                }
                        }
                        // Store metadata in array
                        $metadata[] = $row;
                }
                
                
                // now tweaking the element label and value labels for annotation purposes
                $annotated = array();
                foreach ($metadata as $fld => $fieldattr) {
                        $annotation = '';
                        
                        if ($fieldattr['element_type']!=='descriptive') {
                                $type = ($fieldattr['element_type']==='select') ? 'dropdown' : $fieldattr['element_type'];
                                $valtype = (!is_null($fieldattr['element_validation_type']) && $fieldattr['element_validation_type']==='int') ? 'integer' : $fieldattr['element_validation_type'];

                                $annotation = PHP_EOL.'{['.$fieldattr['field_name'].'] '.$type;
                                if (!is_null($valtype)) { $annotation .= ' '.$valtype; }
                                $annotation .= '}';
                        }
                        
                        if (!is_null($fieldattr['branching_logic'])) { $annotation .= ' '.PHP_EOL.'{Branching logic (show if): '.$fieldattr['branching_logic'].'}'; }
                        
                        $fieldattr['element_label'] .= ' '.$annotation;
                        
                        if (!is_null($fieldattr['element_enum'])) {
                                $choicesannotated = array();
                                $choices = explode('\n', $fieldattr['element_enum']);
                                foreach ($choices as $thischoice) {
                                        $vl = explode(', ', $thischoice, 2);
                                        $v = trim($vl[0]);
                                        $l = trim($vl[1]);
                                        $choicesannotated[] = $v.', {'.$v.'} '.$l.' ';
                                }
                                $fieldattr['element_enum'] = implode('\n', $choicesannotated);
                        }
                        
                        $annotated[$fld] = $fieldattr;
                }
                
                return $annotated;
        }
}
