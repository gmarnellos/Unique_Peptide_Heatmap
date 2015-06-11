<?php
    
    date_default_timezone_set("America/New_York");
    
    //$topdir = dirname(dirname(dirname(dirname(__FILE__))));
    
    //require_once("$topdir/misc/PHPExcel/Classes/PHPExcel.php");
    require_once("PHPExcel/Classes/PHPExcel.php");
    
    error_reporting( error_reporting() & ~E_NOTICE );
    
    class PeptideHeatmapCombiner {
        
        // Needs more logging info and error trapping.  Works but is a bit flaky.  Some tests might be nice too.
        
        //var $protein_url = "http://www.uniprot.org/uniprot/{{acc}}";
        var $format      = "csv ";
        var $files       = array();
        var $filenames   = array();
        var $outfile     = null;
        
        
        public function __construct($files,$format="csv") {
            
            $this->files  = $files;
            $this->format = $format;
            
            foreach ($this->files as $file) {
                $this->filenames[] = basename($file);
            }
            
            $this->outfile = "/tmp/combined_heatmap.unique_peptide_info.". date("Y-m-d_H:m:i") . ".xls";
            
            $this->str = "";
            
        }
    
    
        public function parseFiles() {
        
          // From the input data we need to get:
          
          // Peptide sequence and area information, for peptides unique to a protein group
          // Protein group accession numbers
          // Descriptions corresponding to accessions
          
        
          $this->accessions = array();
            
          $this->minarea   = 1E20;
          $this->maxarea   = -1;
        
          ini_set('auto_detect_line_endings',true);
        
          foreach ($this->files as $i => $file) {
              
              $area_column = null;
              
              $this->current_file = $file;
              
              // data to be read in from each file
              $data = array();
              $sequence = "";
	          $desc = "";
  	          $accession = "";
              
              if ($this->format == "xls") {
                  # fill later
                  
              } else {
                  $handle = fopen($file, "r");
                  
                  $i = 0;
                  
                  while ($tmpline = fgets($handle)) {
                      
                      if (preg_match("/\t/",$tmpline)) {
                          $linearr = preg_split("/\t/",$tmpline);
                          //foreach ($linearr as $ii => $line) {
                              //$linearr[$ii] = preg_replace("/^\"(.*)\"$/",'$1',$line);
                          //}
                      } else {
                          $linearr = csv_string_to_array($tmpline);
                      }
                      //  $this->str .= "I $i " . join(", ",$linearr) . "<br>\n";
                      //  $this->str .= "Line " . sizeof($linearr) . "<br>\n";
                      
                      if ($i > 0) {
                          
                          //$unique_peptides = $linearr[8];
                          $unique_peptides = $linearr[4];
                          //print $unique_peptides;
                          
                          // read appropriate columns from input file(s) into data array
                          if ($unique_peptides == 1) {
                              //$sequence = $linearr[3];
                              $sequence = $linearr[1];
                              //$accession = $linearr[9];
                              $accession = $linearr[5];
                              //$desc      = $linearr[6];
                              //$area = $linearr[16];
                              $area = $linearr[8];
			      
                              
                              if ($data[$accession]) {
                                  if ($data[$accession]['seqs'][$sequence]) {
                                      array_push($data[$accession]['seqs'][$sequence]['area'], $area);
                                  }
                                  else {
                                      $data[$accession]['seqs'][$sequence]['area'] = array($area);
                                  }
                              }
                              else {
                                  $data[$accession]['seqs'][$sequence]['area'] = array($area);
                                  //$data[$accession]['description'] = $desc;
                                  $data[$accession]['description'] = '  ';
                              }

                          }
                          
                          
                      }
                      
                      $i++;
                      
                  } // end while
                  
                  fclose($handle);
                  //print_r($linearr);
                  //var_dump($data);
                  
                  // count unique peptides and calculate the area
                  foreach ($data as $acc => $acc_contents) {
                      $description = $acc_contents['description'];
                      $num_unique_peptides = count($acc_contents['seqs']);
                      
                      $comp_area = 0;

                      // go through unique, non-redundant peptides
                      foreach ($acc_contents['seqs'] as $pep => $pep_contents) {
                          //$new_description = "$acc : $description";
                          $new_description = $acc;
                          //$peptide_string = $pep;
                          $comp_area = array_sum($pep_contents['area'])/count($pep_contents['area']);
                          // process information of each peptide
			              $this->process_peptide($pep,$acc,$description,$comp_area);
//                          $this->process_accession($pep,$new_description,$comp_area);
                      }

                      //echo "Acc: $acc  Desc: $description  Num: $num_unique_peptides Area: $comp_area \n";
                    
                  }
                  
                  unset($data);    // Deletes the whole $data array for parsed input file
         
              } // end if


          } // end foreach
        } // end function
    
    
        public function combineFiles() {
            
            $row = 2;
            
            $minarea = $this->minarea;
            $maxarea = $this->maxarea;
            
            //$this->str .= "Min max $minarea  $maxarea\n";
            
            $outobj = new PHPExcel();
            $outobj->setActiveSheetIndex(0);
            
            $outobj->getActiveSheet()->setCellValue('A1','Peptide sequence');
            $outobj->getActiveSheet()->setCellValue('B1','Protein Accession');
            $outobj->getActiveSheet()->setCellValue('C1','Protein Description');
            $outobj->getActiveSheet()->setCellValue('D1','');
            
            //$cols = array('C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
            
            $cols = array('E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
            
            foreach ($this->files as $i => $f) {
                // Set column headings and widths
                $outobj->getActiveSheet()->setCellValue($cols[$i]."1",$this->filenames[$i] . " Area");
                $outobj->getActiveSheet()->getColumnDimension($cols[$i])->setWidth(12);
                
            }
            
            //$outobj->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
            $outobj->getActiveSheet()->getColumnDimension('A')->setWidth(45);
            $outobj->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $outobj->getActiveSheet()->getColumnDimension('C')->setWidth(20);
            $outobj->getActiveSheet()->getColumnDimension('D')->setWidth(8);
            
            $row = 2;
            
            $accnum = 0;
            
            foreach ($this->accessions as $acc => $vals) {
                
                    //echo $acc . "   " . $vals . "\n";
                
                $common_prot_acc = "";
                $common_desc = "";
                
                $ignore_peptide = "no";
                
                //$desc = $vals['Description'];
                
                //$unique_peptides = "-";
                
                //$outobj->getActiveSheet()->setCellValue('A'.$row,$acc);
                //$outobj->getActiveSheet()->setCellValue('B'.$row,$desc);
                
                //$url = preg_replace("/{{acc}}/",$acc,$this->protein_url);
                //$outobj->getActiveSheet()->getCell('A'.$row)->getHyperlink()->setUrl("http://www.uniprot.org/uniprot/$acc");
                
                // need to check the protein group description of the given peptide in each file to make sure they are all the same
                foreach ($this->files as $ii=>$fl) {
                    
                    if (isset($vals['Protein_Accession'][$fl])) {
                        $current_prot_acc = $vals['Protein_Accession'][$fl];
                    }
                    
                    if ($common_prot_acc == "") {
                        $common_prot_acc = $current_prot_acc;
                    }
                    elseif ($common_prot_acc != $current_prot_acc) {
                        $ignore_peptide = "yes";
                        break;
                    }
                    
                    if (isset($vals['Description'][$fl])) {
                        $common_desc = $vals['Description'][$fl];
                    }
                }
                
                if ($ignore_peptide == "no") {
                
                  $outobj->getActiveSheet()->setCellValue('A'.$row,$acc);
                  $outobj->getActiveSheet()->setCellValue('B'.$row,$common_prot_acc);
                  $outobj->getActiveSheet()->setCellValue('C'.$row,$common_desc);
                  $outobj->getActiveSheet()->setCellValue('D'.$row,'  ');
                  $outobj->getActiveSheet()->getCell('B'.$row)->getHyperlink()->setUrl("http://www.uniprot.org/uniprot/$common_prot_acc");
                    
                  foreach ($this->files as $i=>$f) {
                    if (isset($vals['Area'][$f])) {
                        
                            $area = $vals['Area'][$f];
                            
                            // Calculate color for Area
                            list($r,$g,$b) = $this->rgb($minarea + 1,$maxarea,$area + 1);
                        
                            $col = $r . $g. $b;
                            
                            if ($area > 0) {
                                // Format area cells
                                $this->cellColor($cols[$i].$row,$col,$outobj);
                                $outobj->getActiveSheet()->getStyle($cols[$i].$row)->getNumberFormat()->setFormatCode('0.00E+00');
                                // Don't write area value unless it's different from null and greater than 0
                                $outobj->getActiveSheet()->setCellValue($cols[$i].$row,$area);
                            }
                           // $outobj->getActiveSheet()->setCellValue($cols[$i].$row,$area);
                    } // end if
                    
                  } // end foreach
                    
                  $row++;  // go to next row only of if peptide is not ignored
                    
                } // end if ignore_peptide
                
                //$row++;
                
            } // end foreach
            
            // Write output file
            
            $objwriter = PHPExcel_IOFactory::createWriter($outobj,'Excel2007');
            $objwriter->save($this->outfile);
            
        } // end function
        
        
        public function rgb($min,$max,$val) {
            
            $min = $min*1.0;
            $max = $max*1.0;
            
            $frac = ($val-$min+1)/($max-$min+1);
            
            //$g = intval(255*(1-$frac));
            //$r = 255 - $g;
            
            // specify color scale
            if ($frac < 0.002) {
              $g = 166;
              $r = 111;
            }
            else {
              $g = intval(205 - (205 - 55)*$frac);
              $r = intval((253 - 153)*$frac) + 153;
            }
            
            $b = 55;
            
            // print "RGB $r $g $b : $val  [$min:$max]\n";
            
            $r = dechex($r);
            $g = dechex($g);
            $b = dechex($b);
            
            $r = str_pad($r,2,'0',STR_PAD_LEFT);
            $g = str_pad($g,2,'0',STR_PAD_LEFT);
            $b = str_pad($b,2,'0',STR_PAD_LEFT);
            //print "RGB $r $g $b : $val  [$min:$max]\n";
            return array($r,$g,$b);
        }
        
        
        public function cellColor($cells,$color,$excelobj){
            
            $excelobj->getActiveSheet()->getStyle($cells)->getFill()->applyFromArray(array('type' => PHPExcel_Style_Fill::FILL_SOLID, 'startcolor' => array('rgb' => $color)));
        }
    

    
        public function process_accession($accession,$desc,$area=null) {
          if (isset($accession) && $accession != "") {
            
            if (!isset($this->accessions[$accession]))  {
                //$this->accessions[$accession] = array('Description' => $desc, 'Unique_Peptides' => array(),'Area' => array());
                $this->accessions[$accession] = array('Description' => array(),'Area' => array());
            }
            
            // keep track of each peptide protein group description by file in which peptide occurs
            if (isset($desc)) {
                $this->accessions[$accession]['Description'][$this->current_file] = $desc;
            }

            // keep track of each peptide area by file in which peptide occurs
            if (isset($area)) {
                $this->accessions[$accession]['Area'][$this->current_file] = $area;
            }
              
            if ($area < $this->minarea) {
                  $this->minarea = $area;
            }
            if ($area > $this->maxarea) {
                  $this->maxarea = $area;
            }
              
          } // end if
        
        } // end function

        
        public function process_peptide($pep_sequence,$protein_accession,$desc,$area=null) {
            if (isset($pep_sequence) && $pep_sequence != "") {
                
                if (!isset($this->accessions[$pep_sequence]))  {
                    $this->accessions[$pep_sequence] = array('Protein_Accession' => array(),'Description' => array(),'Area' => array());
                }
                
                // keep track of each peptide protein group accession by file in which peptide occurs
                if (isset($protein_accession)) {
                    $this->accessions[$pep_sequence]['Protein_Accession'][$this->current_file] = $protein_accession;
                }

                // keep track of each peptide protein group description by file in which peptide occurs
                if (isset($desc)) {
                    $this->accessions[$pep_sequence]['Description'][$this->current_file] = $desc;
                }
                
                // keep track of each peptide area by file in which peptide occurs
                if (isset($area)) {
                    $this->accessions[$pep_sequence]['Area'][$this->current_file] = $area;
                }
                
                if ($area < $this->minarea) {
                    $this->minarea = $area;
                }
                if ($area > $this->maxarea) {
                    $this->maxarea = $area;
                }
                
            } // end if
            
        } // end function

    
    
    } // end class

    if (isset($argv) && sizeof($argv) > 0) {
        array_shift($argv);
        
        print_r($argv);
        $hmc = new PeptideHeatmapCombiner($argv,"csv");
        $hmc->parseFiles();
        $hmc->combineFiles();
    }
    
?>
