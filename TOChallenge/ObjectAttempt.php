<?php
include_once 'MySql.php';

class Attempt
{
	var $AttemptId;
	var $AthleteId;
	var $AthleteName;
	var $ChallengeId;
	var $Distance;
	var $Time;
	var $Weight;
	var $Entered;
	var $SPM;
	var $PacePoints;
	var $GainPoints;
	
	function Attempt($attemptId, $athleteId, $athleteName,
		$challengeId, $distance, $time, $weight, $entered, $spm, $pacePoints, $gainPoints)
	{
		$this->AttemptId = $attemptId;
		$this->AthleteId = $athleteId;
		$this->AthleteName = $athleteName;
		$this->ChallengeId = $challengeId;
		$this->Distance = $distance;
		$this->Time = $time;
		$this->Weight = $weight;
		$this->Entered = $entered;
		$this->SPM = $spm;
		$this->PacePoints = $pacePoints;
		$this->GainPoints = $gainPoints;
	}
	
	function Save()
	{
		$deleteSQL = "delete from attempt where AthleteId=".$this->AthleteId." and ChallengeId=".$this->ChallengeId;
		ExecuteStatement($deleteSQL);
		
		$insertSQL = "insert into attempt(AthleteId, ChallengeId, Distance, Time, Weight, Entered, SPM)
		              values(".$this->AthleteId.",".
		                       $this->ChallengeId.",".
		                       $this->Distance.",".
		                       $this->Time.",".
		                       "'".$this->Weight."',".
		                       "Now(),".
		                       $this->SPM.")";

		ExecuteStatement($insertSQL);
		
		Attempt::UpdatePoints($this->ChallengeId);
	}

	private static function CreateFromRecord($r)
	{
		return new Attempt(
				$r['Id'], $r['AthleteId'], $r['AthleteName'], 
				$r['ChallengeId'], $r['Distance'], $r['Time'],
				$r['Weight'], $r['Entered'], $r['SPM'], $r['PacePoints'], $r['GainPoints']);
	}
	
	static function GetForChallenge($challengeId)
	{
		$selectSQL = 
"SELECT a.Id, 
		a.AthleteId,
		ath.Name AthleteName,
		a.ChallengeId,
		a.Distance,
		a.Time,
		a.Weight,
		a.Entered,
		a.SPM,
		a.PacePoints,
		a.GainPoints
   FROM attempt a,
        athlete ath
 where a.AthleteId = ath.Id
   and a.ChallengeId = ".$challengeId."
order by a.Distance/a.Time desc";
		
		$attemptRecords = GetSelectResult($selectSQL);
	
		$attempts = array();
		foreach ($attemptRecords as $r)
		{
			$attempts[count($attempts)] = Attempt::CreateFromRecord($r);
		}
	
		return $attempts;
	}
	
	private static function UpdatePoints($challengeId)
	{
		Attempt::UpdateGainPoints($challengeId);
		Attempt::UpdatePacePoints($challengeId);
	}
	
	private static function UpdateGainPoints($challengeId)
	{
		$gainSql =
"select x.AthleteId,
        x.this_pace,
        y.last_pace
   from (select a.ChallengeId,
                c.Year this_year,
                c.Month this_month,
                a.AthleteId,
                a.Time/(a.Distance/500) this_pace
           from attempt a,
                challenge c
          where a.ChallengeId = c.Id) x left outer join
        (select c.Year last_year,
                c.Month last_month,
                a.AthleteId,
                a.Time/(a.Distance/500) last_pace
           from attempt a,
                challenge c
          where a.ChallengeId = c.Id) y on (x.AthleteId = y.AthleteId and 12*x.this_year + x.this_month = 12*y.last_year + y.last_month + 1) 
  where ChallengeId = $challengeId
order by this_pace - last_pace";
		
		$attemptRecords = GetSelectResult($gainSql);
	
		$points = 1;
		foreach ($attemptRecords as $r)
		{
			if (isset($r["last_pace"]))
			{
				$athleteId = $r["AthleteId"];
				$updateStatement = "update attempt set GainPoints=$points where ChallengeId=$challengeId and AthleteId=$athleteId";
				ExecuteStatement($updateStatement);
			}
			$points++;
		}
	}
	
	private static function UpdatePacePoints($challengeId)
	{
		$paceSql =
"SELECT a.AthleteId,
		a.Distance,
		a.Time
   FROM attempt a
 where a.ChallengeId = $challengeId
order by a.Distance/a.Time";
		
		$attemptRecords = GetSelectResult($paceSql);
	
		$points = 1;
		foreach ($attemptRecords as $r)
		{
			$athleteId = $r["AthleteId"];
			$updateStatement = "update attempt set PacePoints=$points where ChallengeId=$challengeId and AthleteId=$athleteId";
			ExecuteStatement($updateStatement);
				
			$points++;
		}
	}
}
?>