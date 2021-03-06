<?php

class TRH_Pointeuse extends TObjetStd {
	function __construct() { /* declaration */
		$this->set_table(MAIN_DB_PREFIX.'rh_pointeuse');
		
		$this->add_champs('date_deb_am,date_fin_am,date_deb_pm,date_fin_pm','type=date;');
		$this->add_champs('time_presence', 'type=entier;');
		$this->add_champs('fk_user','type=entier;index;');
		$this->add_champs('motif','type=text;');
		$this->add_champs('date_jour',array('type'=>'date','index'=>true));
		
		
		$this->_init_vars();
		
		$this->start();
		
		
		$this->date_deb_am=$this->date_fin_am=$this->date_deb_pm=$this->date_fin_pm=0;
		
		
	}
	function loadByDate(&$ATMdb, $date, $fk_user = 0) {
//		return $this->loadBy($ATMdb, $date, 'date_jour');

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."rh_pointeuse WHERE date_jour LIKE '".$date."%'";
		if($fk_user>0) $sql.=" AND fk_user=".(int)$fk_user;
		$ATMdb->Execute($sql);
		
		if($obj = $ATMdb->Get_line()) {
			return $this->load($ATMdb, $obj->rowid);
		}

	}
	
	function save(&$ATMdb) {
		$this->get_time_presence();
		parent::save($ATMdb);
	}
	function get_time_presence() {
			
		$this->uniformdate();	
		
		if($this->date_fin_am==0 || $this->date_deb_pm==0) {
			$this->time_presence = $this->date_fin_pm - $this->date_deb_am;
		}
		else {
			$this->time_presence = ($this->date_fin_am - $this->date_deb_am) + ( $this->date_fin_pm - $this->date_deb_pm );
		}	
		
		if($this->time_presence<0 || $this->time_presence>86400) $this->time_presence = 0;
		
	}
	
	private function uniformdate() {
			
		$Tab=array('date_deb_am','date_fin_am','date_deb_pm','date_fin_pm');	
			
		foreach($Tab as $f) {
			if(!empty($this->{$f})) $this->{$f} = strtotime( date('Y-m-d', $this->date_jour).' '.date('H:i:s', $this->{$f}) );
		}
		
	}
	
	static function tempsTravailReelDuJour(&$ATMdb, $fk_user, $date, $defaultTR=0) {
		
		$ttr = 0;
		if($defaultTR)$ttr = $defaultTR;
		
		$pointeuse=new TRH_Pointeuse;
		if($pointeuse->loadByDate($ATMdb, $date, $fk_user)) {
			$pointeuse->get_time_presence();
			$ttr = $pointeuse->time_presence;
		}
		else{
			
			$absence=new TRH_Absence;
			$sql = $absence->rechercheAbsenceUser($ATMdb, $fk_user, $date, $date);
			$Tab = $ATMdb->ExecuteAsArray($sql);
			
			foreach($Tab as $row) { // A prévoir à terme, les présence multiple sur une journée
				
				$type=new TRH_TypeAbsence;
				$type->load_by_type($ATMdb, $row->type);
				if($type->isPresence) {
					dol_include_once('/core/lib/date.lib.php');
			
					list($h1, $m1) = explode(':', date('H:i', strtotime( $row->date_hourStart)));
					list($h2, $m2) = explode(':', date('H:i',strtotime( $row->date_hourEnd)));
					list($h3, $m3) = explode(':', date('H:i',strtotime( $row->date_lunchBreak)));
					
					$time1 = convertTime2Seconds($h1, $m1);
					$time2 = convertTime2Seconds($h2, $m2);
					$time3 = convertTime2Seconds($h3, $m3);
					
					$time_total = $time2 - $time1 - $time3;
					
					if($time_total>0) {
						$ttr = $time_total;
						
					}
					else{
						$ttr = strtotime($row->date_hourEnd) - strtotime($row->date_hourStart);	
					}
					
					
				}
				
			}
			
		}
		// arrondi à 5 décimale pour une bonne précision
                $virgule = ceil((($ttr % 3600)/3600)*100000)/100000;
                $ttr = $ttr - ($ttr % 3600) + $virgule * 3600;

		return $ttr / 3600;
		
	}
	
	static function tempsPresenceDuJour(&$ATMdb, $fk_user, $date) {
		
		$TStatPlanning = TRH_Absence::getPlanning($ATMdb, 0, $fk_user,  $date , $date);
		list($dummy,$TStat) = each($TStatPlanning);
		
		list($k, $TReturn) = each($TStat);
		
		return $TReturn;
		
	}
	
	static function getFields(&$ATMdb) {
		$fields = array();
		
		$sql = 'SHOW COLUMNS FROM ' . MAIN_DB_PREFIX . 'rh_pointeuse';
		
		$ATMdb->Execute($sql);
		
		while ($column = $ATMdb->Get_line()) {
			$fields[] = $column->Field;
		}
		
		return $fields;
	}
}

class TRH_declarationTemps extends TObjetStd {
	function __construct() { /* declaration */
		$this->set_table(MAIN_DB_PREFIX.'rh_declaration_temps');
		
		$this->add_champs('date_ref','type=date;');
		$this->add_champs('nb_hour,nb_hour_diff', 'type=float;');
		$this->add_champs('fk_user','type=entier;index;');
		
		$this->_init_vars();
		
		$this->start();
		
	}
	function load_by_date(&$ATMdb, $date_ref, $fk_user=0) {
		global $user;
		
		
		if($fk_user<=0)$fk_user = $user->id;
		
		$ATMdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."rh_declaration_temps WHERE fk_user=".$fk_user." AND date_ref='".$date_ref."'");
		if($obj=$ATMdb->Get_line()) {
			return $this->load($ATMdb, $obj->rowid);
		}
		else{
			return false;
		}
		
	}	
}
	
