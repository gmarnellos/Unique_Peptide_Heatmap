<?php
    
    date_default_timezone_set("America/New_York");
    
    //$topdir = dirname(dirname(dirname(dirname(__FILE__))));
    
    //require_once("$topdir/misc/PHPExcel/Classes/PHPExcel.php");
    require_once("PHPExcel/Classes/PHPExcel.php");
    
    error_reporting( error_reporting() & ~E_NOTICE );
    
    class PeptideHeatmapCombiner {
        
        // Needs more logging info and error trapping.  Works but is a bit flaky.  Some tests might be nice too.
        
        var $protein_url = "http://www.uniprot.org/uniprot/{{acc}}";
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
            
            $this->outfile = "/tmp/combined_heatmap.". date("Y-m-d_H:m:i") . ".xls";
            
            $this->str = "";
            
        }
    
    
        public function parseFiles() {
        
          // First we want to combine the datasets.   We want
        
          // Array of accession numbers (column A)
          // Array of descriptions hashed by accession numbers
          // Each accession is associated with one number from each of the files - unique peptides (Column F)
        
        
          // Loop through rows
          //   If we have some data in column A then :
          //        - test for max and min peptides
        
          $this->accessions = array();
          $this->minpep     = 100000;
          $this->maxpep     = -1;
            
          $this->minarea   = 1E20;
          $this->maxarea   = -1;
        
          ini_set('auto_detect_line_endings',true);
        
          foreach ($this->files as $i => $file) {
              
              $area_column = null;
              
              $this->current_file = $file;
              
              // data to be read in
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
                          foreach ($linearr as $ii => $line) {
                              $linearr[$ii] = preg_replace("/^\"(.*)\"$/",'$1',$line);
                          }
                      } else {
                          $linearr = csv_string_to_array($tmpline);
                      }
                      //  $this->str .= "I $i " . join(", ",$linearr) . "<br>\n";
                      //  $this->str .= "Line " . sizeof($linearr) . "<br>\n";
                      
                      if ($i > 0) {
                          
                          $unique_peptides = $linearr[8];
                          //print $unique_peptides;
                          
                          // read appropriate columns from input file(s) into data array
                          if ($unique_peptides == 1) {
                              $sequence = $linearr[3];
                              $accession = $linearr[9];
                              $desc      = $linearr[6];
                              $area = $linearr[16];
			      
                              
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
                                  $data[$accession]['description'] = $desc;
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
                      foreach ($acc_contents['seqs'] as $pep => $pep_contents) {
                          $comp_area += array_sum($pep_contents['area'])/count($pep_contents['area']);
                      }
                      
                      //echo "Acc: $acc  Desc: $description  Num: $num_unique_peptides Area: $comp_area \n";
                    
                      // ready to process accession information
                      $this->process_accession($acc,$description,$num_unique_peptides,$comp_area);
                      
                  }
                  
                  unset($data);    // This deletes the whole $data array
         
              } // end if


          } // end foreach
        } // end function
    
    
        public function combineFiles() {
            
            $row = 2;
            
            $minpep = $this->minpep;
            $maxpep = $this->maxpep;
            
            $minarea = $this->minarea;
            $maxarea = $this->maxarea;
            
            $this->str .= "Min max $minpep  $maxpep\n";
            
            $outobj = new PHPExcel();
            $outobj->setActiveSheetIndex(0);
            $outobj->getActiveSheet()->setCellValue('A1','Accession');
            $outobj->getActiveSheet()->setCellValue('B1','Description');
            
            $cols = array('C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ');
            
            foreach ($this->files as $i => $f) {
                // Set column headings and widths
                $outobj->getActiveSheet()->setCellValue($cols[$i] . "1",$this->filenames[$i]);
                $outobj->getActiveSheet()->setCellValue($cols[$i+sizeof($this->files)]."1",$this->filenames[$i] . " Area");
                $outobj->getActiveSheet()->getColumnDimension($cols[$i])->setWidth(20);
                $outobj->getActiveSheet()->getColumnDimension($cols[$i+sizeof($this->files)])->setWidth(20);
            }
            
            $outobj->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
            $outobj->getActiveSheet()->getColumnDimension('B')->setWidth(100);;
            
            $row = 2;
            
            $accnum = 0;
            
            foreach ($this->accessions as $acc => $vals) {
                $desc = $vals['Description'];
                
                $unique_peptides = "-";
                
                $url = preg_replace("/{{acc}}/",$acc,$this->protein_url);
                
                
                $outobj->getActiveSheet()->setCellValue('A'.$row,$acc);
                $outobj->getActiveSheet()->setCellValue('B'.$row,$desc);
                
                $outobj->getActiveSheet()->getCell('A'.$row)->getHyperlink()->setUrl("http://www.uniprot.org/uniprot/$acc");
                
                foreach ($this->files as $i=>$f) {
                    
                    if (isset($vals['Unique_Peptides'][$f])) {
                        $unique_peptides = $vals['Unique_Peptides'][$f];
                        
                        // Calculate color for unique peptide count
                        
                        list($r,$g,$b) = $this->rgb($minpep,$maxpep,$unique_peptides);
                        
                        $col = $r . $g. $b;
                        
                        //print "Val $val - color $row $col\n";
                        
                        $this->cellColor($cols[$i].$row,$col,$outobj);
                        $outobj->getActiveSheet()->setCellValue($cols[$i].$row,$unique_peptides);

                        if (isset($vals['Area'][$f])) {
                            if ($row == 2) {
                                //$outobj->getActiveSheet()->setCellValue($cols[$i+sizeof($this->files)]."1",$this->filenames[$i] . " Area");
                            }
                            $area = $vals['Area'][$f];
                            
                            // Calculate color for Area
                            list($r,$g,$b) = $this->rgb($minarea + 1,$maxarea,$area + 1);
                            
                            $col = $r . $g. $b;
                            
                            if ($area > 0) {
                                // Format area cells
                                $this->cellColor($cols[$i+sizeof($this->files)].$row,$col,$outobj);
                                $outobj->getActiveSheet()->getStyle($cols[$i+sizeof($this->files)].$row)->getNumberFormat()->setFormatCode('0.00E+00');
                            }
                            $outobj->getActiveSheet()->setCellValue($cols[$i+sizeof($this->files)].$row,$area);
                        } // end if
                        
                    } // end if
                    
                } // end foreach
                
                $row++;
                
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
            if ($frac < 0.02) {
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
    

    
        public function process_accession($accession,$desc,$unique_peptides,$area=null) {
          if (isset($accession) && $accession != "") {
            
            if (!isset($this->accessions[$accession]))  {
                $this->accessions[$accession] = array('Description' => $desc, 'Unique_Peptides' => array(),'Area' => array());
            }
            
            $this->accessions[$accession]['Unique_Peptides'][$this->current_file] = $unique_peptides;
            
            if (isset($area)) {
                $this->accessions[$accession]['Area'][$this->current_file] = $area;
            }
            
            $accnum = sizeof(array_keys($this->accessions));
            
            if ($accnum%100 == 0) {
                $this->str .= "Found $accnum (th) accession  ".$accession." Min/Max peptides [".$this->minpep."][".$this->maxpep."]\n";
            }
              
            // keep track of min and max values for unique peptides and area
              
            if ($unique_peptides < $this->minpep) {
                $this->minpep = $unique_peptides;
            }
            if ($unique_peptides > $this->maxpep) {
                $this->maxpep = $unique_peptides;
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
        
        //print_r($argv);
        $hmc = new PeptideHeatmapCombiner($argv,"csv");
        $hmc->parseFiles();
        $hmc->combineFiles();
    }
    
?>
