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
                        $url = (version_compare(REDCAP_VERSION, '9.8.0')<0) 
                                ? APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/PDF/index.php?annotated=1&pid='.$project_id
                                : APP_PATH_WEBROOT_FULL.'redcap_v'.REDCAP_VERSION.'/index.php?route=PdfController:index&annotated=1&pid='.$project_id;
                        $sel = (version_compare(REDCAP_VERSION, '13.10.0')<0) 
                                ? 'button[onclick*="window.print()"]'
                                : 'button[onclick*="printCodebook()"]'; // selector for placing button changed with v13.10.0

                        ?>
                        <button id="annotated-crf-btn" class="btn btn-defaultrc btn-xs invisible_in_print">
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
                                    .insertAfter('<?=$sel?>')
                                    .show();
                            });
                        </script>
                        <?php
                }
        }
        
        public function redcap_pdf($project_id, $metadata, $data, $instrument, $record, $event_id, $instance) {
                if (!(isset($_GET['annotated']) && (bool)$_GET['annotated'])) return;

                if ($this->delayModuleExecution()) { 
                        // Boolean true is returned if the hook was successfully delayed, false if the hook cannot be delayed any longer and this is the module's last chance to perform any required actions. 
                        // If delay successful, return; immediately to stop the current execution of hook
                        return; 
                }
                
                // tweak metadata element label and value labels for annotation purposes
                $annotatedMetadata = array();
                foreach ($metadata as $fieldIndex => $fieldattr) {
                        $annotation = '';
                        
                        if ($fieldattr['element_type']!=='descriptive') {
                                $type = ($fieldattr['element_type']==='select') ? 'dropdown' : $fieldattr['element_type'];
                                $valtype = (!is_null($fieldattr['element_validation_type']) && $fieldattr['element_validation_type']==='int') ? 'integer' : $fieldattr['element_validation_type'];

                                $annotation = PHP_EOL.'{['.$fieldattr['field_name'].'] '.$type;
                                $valmin = NULL;
                                $valmax = NULL;
                                $valreq = NULL;
                                $valphi = NULL;

                                if(!is_null($valtype))
                                {
                                    if(!empty($fieldattr['element_validation_min'])){$valmin = 'Min: '. $fieldattr['element_validation_min'];}
                                    if(!empty($fieldattr['element_validation_max'])){$valmax = 'Max: '. $fieldattr['element_validation_max'];}
                                    $annotation .= ' ('.trim($valtype.' '.$valmin.' '.$valmax).')'; 
                                }

                                if($fieldattr['field_req']){ $valreq = 'Required'; $annotation .= ' '.$valreq; }
                                if($fieldattr['field_phi']){ $valphi = 'Identifier'; $annotation .= ' '.$valphi; }  
                                
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
                        
                        $annotatedMetadata[$fieldIndex] = $fieldattr;
                }

                return array('metadata' => $annotatedMetadata);
        }
}
