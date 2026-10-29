import fs from 'node:fs';
import vm from 'node:vm';
import path from 'node:path';
import {spawnSync} from 'node:child_process';

const root=path.resolve(import.meta.dirname,'../../public/assets/lottery/quickref');
for(const file of ['core/engine.js','core/learned.js','core/model-result.js','core/knowledge.js','core/stream.js','module.js','offline.js']){
  vm.runInThisContext(fs.readFileSync(path.join(root,file),'utf8'),{filename:file});
}
const S=globalThis.LotteryRecognitionOdds,settings=S.defaultSettings();
const cases=[
  ['589组200',['g6_3:589:200:']],
  ['196组5',['g6_3:196:5:']],
  ['589组选200',['g6_3:589:200:']],
  ['589直200',['direct:589:200:']],
  ['12345组10',['g6_5:12345:10:']],
  ['123租10',['g6_3:123:10:']],
  ['23防10',['twog3:23:10:']],
  ['12345防对5',['g3_5:12345:5:']],
  ['007组三2',['g3_3:007:2:']],
  ['123 456 786组10',['g6_3:123:10:','g6_3:456:10:','g6_3:786:10:']],
  ['123 123组10',['g6_3:123:10:','g6_3:123:10:']],
  ['256 652 482直组各2',['direct:256:2:','g6_3:256:2:','direct:652:2:','g6_3:652:2:','direct:482:2:','g6_3:482:2:']],
  ['03-05-08各八十放五十',['two:03:80:','twog3:03:50:','two:05:80:','twog3:05:50:','two:08:80:','twog3:08:50:']],
  ['1234-12345-123456各10+5',['g6_4:1234:10:','g3_4:1234:5:','g6_5:12345:10:','g3_5:12345:5:','g6_6:123456:10:','g3_6:123456:5:']],
  ['123 10+10+10',['direct:123:10:','g6_3:123:10:','g3_3:123:10:']],
  ['百位4十位7各100元',['onepos:4:100:0','onepos:7:100:1']],
  ['百3=10',['onepos:3:10:0']],
  ['个8 10员',['onepos:8:10:2']],
  ['一码定位 5** 10元',['onepos:5:10:0']],
  ['胆4打10',['one:4:10:']],
  ['对子10',['pairall::10:']],
  ['对子22 10',['pair:22:10:']],
  ['和大10',['sumbig::10:']],
  ['1234防5；12345组10；123456防20',['g3_4:1234:5:','g6_5:12345:10:','g3_6:123456:20:']],
  ['福014579--136789各300+50\n百位4十位7各100元\n大和值1000',['g6_6:014579:300:','g3_6:014579:50:','g6_6:136789:300:','g3_6:136789:50:','onepos:4:100:0','onepos:7:100:1','sumbig::1000:']],
];
for(const [raw,expected] of cases){
  for(const game of ['3D','P3']){
    const actual=S.recognizeOffline(raw,{game,settings,memories:[]}).map(e=>e.status==='confirmed'?`${e.play}:${e.numbers.join(',')}:${e.amount}:${e.pos}`:`pending:${e.reason}`);
    if(JSON.stringify(actual)!==JSON.stringify(expected))throw Error(JSON.stringify({game,raw,actual,expected}));
  }
}
const serverItems=cases.flatMap(([raw])=>S.recognizeOffline(raw,{game:'3D',settings,memories:[]}).map(e=>({play:e.play,number:e.numbers[0]||'',pos:e.pos,amount:String(e.amount),include_bomb:0})));
const servicePath=path.resolve(import.meta.dirname,'../../application/common/service/QuickBetService.php').replaceAll('\\','/');
const validateCode=`require '${servicePath}'; foreach(json_decode(stream_get_contents(STDIN),true) as $item) \\app\\common\\service\\QuickBetService::validateItem($item); echo 'server validation OK';`;
const php=spawnSync('php',['-r',validateCode],{input:JSON.stringify(serverItems),encoding:'utf8'});
if(php.status!==0||!php.stdout.includes('server validation OK'))throw Error(php.stderr||php.stdout);
for(const raw of ['007组10','12310','一百二','和15 10元','百十大10']){
  const rows=S.recognizeOffline(raw,{game:'3D',settings,memories:[]});
  if(rows.every(e=>e.status==='confirmed'))throw Error('ambiguous or retired play accepted: '+raw);
}
console.log('quick bet text rules passed: '+cases.length*2+' positive and 5 guarded cases');
