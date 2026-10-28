<?php
namespace App\Support;

/** Pure functions: holdout uses only data preceding each origin. */
class JimnyOwnerForecast
{
    public const MINIMUM = [1=>14,7=>21,30=>60,90=>180,365=>540];
    public static function predict(array $series, int $horizon, string $method): float
    {
        $n=count($series); if (!$n) return 0;
        $recent=array_slice($series,-28); $mean=array_sum($recent)/count($recent); $sum=0;
        for($k=0;$k<$horizon;$k++) {
            $same=[];
            for($j=$n-7+($k%7);$j>=0 && count($same)<4;$j-=7) $same[]=$series[$j];
            $weekday=$same ? array_sum($same)/count($same) : $mean;
            $sum += $method==='mean28' ? $mean : .7*$weekday+.3*$mean;
        }
        return max(0,$sum);
    }
    public static function forecast(array $series,int $horizon): array
    {
        $min=self::MINIMUM[$horizon]??PHP_INT_MAX; $n=count($series);
        $r=['horizon_days'=>$horizon,'history_days'=>$n,'min_history_days'=>$min,'available'=>$n >= $min,
            'method'=>'mean28','point'=>null,'lower'=>null,'upper'=>null,'tests'=>0,'mae'=>null,
            'baseline_mae'=>null,'evaluation'=>[], 'status'=>'insufficient_history'];
        if(!$r['available'])return $r;
        // Non-overlapping complete windows. Earlier windows select; latest evaluates independently.
        $origins=[];
        for($i=$n-$horizon;$i >= $min && count($origins)<6;$i-=$horizon)$origins[]=$i;
        sort($origins); $errors=['mean28'=>[],'weekday_blend'=>[]];
        foreach($origins as $i) {
            $actual=array_sum(array_slice($series,$i,$horizon));
            foreach(array_keys($errors) as $m)$errors[$m][]=abs($actual-self::predict(array_slice($series,0,$i),$horizon,$m));
        }
        $method='mean28';
        if(count($origins)>=3) {
            $a=array_slice($errors['mean28'],0,-1);$b=array_slice($errors['weekday_blend'],0,-1);
            if(array_sum($b)<array_sum($a)-0.000001)$method='weekday_blend';
        }
        $r['method']=$method;$r['tests']=count($origins);
        if($origins) {
            $r['mae']=round(end($errors[$method]),2);
            $r['baseline_mae']=round(end($errors['mean28']),2);
            $last=end($origins);
            $r['evaluation']=['actual'=>round(array_sum(array_slice($series,$last,$horizon)),1),
                'predicted'=>round(self::predict(array_slice($series,0,$last),$horizon,$method),1),
                'origin_index'=>$last,'selection_windows'=>max(0,count($origins)-1)];
        }
        $p=self::predict($series,$horizon,$method);
        // Explicit scenario spread, not a calibrated probability interval.
        $margin=max($p*($horizon>=365?.4:($horizon>=90?.3:.2)),(float)$r['mae']);
        $r['point']=round($p,1);$r['lower']=round(max(0,$p-$margin),1);$r['upper']=round($p+$margin,1);
        $r['status']=$origins?'limited_holdout_review_required':'no_full_horizon_holdout';
        return $r;
    }
}
