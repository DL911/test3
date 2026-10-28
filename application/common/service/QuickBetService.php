<?php
namespace app\common\service;
use think\Db;

/** Browser recognition is advisory; every submitted entry is checked and settled here. */
class QuickBetService
{
    public static function catalog()
    {
        $c=['one'=>'一码','onepos'=>'一码定位','two'=>'两码','pair'=>'两码对子','twopos'=>'两码定位','twog3'=>'两码组三','pairall'=>'对子全包','g3all'=>'组三全包','g6all'=>'组六全包','direct'=>'三码直选','g6_3'=>'三码组选六','g3_3'=>'三码组三','bomb'=>'具体豹子','bomball'=>'豹子全包','sumodd'=>'和值单','sumeven'=>'和值双','sumbig'=>'和值大','sumsmall'=>'和值小','posodd'=>'位置单','poseven'=>'位置双','posbig'=>'单位置大','possmall'=>'单位置小'];
        for($n=4;$n<=9;$n++){ $c['g6_'.$n]=$n.'码组六'; $c['g3_'.$n]=$n.'码组三'; }
        for($n=0;$n<=13;$n++)$c['sum_'.$n]='和值 '.$n.'／'.(27-$n);
        return $c;
    }
    public static function displayContent($raw)
    {
        $items = json_decode((string)$raw, true);
        if (!is_array($items)) return '快译注单';
        $catalog = self::catalog();
        $positions = ['0'=>'百位','1'=>'十位','2'=>'个位','01'=>'百十','02'=>'百个','12'=>'十个'];
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $play = (string)($item['play'] ?? '');
            $name = $catalog[$play] ?? '快译玩法';
            $number = (string)($item['number'] ?? '');
            $pos = (string)($item['pos'] ?? '');
            $amount = number_format((float)($item['amount'] ?? 0), 2, '.', '');
            $parts[] = $name . ($number !== '' ? ' ' . $number : '') . ($pos !== '' ? ' ' . ($positions[$pos] ?? '位置' . $pos) : '') . ' ¥' . $amount;
        }
        return $parts ? implode('；', $parts) : '快译注单';
    }
    // lottery_type=0 is the single shared configuration for all supported games.
    public static function settings($type){return Db::name('quick_bet_odds')->where('lottery_type',0)->order('id')->select();}
    public static function validateItem($i)
    {
        if(!is_array($i))throw new \RuntimeException('注单格式错误');
        $p=(string)($i['play']??'');$n=(string)($i['number']??'');$pos=(string)($i['pos']??'');$a=(string)($i['amount']??'');$bomb=!empty($i['include_bomb']);
        if(!isset(self::catalog()[$p]))throw new \RuntimeException('存在暂不支持的玩法');
        if(!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$a)||(float)$a<=0)throw new \RuntimeException('金额无效');
        $empty=['pairall','g3all','g6all','bomball','sumodd','sumeven','sumbig','sumsmall','posodd','poseven','posbig','possmall'];
        if(in_array($p,$empty,true)){if($n!=='')throw new \RuntimeException('条件玩法不能带号码');}
        elseif(strpos($p,'sum_')===0){if(!preg_match('/^\d{1,2}$/',$n)||(int)$n>27||$p!=='sum_'.min((int)$n,27-(int)$n))throw new \RuntimeException('和值档位错误');}
        else{
            $len=0;
            if(in_array($p,['one','onepos'],true))$len=1;
            elseif(in_array($p,['two','pair','twopos','twog3'],true))$len=2;
            elseif(in_array($p,['direct','bomb'],true))$len=3;
            elseif(preg_match('/^g[36]_([3-9])$/',$p,$m))$len=(int)$m[1];
            if(!$len||!preg_match('/^\d{'.$len.'}$/',$n))throw new \RuntimeException('号码位数错误');
            $unique=count(array_unique(str_split($n)));
            if(in_array($p,['pair','bomb'],true)&&$unique!==1)throw new \RuntimeException('对子或豹子号码错误');
            if(in_array($p,['two','twog3'],true)&&$unique!==2)throw new \RuntimeException('两码须为不同数字');
            if(preg_match('/^g6_[3-9]$/',$p)&&$unique!==$len)throw new \RuntimeException('组六号码不能重复');
            if(preg_match('/^g3_[4-9]$/',$p)&&$unique!==$len)throw new \RuntimeException('多码组三号码不能重复');
            if($p==='g3_3'&&!in_array($unique,[2,3],true))throw new \RuntimeException('三码组三号码错误');
        }
        if($p==='onepos'&&!preg_match('/^[012]$/',$pos))throw new \RuntimeException('一码定位位置错误');
        if($p==='twopos'&&!in_array($pos,['01','02','12'],true))throw new \RuntimeException('两码定位位置错误');
        if(strpos($p,'pos')===0&&!preg_match('/^[012]$/',$pos))throw new \RuntimeException('位置玩法仅支持单位置');
        if(!in_array($p,['onepos','twopos','posodd','poseven','posbig','possmall'],true)&&$pos!=='')throw new \RuntimeException('该玩法不应指定位置');
        if($bomb&&!in_array($p,['pair','twog3','pairall','g3all','g3_3','g3_4','g3_5','g3_6','g3_7','g3_8','g3_9'],true))throw new \RuntimeException('该玩法不能包含豹子');
        return ['play'=>$p,'number'=>$n,'pos'=>$pos,'amount'=>number_format((float)$a,2,'.',''),'include_bomb'=>$bomb?1:0];
    }
    public static function award($items,$draw)
    {
        if(!preg_match('/^\d{3}$/',(string)$draw))return 0;
        $d=str_split($draw);$unique=count(array_unique($d));$sum=array_sum(array_map('intval',$d));$win=0;
        foreach($items as $i){
            $p=$i['play'];$n=$i['number'];$pos=$i['pos'];$bomb=!empty($i['include_bomb']);$hit=false;
            if($p==='one')$hit=strpos($draw,$n)!==false;
            elseif($p==='onepos')$hit=$d[(int)$pos]===$n;
            elseif($p==='two')$hit=strpos($draw,$n[0])!==false&&strpos($draw,$n[1])!==false;
            elseif($p==='pair')$hit=($unique!==1||$bomb)&&count(array_filter($d,function($v)use($n){return $v===$n[0];}))>=2;
            elseif($p==='twopos')$hit=$d[(int)$pos[0]]===$n[0]&&$d[(int)$pos[1]]===$n[1];
            elseif($p==='twog3')$hit=($unique===2||($unique===1&&$bomb))&&!array_diff($d,str_split($n));
            elseif($p==='direct'||$p==='bomb')$hit=$n===$draw;
            elseif(strpos($p,'g6_')===0)$hit=$unique===3&&!array_diff($d,str_split($n));
            elseif($p==='g3_3'&&count(array_unique(str_split($n)))===2){$a=str_split($n);sort($a);$b=$d;sort($b);$hit=$unique===2&&$a===$b;}
            elseif(strpos($p,'g3_')===0)$hit=($unique===2||($unique===1&&$bomb))&&!array_diff($d,str_split($n));
            elseif($p==='pairall'||$p==='g3all')$hit=$unique===2||($unique===1&&$bomb);
            elseif($p==='g6all')$hit=$unique===3;
            elseif($p==='bomball')$hit=$unique===1;
            elseif(strpos($p,'sum_')===0)$hit=$sum===(int)$n;
            elseif($p==='sumodd')$hit=$sum%2===1;
            elseif($p==='sumeven')$hit=$sum%2===0;
            elseif($p==='sumbig')$hit=$sum>=14;
            elseif($p==='sumsmall')$hit=$sum<=13;
            elseif($p==='posodd')$hit=((int)$d[(int)$pos])%2===1;
            elseif($p==='poseven')$hit=((int)$d[(int)$pos])%2===0;
            elseif($p==='posbig')$hit=(int)$d[(int)$pos]>=5;
            elseif($p==='possmall')$hit=(int)$d[(int)$pos]<=4;
            if($hit)$win+=round((float)$i['amount']*(float)$i['odds'],2);
        }
        return round($win,2);
    }
}
